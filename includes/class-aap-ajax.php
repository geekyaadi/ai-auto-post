<?php
/**
 * AJAX Handlers for the Generate Post UI.
 * Handles title fetching, full post generation pipeline, and preview.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class AAP_Ajax {

    public static function init() {
        $actions = [
            'aap_get_titles',
            'aap_generate_post',
            'aap_check_duplicate',
            'aap_get_step_status',
            'aap_save_reference_image',
            'aap_delete_reference_image',
            'aap_ping_key',
            'aap_ping_all_keys',
            'aap_generate_planner_titles',
            'aap_save_planner_tasks',
            'aap_process_queue_item',
            'aap_generate_pending_thumbnail',
            'aap_generate_tags',
            'aap_translate_post',
            'aap_request_indexing',
            'aap_rewrite_post',
        ];
        foreach ( $actions as $action ) {
            add_action( 'wp_ajax_' . $action, [ __CLASS__, 'handle_' . $action ] );
        }
    }

    // -------------------------------------------------------------------------
    // Helper: JSON response
    // -------------------------------------------------------------------------

    private static function success( array $data = [] ) {
        wp_send_json_success( $data );
    }

    private static function error( string $message, array $extra = [] ) {
        wp_send_json_error( array_merge( [ 'message' => $message ], $extra ) );
    }

    private static function verify_nonce() {
        if ( ! check_ajax_referer( 'aap_nonce', 'nonce', false ) ) {
            self::error( __( 'Security check failed.', 'ai-auto-post' ) );
            exit;
        }
    }

    // -------------------------------------------------------------------------
    // Step 1: Get Title Suggestions
    // -------------------------------------------------------------------------

    public static function handle_aap_get_titles() {
        self::verify_nonce();

        $niche          = sanitize_text_field( $_POST['niche'] ?? '' );
        $focus_keywords = sanitize_text_field( $_POST['focus_keywords'] ?? '' );
        $session_id     = sanitize_text_field( $_POST['session_id'] ?? uniqid( 'aap_' ) );

        if ( empty( $niche ) ) {
            self::error( __( 'Please enter a niche.', 'ai-auto-post' ) );
            return;
        }

        $result = AAP_Gemini::get_title_suggestions( $session_id, $niche, $focus_keywords );

        if ( is_wp_error( $result ) ) {
            self::error( $result->get_error_message() );
            return;
        }

        self::success( [
            'titles'     => $result['titles'],
            'session_id' => $session_id,
            'cached'     => $result['cached'],
            'key_used'   => AAP_Key_Manager::mask_key( $result['key_used'] ?? '' ),
            'switched'   => $result['switched'] ?? false,
        ] );
    }

    // -------------------------------------------------------------------------
    // Step 2+: Full Post Generation Pipeline
    // -------------------------------------------------------------------------

    public static function handle_aap_generate_post() {
        self::verify_nonce();

        $session_id     = sanitize_text_field( $_POST['session_id'] ?? '' );
        $title          = sanitize_text_field( $_POST['title'] ?? '' );
        $niche          = sanitize_text_field( $_POST['niche'] ?? '' );
        $focus_keywords = sanitize_text_field( $_POST['focus_keywords'] ?? '' );
        $step           = sanitize_text_field( $_POST['step'] ?? 'article' );
        $post_status    = sanitize_text_field( $_POST['post_status'] ?? get_option( 'aap_default_status', 'draft' ) );
        $skip_publish   = (bool) ( $_POST['preview_only'] ?? false );

        if ( empty( $session_id ) || empty( $title ) ) {
            self::error( __( 'Missing session or title.', 'ai-auto-post' ) );
            return;
        }

        // Review Mode: always force draft
        if ( get_option( 'aap_review_mode', 0 ) ) {
            $post_status = 'draft';
        }

        switch ( $step ) {

            // ------------------------------------------------------------------
            case 'article':
                $result = AAP_Gemini::generate_article( $session_id, $title, $focus_keywords );
                if ( is_wp_error( $result ) ) { self::error( $result->get_error_message(), [ 'step' => 'article' ] ); return; }
                self::success( [
                    'step'     => 'article',
                    'cached'   => $result['cached'],
                    'key_used' => AAP_Key_Manager::mask_key( $result['key_used'] ?? '' ),
                    'switched' => $result['switched'] ?? false,
                    'preview'  => wp_trim_words( wp_strip_all_tags( $result['article'] ), 50 ),
                ] );
                break;

            case 'tags':
                $tag_count = isset( $_POST['tag_count'] ) ? (int) $_POST['tag_count'] : 0;
                $result = AAP_Gemini::generate_tags( $session_id, $title, 'English', $tag_count );
                if ( is_wp_error( $result ) ) { self::error( $result->get_error_message(), [ 'step' => 'tags' ] ); return; }
                self::success( [
                    'step'      => 'tags',
                    'count'     => count( $result['tags'] ),
                    'cached'    => $result['cached'],
                    'key_used'  => AAP_Key_Manager::mask_key( $result['key_used'] ?? '' ),
                    'switched'  => $result['switched'] ?? false,
                ] );
                break;

            // ------------------------------------------------------------------
            case 'meta':
                $result = AAP_Gemini::generate_meta_description( $session_id, $title, $focus_keywords );
                if ( is_wp_error( $result ) ) { self::error( $result->get_error_message(), [ 'step' => 'meta' ] ); return; }
                self::success( [
                    'step'     => 'meta',
                    'meta'     => $result['meta'],
                    'cached'   => $result['cached'],
                    'key_used' => AAP_Key_Manager::mask_key( $result['key_used'] ?? '' ),
                    'switched' => $result['switched'] ?? false,
                ] );
                break;

            // ------------------------------------------------------------------
            case 'category':
                $user_category = sanitize_text_field( $_POST['category'] ?? '' );
                if ( ! empty( $user_category ) ) {
                    // Set transient cache so the publish step picks it up!
                    $cache_key = 'aap_cat_' . $session_id;
                    set_transient( $cache_key, $user_category, 7200 );
                    
                    self::success( [
                        'step'     => 'category',
                        'category' => $user_category,
                        'cached'   => true,
                        'key_used' => '',
                        'switched' => false,
                    ] );
                    return;
                }

                $result = AAP_Gemini::suggest_category( $session_id, $title );
                if ( is_wp_error( $result ) ) { self::error( $result->get_error_message(), [ 'step' => 'category' ] ); return; }
                self::success( [
                    'step'     => 'category',
                    'category' => $result['category'],
                    'cached'   => $result['cached'],
                    'key_used' => AAP_Key_Manager::mask_key( $result['key_used'] ?? '' ),
                    'switched' => $result['switched'] ?? false,
                ] );
                break;

            // ------------------------------------------------------------------
            case 'thumbnail':
                // Accept per-request reference image (base64 sent from JS file reader)
                $ref_image = [];
                $ref_b64   = sanitize_text_field( $_POST['ref_image_b64']   ?? '' );
                $ref_mime  = sanitize_text_field( $_POST['ref_image_mime']  ?? '' );
                if ( $ref_b64 && $ref_mime ) {
                    $ref_image = [ 'base64' => $ref_b64, 'mime_type' => $ref_mime ];
                }

                // Gather Title-to-Image choices
                $t2i_opts = [
                    'thumb_type' => sanitize_text_field( $_POST['thumb_type'] ?? '' ),
                    'bg_type'    => sanitize_text_field( $_POST['t2i_bg_type'] ?? '' ),
                    'bg_val'     => sanitize_text_field( $_POST['t2i_bg_val'] ?? '' ),
                    'size'       => sanitize_text_field( $_POST['t2i_size'] ?? '' ),
                ];

                $result = AAP_Gemini::generate_thumbnail( $session_id, $title, $ref_image, $t2i_opts );
                if ( is_wp_error( $result ) ) { self::error( $result->get_error_message(), [ 'step' => 'thumbnail' ] ); return; }
                self::success( [
                    'step'           => 'thumbnail',
                    'used_reference' => $result['used_reference'] ?? false,
                    'cached'         => $result['cached'],
                    'key_used'       => $result['key_used'] ? AAP_Key_Manager::mask_key( $result['key_used'] ) : '',
                    'switched'       => $result['switched'] ?? false,
                ] );
                break;

            // ------------------------------------------------------------------
            case 'og_image':
                $t2i_opts = [
                    'thumb_type' => sanitize_text_field( $_POST['thumb_type'] ?? '' ),
                    'bg_type'    => sanitize_text_field( $_POST['t2i_bg_type'] ?? '' ),
                    'bg_val'     => sanitize_text_field( $_POST['t2i_bg_val'] ?? '' ),
                ];

                $result = AAP_Gemini::generate_og_image( $session_id, $title, $t2i_opts );
                if ( is_wp_error( $result ) ) { self::error( $result->get_error_message(), [ 'step' => 'og_image' ] ); return; }
                self::success( [
                    'step'     => 'og_image',
                    'cached'   => $result['cached'],
                    'key_used' => $result['key_used'] ? AAP_Key_Manager::mask_key( $result['key_used'] ) : '',
                    'switched' => $result['switched'] ?? false,
                ] );
                break;

            // ------------------------------------------------------------------
            case 'alt_text':
                $result = AAP_Gemini::generate_alt_text( $session_id, $title );
                if ( is_wp_error( $result ) ) { self::error( $result->get_error_message(), [ 'step' => 'alt_text' ] ); return; }
                self::success( [
                    'step'     => 'alt_text',
                    'alt_text' => $result['alt_text'],
                    'cached'   => $result['cached'],
                    'key_used' => AAP_Key_Manager::mask_key( $result['key_used'] ?? '' ),
                    'switched' => $result['switched'] ?? false,
                ] );
                break;

            // ------------------------------------------------------------------
            case 'publish':
                // Gather all cached data
                $article      = AAP_Gemini::get_cached_step( $session_id, 'article' );
                $tags         = AAP_Gemini::get_cached_step( $session_id, 'tags' );
                $meta         = AAP_Gemini::get_cached_step( $session_id, 'meta' );
                $category     = AAP_Gemini::get_cached_step( $session_id, 'cat' );
                $thumb_data   = AAP_Gemini::get_cached_step( $session_id, 'thumb' );
                $og_data      = AAP_Gemini::get_cached_step( $session_id, 'og' );
                $alt_text     = AAP_Gemini::get_cached_step( $session_id, 'alt' );

                if ( ! $article ) {
                    self::error( __( 'Article data not found. Please regenerate.', 'ai-auto-post' ) );
                    return;
                }

                if ( $skip_publish ) {
                    // Preview mode: return content without publishing
                    self::success( [
                        'step'    => 'preview',
                        'title'   => $title,
                        'article' => $article,
                        'tags'    => is_array( $tags ) ? implode( ', ', array_slice( $tags, 0, 10 ) ) . ( count( $tags ) > 10 ? '...' : '' ) : '',
                        'meta'    => $meta,
                        'category'=> $category,
                    ] );
                    return;
                }

                // Duplicate check
                $dup = AAP_Duplicate_Check::find_duplicate( $title );
                if ( $dup ) {
                    self::success( [
                        'step'      => 'duplicate_warning',
                        'dup_id'    => $dup->ID,
                        'dup_title' => $dup->post_title,
                        'dup_url'   => get_permalink( $dup->ID ),
                    ] );
                    return;
                }

                $post_id = AAP_Post_Creator::create( [
                    'title'            => $title,
                    'article'          => $article,
                    'tags'             => $tags ?: [],
                    'meta_description' => $meta ?: '',
                    'category'         => $category ?: '',
                    'alt_text'         => $alt_text ?: $title,
                    'thumbnail_data'   => $thumb_data ?: null,
                    'og_image_data'    => $og_data ?: null,
                    'post_status'      => $post_status,
                ] );

                if ( is_wp_error( $post_id ) ) {
                    self::error( $post_id->get_error_message(), [ 'step' => 'publish' ] );
                    return;
                }

                // Log to history
                $token_est = AAP_History::estimate_tokens(
                    $article,
                    is_array( $tags ) ? implode( ',', $tags ) : '',
                    $meta ?: ''
                );
                $history_id = AAP_History::insert( [
                    'session_id'     => $session_id,
                    'niche'          => $niche,
                    'title'          => $title,
                    'post_id'        => $post_id,
                    'status'         => 'success',
                    'key_used'       => AAP_Gemini::get_current_key( $session_id ) ?? '',
                    'token_estimate' => $token_est,
                ] );

                AAP_Gemini::clear_session_cache( $session_id );

                self::success( [
                    'step'        => 'done',
                    'post_id'     => $post_id,
                    'post_url'    => get_permalink( $post_id ),
                    'edit_url'    => get_edit_post_link( $post_id, 'raw' ),
                    'post_status' => $post_status,
                    'token_est'   => $token_est,
                    'history_id'  => $history_id,
                ] );
                break;

            // ------------------------------------------------------------------
            case 'force_publish':
                // User confirmed duplicate — publish anyway
                $article    = AAP_Gemini::get_cached_step( $session_id, 'article' );
                $tags       = AAP_Gemini::get_cached_step( $session_id, 'tags' );
                $meta       = AAP_Gemini::get_cached_step( $session_id, 'meta' );
                $category   = AAP_Gemini::get_cached_step( $session_id, 'cat' );
                $thumb_data = AAP_Gemini::get_cached_step( $session_id, 'thumb' );
                $og_data    = AAP_Gemini::get_cached_step( $session_id, 'og' );
                $alt_text   = AAP_Gemini::get_cached_step( $session_id, 'alt' );

                $post_id = AAP_Post_Creator::create( [
                    'title'            => $title,
                    'article'          => $article,
                    'tags'             => $tags ?: [],
                    'meta_description' => $meta ?: '',
                    'category'         => $category ?: '',
                    'alt_text'         => $alt_text ?: $title,
                    'thumbnail_data'   => $thumb_data ?: null,
                    'og_image_data'    => $og_data ?: null,
                    'post_status'      => $post_status,
                ] );

                if ( is_wp_error( $post_id ) ) {
                    self::error( $post_id->get_error_message() );
                    return;
                }

                AAP_Gemini::clear_session_cache( $session_id );

                self::success( [
                    'step'     => 'done',
                    'post_id'  => $post_id,
                    'post_url' => get_permalink( $post_id ),
                    'edit_url' => get_edit_post_link( $post_id, 'raw' ),
                ] );
                break;

            default:
                self::error( __( 'Unknown generation step.', 'ai-auto-post' ) );
        }
    }

    // -------------------------------------------------------------------------
    // Duplicate Check (standalone)
    // -------------------------------------------------------------------------

    public static function handle_aap_check_duplicate() {
        self::verify_nonce();
        $title = sanitize_text_field( $_POST['title'] ?? '' );
        $dup   = AAP_Duplicate_Check::find_duplicate( $title );

        if ( $dup ) {
            self::success( [
                'duplicate' => true,
                'dup_id'    => $dup->ID,
                'dup_title' => $dup->post_title,
                'dup_url'   => get_permalink( $dup->ID ),
            ] );
        } else {
            self::success( [ 'duplicate' => false ] );
        }
    }

    // -------------------------------------------------------------------------
    // Step Status (cached steps for resume indicator)
    // -------------------------------------------------------------------------

    public static function handle_aap_get_step_status() {
        self::verify_nonce();
        $session_id = sanitize_text_field( $_POST['session_id'] ?? '' );

        $steps = [ 'titles', 'article', 'tags', 'meta', 'cat', 'thumb', 'og', 'alt' ];
        $done  = [];
        foreach ( $steps as $s ) {
            if ( AAP_Gemini::get_cached_step( $session_id, $s ) ) {
                $done[] = $s;
            }
        }

        self::success( [
            'completed_steps' => $done,
            'current_key'     => AAP_Key_Manager::mask_key( AAP_Gemini::get_current_key( $session_id ) ?? '' ),
        ] );
    }

    // -------------------------------------------------------------------------
    // Save Default Reference Image (from Settings page uploader)
    // -------------------------------------------------------------------------

    public static function handle_aap_save_reference_image() {
        self::verify_nonce();
        if ( ! current_user_can( 'manage_options' ) ) {
            self::error( 'Unauthorized' );
            return;
        }

        $base64    = $_POST['image_b64']  ?? '';
        $mime_type = sanitize_text_field( $_POST['image_mime'] ?? 'image/jpeg' );

        if ( empty( $base64 ) ) {
            self::error( 'No image data received.' );
            return;
        }

        // Basic validation — must decode cleanly
        $decoded = base64_decode( $base64, true );
        if ( ! $decoded ) {
            self::error( 'Invalid image data.' );
            return;
        }

        AAP_Gemini::save_default_reference_image( $base64, $mime_type );

        self::success( [ 'message' => 'Default reference image saved.' ] );
    }

    // -------------------------------------------------------------------------
    // Delete Default Reference Image
    // -------------------------------------------------------------------------

    public static function handle_aap_delete_reference_image() {
        self::verify_nonce();
        if ( ! current_user_can( 'manage_options' ) ) {
            self::error( 'Unauthorized' );
            return;
        }

        AAP_Gemini::delete_default_reference_image();
        self::success( [ 'message' => 'Default reference image removed.' ] );
    }

    // -------------------------------------------------------------------------
    // 🏓 Ping a single key
    // -------------------------------------------------------------------------

    public static function handle_aap_ping_key() {
        self::verify_nonce();
        if ( ! current_user_can( 'manage_options' ) ) {
            self::error( 'Unauthorized' );
            return;
        }

        $index = (int) ( $_POST['key_index'] ?? -1 );
        $keys  = AAP_Key_Manager::get_all_keys();

        if ( ! isset( $keys[ $index ] ) ) {
            self::error( 'Key not found.' );
            return;
        }

        $api_key = $keys[ $index ]['key'];
        $result  = AAP_Key_Manager::ping_key( $api_key );
        $now     = current_time( 'mysql' );

        // Update stored key status based on ping result
        $keys[ $index ]['last_ping_status'] = $result['status'];
        $keys[ $index ]['last_ping_at']     = $now;

        switch ( $result['status'] ) {
            case 'active':
                // If was exhausted, auto-restore
                if ( $keys[ $index ]['status'] === 'exhausted' ) {
                    $keys[ $index ]['status']           = 'active';
                    $keys[ $index ]['exhausted_at']     = null;
                    $keys[ $index ]['reset_at_ts']      = null;
                    $keys[ $index ]['retry_after_secs'] = null;
                }
                break;
            case 'exhausted':
                $keys[ $index ]['status']           = 'exhausted';
                $keys[ $index ]['exhausted_at']     = $keys[ $index ]['exhausted_at'] ?? $now;
                $keys[ $index ]['reset_at_ts']      = $result['reset_at_ts'];
                $keys[ $index ]['retry_after_secs'] = $result['retry_after'];
                break;
            case 'invalid':
                $keys[ $index ]['status'] = 'invalid';
                break;
        }

        AAP_Key_Manager::save_all_keys( $keys );

        // Compute reset countdown for UI
        $reset_in_secs = null;
        if ( ! empty( $keys[ $index ]['reset_at_ts'] ) ) {
            $reset_in_secs = max( 0, (int) $keys[ $index ]['reset_at_ts'] - time() );
        }

        self::success( [
            'index'          => $index,
            'status'         => $result['status'],
            'message'        => $result['message'],
            'http_code'      => $result['http_code'],
            'retry_after'    => $result['retry_after'],
            'reset_at_ts'    => $result['reset_at_ts'] ?? null,
            'reset_in_secs'  => $reset_in_secs,
            'reset_in_human' => $reset_in_secs !== null
                                    ? AAP_Key_Manager::format_seconds( $reset_in_secs )
                                    : null,
        ] );
    }

    // -------------------------------------------------------------------------
    // 🏓 Ping all keys
    // -------------------------------------------------------------------------

    public static function handle_aap_ping_all_keys() {
        self::verify_nonce();
        if ( ! current_user_can( 'manage_options' ) ) {
            self::error( 'Unauthorized' );
            return;
        }

        $raw_results = AAP_Key_Manager::ping_all_keys();
        $keys        = AAP_Key_Manager::get_all_keys();

        $formatted = [];
        foreach ( $raw_results as $i => $r ) {
            $reset_in_secs = null;
            if ( ! empty( $keys[ $i ]['reset_at_ts'] ) ) {
                $reset_in_secs = max( 0, (int) $keys[ $i ]['reset_at_ts'] - time() );
            }

            $formatted[ $i ] = [
                'index'          => $i,
                'status'         => $r['status'],
                'message'        => $r['message'],
                'reset_at_ts'    => $keys[ $i ]['reset_at_ts'] ?? null,
                'reset_in_secs'  => $reset_in_secs,
                'reset_in_human' => $reset_in_secs !== null
                                        ? AAP_Key_Manager::format_seconds( $reset_in_secs )
                                        : null,
            ];
        }

        $stats = AAP_Key_Manager::get_stats();
        self::success( [
            'results' => $formatted,
            'summary' => [
                'active'    => $stats['active'],
                'exhausted' => $stats['exhausted'],
                'invalid'   => $stats['invalid'],
                'total'     => $stats['total'],
            ],
        ] );
    }

    // -------------------------------------------------------------------------
    // Bulk Planner: Generate 20 Unique Titles
    // -------------------------------------------------------------------------

    public static function handle_aap_generate_planner_titles() {
        self::verify_nonce();
        if ( ! current_user_can( 'manage_options' ) ) {
            self::error( 'Unauthorized' );
            return;
        }

        $niche      = sanitize_text_field( $_POST['niche'] ?? '' );
        $language   = sanitize_text_field( $_POST['language'] ?? 'English' );
        $mode       = sanitize_text_field( $_POST['mode'] ?? 'standard' );
        $session_id = 'planner_' . uniqid();

        if ( empty( $niche ) ) {
            self::error( __( 'Please enter a niche.', 'ai-auto-post' ) );
            return;
        }

        if ( $mode === 'silo' ) {
            $result = AAP_Gemini::get_silo_suggestions( $session_id, $niche, $language );
            if ( is_wp_error( $result ) ) {
                self::error( $result->get_error_message() );
                return;
            }
            self::success( [
                'mode'     => 'silo',
                'pillar'   => $result['silo']['pillar'],
                'titles'   => $result['silo']['subposts'],
                'key_used' => AAP_Key_Manager::mask_key( $result['key_used'] ?? '' ),
                'switched' => $result['switched'] ?? false,
            ] );
            return;
        }

        $count = isset( $_POST['count'] ) ? (int) $_POST['count'] : 20;
        if ( $count < 5 )  $count = 5;
        if ( $count > 50 ) $count = 50;

        $result = AAP_Gemini::get_planner_title_suggestions( $session_id, $niche, $language, $count );

        if ( is_wp_error( $result ) ) {
            self::error( $result->get_error_message() );
            return;
        }

        $titles = $result['titles'];

        if ( empty( $titles ) ) {
            self::error( __( 'No titles could be parsed. Try again.', 'ai-auto-post' ) );
            return;
        }

        self::success( [
            'mode'     => 'standard',
            'titles'   => array_slice( $titles, 0, $count ),
            'key_used' => AAP_Key_Manager::mask_key( $result['key_used'] ?? '' ),
            'switched' => $result['switched'] ?? false,
        ] );
    }

    // -------------------------------------------------------------------------
    // Bulk Planner: Save Planned Tasks
    // -------------------------------------------------------------------------

    public static function handle_aap_save_planner_tasks() {
        self::verify_nonce();
        if ( ! current_user_can( 'manage_options' ) ) {
            self::error( 'Unauthorized' );
            return;
        }

        $niche     = sanitize_text_field( $_POST['niche'] ?? '' );
        $language  = sanitize_text_field( $_POST['language'] ?? 'English' );
        $mode      = sanitize_text_field( $_POST['mode'] ?? 'standard' );
        $tasks     = isset( $_POST['tasks'] ) ? (array) $_POST['tasks'] : [];
        $tag_count = isset( $_POST['tag_count'] ) ? (int) $_POST['tag_count'] : 0;
        
        $meta_post = isset( $_POST['meta'] ) ? (array) $_POST['meta'] : [];
        $meta = [];
        foreach ( $meta_post as $k => $v ) {
            $meta[ sanitize_key( $k ) ] = sanitize_text_field( $v );
        }

        if ( empty( $niche ) || empty( $tasks ) ) {
            self::error( __( 'Missing niche or task list.', 'ai-auto-post' ) );
            return;
        }

        $count = 0;
        if ( $mode === 'silo' ) {
            // First task is the pillar
            $pillar_task = array_shift( $tasks );
            $pillar_title = sanitize_text_field( $pillar_task['title'] ?? '' );
            $pillar_cat   = sanitize_text_field( $pillar_task['category'] ?? '' );

            if ( $pillar_title ) {
                $pillar_queue_id = AAP_Queue::enqueue( $niche, $pillar_title, $language, $pillar_cat, 0, null, 'pillar', $tag_count, $meta );
                if ( $pillar_queue_id ) {
                    // Update it to have silo_id = itself
                    global $wpdb;
                    $wpdb->update( AAP_Queue::table_name(), [ 'silo_id' => $pillar_queue_id ], [ 'id' => $pillar_queue_id ] );
                    $count++;

                    // Now enqueue all cluster tasks with this silo_id
                    foreach ( $tasks as $task ) {
                        $title    = sanitize_text_field( $task['title'] ?? '' );
                        $category = sanitize_text_field( $task['category'] ?? '' );
                        if ( $title ) {
                            AAP_Queue::enqueue( $niche, $title, $language, $category, 0, $pillar_queue_id, 'cluster', $tag_count, $meta );
                            $count++;
                        }
                    }
                }
            }
        } else {
            // Standard enqueuing
            foreach ( $tasks as $task ) {
                $title    = sanitize_text_field( $task['title'] ?? '' );
                $category = sanitize_text_field( $task['category'] ?? '' );

                if ( $title ) {
                    AAP_Queue::enqueue( $niche, $title, $language, $category, 0, null, null, $tag_count, $meta );
                    $count++;
                }
            }
        }

        self::success( [
            'saved'   => $count,
            'message' => sprintf( __( 'Successfully enqueued %d tasks in the background worker.', 'ai-auto-post' ), $count )
        ] );
    }

    // -------------------------------------------------------------------------
    // Queue Runner: Process Next Item (Manual AJAX Trigger)
    // -------------------------------------------------------------------------

    public static function handle_aap_process_queue_item() {
        self::verify_nonce();
        if ( ! current_user_can( 'manage_options' ) ) {
            self::error( 'Unauthorized' );
            return;
        }

        $stats = AAP_Key_Manager::get_stats();
        if ( $stats['active'] === 0 ) {
            self::error( __( 'All API keys are exhausted. Pausing queue processor.', 'ai-auto-post' ) );
            return;
        }

        $next = AAP_Queue::get_next_queued();
        if ( ! $next ) {
            self::success( [ 'processed' => false, 'message' => __( 'Queue is empty.', 'ai-auto-post' ) ] );
            return;
        }

        // Process item
        AAP_Queue::mark_processing( $next->id );

        // If WP post already exists as draft, publish it
        if ( ! empty( $next->post_id ) ) {
            $res = wp_update_post( [ 'ID' => $next->post_id, 'post_status' => 'publish' ], true );
            if ( is_wp_error( $res ) ) {
                AAP_Queue::mark_failed( $next->id, $res->get_error_message() );
                self::error( $res->get_error_message() );
                return;
            }
            AAP_Queue::mark_published( $next->id, $next->post_id );
            self::success( [ 'processed' => true, 'title' => $next->title, 'post_id' => $next->post_id ] );
            return;
        }

        $meta = ! empty( $next->meta ) ? maybe_unserialize( $next->meta ) : [];
        if ( ! is_array( $meta ) ) {
            $meta = [];
        }

        // Otherwise generate fresh post
        $post_id = AAP_Scheduler::generate_and_publish(
            $next->niche,
            $next->title,
            '',
            $next->language ?? 'English',
            $next->category ?? '',
            $next->silo_id ?? null,
            $next->silo_role ?? null,
            (int) ( $next->tag_count ?? 0 ),
            $meta
        );

        if ( is_wp_error( $post_id ) ) {
            AAP_Queue::mark_failed( $next->id, $post_id->get_error_message() );
            self::error( $post_id->get_error_message(), [ 'id' => $next->id ] );
            return;
        }

        AAP_Queue::mark_published( $next->id, $post_id );
        self::success( [
            'processed' => true,
            'id'        => $next->id,
            'title'     => $next->title,
            'post_id'   => $post_id,
            'url'       => get_permalink( $post_id ),
        ] );
    }

    public static function handle_aap_generate_pending_thumbnail() {
        self::verify_nonce();
        if ( ! current_user_can( 'manage_options' ) ) {
            self::error( 'Unauthorized' );
            return;
        }

        $post_id = (int) ( $_POST['post_id'] ?? 0 );
        if ( ! $post_id ) {
            self::error( 'Invalid Post ID.' );
            return;
        }

        $post = get_post( $post_id );
        if ( ! $post ) {
            self::error( 'Post not found.' );
            return;
        }

        $engine = sanitize_text_field( $_POST['engine'] ?? '' );
        if ( ! in_array( $engine, [ 'ai', 'text_to_image' ], true ) ) {
            $engine = get_option( 'aap_thumb_type', 'ai' );
        }

        $session_id = 'thumb_regen_' . uniqid();
        $result = AAP_Gemini::generate_thumbnail( $session_id, $post->post_title, [], [ 'thumb_type' => $engine ] );

        if ( is_wp_error( $result ) ) {
            self::error( $result->get_error_message() );
            return;
        }

        $image_data = $result['image_data'];
        if ( empty( $image_data ) ) {
            self::error( 'Failed to generate image data.' );
            return;
        }

        $alt_text = get_post_meta( $post_id, '_wp_attachment_image_alt', true );
        if ( empty( $alt_text ) ) {
            $alt_text = $post->post_title;
        }

        $attachment_id = AAP_Post_Creator::upload_image(
            $image_data['base64'],
            $image_data['mime_type'],
            $post->post_title . '-thumbnail',
            $alt_text
        );

        if ( is_wp_error( $attachment_id ) ) {
            self::error( $attachment_id->get_error_message() );
            return;
        }

        set_post_thumbnail( $post_id, $attachment_id );
        delete_post_meta( $post_id, '_aap_thumbnail_pending' );

        $thumbnail_url = get_the_post_thumbnail_url( $post_id, 'thumbnail' );

        self::success( [
            'message'       => 'Thumbnail successfully generated and set!',
            'thumbnail_url' => $thumbnail_url,
        ] );
    }

    public static function handle_aap_generate_tags() {
        self::verify_nonce();
        if ( ! current_user_can( 'manage_options' ) ) {
            self::error( 'Unauthorized' );
            return;
        }

        $post_id   = (int) ( $_POST['post_id'] ?? 0 );
        $tag_count = (int) ( $_POST['tag_count'] ?? 5 );

        if ( ! $post_id ) {
            self::error( 'Invalid Post ID.' );
            return;
        }

        $post = get_post( $post_id );
        if ( ! $post ) {
            self::error( 'Post not found.' );
            return;
        }

        if ( $tag_count < 1 )   $tag_count = 1;
        if ( $tag_count > 100 ) $tag_count = 100;

        $session_id = 'tags_regen_' . uniqid();
        $result = AAP_Gemini::generate_tags( $session_id, $post->post_title, 'English', $tag_count );

        if ( is_wp_error( $result ) ) {
            self::error( $result->get_error_message() );
            return;
        }

        $tags = $result['tags'] ?? [];
        if ( empty( $tags ) ) {
            self::error( 'No tags returned by Gemini.' );
            return;
        }

        // Set the new tags (overwrite existing ones)
        wp_set_post_tags( $post_id, $tags, false );

        self::success( [
            'message' => 'Tags successfully generated and updated!',
            'tags'    => $tags,
        ] );
    }

    public static function handle_aap_translate_post() {
        self::verify_nonce();
        if ( ! current_user_can( 'manage_options' ) ) {
            self::error( 'Unauthorized' );
            return;
        }

        $post_id     = (int) ( $_POST['post_id'] ?? 0 );
        $target_lang = sanitize_text_field( $_POST['target_lang'] ?? 'Spanish' );
        $status      = sanitize_text_field( $_POST['status'] ?? 'draft' );

        if ( ! $post_id ) {
            self::error( 'Invalid Post ID.' );
            return;
        }

        $post = get_post( $post_id );
        if ( ! $post ) {
            self::error( 'Post not found.' );
            return;
        }

        $session_id = 'trans_' . $post_id . '_' . uniqid();

        // 1. Translate Title
        $title_res = AAP_Gemini::translate_text( $session_id, $post->post_title, $target_lang );
        if ( is_wp_error( $title_res ) ) {
            self::error( 'Failed to translate title: ' . $title_res->get_error_message() );
            return;
        }
        $translated_title = $title_res['text'];

        // 2. Translate HTML Content (Article)
        $article_res = AAP_Gemini::translate_html( $session_id, $post->post_content, $target_lang );
        if ( is_wp_error( $article_res ) ) {
            self::error( 'Failed to translate article content: ' . $article_res->get_error_message() );
            return;
        }
        $translated_content = $article_res['html'];

        // 3. Translate Meta Description
        $orig_meta = get_post_meta( $post_id, '_yoast_wpseo_metadesc', true );
        if ( empty( $orig_meta ) ) {
            $orig_meta = get_post_meta( $post_id, '_rank_math_description', true );
        }
        if ( empty( $orig_meta ) ) {
            $orig_meta = get_post_meta( $post_id, '_aap_meta_description', true );
        }
        
        $translated_meta = '';
        if ( ! empty( $orig_meta ) ) {
            $meta_res = AAP_Gemini::translate_text( $session_id, $orig_meta, $target_lang );
            if ( ! is_wp_error( $meta_res ) ) {
                $translated_meta = $meta_res['text'];
            }
        }

        // 4. Translate/Generate Tags
        $orig_tags = wp_get_post_tags( $post_id, [ 'fields' => 'names' ] );
        $translated_tags = [];
        if ( ! empty( $orig_tags ) ) {
            $tags_string = implode( ', ', $orig_tags );
            $tags_res = AAP_Gemini::translate_text( $session_id, $tags_string, $target_lang );
            if ( ! is_wp_error( $tags_res ) ) {
                $translated_tags = array_filter( array_map( 'trim', explode( ',', $tags_res['text'] ) ) );
            }
        }

        // 5. Insert translated post
        $categories = wp_get_post_categories( $post_id );
        
        $new_post_id = wp_insert_post( [
            'post_title'    => sanitize_text_field( $translated_title ),
            'post_content'  => wp_kses_post( $translated_content ),
            'post_status'   => $status,
            'post_author'   => $post->post_author,
            'post_category' => $categories,
        ], true );

        if ( is_wp_error( $new_post_id ) ) {
            self::error( 'Failed to create translated post: ' . $new_post_id->get_error_message() );
            return;
        }

        // Meta flags
        update_post_meta( $new_post_id, '_aap_generated', '1' );
        update_post_meta( $new_post_id, '_aap_translated_from', $post_id );
        update_post_meta( $new_post_id, '_aap_translation_lang', $target_lang );

        // Copy over featured image (thumbnail)
        $thumbnail_id = get_post_thumbnail_id( $post_id );
        if ( $thumbnail_id ) {
            set_post_thumbnail( $new_post_id, $thumbnail_id );
        }

        // Set Meta Description
        if ( ! empty( $translated_meta ) ) {
            update_post_meta( $new_post_id, '_aap_meta_description', $translated_meta );
            if ( class_exists( 'WPSEO_Meta' ) ) {
                update_post_meta( $new_post_id, '_yoast_wpseo_metadesc', $translated_meta );
            }
            update_post_meta( $new_post_id, '_rank_math_description', $translated_meta );
        }

        // Set Tags
        if ( ! empty( $translated_tags ) ) {
            wp_set_post_tags( $new_post_id, $translated_tags, false );
        }

        self::success( [
            'message'         => 'Post successfully translated!',
            'translated_id'   => $new_post_id,
            'translated_title' => $translated_title,
            'edit_url'        => get_edit_post_link( $new_post_id ),
        ] );
    }

    // -------------------------------------------------------------------------
    // Google Indexing API — Manual Request
    // -------------------------------------------------------------------------

    public static function handle_aap_request_indexing() {
        self::verify_nonce();
        if ( ! current_user_can( 'manage_options' ) ) {
            self::error( 'Unauthorized' );
            return;
        }

        $post_id = (int) ( $_POST['post_id'] ?? 0 );
        if ( ! $post_id ) {
            self::error( 'Invalid Post ID.' );
            return;
        }

        $post = get_post( $post_id );
        if ( ! $post || $post->post_status !== 'publish' ) {
            self::error( 'Post must be published to request indexing.' );
            return;
        }

        $url    = get_permalink( $post_id );
        $result = AAP_GSC_Helper::submit_url( $url, 'URL_UPDATED' );

        if ( is_wp_error( $result ) ) {
            self::error( $result->get_error_message() );
            return;
        }

        update_post_meta( $post_id, '_aap_gsc_last_ping', current_time( 'mysql' ) );

        self::success( [
            'message'   => 'Indexing request submitted successfully!',
            'url'       => $url,
            'response'  => $result,
        ] );
    }

    // -------------------------------------------------------------------------
    // AI Article Rewriter
    // -------------------------------------------------------------------------

    public static function handle_aap_rewrite_post() {
        self::verify_nonce();
        if ( ! current_user_can( 'manage_options' ) ) {
            self::error( 'Unauthorized' );
            return;
        }

        $post_id      = (int) ( $_POST['post_id'] ?? 0 );
        $instructions = sanitize_textarea_field( $_POST['instructions'] ?? '' );
        $save         = sanitize_text_field( $_POST['save'] ?? 'preview' );

        if ( ! $post_id ) {
            self::error( 'Invalid Post ID.' );
            return;
        }

        $post = get_post( $post_id );
        if ( ! $post ) {
            self::error( 'Post not found.' );
            return;
        }

        $session_id = 'rewrite_' . $post_id . '_' . uniqid();
        $extra_instructions = ! empty( $instructions )
            ? "\n\nAdditional update instructions from the user:\n\"{$instructions}\""
            : '';

        $prompt = "You are a professional blog content editor. Rewrite and freshen the following blog post to make it more engaging, up-to-date, and SEO-optimized.
Keep the same general topic and heading structure, but improve readability, update any outdated information, and vary the sentence structure to sound more natural and human-written.
{$extra_instructions}

IMPORTANT: Return ONLY the rewritten HTML content (using h2, h3, p, ul, li, strong tags). Do NOT include the title, markdown code fences, or conversational text.

Original blog post content:
\"\"\"
{$post->post_content}
\"\"\"";

        $result = AAP_Gemini::request( $session_id, AAP_Gemini::get_text_model(), [
            'contents' => [
                [ 'parts' => [ [ 'text' => $prompt ] ] ]
            ],
            'generationConfig' => [
                'temperature'     => 0.6,
                'maxOutputTokens' => 8192,
            ],
        ] );

        if ( is_wp_error( $result ) ) {
            self::error( $result->get_error_message() );
            return;
        }

        $rewritten = AAP_Gemini::extract_text( $result['data'] );
        $rewritten = preg_replace( '/^```html\s*/i', '', $rewritten );
        $rewritten = preg_replace( '/```\s*$/', '', $rewritten );

        if ( $save === 'save' ) {
            wp_update_post( [
                'ID'           => $post_id,
                'post_content' => wp_kses_post( $rewritten ),
            ] );

            self::success( [
                'message' => 'Post content successfully updated!',
                'saved'   => true,
            ] );
        } else {
            self::success( [
                'message'  => 'Rewrite preview generated!',
                'saved'    => false,
                'preview'  => $rewritten,
            ] );
        }
    }
}
