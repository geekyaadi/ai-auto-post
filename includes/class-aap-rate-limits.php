<?php
/**
 * AAP Rate Limits
 *
 * Stores the known free-tier rate limits for each Gemini model and tracks
 * local usage (RPM, TPM, RPD) per API key using WordPress transients.
 *
 * This lets the plugin:
 *  - Warn / rotate BEFORE hitting the API (avoiding wasted 429 round-trips)
 *  - Show real usage bars in the Settings page
 *  - Choose the right model based on what the user's tier supports
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class AAP_Rate_Limits {

    // -------------------------------------------------------------------------
    // Known Free-Tier Limits
    // null = unlimited / unknown / not available on free tier (treat as 0)
    // -------------------------------------------------------------------------

    /**
     * The 5 confirmed free-tier text models from the user's AI Studio quota page.
     *
     * Format per entry:
     *   label       — human name shown in Settings dropdown
     *   api_id      — actual Gemini API model string
     *   rpm         — requests per minute (free tier)
     *   tpm         — tokens per minute   (free tier)
     *   rpd         — requests per day    (free tier)
     *   recommended — pre-selected default
     *
     * Limits source: Google AI Studio quota page (as confirmed by user).
     */
    const MODELS = [

        // ── Google Gemini Text Models ─────────────────────────────────────────
        'gemini-3.1-flash-lite-preview' => [
            'label'       => 'Gemini 3.1 Flash Lite ⭐ (Best free — 500/day)',
            'api_id'      => 'gemini-3.1-flash-lite-preview',
            'rpm'         => 15,
            'tpm'         => 250000,
            'rpd'         => 500,
            'type'        => 'text',
            'provider'    => 'gemini',
            'free'        => true,
            'recommended' => true,
        ],

        'gemini-2.5-flash-lite-preview-06-17' => [
            'label'       => 'Gemini 2.5 Flash Lite',
            'api_id'      => 'gemini-2.5-flash-lite-preview-06-17',
            'rpm'         => 10,
            'tpm'         => 250000,
            'rpd'         => 20,
            'type'        => 'text',
            'provider'    => 'gemini',
            'free'        => true,
            'recommended' => false,
        ],

        'gemini-2.5-flash-preview-05-20' => [
            'label'       => 'Gemini 2.5 Flash',
            'api_id'      => 'gemini-2.5-flash-preview-05-20',
            'rpm'         => 5,
            'tpm'         => 250000,
            'rpd'         => 20,
            'type'        => 'text',
            'provider'    => 'gemini',
            'free'        => true,
            'recommended' => false,
        ],

        'gemini-3-flash' => [
            'label'       => 'Gemini 3 Flash',
            'api_id'      => 'gemini-3-flash',
            'rpm'         => 5,
            'tpm'         => 250000,
            'rpd'         => 20,
            'type'        => 'text',
            'provider'    => 'gemini',
            'free'        => true,
            'recommended' => false,
        ],

        'gemini-3.5-flash' => [
            'label'       => 'Gemini 3.5 Flash',
            'api_id'      => 'gemini-3.5-flash',
            'rpm'         => 5,
            'tpm'         => 250000,
            'rpd'         => 20,
            'type'        => 'text',
            'provider'    => 'gemini',
            'free'        => true,
            'recommended' => false,
        ],

        // ── OpenAI ChatGPT Text Models ────────────────────────────────────────
        'gpt-4o-mini' => [
            'label'       => 'GPT-4o Mini ⭐ (Recommended for speed/cost)',
            'api_id'      => 'gpt-4o-mini',
            'rpm'         => 15,
            'tpm'         => 200000,
            'rpd'         => 10000,
            'type'        => 'text',
            'provider'    => 'openai',
            'free'        => true,
            'recommended' => true,
        ],

        'gpt-4o' => [
            'label'       => 'GPT-4o (Higher quality, slower)',
            'api_id'      => 'gpt-4o',
            'rpm'         => 3,
            'tpm'         => 30000,
            'rpd'         => 500,
            'type'        => 'text',
            'provider'    => 'openai',
            'free'        => true,
            'recommended' => false,
        ],

        'gpt-3.5-turbo' => [
            'label'       => 'GPT-3.5 Turbo',
            'api_id'      => 'gpt-3.5-turbo',
            'rpm'         => 3,
            'tpm'         => 50000,
            'rpd'         => 1000,
            'type'        => 'text',
            'provider'    => 'openai',
            'free'        => true,
            'recommended' => false,
        ],

        // ── Image generation (shown for reference — all 0 free quota) ─────────
        'gemini-2.0-flash' => [
            'label'       => 'Gemini 2.0 Flash (Native Image Generation)',
            'api_id'      => 'gemini-2.0-flash',
            'rpm'         => 0,
            'tpm'         => 0,
            'rpd'         => 0,
            'type'        => 'image',
            'provider'    => 'gemini',
            'free'        => false,
            'recommended' => false,
            'note'        => 'Free quota is 0 — may still work on some accounts.',
        ],
    ];

    /**
     * Returns the free-tier Gemini text models as a simple [ api_id => label ] map.
     */
    public static function get_text_model_options() {
        $out = [];
        foreach ( self::MODELS as $id => $m ) {
            if ( $m['type'] === 'text' && $m['free'] && ( $m['provider'] ?? 'gemini' ) === 'gemini' ) {
                $out[ $id ] = $m['label'];
            }
        }
        return $out;
    }

    /**
     * Returns the OpenAI models.
     */
    public static function get_openai_model_options() {
        $out = [];
        foreach ( self::MODELS as $id => $m ) {
            if ( $m['type'] === 'text' && ( $m['provider'] ?? 'gemini' ) === 'openai' ) {
                $out[ $id ] = $m['label'];
            }
        }
        return $out;
    }

    /**
     * Returns an ordered list of model API IDs to use as fallbacks in the Key×Model
     * rotation matrix. Sorted by daily quota descending (best first).
     *
     * @param string $type     'text' or 'image'
     * @param string $provider 'gemini' or 'openai'
     * @return string[]        Ordered array of model IDs
     */
    public static function get_fallback_models_for_type( string $type = 'text', string $provider = 'gemini' ): array {
        $candidates = [];
        foreach ( self::MODELS as $id => $m ) {
            if ( ( $m['type'] ?? 'text' ) !== $type ) continue;
            if ( ( $m['provider'] ?? 'gemini' ) !== $provider ) continue;
            $candidates[ $id ] = $m['rpd'] ?? 0; // higher RPD = better
        }
        // Sort by RPD descending
        arsort( $candidates );
        return array_keys( $candidates );
    }

    /**
     * Record a completed request for a given key+model.
     *
     * @param string $api_key  Raw API key string
     * @param string $model    Model ID (from MODELS keys)
     * @param int    $tokens   Tokens used (from usageMetadata)
     */
    public static function record( string $api_key, string $model, int $tokens = 0 ) {
        $hash = self::key_hash( $api_key );
        self::increment_rpm( $hash );
        self::increment_rpd( $hash );
        if ( $tokens > 0 ) self::add_tpm( $hash, $tokens );
    }

    /**
     * Check if making one more request would exceed local tracked limits.
     * Returns true if it's SAFE to proceed, false if we should rotate/wait.
     *
     * @param string $api_key Raw API key string
     * @param string $model   Model ID
     */
    public static function is_within_limits( string $api_key, string $model ) {
        $limits = self::MODELS[ $model ] ?? null;
        if ( ! $limits ) return true; // unknown model → don't block

        if ( ! $limits['free'] ) return true; // model has 0 limits → let API decide

        $hash  = self::key_hash( $api_key );
        $usage = self::get_usage( $hash );

        // Check RPM (with 10% safety margin)
        if ( $limits['rpm'] > 0 ) {
            $safe_rpm = (int) floor( $limits['rpm'] * 0.9 );
            if ( $usage['rpm'] >= $safe_rpm ) return false;
        }

        // Check RPD (hard limit — no safety margin for daily)
        if ( $limits['rpd'] > 0 ) {
            if ( $usage['rpd'] >= $limits['rpd'] ) return false;
        }

        return true;
    }

    /**
     * How many requests are left today for a given key+model?
     * Returns [ 'rpm' => remaining, 'rpd' => remaining, 'tpm' => remaining ]
     * Values are null if the limit is unknown / not applicable.
     */
    public static function get_remaining( string $api_key, string $model ) {
        $limits = self::MODELS[ $model ] ?? null;
        if ( ! $limits ) return [ 'rpm' => null, 'rpd' => null, 'tpm' => null ];

        $hash  = self::key_hash( $api_key );
        $usage = self::get_usage( $hash );

        return [
            'rpm' => $limits['rpm'] > 0 ? max( 0, $limits['rpm'] - $usage['rpm'] ) : null,
            'rpd' => $limits['rpd'] > 0 ? max( 0, $limits['rpd'] - $usage['rpd'] ) : null,
            'tpm' => $limits['tpm'] > 0 ? max( 0, $limits['tpm'] - $usage['tpm'] ) : null,
        ];
    }

    /**
     * Returns current usage counters for a key hash.
     * { rpm: int, tpm: int, rpd: int }
     */
    public static function get_usage( string $hash ) {
        return [
            'rpm' => (int) ( get_transient( 'aap_rpm_' . $hash ) ?: 0 ),
            'tpm' => (int) ( get_transient( 'aap_tpm_' . $hash ) ?: 0 ),
            'rpd' => (int) ( get_transient( 'aap_rpd_' . $hash . '_' . self::today_key() ) ?: 0 ),
        ];
    }

    /**
     * Get usage for all keys (used by Settings page).
     * Returns array indexed by key_hash.
     */
    public static function get_all_usage( array $keys ) {
        $result = [];
        foreach ( $keys as $k ) {
            $hash            = self::key_hash( $k['key'] );
            $result[ $hash ] = self::get_usage( $hash );
        }
        return $result;
    }

    /**
     * Reset local counters for a key (called after key is manually reset).
     */
    public static function reset_counters( string $api_key ) {
        $hash = self::key_hash( $api_key );
        delete_transient( 'aap_rpm_' . $hash );
        delete_transient( 'aap_tpm_' . $hash );
        delete_transient( 'aap_rpd_' . $hash . '_' . self::today_key() );
    }

    // -------------------------------------------------------------------------
    // Internal counter helpers (sliding 60s window for RPM, daily for RPD)
    // -------------------------------------------------------------------------

    private static function increment_rpm( string $hash ) {
        $key = 'aap_rpm_' . $hash;
        $val = (int) get_transient( $key );
        // Transient expires after 60s — natural sliding window reset
        set_transient( $key, $val + 1, 60 );
    }

    private static function add_tpm( string $hash, int $tokens ) {
        $key = 'aap_tpm_' . $hash;
        $val = (int) get_transient( $key );
        set_transient( $key, $val + $tokens, 60 );
    }

    private static function increment_rpd( string $hash ) {
        $key = 'aap_rpd_' . $hash . '_' . self::today_key();
        $val = (int) get_transient( $key );
        // Expire at end of day (seconds remaining in current UTC day)
        $ttl = self::seconds_until_midnight();
        set_transient( $key, $val + 1, $ttl );
    }

    // -------------------------------------------------------------------------
    // Model helpers
    // -------------------------------------------------------------------------

    /**
     * Returns only models with free tier availability of the given type.
     */
    public static function get_free_models( string $type = 'text' ) {
        return array_filter( self::MODELS, function ( $m ) use ( $type ) {
            return $m['type'] === $type && $m['free'] === true;
        } );
    }

    /**
     * Returns the limit array for a model, or a zeroed-out array if unknown.
     */
    public static function get_limits( string $model ) {
        return self::MODELS[ $model ] ?? [
            'rpm' => null, 'tpm' => null, 'rpd' => null, 'free' => false,
        ];
    }

    /**
     * Returns a usage percentage (0–100) for a given dimension.
     * Used to render progress bars.
     */
    public static function usage_percent( int $used, ?int $limit ) {
        if ( ! $limit || $limit <= 0 ) return 0;
        return min( 100, round( ( $used / $limit ) * 100 ) );
    }

    /**
     * Human-readable limit value. Returns "∞" for null, "—" for 0.
     */
    public static function fmt_limit( ?int $v ) {
        if ( $v === null ) return '∞';
        if ( $v === 0 )    return '—';
        if ( $v >= 1000000 ) return round( $v / 1000000, 1 ) . 'M';
        if ( $v >= 1000 )   return round( $v / 1000, 1 ) . 'K';
        return (string) $v;
    }

    // -------------------------------------------------------------------------
    // Utilities
    // -------------------------------------------------------------------------

    public static function key_hash( string $api_key ) {
        return substr( md5( $api_key ), 0, 12 );
    }

    private static function today_key() {
        return gmdate( 'Ymd' ); // UTC date key
    }

    private static function seconds_until_midnight() {
        $now     = time();
        $midnight = mktime( 0, 0, 0, (int) gmdate('m'), (int) gmdate('d') + 1, (int) gmdate('Y') );
        return max( 60, $midnight - $now );
    }
}
