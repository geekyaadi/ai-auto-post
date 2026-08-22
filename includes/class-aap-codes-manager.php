<?php
/**
 * Codes & ads.txt Manager for AI Auto Post
 * Injects Custom Header (<head>) and Footer (</body>) scripts and manages ads.txt dynamically and physically.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class AAP_Codes_Manager {

    public static function init() {
        add_action( 'wp_head',   [ __CLASS__, 'inject_header_code' ], 99 );
        add_action( 'wp_footer', [ __CLASS__, 'inject_footer_code' ], 99 );
        add_action( 'init',      [ __CLASS__, 'serve_ads_txt_dynamically' ] );
        add_action( 'admin_post_aap_save_custom_codes', [ __CLASS__, 'handle_save_custom_codes' ] );
    }

    public static function inject_header_code() {
        if ( is_admin() ) return;
        $code = get_option( 'aap_header_code', '' );
        if ( ! empty( $code ) ) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Custom script tag injection by admin
            echo "\n<!-- AI Auto Post Header Script -->\n" . $code . "\n<!-- /AI Auto Post Header Script -->\n";
        }
    }

    public static function inject_footer_code() {
        if ( is_admin() ) return;
        $code = get_option( 'aap_footer_code', '' );
        if ( ! empty( $code ) ) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Custom script tag injection by admin
            echo "\n<!-- AI Auto Post Footer Script -->\n" . $code . "\n<!-- /AI Auto Post Footer Script -->\n";
        }
    }

    public static function serve_ads_txt_dynamically() {
        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? strtok( wp_unslash( $_SERVER['REQUEST_URI'] ), '?' ) : '';
        if ( empty( $request_uri ) ) return;

        $path = trim( $request_uri, '/' );
        if ( strtolower( $path ) === 'ads.txt' ) {
            $content = get_option( 'aap_ads_txt_content', '' );
            header( 'Content-Type: text/plain; charset=utf-8' );
            header( 'X-Robots-Tag: noindex, follow', true );
            echo esc_html( $content );
            exit;
        }
    }

    public static function sync_physical_ads_txt( $content ) {
        // phpcs:ignore PluginCheck.CodeAnalysis.WriteFile.ABSPATHDetected, WordPress.WP.AlternativeFunctions.file_system_operations_is_writable
        $file_path = ABSPATH . 'ads.txt';
        if ( wp_is_writable( ABSPATH ) || ( file_exists( $file_path ) && wp_is_writable( $file_path ) ) ) {
            @file_put_contents( $file_path, $content );
        }
    }

    public static function handle_save_custom_codes() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
        check_admin_referer( 'aap_codes_nonce' );

        $header_code = isset( $_POST['aap_header_code'] ) ? wp_unslash( $_POST['aap_header_code'] ) : '';
        $footer_code = isset( $_POST['aap_footer_code'] ) ? wp_unslash( $_POST['aap_footer_code'] ) : '';
        $ads_txt     = isset( $_POST['aap_ads_txt_content'] ) ? sanitize_textarea_field( wp_unslash( $_POST['aap_ads_txt_content'] ) ) : '';

        update_option( 'aap_header_code', $header_code );
        update_option( 'aap_footer_code', $footer_code );
        update_option( 'aap_ads_txt_content', $ads_txt );

        self::sync_physical_ads_txt( $ads_txt );

        wp_safe_redirect( admin_url( 'admin.php?page=aap-codes&updated=true' ) );
        exit;
    }
}
