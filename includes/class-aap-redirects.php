<?php
/**
 * Redirect Manager & 404 Error Log Tracker Engine for AI Auto Post
 * Handles 301/302/307 Redirect Rules, Auto 404 Log Capture with Image/Link Filters & 1-Click 301 Conversion.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class AAP_Redirects {

    public static function init() {
        self::create_tables();
        add_action( 'template_redirect', [ __CLASS__, 'handle_frontend_redirects_and_404s' ], 1 );
        add_action( 'admin_post_aap_save_redirect_rule', [ __CLASS__, 'handle_save_redirect_rule' ] );
        add_action( 'admin_post_aap_delete_redirect_rule', [ __CLASS__, 'handle_delete_redirect_rule' ] );
        add_action( 'admin_post_aap_convert_404_to_301', [ __CLASS__, 'handle_convert_404_to_301' ] );
        add_action( 'admin_post_aap_clear_404_logs', [ __CLASS__, 'handle_clear_404_logs' ] );
        add_action( 'admin_post_aap_save_404_redirect_settings', [ __CLASS__, 'handle_save_404_redirect_settings' ] );
    }

    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $table_redirects = $wpdb->prefix . 'aap_redirects';
        $table_404s      = $wpdb->prefix . 'aap_404_logs';

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql1 = "CREATE TABLE IF NOT EXISTS {$table_redirects} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            source_url varchar(255) NOT NULL,
            target_url varchar(255) NOT NULL,
            redirect_type int(3) NOT NULL DEFAULT 301,
            hit_count bigint(20) NOT NULL DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY source_url (source_url)
        ) {$charset_collate};";

        $sql2 = "CREATE TABLE IF NOT EXISTS {$table_404s} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            url varchar(255) NOT NULL,
            is_image tinyint(1) NOT NULL DEFAULT 0,
            hit_count bigint(20) NOT NULL DEFAULT 1,
            user_agent text,
            last_detected datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY url (url)
        ) {$charset_collate};";

        dbDelta( $sql1 );
        dbDelta( $sql2 );
    }

    public static function handle_frontend_redirects_and_404s() {
        if ( is_admin() ) return;

        global $wpdb;
        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? strtok( $_SERVER['REQUEST_URI'], '?' ) : '';
        if ( empty( $request_uri ) ) return;

        $path_clean  = '/' . ltrim( $request_uri, '/' );
        $table_redir = $wpdb->prefix . 'aap_redirects';

        // 1. Check for Active Redirect Rules
        $rule = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table_redir} WHERE source_url = %s AND status = 'active' LIMIT 1",
            $path_clean
        ) );

        if ( $rule ) {
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$table_redir} SET hit_count = hit_count + 1 WHERE id = %d",
                $rule->id
            ) );

            $code = in_array( (int)$rule->redirect_type, [ 301, 302, 307 ], true ) ? (int)$rule->redirect_type : 301;
            wp_safe_redirect( $rule->target_url, $code );
            exit;
        }

        // 2. Monitor & Capture 404 Errors
        if ( is_404() ) {
            $table_404s = $wpdb->prefix . 'aap_404_logs';
            $is_image   = preg_match( '/\.(jpg|jpeg|png|gif|webp|svg|ico|bmp|tiff)$/i', $path_clean ) ? 1 : 0;
            $user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ) : '';

            $wpdb->query( $wpdb->prepare(
                "INSERT INTO {$table_404s} (url, is_image, hit_count, user_agent, last_detected)
                 VALUES (%s, %d, 1, %s, %s)
                 ON DUPLICATE KEY UPDATE hit_count = hit_count + 1, last_detected = %s",
                $path_clean, $is_image, $user_agent, current_time( 'mysql' ), current_time( 'mysql' )
            ) );

            // Auto-redirect 404 pages to homepage if toggle switch is enabled
            if ( get_option( 'aap_redirect_404_to_home', '0' ) === '1' ) {
                wp_safe_redirect( home_url( '/' ), 301 );
                exit;
            }
        }
    }

    public static function handle_save_404_redirect_settings() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
        check_admin_referer( 'aap_redirect_nonce' );

        $enable = isset( $_POST['aap_redirect_404_to_home'] ) ? '1' : '0';
        update_option( 'aap_redirect_404_to_home', $enable );

        wp_safe_redirect( admin_url( 'admin.php?page=aap-redirects&settings_saved=true' ) );
        exit;
    }

    public static function handle_save_redirect_rule() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
        check_admin_referer( 'aap_redirect_nonce' );

        global $wpdb;
        $source = sanitize_text_field( $_POST['source_url'] ?? '' );
        $target = sanitize_text_field( $_POST['target_url'] ?? '' );
        $type   = intval( $_POST['redirect_type'] ?? 301 );

        if ( ! empty( $source ) && ! empty( $target ) ) {
            $source_clean = '/' . ltrim( wp_parse_url( $source, PHP_URL_PATH ) ?: $source, '/' );
            $table_redir  = $wpdb->prefix . 'aap_redirects';

            $wpdb->query( $wpdb->prepare(
                "INSERT INTO {$table_redir} (source_url, target_url, redirect_type, status)
                 VALUES (%s, %s, %d, 'active')
                 ON DUPLICATE KEY UPDATE target_url = %s, redirect_type = %d, status = 'active'",
                $source_clean, $target, $type, $target, $type
            ) );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=aap-redirects&updated=true' ) );
        exit;
    }

    public static function handle_delete_redirect_rule() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
        check_admin_referer( 'aap_redirect_nonce' );

        global $wpdb;
        $id = intval( $_POST['rule_id'] ?? 0 );
        if ( $id > 0 ) {
            $table_redir = $wpdb->prefix . 'aap_redirects';
            $wpdb->delete( $table_redir, [ 'id' => $id ], [ '%d' ] );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=aap-redirects&deleted=true' ) );
        exit;
    }

    public static function handle_convert_404_to_301() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
        check_admin_referer( 'aap_redirect_nonce' );

        global $wpdb;
        $source = sanitize_text_field( $_POST['source_url'] ?? '' );
        $target = sanitize_text_field( $_POST['target_url'] ?? '' );

        if ( ! empty( $source ) && ! empty( $target ) ) {
            $source_clean = '/' . ltrim( wp_parse_url( $source, PHP_URL_PATH ) ?: $source, '/' );
            $table_redir  = $wpdb->prefix . 'aap_redirects';
            $table_404s   = $wpdb->prefix . 'aap_404_logs';

            // Insert into redirects
            $wpdb->query( $wpdb->prepare(
                "INSERT INTO {$table_redir} (source_url, target_url, redirect_type, status)
                 VALUES (%s, %s, 301, 'active')
                 ON DUPLICATE KEY UPDATE target_url = %s, status = 'active'",
                $source_clean, $target, $target
            ) );

            // Delete from 404 log
            $wpdb->delete( $table_404s, [ 'url' => $source_clean ], [ '%s' ] );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=aap-redirects&converted=true' ) );
        exit;
    }

    public static function handle_clear_404_logs() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
        check_admin_referer( 'aap_redirect_nonce' );

        global $wpdb;
        $table_404s = $wpdb->prefix . 'aap_404_logs';
        $wpdb->query( "TRUNCATE TABLE {$table_404s}" );

        wp_safe_redirect( admin_url( 'admin.php?page=aap-redirects&logs_cleared=true' ) );
        exit;
    }

    public static function get_redirect_rules() {
        global $wpdb;
        $table = $wpdb->prefix . 'aap_redirects';
        return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC LIMIT 200" );
    }

    public static function get_404_logs( $filter = 'all' ) {
        global $wpdb;
        $table = $wpdb->prefix . 'aap_404_logs';

        if ( $filter === 'images' ) {
            return $wpdb->get_results( "SELECT * FROM {$table} WHERE is_image = 1 ORDER BY hit_count DESC LIMIT 200" );
        } elseif ( $filter === 'links' ) {
            return $wpdb->get_results( "SELECT * FROM {$table} WHERE is_image = 0 ORDER BY hit_count DESC LIMIT 200" );
        }

        return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY hit_count DESC LIMIT 200" );
    }
}
