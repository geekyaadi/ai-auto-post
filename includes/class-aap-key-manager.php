<?php
/**
 * Manages the Gemini API key pool.
 *
 * Key data structure (stored in wp_options):
 * {
 *   key:              string  — the raw API key
 *   status:           string  — 'active' | 'exhausted' | 'invalid'
 *   exhausted_at:     string  — MySQL datetime when it was exhausted
 *   reset_at_ts:      int     — Unix timestamp of exact reset time (from Retry-After)
 *   retry_after_secs: int     — raw Retry-After value in seconds (for display)
 *   requests:         int     — total requests made
 *   failures:         int     — total quota failures
 *   tokens_used:      int     — estimated tokens consumed
 *   last_ping_status: string  — 'ok' | 'exhausted' | 'invalid' | null
 *   last_ping_at:     string  — MySQL datetime of last ping
 * }
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class AAP_Key_Manager {

    const OPTION_KEYS      = 'aap_api_keys';
    const OPTION_RESET_MIN = 'aap_key_reset_minutes'; // fallback only

    // -------------------------------------------------------------------------
    // Key CRUD
    // -------------------------------------------------------------------------

    public static function get_all_keys() {
        $keys = get_option( self::OPTION_KEYS, [] );
        if ( ! is_array( $keys ) ) {
            $keys = [];
        }
        return array_values( $keys );
    }

    public static function save_all_keys( array $keys ) {
        update_option( self::OPTION_KEYS, array_values( $keys ) );
    }

    public static function add_key( string $api_key, string $provider = 'gemini' ) {
        $keys = self::get_all_keys();
        foreach ( $keys as $k ) {
            if ( $k['key'] === $api_key ) return false;
        }
        $keys[] = [
            'key'              => $api_key,
            'provider'         => $provider,
            'status'           => 'active',
            'exhausted_at'     => null,
            'reset_at_ts'      => null,
            'retry_after_secs' => null,
            'requests'         => 0,
            'failures'         => 0,
            'tokens_used'      => 0,
            'last_ping_status' => null,
            'last_ping_at'     => null,
        ];
        self::save_all_keys( $keys );
        return true;
    }

    public static function delete_key( int $index ) {
        $keys = self::get_all_keys();
        if ( isset( $keys[ $index ] ) ) {
            unset( $keys[ $index ] );
            self::save_all_keys( $keys );
            return true;
        }
        return false;
    }

    public static function reset_key( int $index ) {
        $keys = self::get_all_keys();
        if ( isset( $keys[ $index ] ) ) {
            $keys[ $index ]['status']           = 'active';
            $keys[ $index ]['exhausted_at']     = null;
            $keys[ $index ]['reset_at_ts']      = null;
            $keys[ $index ]['retry_after_secs'] = null;
            self::save_all_keys( $keys );
            return true;
        }
        return false;
    }

    // -------------------------------------------------------------------------
    // Active Key Selection & Rotation
    // -------------------------------------------------------------------------

    /**
     * Returns the first active key matching the current provider.
     */
    public static function get_active_key( ?string $provider = null ) {
        if ( $provider === null ) {
            $provider = get_option( 'aap_active_provider', 'gemini' );
        }
        self::auto_reset_check();
        $keys = self::get_all_keys();
        foreach ( $keys as $k ) {
            $k_provider = $k['provider'] ?? 'gemini';
            if ( $k_provider === $provider && $k['status'] === 'active' ) {
                return $k['key'];
            }
        }
        return null;
    }

    /**
     * Returns the next active key of the same provider.
     */
    public static function get_next_active_key( string $current_key, ?string $provider = null ) {
        if ( $provider === null ) {
            $provider = get_option( 'aap_active_provider', 'gemini' );
        }
        self::auto_reset_check();
        $keys  = self::get_all_keys();
        $found = false;

        // First pass: look after current key
        foreach ( $keys as $k ) {
            $k_provider = $k['provider'] ?? 'gemini';
            if ( $found && $k_provider === $provider && $k['status'] === 'active' ) {
                return $k['key'];
            }
            if ( $k['key'] === $current_key ) {
                $found = true;
            }
        }
        // Wrap around
        foreach ( $keys as $k ) {
            if ( $k['key'] === $current_key ) break;
            $k_provider = $k['provider'] ?? 'gemini';
            if ( $k_provider === $provider && $k['status'] === 'active' ) {
                return $k['key'];
            }
        }
        return null;
    }

    // -------------------------------------------------------------------------
    // Status Updates
    // -------------------------------------------------------------------------

    /**
     * Mark a key as exhausted.
     *
     * @param string   $api_key       The API key string.
     * @param int|null $retry_after   Seconds until quota resets (from Retry-After header or body).
     *                                If null, falls back to the configured default interval.
     */
    public static function mark_exhausted( string $api_key, ?int $retry_after = null ) {
        $keys = self::get_all_keys();

        // Fallback: use the configured reset interval
        if ( $retry_after === null || $retry_after <= 0 ) {
            $retry_after = (int) get_option( self::OPTION_RESET_MIN, 60 ) * 60;
        }

        // Clamp: never less than 10s, never more than 24h
        $retry_after = max( 10, min( $retry_after, 86400 ) );
        $reset_ts    = time() + $retry_after;

        foreach ( $keys as &$k ) {
            if ( $k['key'] === $api_key ) {
                $k['status']           = 'exhausted';
                $k['exhausted_at']     = current_time( 'mysql' );
                $k['reset_at_ts']      = $reset_ts;
                $k['retry_after_secs'] = $retry_after;
                $k['failures']         = ( $k['failures'] ?? 0 ) + 1;
                break;
            }
        }
        unset( $k );
        self::save_all_keys( $keys );
    }

    /**
     * Mark a key as permanently invalid (bad key, 400/403 response).
     */
    public static function mark_invalid( string $api_key ) {
        $keys = self::get_all_keys();
        foreach ( $keys as &$k ) {
            if ( $k['key'] === $api_key ) {
                $k['status']  = 'invalid';
                $k['failures'] = ( $k['failures'] ?? 0 ) + 1;
                break;
            }
        }
        unset( $k );
        self::save_all_keys( $keys );
    }

    public static function increment_requests( string $api_key, int $tokens = 0 ) {
        $keys = self::get_all_keys();
        foreach ( $keys as &$k ) {
            if ( $k['key'] === $api_key ) {
                $k['requests']    = ( $k['requests']   ?? 0 ) + 1;
                $k['tokens_used'] = ( $k['tokens_used'] ?? 0 ) + $tokens;
                break;
            }
        }
        unset( $k );
        self::save_all_keys( $keys );
    }

    // -------------------------------------------------------------------------
    // Auto-Reset (Retry-After based)
    // -------------------------------------------------------------------------

    /**
     * Checks each exhausted key against its exact reset_at_ts.
     * Falls back to the configured reset_minutes if reset_at_ts is not set.
     */
    public static function auto_reset_check() {
        $keys    = self::get_all_keys();
        $changed = false;
        $now     = time();

        foreach ( $keys as &$k ) {
            if ( $k['status'] !== 'exhausted' ) continue;

            $should_reset = false;

            if ( ! empty( $k['reset_at_ts'] ) ) {
                // Exact reset time from Retry-After header
                $should_reset = ( $now >= (int) $k['reset_at_ts'] );
            } elseif ( ! empty( $k['exhausted_at'] ) ) {
                // Fallback: use configured interval
                $fallback_secs = (int) get_option( self::OPTION_RESET_MIN, 60 ) * 60;
                $should_reset  = ( $now - strtotime( $k['exhausted_at'] ) ) >= $fallback_secs;
            }

            if ( $should_reset ) {
                $k['status']           = 'active';
                $k['exhausted_at']     = null;
                $k['reset_at_ts']      = null;
                $k['retry_after_secs'] = null;
                $changed               = true;
            }
        }
        unset( $k );

        if ( $changed ) {
            self::save_all_keys( $keys );
        }
    }

    // -------------------------------------------------------------------------
    // 🏓 Ping — Minimal test call to verify a key's status
    // -------------------------------------------------------------------------

    /**
     * Makes a minimal, near-zero-token API call to check if a key is alive.
     *
     * Returns an array:
     * {
     *   status:        'active' | 'exhausted' | 'invalid' | 'error'
     *   retry_after:   int|null  — seconds until reset (if exhausted)
     *   reset_at_ts:   int|null  — Unix timestamp of reset (if exhausted)
     *   message:       string    — human-readable status message
     *   http_code:     int
     * }
     */
    public static function ping_key( string $api_key ) {
        $keys     = self::get_all_keys();
        $provider = 'gemini';
        foreach ( $keys as $k ) {
            if ( $k['key'] === $api_key ) {
                $provider = $k['provider'] ?? 'gemini';
                break;
            }
        }

        if ( $provider === 'openai' ) {
            $url  = 'https://api.openai.com/v1/chat/completions';
            $body = wp_json_encode( [
                'model'                  => 'gpt-4o-mini',
                'messages'               => [ [ 'role' => 'user', 'content' => 'Hi' ] ],
                'max_completion_tokens'  => 1,
            ] );

            $response = wp_remote_post( $url, [
                'timeout' => 20,
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $api_key,
                ],
                'body'    => $body,
            ] );
        } else {
            $url  = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . $api_key;
            $body = wp_json_encode( [
                'contents'         => [ [ 'parts' => [ [ 'text' => 'Hi' ] ] ] ],
                'generationConfig' => [ 'maxOutputTokens' => 1, 'temperature' => 0 ],
            ] );

            $response = wp_remote_post( $url, [
                'timeout' => 20,
                'headers' => [ 'Content-Type' => 'application/json' ],
                'body'    => $body,
            ] );
        }

        if ( is_wp_error( $response ) ) {
            return [
                'status'    => 'error',
                'message'   => $response->get_error_message(),
                'http_code' => 0,
            ];
        }

        $http_code    = (int) wp_remote_retrieve_response_code( $response );
        $body_decoded = json_decode( wp_remote_retrieve_body( $response ), true );

        // --- Active ---
        if ( $http_code === 200 ) {
            return [
                'status'       => 'active',
                'message'      => 'Key is active and working.',
                'http_code'    => 200,
                'retry_after'  => null,
                'reset_at_ts'  => null,
            ];
        }

        // --- Exhausted (429) ---
        if ( $http_code === 429 ||
             ( $provider === 'gemini' && isset( $body_decoded['error']['status'] ) && $body_decoded['error']['status'] === 'RESOURCE_EXHAUSTED' ) ||
             ( $provider === 'openai' && isset( $body_decoded['error']['type'] ) && $body_decoded['error']['type'] === 'insufficient_quota' ) ) {

            $retry_after = self::parse_retry_after( $response, $body_decoded );
            $reset_ts    = $retry_after ? time() + $retry_after : null;

            return [
                'status'       => 'exhausted',
                'message'      => 'Quota exhausted. Resets in ' . ( $retry_after ? self::format_seconds( $retry_after ) : 'unknown time' ) . '.',
                'http_code'    => $http_code,
                'retry_after'  => $retry_after,
                'reset_at_ts'  => $reset_ts,
            ];
        }

        // --- Invalid (400 / 401 / 403) ---
        if ( in_array( $http_code, [ 400, 401, 403 ], true ) ) {
            $msg = $body_decoded['error']['message'] ?? 'Invalid or unauthorized API key.';
            return [
                'status'      => 'invalid',
                'message'     => $msg,
                'http_code'   => $http_code,
                'retry_after' => null,
                'reset_at_ts' => null,
            ];
        }

        // --- Other error ---
        $msg = $body_decoded['error']['message'] ?? "Unexpected HTTP {$http_code}";
        return [
            'status'      => 'error',
            'message'     => $msg,
            'http_code'   => $http_code,
            'retry_after' => null,
            'reset_at_ts' => null,
        ];
    }

    /**
     * Pings all keys and updates their stored status.
     * Returns array of results keyed by index.
     */
    public static function ping_all_keys() {
        $keys    = self::get_all_keys();
        $results = [];

        foreach ( $keys as $i => &$k ) {
            $result = self::ping_key( $k['key'] );
            $now    = current_time( 'mysql' );

            $k['last_ping_status'] = $result['status'];
            $k['last_ping_at']     = $now;

            // Update stored status based on ping result
            switch ( $result['status'] ) {
                case 'active':
                    if ( $k['status'] === 'exhausted' ) {
                        // Key recovered — reset it
                        $k['status']           = 'active';
                        $k['exhausted_at']     = null;
                        $k['reset_at_ts']      = null;
                        $k['retry_after_secs'] = null;
                    }
                    break;
                case 'exhausted':
                    $k['status']           = 'exhausted';
                    $k['exhausted_at']     = $k['exhausted_at'] ?? $now;
                    $k['reset_at_ts']      = $result['reset_at_ts'];
                    $k['retry_after_secs'] = $result['retry_after'];
                    break;
                case 'invalid':
                    $k['status'] = 'invalid';
                    break;
            }

            $results[ $i ] = $result;
        }
        unset( $k );

        self::save_all_keys( $keys );
        return $results;
    }

    // -------------------------------------------------------------------------
    // Error Detection (used by Gemini request loop)
    // -------------------------------------------------------------------------

    public static function is_quota_error( $response ) {
        if ( is_wp_error( $response ) ) return false;

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code === 429 ) return true;

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( isset( $body['error']['code'] ) &&
             in_array( $body['error']['code'], [ 429, 503 ], true ) ) return true;
        if ( isset( $body['error']['status'] ) &&
             in_array( $body['error']['status'], [ 'RESOURCE_EXHAUSTED', 'UNAVAILABLE' ], true ) ) return true;

        return false;
    }

    /**
     * Detect if response indicates an invalid key (400 / 403 / API_KEY_INVALID).
     */
    public static function is_invalid_key_error( $response ) {
        if ( is_wp_error( $response ) ) return false;

        $code = (int) wp_remote_retrieve_response_code( $response );
        if ( in_array( $code, [ 400, 403 ], true ) ) {
            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            $status = $body['error']['status'] ?? '';
            $msg    = $body['error']['message'] ?? '';
            if ( in_array( $status, [ 'INVALID_ARGUMENT', 'PERMISSION_DENIED', 'UNAUTHENTICATED' ], true ) ||
                 stripos( $msg, 'api key' ) !== false ||
                 stripos( $msg, 'invalid' ) !== false ) {
                return true;
            }
        }
        return false;
    }

    // -------------------------------------------------------------------------
    // Retry-After Parsing
    // -------------------------------------------------------------------------

    /**
     * Extracts retry-after seconds from:
     * 1. HTTP `Retry-After` header
     * 2. Gemini error body `details[].retryDelay` (e.g. "47s")
     *
     * Returns int seconds or null.
     */
    public static function parse_retry_after( $response, ?array $body_decoded = null ) {
        // 1. HTTP header
        $header = (int) wp_remote_retrieve_header( $response, 'retry-after' );
        if ( $header > 0 ) return $header;

        // 2. Gemini body: error.details[].retryDelay = "47s"
        if ( $body_decoded === null ) {
            $body_decoded = json_decode( wp_remote_retrieve_body( $response ), true );
        }
        $details = $body_decoded['error']['details'] ?? [];
        foreach ( $details as $detail ) {
            if ( isset( $detail['retryDelay'] ) ) {
                // Format: "47s" or "3600s"
                $raw = $detail['retryDelay'];
                if ( preg_match( '/^(\d+)s$/', $raw, $m ) ) {
                    return (int) $m[1];
                }
            }
        }

        // 3. Check ratelimitInfo
        foreach ( $details as $detail ) {
            if ( isset( $detail['violations'] ) ) {
                foreach ( $detail['violations'] as $v ) {
                    if ( isset( $v['quotaValue'] ) ) {
                        // Can't infer reset time from this
                        break;
                    }
                }
            }
        }

        return null;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public static function mask_key( string $key ) {
        $len = strlen( $key );
        if ( $len <= 8 ) return str_repeat( '*', $len );
        return substr( $key, 0, 4 ) . str_repeat( '*', max( 0, $len - 8 ) ) . substr( $key, -4 );
    }

    /**
     * Returns seconds remaining until a key resets, or null.
     */
    public static function seconds_until_reset( array $key_data ) {
        if ( empty( $key_data['reset_at_ts'] ) ) return null;
        $secs = (int) $key_data['reset_at_ts'] - time();
        return max( 0, $secs );
    }

    /**
     * Human-readable time format: "47s", "3m 12s", "1h 2m"
     */
    public static function format_seconds( int $secs ) {
        if ( $secs < 60 )   return "{$secs}s";
        if ( $secs < 3600 ) return floor( $secs / 60 ) . 'm ' . ( $secs % 60 ) . 's';
        $h = floor( $secs / 3600 );
        $m = floor( ( $secs % 3600 ) / 60 );
        return "{$h}h {$m}m";
    }

    public static function get_stats() {
        $keys  = self::get_all_keys();
        $stats = [
            'total'       => count( $keys ),
            'active'      => 0,
            'exhausted'   => 0,
            'invalid'     => 0,
            'requests'    => 0,
            'failures'    => 0,
            'tokens_used' => 0,
        ];
        foreach ( $keys as $k ) {
            $stats['requests']    += $k['requests']    ?? 0;
            $stats['failures']    += $k['failures']    ?? 0;
            $stats['tokens_used'] += $k['tokens_used'] ?? 0;
            if ( $k['status'] === 'active' )    $stats['active']++;
            elseif ( $k['status'] === 'invalid' ) $stats['invalid']++;
            else $stats['exhausted']++;
        }
        return $stats;
    }
}
