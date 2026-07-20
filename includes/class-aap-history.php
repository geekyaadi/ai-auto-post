<?php
/**
 * Generation History — Custom DB Table
 * Tracks every post generation attempt with niche, title, key used, status, and token estimate.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class AAP_History {

    const TABLE_SUFFIX = 'aap_history';

    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_SUFFIX;
    }

    // -------------------------------------------------------------------------
    // Activation: Create Table
    // -------------------------------------------------------------------------

    public static function create_tables() {
        global $wpdb;
        $table      = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id              BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id      VARCHAR(64)         NOT NULL,
            niche           VARCHAR(255)        NOT NULL DEFAULT '',
            title           VARCHAR(500)        NOT NULL DEFAULT '',
            post_id         BIGINT(20) UNSIGNED          DEFAULT NULL,
            status          VARCHAR(32)         NOT NULL DEFAULT 'pending',
            key_used        VARCHAR(100)        NOT NULL DEFAULT '',
            keys_switched   TINYINT(1)          NOT NULL DEFAULT 0,
            token_estimate  INT(11)             NOT NULL DEFAULT 0,
            error_message   TEXT                         DEFAULT NULL,
            created_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY session_id (session_id),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    // -------------------------------------------------------------------------
    // CRUD
    // -------------------------------------------------------------------------

    public static function insert( array $data ) {
        global $wpdb;
        $wpdb->insert( self::table_name(), [
            'session_id'     => $data['session_id']     ?? '',
            'niche'          => $data['niche']           ?? '',
            'title'          => $data['title']           ?? '',
            'post_id'        => $data['post_id']         ?? null,
            'status'         => $data['status']          ?? 'pending',
            'key_used'       => $data['key_used']        ?? '',
            'keys_switched'  => $data['keys_switched']   ?? 0,
            'token_estimate' => $data['token_estimate']  ?? 0,
            'error_message'  => $data['error_message']   ?? null,
        ], [ '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%d', '%s' ] );

        return $wpdb->insert_id;
    }

    public static function update( int $id, array $data ) {
        global $wpdb;
        $wpdb->update( self::table_name(), $data, [ 'id' => $id ] );
    }

    public static function get_all( int $limit = 50, int $offset = 0 ) {
        global $wpdb;
        $table = self::table_name();
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $limit, $offset
        ) );
    }

    public static function count() {
        global $wpdb;
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . self::table_name() );
    }

    public static function delete( int $id ) {
        global $wpdb;
        $wpdb->delete( self::table_name(), [ 'id' => $id ], [ '%d' ] );
    }

    public static function clear_all() {
        global $wpdb;
        $wpdb->query( "TRUNCATE TABLE " . self::table_name() );
    }

    // -------------------------------------------------------------------------
    // Stats for Dashboard
    // -------------------------------------------------------------------------

    public static function get_summary_stats() {
        global $wpdb;
        $table = self::table_name();
        return [
            'total'         => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ),
            'success'       => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'success'" ),
            'failed'        => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'failed'" ),
            'total_tokens'  => (int) $wpdb->get_var( "SELECT SUM(token_estimate) FROM {$table}" ),
            'keys_switched' => (int) $wpdb->get_var( "SELECT SUM(keys_switched) FROM {$table}" ),
        ];
    }

    // -------------------------------------------------------------------------
    // Token Estimator
    // -------------------------------------------------------------------------

    /**
     * Rough token estimate: ~4 chars per token for English text.
     */
    public static function estimate_tokens( string $article, string $tags_str = '', string $meta = '' ) {
        $total_chars = strlen( $article ) + strlen( $tags_str ) + strlen( $meta );
        return (int) ceil( $total_chars / 4 );
    }
}
