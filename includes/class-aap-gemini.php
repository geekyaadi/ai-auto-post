<?php
/**
 * Gemini API Client
 * Handles all API calls with automatic key rotation and per-step transient caching.
 * Steps: titles → article → tags → thumbnail → og_image → alt_text → meta_desc → category
 *
 * Model selection reads from plugin settings so users can switch models without
 * touching code. Defaults to the best free-tier models based on known quotas.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class AAP_Gemini {

    // Default model IDs — overridden by plugin settings
    const DEFAULT_TEXT_MODEL  = 'gemini-3.1-flash-lite-preview';
    const DEFAULT_IMAGE_MODEL = 'gemini-2.0-flash';
    const API_BASE            = 'https://generativelanguage.googleapis.com/v1beta/models/';

    // Transient TTL: 2 hours (covers generation session)
    const CACHE_TTL = 7200;

    /**
     * Returns the active text model ID (from settings or default).
     */
    public static function get_text_model() {
        $provider = get_option( 'aap_active_provider', 'gemini' );
        if ( $provider === 'openai' ) {
            return get_option( 'aap_openai_model', 'gpt-4o-mini' );
        }
        return get_option( 'aap_text_model', self::DEFAULT_TEXT_MODEL );
    }

    /**
     * Returns the active image model ID (from settings or default).
     */
    public static function get_image_model() {
        $provider = get_option( 'aap_active_provider', 'gemini' );
        if ( $provider === 'openai' ) {
            return 'dall-e-3';
        }
        $model = get_option( 'aap_image_model', self::DEFAULT_IMAGE_MODEL );
        if ( $model === 'gemini-2.0-flash-preview-image-generation' || $model === 'gemini-3.1-flash-image-generation-preview' ) {
            $model = self::DEFAULT_IMAGE_MODEL;
            update_option( 'aap_image_model', $model );
        }
        return $model;
    }

    /**
     * Tracks which key is currently active during a generation session.
     * Stored in a transient keyed by session_id.
     */
    public static function get_current_key( string $session_id ) {
        $key = get_transient( 'aap_session_key_' . $session_id );
        if ( ! $key ) {
            $key = AAP_Key_Manager::get_active_key();
            if ( $key ) {
                set_transient( 'aap_session_key_' . $session_id, $key, self::CACHE_TTL );
            }
        }
        return $key;
    }

    public static function set_current_key( string $session_id, string $key ) {
        set_transient( 'aap_session_key_' . $session_id, $key, self::CACHE_TTL );
    }

    // -------------------------------------------------------------------------
    // Core Request with Auto-Rotation
    // -------------------------------------------------------------------------

    /**
     * Makes a Gemini API call. On quota error, reads Retry-After and rotates key.
     * On invalid key error (400/403), marks key invalid and skips to next.
     * Returns [ 'data' => mixed, 'key_used' => string, 'switched' => bool ] or WP_Error.
     */
    private static function request( string $session_id, string $model, array $body ) {

        // ── Build the full Key × Model rotation matrix ───────────────────────
        // Order: [Key1+ModelA, Key1+ModelB, ..., Key2+ModelA, Key2+ModelB, ...]
        // On each quota error we advance to the next slot, wrapping around.

        $all_keys = AAP_Key_Manager::get_all_keys();
        $provider = get_option( 'aap_active_provider', 'gemini' );

        // Filter keys for the current provider
        $provider_keys = [];
        foreach ( $all_keys as $k ) {
            if ( ( $k['provider'] ?? 'gemini' ) === $provider && $k['status'] !== 'invalid' ) {
                $provider_keys[] = $k['key'];
            }
        }

        if ( empty( $provider_keys ) ) {
            return new WP_Error( 'no_key', __( 'No active API keys available.', 'ai-auto-post' ) );
        }

        // BUG FIX #1: Determine model type correctly.
        // Image generation models (gemini-2.0-flash used with responseModalities=IMAGE)
        // are NOT in the MODELS constant (which only lists text models), so detect by name.
        $image_model_names = [ 'gemini-2.0-flash', 'gemini-2.0-flash-exp', 'dall-e-3', 'imagen-3.0-generate-002' ];
        if ( isset( AAP_Rate_Limits::MODELS[ $model ] ) ) {
            $model_type = AAP_Rate_Limits::MODELS[ $model ]['type'] ?? 'text';
        } elseif ( in_array( $model, $image_model_names, true ) ) {
            $model_type = 'image';
        } else {
            $model_type = 'text';
        }

        $all_models = AAP_Rate_Limits::get_fallback_models_for_type( $model_type, $provider );

        // Always put the requested model first so it's tried first
        $all_models = array_values( array_unique( array_merge( [ $model ], $all_models ) ) );

        // For image type, if fallback list is empty (image models not in MODELS),
        // just use the requested model alone — no model cycling for image generation
        if ( $model_type === 'image' && count( $all_models ) === 1 ) {
            // Only cycle across KEYS, not models
        }

        // Build the flat rotation list: [key0+model0, key0+model1, ..., key1+model0, ...]
        $slots = [];
        foreach ( $provider_keys as $key ) {
            foreach ( $all_models as $m ) {
                $slots[] = [ 'key' => $key, 'model' => $m ];
            }
        }
        $total_slots = count( $slots );

        // BUG FIX #2a: Use model-type-specific slot transient.
        // Text steps and image steps build DIFFERENT slot arrays (different sizes),
        // so sharing a transient causes slot index out-of-range → reset → wrong key.
        $slot_transient = 'aap_session_slot_' . $session_id . '_' . $model_type;
        $current_slot   = (int) get_transient( $slot_transient );

        // Make sure the stored slot index is in range; if not, reset to 0
        if ( ! isset( $slots[ $current_slot ] ) ) {
            $current_slot = 0;
        }

        // BUG FIX #2b: Use a tried-set instead of a skipped-counter.
        // Counter approach had off-by-one: $skipped <= $total_slots allowed wrapping
        // back to already-tried slots. tried-set correctly stops when all N unique
        // slots have been visited.
        $tried_set     = []; // slot indices already attempted an HTTP call
        $switched      = false;
        $current_model = $model;

        while ( true ) {

            $slot          = $slots[ $current_slot ];
            $current_key   = $slot['key'];
            $current_model = $slot['model'];

            // Auto-reset check each loop
            AAP_Key_Manager::auto_reset_check();

            // Refresh key status
            $key_data = null;
            foreach ( AAP_Key_Manager::get_all_keys() as $k ) {
                if ( $k['key'] === $current_key ) { $key_data = $k; break; }
            }

            // Skip exhausted / invalid keys without counting as a "tried" attempt
            if ( $key_data && in_array( $key_data['status'], [ 'exhausted', 'invalid' ], true ) ) {
                $tried_set[ $current_slot ] = true; // mark this slot as done
                if ( count( $tried_set ) >= $total_slots ) break; // all slots visited
                $current_slot = ( $current_slot + 1 ) % $total_slots;
                $switched = true;
                continue;
            }

            // ── PRE-FLIGHT: Check local rate-limit counters for this key+model ─
            if ( ! AAP_Rate_Limits::is_within_limits( $current_key, $current_model ) ) {
                $tried_set[ $current_slot ] = true;
                if ( count( $tried_set ) >= $total_slots ) break;
                $current_slot = ( $current_slot + 1 ) % $total_slots;
                set_transient( $slot_transient, $current_slot, self::CACHE_TTL );
                $switched = true;
                continue;
            }

            // ── Mark this slot as tried BEFORE the HTTP call ──────────────────
            $tried_set[ $current_slot ] = true;

            AAP_Key_Manager::increment_requests( $current_key );

            // ── Prepare HTTP Request ──────────────────────────────────────────
            if ( $provider === 'openai' ) {
                if ( $current_model === 'dall-e-3' ) {
                    $url = 'https://api.openai.com/v1/images/generations';
                    $prompt = '';
                    if ( isset( $body['contents'][0]['parts'] ) ) {
                        foreach ( $body['contents'][0]['parts'] as $part ) {
                            if ( isset( $part['text'] ) ) $prompt .= $part['text'] . ' ';
                        }
                    }
                    $req_body = [
                        'model'           => 'dall-e-3',
                        'prompt'          => trim( $prompt ),
                        'n'               => 1,
                        'size'            => '1792x1024',
                        'response_format' => 'b64_json',
                    ];
                } else {
                    $url      = 'https://api.openai.com/v1/chat/completions';
                    $messages = [];
                    foreach ( $body['contents'] ?? [] as $item ) {
                        $msg_text = '';
                        foreach ( $item['parts'] ?? [] as $part ) {
                            if ( isset( $part['text'] ) ) $msg_text .= $part['text'];
                        }
                        $messages[] = [ 'role' => 'user', 'content' => $msg_text ];
                    }
                    if ( empty( $messages ) ) $messages[] = [ 'role' => 'user', 'content' => 'Hello' ];
                    $req_body = [
                        'model'       => $current_model,
                        'messages'    => $messages,
                        'temperature' => isset( $body['generationConfig']['temperature'] ) ? (float) $body['generationConfig']['temperature'] : 0.7,
                    ];
                    if ( isset( $body['generationConfig']['maxOutputTokens'] ) ) {
                        $req_body['max_completion_tokens'] = (int) $body['generationConfig']['maxOutputTokens'];
                    }
                }
                $headers = [
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $current_key,
                ];
            } else {
                // Gemini
                $url      = self::API_BASE . $current_model . ':generateContent?key=' . $current_key;
                $headers  = [ 'Content-Type' => 'application/json' ];
                $req_body = $body;
            }

            $response = wp_remote_post( $url, [
                'timeout' => 120,
                'headers' => $headers,
                'body'    => wp_json_encode( $req_body ),
            ] );

            if ( is_wp_error( $response ) ) {
                return $response;
            }

            $http_code    = (int) wp_remote_retrieve_response_code( $response );
            $body_decoded = json_decode( wp_remote_retrieve_body( $response ), true );

            // ── Quota / Rate-limit hit (429) → advance slot ───────────────────
            if ( $http_code === 429 ||
                 ( $provider === 'openai' && isset( $body_decoded['error']['type'] ) && $body_decoded['error']['type'] === 'insufficient_quota' ) ||
                 ( $provider === 'gemini' && AAP_Key_Manager::is_quota_error( $response ) ) ) {

                $retry_after = AAP_Key_Manager::parse_retry_after( $response, $body_decoded );

                // Mark key exhausted only when all its model-slots have been tried
                $key_has_untried = false;
                foreach ( $slots as $idx => $s ) {
                    if ( $s['key'] === $current_key && ! isset( $tried_set[ $idx ] ) ) {
                        $key_has_untried = true;
                        break;
                    }
                }
                if ( ! $key_has_untried ) {
                    AAP_Key_Manager::mark_exhausted( $current_key, $retry_after );
                }

                if ( count( $tried_set ) >= $total_slots ) break; // all slots tried
                $current_slot = ( $current_slot + 1 ) % $total_slots;
                set_transient( $slot_transient, $current_slot, self::CACHE_TTL );
                self::set_current_key( $session_id, $slots[ $current_slot ]['key'] );
                $switched = true;
                continue;
            }

            // ── Invalid Key → mark invalid, skip all its slots ────────────────
            if ( in_array( $http_code, [ 400, 401, 403 ], true ) ) {
                $is_invalid = false;
                if ( $provider === 'openai' && in_array( $http_code, [ 401, 403 ], true ) ) $is_invalid = true;
                elseif ( $provider === 'gemini' && AAP_Key_Manager::is_invalid_key_error( $response ) ) $is_invalid = true;

                if ( $is_invalid ) {
                    AAP_Key_Manager::mark_invalid( $current_key );
                    // Mark all slots for this key as tried
                    foreach ( $slots as $idx => $s ) {
                        if ( $s['key'] === $current_key ) $tried_set[ $idx ] = true;
                    }
                    if ( count( $tried_set ) >= $total_slots ) break;
                    // Find next slot for a different key
                    $current_slot = ( $current_slot + 1 ) % $total_slots;
                    while ( $slots[ $current_slot ]['key'] === $current_key ) {
                        $current_slot = ( $current_slot + 1 ) % $total_slots;
                    }
                    set_transient( $slot_transient, $current_slot, self::CACHE_TTL );
                    self::set_current_key( $session_id, $slots[ $current_slot ]['key'] );
                    $switched = true;
                    continue;
                }
            }

            // ── Generic non-200 error (model not found etc.) → try next slot ──
            if ( $http_code !== 200 ) {
                $msg = $body_decoded['error']['message'] ?? 'Unknown API error (HTTP ' . $http_code . ')';
                if ( $http_code === 404 || stripos( $msg, 'not found' ) !== false || stripos( $msg, 'not supported' ) !== false ) {
                    if ( count( $tried_set ) >= $total_slots ) break;
                    $current_slot = ( $current_slot + 1 ) % $total_slots;
                    set_transient( $slot_transient, $current_slot, self::CACHE_TTL );
                    $switched = true;
                    continue;
                }
                return new WP_Error( 'api_error', $msg );
            }

            // ── Success ───────────────────────────────────────────────────────
            $token_est = 0;
            if ( $provider === 'openai' ) {
                $token_est = isset( $body_decoded['usage']['total_tokens'] ) ? (int) $body_decoded['usage']['total_tokens'] : 0;
            } else {
                $token_est = isset( $body_decoded['usageMetadata']['totalTokenCount'] ) ? (int) $body_decoded['usageMetadata']['totalTokenCount'] : 0;
            }

            if ( $token_est > 0 ) {
                AAP_Key_Manager::increment_requests( $current_key, $token_est );
            }

            AAP_Rate_Limits::record( $current_key, $current_model, $token_est );

            // Persist current slot for next request in this session
            set_transient( $slot_transient, $current_slot, self::CACHE_TTL );
            self::set_current_key( $session_id, $current_key );

            return [
                'data'        => $body_decoded,
                'key_used'    => $current_key,
                'model_used'  => $current_model,
                'switched'    => $switched,
                'token_used'  => $token_est,
            ];
        }

        return new WP_Error( 'all_exhausted', __( 'All API key + model combinations are exhausted. Please wait for quota reset or add more keys.', 'ai-auto-post' ) );
    }

    // -------------------------------------------------------------------------
    // Helper: Extract text from Gemini response
    // -------------------------------------------------------------------------

    private static function extract_text( array $data ) {
        if ( isset( $data['choices'][0]['message']['content'] ) ) {
            return $data['choices'][0]['message']['content'];
        }
        return $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    }

    // -------------------------------------------------------------------------
    // Helper: Build standard text request body
    // -------------------------------------------------------------------------

    private static function text_body( string $prompt, array $extra = [] ) {
        return array_merge( [
            'contents' => [
                [ 'parts' => [ [ 'text' => $prompt ] ] ]
            ],
            'generationConfig' => [
                'temperature'     => 0.7,
                'maxOutputTokens' => 4096,
            ],
        ], $extra );
    }

    // -------------------------------------------------------------------------
    // Step 1: Title Suggestions
    // -------------------------------------------------------------------------

    public static function get_title_suggestions( string $session_id, string $niche, string $focus_keywords = '', string $language = 'English' ) {
        $cache_key = 'aap_titles_' . $session_id;
        $cached    = get_transient( $cache_key );
        if ( $cached ) return [ 'titles' => $cached, 'cached' => true, 'switched' => false ];

        $default_prompt = "Generate exactly {count} highly engaging, CTR-optimized, SEO blog post title ideas for the niche: \"{niche}\".
Write the response in the language: \"{language}\".
{keywords_clause}
The titles must feel human-written, catch curiosity, and be optimized for search traffic in that language.
Return ONLY a numbered list (1. Title, 2. Title, etc.) with no additional conversational text or formatting.";

        $keywords_clause = $focus_keywords
            ? "Try to naturally incorporate some of these focus keywords: \"{$focus_keywords}\"."
            : "";

        $prompt = self::get_custom_prompt( 'aap_prompt_titles', $default_prompt, [
            'count'           => 5,
            'niche'           => $niche,
            'language'        => $language,
            'keywords_clause' => $keywords_clause,
            'keywords'        => $focus_keywords,
        ] );

        $result = self::request( $session_id, self::get_text_model(), self::text_body( $prompt ) );
        if ( is_wp_error( $result ) ) return $result;

        $raw    = self::extract_text( $result['data'] );
        $titles = self::parse_numbered_list( $raw );

        set_transient( $cache_key, $titles, self::CACHE_TTL );

        return [
            'titles'   => $titles,
            'cached'   => false,
            'key_used' => $result['key_used'],
            'switched' => $result['switched'],
        ];
    }

    public static function get_planner_title_suggestions( string $session_id, string $niche, string $language = 'English', int $count = 20 ) {
        $default_prompt = "Generate exactly {count} unique, highly engaging, CTR-optimized, SEO blog post title ideas for the niche: \"{niche}\".
Write the titles in the language: \"{language}\".
The titles must catch curiosity, vary in angles, and target search traffic in that language.
Return ONLY a numbered list (1. Title, 2. Title, etc.) with no additional conversational text, notes, or markdown.";

        $prompt = self::get_custom_prompt( 'aap_prompt_titles', $default_prompt, [
            'count'    => $count,
            'niche'    => $niche,
            'language' => $language,
        ] );

        $result = self::request( $session_id, self::get_text_model(), self::text_body( $prompt ) );
        if ( is_wp_error( $result ) ) return $result;

        $raw    = self::extract_text( $result['data'] );
        $titles = self::parse_numbered_list( $raw );

        return [
            'titles'   => $titles,
            'key_used' => $result['key_used'],
            'switched' => $result['switched'],
        ];
    }

    public static function get_silo_suggestions( string $session_id, string $niche, string $language = 'English' ) {
        $blacklist = self::get_blacklist_phrase();
        $prompt = "Create a search engine optimized Pillar-Cluster (Silo) content structure for the niche/topic: \"{$niche}\".
Write the response in the language: \"{$language}\".
You must generate exactly 1 primary Pillar Article Title, and exactly 5 highly related Supporting Cluster Article Titles.
Return the response ONLY as a valid JSON object structure with no additional conversational text or markdown code block formatting. The JSON schema MUST be exactly:
{
  \"pillar\": \"Pillar Article Title\",
  \"subposts\": [
    \"Cluster Title 1\",
    \"Cluster Title 2\",
    \"Cluster Title 3\",
    \"Cluster Title 4\",
    \"Cluster Title 5\"
  ]
}
{$blacklist}";

        $body = self::text_body( $prompt, [
            'generationConfig' => [
                'temperature'     => 0.7,
                'responseMimeType' => 'application/json',
            ]
        ] );

        $result = self::request( $session_id, self::get_text_model(), $body );
        if ( is_wp_error( $result ) ) return $result;

        $raw = self::extract_text( $result['data'] );
        $raw = trim( preg_replace( '/^```(?:json)?|```$/i', '', trim( $raw ) ) );

        $decoded = json_decode( $raw, true );
        if ( ! is_array( $decoded ) || empty( $decoded['pillar'] ) || empty( $decoded['subposts'] ) ) {
            return new WP_Error( 'json_parse_failed', 'Failed to parse generated silo structure JSON. Raw response: ' . substr( $raw, 0, 500 ) );
        }

        return [
            'silo'     => $decoded,
            'key_used' => $result['key_used'] ?? '',
            'switched' => $result['switched'] ?? false,
        ];
    }

    public static function generate_comments( string $session_id, string $title, int $count = 2, string $language = 'English' ) {
        $prompt = "Write exactly {$count} realistic user comments for a blog post titled: \"{$title}\".
The comments must sound natural, like different real people expressing thoughts, questions, or feedback in the language: \"{$language}\".
Return the response ONLY as a valid JSON array of objects, with no additional conversational text or markdown code block formatting. Each object MUST contain exactly:
- \"name\": a realistic display name of the commenter
- \"email\": a realistic dummy email address
- \"comment\": the comment text
Example schema:
[
  {
    \"name\": \"Jane Doe\",
    \"email\": \"jane.doe@example.com\",
    \"comment\": \"This is a fantastic guide! Extremely helpful.\"
  }
]";

        $body = self::text_body( $prompt, [
            'generationConfig' => [
                'temperature'     => 0.7,
                'responseMimeType' => 'application/json',
            ]
        ] );

        $result = self::request( $session_id, self::get_text_model(), $body );
        if ( is_wp_error( $result ) ) return $result;

        $raw = self::extract_text( $result['data'] );
        $raw = trim( preg_replace( '/^```(?:json)?|```$/i', '', trim( $raw ) ) );

        $decoded = json_decode( $raw, true );
        if ( ! is_array( $decoded ) ) {
            return new WP_Error( 'json_parse_failed', 'Failed to parse generated comments JSON. Raw response: ' . substr( $raw, 0, 500 ) );
        }

        return [
            'comments' => $decoded,
            'key_used' => $result['key_used'] ?? '',
            'switched' => $result['switched'] ?? false,
        ];
    }

    // -------------------------------------------------------------------------
    // Step 2: Article Generation
    // -------------------------------------------------------------------------

    public static function generate_article( string $session_id, string $title, string $focus_keywords = '', string $language = 'English' ) {
        $cache_key = 'aap_article_' . $session_id;
        $cached    = get_transient( $cache_key );
        if ( $cached ) return [ 'article' => $cached, 'cached' => true, 'switched' => false ];

        $word_count = (int) get_option( 'aap_word_count', 1000 );
        $tone       = get_option( 'aap_content_tone', 'professional' );
        $default_prompt = "Write a comprehensive, 100% unique, human-like, SEO-optimized blog post titled: \"{title}\".
Write the response in the language: \"{language}\".
Word count target: {word_count} words.
Tone: {tone}.
{focus_clause}

Writing instructions for AdSense approval and human readability:
1. Write the entire post in the specified language: \"{language}\". Ensure it is grammatically correct and natural-sounding for native speakers of that language.
2. Vary sentence lengths (mix short punchy sentences with longer complex sentences) to create a natural, engaging flow (burstiness) and bypass AI detectors.
3. Do NOT use typical AI clichés, generic buzzwords, or repetitive transitions (e.g., ban the use of: \"in conclusion\", \"it is important to note\", \"delve\", \"testament\", \"tapestry\", \"moreover\", \"furthermore\", \"crucial\", \"essential\", \"first and foremost\", \"look no further\").
4. Use active voice and write in a conversational, authoritative expert persona (E-E-A-T friendly) with natural transition flow.
5. Organize content with clear H2 and H3 subheadings. Integrate focus keywords naturally in at least one subheading, in the introduction paragraph, and in the conclusion.
6. Keep paragraphs short (2-3 sentences max) for readability.
7. Use standard list formatting (bullet points) where appropriate.

Return ONLY the HTML content formatted with tags (h2, h3, p, ul, li, strong) suitable for WordPress. Do not include page title, markdown backticks, or any conversational intro/outro text in your response.";

        $focus_clause = $focus_keywords
            ? "Focus Keywords to integrate naturally: \"{$focus_keywords}\"."
            : "";

        $prompt = self::get_custom_prompt( 'aap_prompt_article', $default_prompt, [
            'title'        => $title,
            'language'     => $language,
            'word_count'   => $word_count,
            'tone'         => $tone,
            'focus_clause' => $focus_clause,
            'keywords'     => $focus_keywords,
        ] );

        $result = self::request( $session_id, self::get_text_model(), self::text_body( $prompt, [
            'generationConfig' => [ 'temperature' => 0.7, 'maxOutputTokens' => 8192 ],
        ] ) );
        if ( is_wp_error( $result ) ) return $result;

        $article = self::extract_text( $result['data'] );
        // Strip markdown code fences if present
        $article = preg_replace( '/^```html\s*/i', '', $article );
        $article = preg_replace( '/```\s*$/', '', $article );

        set_transient( $cache_key, $article, self::CACHE_TTL );

        return [
            'article'  => $article,
            'cached'   => false,
            'key_used' => $result['key_used'],
            'switched' => $result['switched'],
        ];
    }

    // -------------------------------------------------------------------------
    // Step 3: Tag Generation
    // -------------------------------------------------------------------------

    public static function generate_tags( string $session_id, string $title, string $language = 'English', int $tag_count = 0 ) {
        // Resolve count: argument → wp option → hard default of 15
        if ( $tag_count <= 0 ) {
            $tag_count = (int) get_option( 'aap_tag_count', 15 );
        }
        $tag_count = max( 1, min( 100, $tag_count ) );

        $cache_key = 'aap_tags_' . $session_id . '_' . $tag_count;
        $cached    = get_transient( $cache_key );
        if ( $cached ) return [ 'tags' => $cached, 'cached' => true, 'switched' => false ];

        $default_prompt = "Generate exactly {tag_count} relevant, specific SEO tags for a blog post titled: \"{title}\".
Return the tags in the language: \"{language}\".
Return ONLY a comma-separated list of tags, no numbering, no extra text.";

        $prompt = self::get_custom_prompt( 'aap_prompt_tags', $default_prompt, [
            'tag_count' => $tag_count,
            'title'     => $title,
            'language'  => $language,
        ] );

        $result = self::request( $session_id, self::get_text_model(), self::text_body( $prompt ) );
        if ( is_wp_error( $result ) ) return $result;

        $raw  = self::extract_text( $result['data'] );
        $tags = array_filter( array_map( 'trim', explode( ',', $raw ) ) );
        $tags = array_slice( array_values( $tags ), 0, $tag_count );

        set_transient( $cache_key, $tags, self::CACHE_TTL );

        return [
            'tags'     => $tags,
            'cached'   => false,
            'key_used' => $result['key_used'],
            'switched' => $result['switched'],
        ];
    }

    // -------------------------------------------------------------------------
    // Step 4: Meta Description
    // -------------------------------------------------------------------------

    public static function generate_meta_description( string $session_id, string $title, string $focus_keywords = '', string $language = 'English' ) {
        $cache_key = 'aap_meta_' . $session_id;
        $cached    = get_transient( $cache_key );
        if ( $cached ) return [ 'meta' => $cached, 'cached' => true, 'switched' => false ];

        $focus_clause = $focus_keywords
            ? "Weave in the following focus keywords naturally: \"{$focus_keywords}\"."
            : "";

        $default_prompt = "Write an SEO-optimized meta description (max 160 characters) for a blog post titled: \"{title}\".
Write the meta description in the language: \"{language}\".
{focus_clause}
Make it compelling, high CTR, and write in a natural human tone. Return ONLY the meta description text, no quotes, no extra remarks.";

        $prompt = self::get_custom_prompt( 'aap_prompt_meta', $default_prompt, [
            'title'        => $title,
            'language'     => $language,
            'focus_clause' => $focus_clause,
            'keywords'     => $focus_keywords,
        ] );

        $result = self::request( $session_id, self::get_text_model(), self::text_body( $prompt ) );
        if ( is_wp_error( $result ) ) return $result;

        $meta = trim( self::extract_text( $result['data'] ) );
        $meta = str_replace( ['"', "'"], '', $meta );
        $meta = substr( $meta, 0, 160 );

        set_transient( $cache_key, $meta, self::CACHE_TTL );

        return [
            'meta'     => $meta,
            'cached'   => false,
            'key_used' => $result['key_used'],
            'switched' => $result['switched'],
        ];
    }

    // -------------------------------------------------------------------------
    // Step 5: Category Suggestion
    // -------------------------------------------------------------------------

    public static function suggest_category( string $session_id, string $title, string $language = 'English' ) {
        $cache_key = 'aap_cat_' . $session_id;
        $cached    = get_transient( $cache_key );
        if ( $cached ) return [ 'category' => $cached, 'cached' => true, 'switched' => false ];

        // Get existing WP categories to match against
        $categories = get_categories( [ 'hide_empty' => false ] );
        $cat_names  = wp_list_pluck( $categories, 'name' );
        $cat_list   = implode( ', ', $cat_names );

        $prompt = "Given this blog post title: \"{$title}\", and these existing WordPress categories: [{$cat_list}].
Which ONE category best fits this post? If none fit well, suggest a new category name in the language: \"{$language}\".
Return ONLY the category name, nothing else.";

        $result = self::request( $session_id, self::get_text_model(), self::text_body( $prompt ) );
        if ( is_wp_error( $result ) ) return $result;

        $category = trim( self::extract_text( $result['data'] ) );

        set_transient( $cache_key, $category, self::CACHE_TTL );

        return [
            'category' => $category,
            'cached'   => false,
            'key_used' => $result['key_used'],
            'switched' => $result['switched'],
        ];
    }

    // -------------------------------------------------------------------------
    // Step 6: Thumbnail Generation
    // Accepts optional reference_image [ 'base64' => '...', 'mime_type' => '...' ]
    // When provided, Gemini uses it as a style/composition guide.
    // -------------------------------------------------------------------------

    public static function generate_thumbnail( string $session_id, string $title, array $reference_image = [], array $t2i_opts = [] ) {
        $cache_key = 'aap_thumb_' . $session_id;
        $cached    = get_transient( $cache_key );
        if ( $cached ) return [ 'image_data' => $cached, 'cached' => true, 'switched' => false ];

        // Check if Title to Image (local GD) is selected
        $thumb_type = $t2i_opts['thumb_type'] ?? get_option( 'aap_thumb_type', 'ai' );
        if ( $thumb_type === 'text_to_image' ) {
            $bg_type = $t2i_opts['bg_type'] ?? get_option( 'aap_t2i_bg_type', 'gradient' );
            $bg_val  = $t2i_opts['bg_val']  ?? get_option( 'aap_t2i_bg_val', 'blue_purple' );
            $size    = $t2i_opts['size']    ?? get_option( 'aap_t2i_size', '600x315' );

            $t2i_result = AAP_Text_To_Image::generate( $title, $bg_type, $bg_val, $size );
            if ( ! $t2i_result ) {
                return new WP_Error( 't2i_failed', __( 'GD Text-to-Image generation failed.', 'ai-auto-post' ) );
            }

            set_transient( $cache_key, $t2i_result, self::CACHE_TTL );
            return [
                'image_data'     => $t2i_result,
                'used_reference' => false,
                'cached'         => false,
                'key_used'       => '',
                'switched'       => false,
            ];
        }

        // Use provided reference image, or fall back to the saved default from Settings
        if ( empty( $reference_image ) ) {
            $reference_image = self::get_default_reference_image();
        }

        if ( ! empty( $reference_image['base64'] ) ) {
            // --- Multimodal request: image + text ---
            $prompt = "Using the uploaded reference image as a style guide, generate a 600x315 blog post thumbnail for the topic: \"{$title}\".
Match the reference image's color palette, design style, layout composition, and overall aesthetic.
Make it visually relevant to the topic while keeping the same look and feel as the reference.
Output ONLY the image, no text overlays unless present in the reference.";

            $body = [
                'contents' => [ [
                    'parts' => [
                        [
                            'inlineData' => [
                                'mimeType' => $reference_image['mime_type'] ?? 'image/jpeg',
                                'data'     => $reference_image['base64'],
                            ],
                        ],
                        [ 'text' => $prompt ],
                    ],
                ] ],
                'generationConfig' => [ 'responseModalities' => [ 'IMAGE', 'TEXT' ] ],
            ];
        } else {
            // --- Text-only request (no reference image) ---
            $prompt = "Create a professional, eye-catching 600x315 blog post featured image (thumbnail) for the topic: \"{$title}\".
Use vibrant colors, modern design, and include relevant visual elements. Make it suitable for a blog header.";

            $body = [
                'contents'         => [ [ 'parts' => [ [ 'text' => $prompt ] ] ] ],
                'generationConfig' => [ 'responseModalities' => [ 'IMAGE', 'TEXT' ] ],
            ];
        }

        $result = self::request( $session_id, self::get_image_model(), $body );
        if ( is_wp_error( $result ) ) return $result;

        $image_data = self::extract_image_data( $result['data'] );
        if ( ! $image_data ) {
            return new WP_Error( 'no_image', __( 'No image returned by Gemini.', 'ai-auto-post' ) );
        }

        set_transient( $cache_key, $image_data, self::CACHE_TTL );

        return [
            'image_data'       => $image_data,
            'used_reference'   => ! empty( $reference_image['base64'] ),
            'cached'           => false,
            'key_used'         => $result['key_used'],
            'switched'         => $result['switched'],
        ];
    }

    // -------------------------------------------------------------------------
    // Step 7: OG Image Generation (1200x630)
    // -------------------------------------------------------------------------

    public static function generate_og_image( string $session_id, string $title, array $t2i_opts = [] ) {
        $cache_key = 'aap_og_' . $session_id;
        $cached    = get_transient( $cache_key );
        if ( $cached ) return [ 'image_data' => $cached, 'cached' => true, 'switched' => false ];

        // Check if Title to Image (local GD) is selected
        $thumb_type = $t2i_opts['thumb_type'] ?? get_option( 'aap_thumb_type', 'ai' );
        if ( $thumb_type === 'text_to_image' ) {
            $bg_type = $t2i_opts['bg_type'] ?? get_option( 'aap_t2i_bg_type', 'gradient' );
            $bg_val  = $t2i_opts['bg_val']  ?? get_option( 'aap_t2i_bg_val', 'blue_purple' );

            $t2i_result = AAP_Text_To_Image::generate( $title, $bg_type, $bg_val, '1200x630' );
            if ( ! $t2i_result ) {
                return new WP_Error( 't2i_failed', __( 'GD Text-to-Image OG generation failed.', 'ai-auto-post' ) );
            }

            set_transient( $cache_key, $t2i_result, self::CACHE_TTL );
            return [
                'image_data'     => $t2i_result,
                'cached'         => false,
                'key_used'       => '',
                'switched'       => false,
            ];
        }

        $prompt = "Create an Open Graph social sharing image (landscape, 1200x630 ratio) for a blog post titled: \"{$title}\".
Include the blog title text prominently, use a clean modern design with complementary colors.";

        $body = [
            'contents'         => [ [ 'parts' => [ [ 'text' => $prompt ] ] ] ],
            'generationConfig' => [ 'responseModalities' => [ 'IMAGE', 'TEXT' ] ],
        ];

        $result = self::request( $session_id, self::get_image_model(), $body );
        if ( is_wp_error( $result ) ) return $result;

        $image_data = self::extract_image_data( $result['data'] );
        if ( ! $image_data ) {
            return new WP_Error( 'no_og_image', __( 'No OG image returned by Gemini.', 'ai-auto-post' ) );
        }

        set_transient( $cache_key, $image_data, self::CACHE_TTL );

        return [
            'image_data' => $image_data,
            'cached'     => false,
            'key_used'   => $result['key_used'],
            'switched'   => $result['switched'],
        ];
    }

    // -------------------------------------------------------------------------
    // Step 8: Alt Text for Thumbnail
    // -------------------------------------------------------------------------

    public static function generate_alt_text( string $session_id, string $title, string $language = 'English' ) {
        $cache_key = 'aap_alt_' . $session_id;
        $cached    = get_transient( $cache_key );
        if ( $cached ) return [ 'alt_text' => $cached, 'cached' => true, 'switched' => false ];

        $prompt = "Write a concise, descriptive alt text (max 125 characters) in the language: \"{$language}\" for a blog thumbnail image about: \"{$title}\".
Return ONLY the alt text, nothing else.";

        $result = self::request( $session_id, self::get_text_model(), self::text_body( $prompt ) );
        if ( is_wp_error( $result ) ) return $result;

        $alt = substr( trim( self::extract_text( $result['data'] ) ), 0, 125 );
        set_transient( $cache_key, $alt, self::CACHE_TTL );

        return [
            'alt_text' => $alt,
            'cached'   => false,
            'key_used' => $result['key_used'],
            'switched' => $result['switched'],
        ];
    }

    // -------------------------------------------------------------------------
    // Cache Management
    // -------------------------------------------------------------------------

    public static function clear_session_cache( string $session_id ) {
        $keys = [ 'titles', 'article', 'tags', 'meta', 'cat', 'thumb', 'og', 'alt' ];
        foreach ( $keys as $k ) {
            delete_transient( 'aap_' . $k . '_' . $session_id );
        }
        delete_transient( 'aap_session_key_' . $session_id );
    }

    public static function get_cached_step( string $session_id, string $step ) {
        return get_transient( 'aap_' . $step . '_' . $session_id );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private static function parse_numbered_list( string $text ) {
        $lines  = explode( "\n", $text );
        $titles = [];
        foreach ( $lines as $line ) {
            $line = trim( $line );
            if ( preg_match( '/^\d+[\.\)]\s*(.+)/', $line, $m ) ) {
                $titles[] = trim( $m[1] );
            }
        }
        return $titles;
    }

    private static function extract_image_data( array $data ) {
        // OpenAI DALL-E format
        if ( isset( $data['data'][0]['b64_json'] ) ) {
            return [
                'base64'    => $data['data'][0]['b64_json'],
                'mime_type' => 'image/png',
            ];
        }

        // Gemini format
        $candidates = $data['candidates'] ?? [];
        foreach ( $candidates as $candidate ) {
            $parts = $candidate['content']['parts'] ?? [];
            foreach ( $parts as $part ) {
                if ( isset( $part['inlineData']['data'] ) ) {
                    return [
                        'base64'    => $part['inlineData']['data'],
                        'mime_type' => $part['inlineData']['mimeType'] ?? 'image/png',
                    ];
                }
            }
        }
        return null;
    }

    private static function get_blacklist_phrase() {
        $blacklist = get_option( 'aap_blacklist_words', '' );
        if ( empty( trim( $blacklist ) ) ) return '';
        return "IMPORTANT: Do NOT use any of these words or phrases in your response: {$blacklist}.";
    }

    // -------------------------------------------------------------------------
    // Default Reference Image (saved in Settings)
    // Returns [ 'base64' => '...', 'mime_type' => '...' ] or []
    // -------------------------------------------------------------------------

    public static function get_default_reference_image() {
        $saved = get_option( 'aap_default_reference_image', [] );
        if ( ! empty( $saved['base64'] ) && ! empty( $saved['mime_type'] ) ) {
            return $saved;
        }
        return [];
    }

    public static function save_default_reference_image( string $base64, string $mime_type ) {
        update_option( 'aap_default_reference_image', [
            'base64'    => $base64,
            'mime_type' => $mime_type,
        ] );
    }

    public static function delete_default_reference_image() {
        delete_option( 'aap_default_reference_image' );
    }

    private static function get_custom_prompt( string $option_name, string $default_prompt, array $placeholders ) {
        $custom = get_option( $option_name );
        $prompt = ! empty( $custom ) ? $custom : $default_prompt;
        
        foreach ( $placeholders as $tag => $val ) {
            $prompt = str_replace( '{' . $tag . '}', $val, $prompt );
        }
        
        $blacklist = self::get_blacklist_phrase();
        if ( $blacklist ) {
            $prompt .= "\n" . $blacklist;
        }
        return $prompt;
    }

    public static function generate_faq( string $session_id, string $title, string $language = 'English', int $faq_count = 3 ) {
        if ( $faq_count <= 0 ) {
            $faq_count = (int) get_option( 'aap_faq_count', 3 );
        }
        $faq_count = max( 1, min( 20, $faq_count ) );

        $cache_key = 'aap_faq_' . $session_id . '_' . $faq_count;
        $cached    = get_transient( $cache_key );
        if ( $cached ) return [ 'faqs' => $cached, 'cached' => true, 'switched' => false ];

        $default_prompt = "Generate exactly {faq_count} relevant Frequently Asked Questions with detailed answers for a blog post titled: \"{title}\".
Write the response in the language: \"{language}\".
Return the response ONLY as a valid JSON array of objects, with no additional conversational text or markdown code block formatting. Each object MUST contain exactly:
- \"question\": the question text
- \"answer\": the answer text
Example format:
[
  {
    \"question\": \"What is smart gardening?\",
    \"answer\": \"Smart gardening involves using technology like automated sensors and watering systems to grow...\"
  }
]";

        $prompt = self::get_custom_prompt( 'aap_prompt_faq', $default_prompt, [
            'faq_count' => $faq_count,
            'title'     => $title,
            'language'  => $language,
        ] );

        $body = self::text_body( $prompt, [
            'generationConfig' => [
                'temperature'     => 0.7,
                'responseMimeType' => 'application/json',
            ]
        ] );

        $result = self::request( $session_id, self::get_text_model(), $body );
        if ( is_wp_error( $result ) ) return $result;

        $raw = self::extract_text( $result['data'] );
        $raw = trim( preg_replace( '/^```(?:json)?|```$/i', '', trim( $raw ) ) );

        $decoded = json_decode( $raw, true );
        if ( ! is_array( $decoded ) ) {
            return new WP_Error( 'json_parse_failed', 'Failed to parse generated FAQs JSON. Raw response: ' . substr( $raw, 0, 500 ) );
        }

        set_transient( $cache_key, $decoded, self::CACHE_TTL );

        return [
            'faqs'     => $decoded,
            'cached'   => false,
            'key_used' => $result['key_used'] ?? '',
            'switched' => $result['switched'] ?? false,
        ];
    }

    public static function translate_text( string $session_id, string $text, string $target_lang ) {
        if ( empty( trim( $text ) ) ) return [ 'text' => '' ];

        $prompt = "You are a professional translator. Translate the following text into the target language: \"{$target_lang}\".
Return ONLY the translated text, no introductory remarks, no side-notes, no formatting.
Text to translate:
\"\"\"
{$text}
\"\"\";";

        $result = self::request( $session_id, self::get_text_model(), self::text_body( $prompt ) );
        if ( is_wp_error( $result ) ) return $result;

        $translated = trim( self::extract_text( $result['data'] ) );
        // Strip wrap-around quotes if Gemini added them
        $translated = preg_replace( '/^["\']|["\']$/u', '', $translated );

        return [
            'text'     => $translated,
            'key_used' => $result['key_used'] ?? '',
            'switched' => $result['switched'] ?? false,
        ];
    }

    public static function translate_html( string $session_id, string $html, string $target_lang ) {
        if ( empty( trim( $html ) ) ) return [ 'html' => '' ];

        $prompt = "You are a professional HTML localization expert. Translate the text inside the following HTML fragment into the target language: \"{$target_lang}\".
IMPORTANT rules:
1. Do NOT translate or change any HTML tag names, attribute values (like class, href, id, style, src, alt, etc.), or link URLs.
2. Maintain the HTML structure, formatting, line breaks, and tag hierarchy EXACTLY as is.
3. Translate only the human-readable text contents.
4. Do NOT wrap the output in markdown code blocks (like ```html). Return ONLY the raw translated HTML string.
HTML to translate:
\"\"\"
{$html}
\"\"\";";

        $result = self::request( $session_id, self::get_text_model(), self::text_body( $prompt, [
            'generationConfig' => [
                'temperature'     => 0.3,
                'maxOutputTokens' => 8192,
            ]
        ] ) );
        if ( is_wp_error( $result ) ) return $result;

        $translated_html = self::extract_text( $result['data'] );
        // Strip markdown code fences if present
        $translated_html = preg_replace( '/^```html\s*/i', '', $translated_html );
        $translated_html = preg_replace( '/```\s*$/', '', $translated_html );

        return [
            'html'     => $translated_html,
            'key_used' => $result['key_used'] ?? '',
            'switched' => $result['switched'] ?? false,
        ];
    }
}
