<?php
/**
 * Plugin Name:       AI Auto Post
 * Plugin URI:        https://github.com/geekyaadi/ai-auto-post
 * Description:       Auto-generate SEO blog posts using Google Gemini API - with multi-key rotation, scheduling, queue, history log, and full quality controls.
 * Version:           1.3.1
 * Author:            Aadi
 * Author URI:        https://github.com/geekyaadi
 * Contributors:      Anand Soni
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       ai-auto-post
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Constants
define( 'AAP_VERSION',     '1.3.1' );
define( 'AAP_PLUGIN_FILE', __FILE__ );
define( 'AAP_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'AAP_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );

// Autoload includes
$includes = [
    'includes/class-aap-key-manager.php',
    'includes/class-aap-rate-limits.php',
    'includes/class-aap-gemini.php',
    'includes/class-aap-post-creator.php',
    'includes/class-aap-settings.php',
    'includes/class-aap-scheduler.php',
    'includes/class-aap-queue.php',
    'includes/class-aap-history.php',
    'includes/class-aap-duplicate-check.php',
    'includes/class-aap-ajax.php',
    'includes/class-aap-updater.php',
    'includes/class-aap-text-to-image.php',
    'includes/class-aap-gsc-helper.php',
];

foreach ( $includes as $file ) {
    require_once AAP_PLUGIN_DIR . $file;
}

// Comprehensive Auto-Cache Purge Function
function aap_purge_all_caches() {
    delete_transient( 'aap_github_release_info' );
    delete_site_transient( 'update_plugins' );

    // 1. Reset PHP OPcache (forces server to load updated PHP code immediately)
    if ( function_exists( 'opcache_reset' ) ) {
        @opcache_reset();
    }

    // 2. Flush WP Object Cache
    if ( function_exists( 'wp_cache_flush' ) ) {
        @wp_cache_flush();
    }

    // 3. Purge LiteSpeed Cache
    do_action( 'litespeed_purge_all' );
    if ( class_exists( 'LiteSpeed_Cache_API' ) && method_exists( 'LiteSpeed_Cache_API', 'purge_all' ) ) {
        @LiteSpeed_Cache_API::purge_all();
    }

    // 4. Purge WP Rocket
    if ( function_exists( 'rocket_clean_domain' ) ) {
        @rocket_clean_domain();
    }

    // 5. Purge WP Super Cache
    if ( function_exists( 'wp_cache_clear_cache' ) ) {
        @wp_cache_clear_cache();
    }

    // 6. Purge Autoptimize
    if ( class_exists( 'autoptimizeCache' ) && method_exists( 'autoptimizeCache', 'clearall' ) ) {
        @autoptimizeCache::clearall();
    }

    // 7. Purge W3 Total Cache
    if ( function_exists( 'w3tc_flush_all' ) ) {
        @w3tc_flush_all();
    }
}

// Activation / Deactivation
register_activation_hook( __FILE__, 'aap_clear_transient_on_activate' );
function aap_clear_transient_on_activate() {
    aap_purge_all_caches();
    AAP_History::create_tables();
    AAP_Queue::create_tables();
}
register_deactivation_hook( __FILE__, [ 'AAP_Scheduler', 'deactivate' ] );

// Automatic Cache Purge on Plugin Upgrade
add_action( 'upgrader_process_complete', 'aap_on_plugin_update', 10, 2 );
function aap_on_plugin_update( $upgrader_object, $options ) {
    if ( isset( $options['action'] ) && $options['action'] === 'update' && isset( $options['type'] ) && $options['type'] === 'plugin' ) {
        aap_purge_all_caches();
    }
}

// Bootstrap
add_action( 'plugins_loaded', 'aap_init' );

function aap_init() {
    AAP_Queue::create_tables();
    AAP_Settings::init();
    AAP_Ajax::init();
    AAP_Scheduler::init();
    
    // Enable automated updates from GitHub Release API
    if ( is_admin() ) {
        new AAP_Updater( AAP_PLUGIN_FILE );

        // Version update check to clear transients and page caches
        $db_ver = get_option( 'aap_db_version' );
        if ( $db_ver !== AAP_VERSION ) {
            aap_purge_all_caches();
            update_option( 'aap_db_version', AAP_VERSION );
        }
    }
}

// Serve dynamic IndexNow key verification file
add_action( 'init', 'aap_indexnow_serve_key' );
function aap_indexnow_serve_key() {
    $key = get_option( 'aap_indexnow_key' );
    if ( empty( $key ) ) {
        $key = md5( get_bloginfo( 'url' ) . time() );
        update_option( 'aap_indexnow_key', $key );
    }

    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    $path = trim( parse_url( $request_uri, PHP_URL_PATH ), '/' );

    if ( $path === $key . '.txt' ) {
        header( 'Content-Type: text/plain; charset=utf-8' );
        echo $key;
        exit;
    }
}

// Hook to transition post status and ping IndexNow
add_action( 'transition_post_status', 'aap_indexnow_submit_on_publish', 10, 3 );
function aap_indexnow_submit_on_publish( $new_status, $old_status, $post ) {
    if ( $post->post_type !== 'post' || $new_status !== 'publish' || $old_status === 'publish' ) {
        return;
    }

    if ( ! get_option( 'aap_enable_indexnow', 0 ) ) {
        return;
    }

    $key = get_option( 'aap_indexnow_key' );
    if ( empty( $key ) ) {
        return;
    }

    $permalink = get_permalink( $post->ID );
    if ( ! $permalink ) {
        return;
    }

    $host = parse_url( home_url(), PHP_URL_HOST );
    $key_location = home_url( '/' . $key . '.txt' );

    $body = [
        'host'        => $host,
        'key'         => $key,
        'keyLocation' => $key_location,
        'urlList'     => [ $permalink ],
    ];

    wp_remote_post( 'https://api.indexnow.org/indexnow', [
        'headers'   => [ 'Content-Type' => 'application/json; charset=utf-8' ],
        'body'      => json_encode( $body ),
        'timeout'   => 15,
        'blocking'  => false,
    ] );
}

// Google Indexing API auto-ping on publish
add_action( 'transition_post_status', 'aap_gsc_auto_ping_on_publish', 10, 3 );
function aap_gsc_auto_ping_on_publish( $new_status, $old_status, $post ) {
    if ( $new_status !== 'publish' || $old_status === 'publish' ) {
        return;
    }
    if ( ! get_option( 'aap_enable_gsc_auto_ping', 0 ) ) {
        return;
    }
    $json_creds = get_option( 'aap_gsc_json', '' );
    if ( empty( $json_creds ) ) {
        return;
    }
    $url = get_permalink( $post->ID );
    if ( $url ) {
        AAP_GSC_Helper::submit_url( $url, 'URL_UPDATED' );
        update_post_meta( $post->ID, '_aap_gsc_last_ping', current_time( 'mysql' ) );
    }
}
