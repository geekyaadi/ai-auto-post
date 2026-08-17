<?php
/**
 * Admin Settings — registers all admin menu pages and handles option saving.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class AAP_Settings {

    public static function init() {
        add_action( 'admin_menu',            [ __CLASS__, 'register_menus' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
        add_action( 'admin_post_aap_save_settings', [ __CLASS__, 'save_settings' ] );
        add_action( 'admin_post_aap_add_key',        [ __CLASS__, 'handle_add_key' ] );
        add_action( 'admin_post_aap_delete_key',     [ __CLASS__, 'handle_delete_key' ] );
        add_action( 'admin_post_aap_reset_key',      [ __CLASS__, 'handle_reset_key' ] );
        add_action( 'admin_post_aap_save_schedule',  [ __CLASS__, 'save_schedule' ] );
        add_action( 'admin_post_aap_enqueue_niche',  [ __CLASS__, 'handle_enqueue_niche' ] );
        add_action( 'admin_post_aap_delete_queue',   [ __CLASS__, 'handle_delete_queue' ] );
        add_action( 'admin_post_aap_pause_queue',    [ __CLASS__, 'handle_pause_queue' ] );
        add_action( 'admin_post_aap_resume_queue',   [ __CLASS__, 'handle_resume_queue' ] );
        add_action( 'admin_post_aap_clear_queue',    [ __CLASS__, 'handle_clear_queue' ] );
        add_action( 'admin_post_aap_delete_selected_queue', [ __CLASS__, 'handle_delete_selected_queue' ] );
        add_action( 'admin_post_aap_delete_history', [ __CLASS__, 'handle_delete_history' ] );
        add_action( 'admin_post_aap_clear_history',  [ __CLASS__, 'clear_history' ] );
        add_action( 'admin_post_aap_reset_settings',   [ __CLASS__, 'handle_reset_settings' ] );
        add_action( 'admin_post_aap_clear_plugin_data', [ __CLASS__, 'handle_clear_plugin_data' ] );
        add_action( 'admin_post_aap_save_speed_settings', [ __CLASS__, 'save_speed_settings' ] );
        add_action( 'admin_post_aap_purge_speed_cache',   [ __CLASS__, 'handle_purge_speed_cache' ] );
        add_action( 'admin_post_aap_save_sitemap_settings', [ __CLASS__, 'save_sitemap_settings' ] );
        add_action( 'admin_post_aap_generate_essential_pages', [ __CLASS__, 'handle_generate_essential_pages' ] );
        add_action( 'admin_post_aap_save_cookie_settings',     [ __CLASS__, 'handle_save_cookie_settings' ] );
    }

    // -------------------------------------------------------------------------
    // Admin Menus
    // -------------------------------------------------------------------------

    public static function register_menus() {
        add_menu_page(
            __( 'AI Auto Post', 'ai-auto-post' ),
            __( 'AI Auto Post', 'ai-auto-post' ),
            'manage_options',
            'ai-auto-post',
            [ __CLASS__, 'render_dashboard_page' ],
            'dashicons-dashboard',
            25
        );

        add_submenu_page( 'ai-auto-post', __( 'Dashboard', 'ai-auto-post' ),      __( 'Dashboard', 'ai-auto-post' ),      'manage_options', 'ai-auto-post',             [ __CLASS__, 'render_dashboard_page' ] );
        add_submenu_page( 'ai-auto-post', __( 'Generate Post', 'ai-auto-post' ),  __( 'Generate Post', 'ai-auto-post' ),  'manage_options', 'aap-generate',             [ __CLASS__, 'render_generate_page' ] );
        add_submenu_page( 'ai-auto-post', __( 'Bulk Planner', 'ai-auto-post' ),   __( 'Bulk Planner', 'ai-auto-post' ),   'manage_options', 'aap-planner',              [ __CLASS__, 'render_planner_page' ] );
        add_submenu_page( 'ai-auto-post', __( 'Scheduler & Queue', 'ai-auto-post' ), __( 'Scheduler & Queue', 'ai-auto-post' ), 'manage_options', 'aap-scheduler',      [ __CLASS__, 'render_scheduler_page' ] );
        add_submenu_page( 'ai-auto-post', __( 'Thumbnail Manager', 'ai-auto-post' ), __( 'Thumbnail Manager', 'ai-auto-post' ), 'manage_options', 'aap-thumbnails',   [ __CLASS__, 'render_thumbnails_page' ] );
        add_submenu_page( 'ai-auto-post', __( 'Tags Manager', 'ai-auto-post' ),    __( 'Tags Manager', 'ai-auto-post' ),    'manage_options', 'aap-tags',                 [ __CLASS__, 'render_tags_page' ] );
        add_submenu_page( 'ai-auto-post', __( 'Bulk Translator', 'ai-auto-post' ),  __( 'Bulk Translator', 'ai-auto-post' ),  'manage_options', 'aap-translator',           [ __CLASS__, 'render_translator_page' ] );
        add_submenu_page( 'ai-auto-post', __( 'Google Indexing Tool', 'ai-auto-post' ), __( 'Google Indexing Tool', 'ai-auto-post' ), 'manage_options', 'aap-gsc',      [ __CLASS__, 'render_gsc_page' ] );
        add_submenu_page( 'ai-auto-post', __( 'Article Rewriter', 'ai-auto-post' ), __( 'Article Rewriter', 'ai-auto-post' ), 'manage_options', 'aap-rewriter',   [ __CLASS__, 'render_rewriter_page' ] );
        add_submenu_page( 'ai-auto-post', __( 'Speed Optimizer', 'ai-auto-post' ), __( '⚡ Speed Optimizer', 'ai-auto-post' ), 'manage_options', 'aap-speed',      [ __CLASS__, 'render_speed_page' ] );
        add_submenu_page( 'ai-auto-post', __( 'XML Sitemap Manager', 'ai-auto-post' ), __( '🗺️ Sitemap Manager', 'ai-auto-post' ), 'manage_options', 'aap-sitemap',   [ __CLASS__, 'render_sitemap_page' ] );
        add_submenu_page( 'ai-auto-post', __( 'Pages & Cookie Consent', 'ai-auto-post' ), __( '📄 Pages & Cookies', 'ai-auto-post' ), 'manage_options', 'aap-pages',   [ __CLASS__, 'render_pages_generator_page' ] );
        add_submenu_page( 'ai-auto-post', __( 'Redirect & 404 Manager', 'ai-auto-post' ), __( '🔀 Redirect Manager', 'ai-auto-post' ), 'manage_options', 'aap-redirects', [ __CLASS__, 'render_redirects_page' ] );
        add_submenu_page( 'ai-auto-post', __( 'Codes & ads.txt', 'ai-auto-post' ),       __( '💻 Codes & ads.txt', 'ai-auto-post' ), 'manage_options', 'aap-codes',     [ __CLASS__, 'render_codes_page' ] );
        add_submenu_page( 'ai-auto-post', __( 'Settings', 'ai-auto-post' ),       __( 'Settings', 'ai-auto-post' ),       'manage_options', 'aap-settings',             [ __CLASS__, 'render_settings_page' ] );
    }

    // -------------------------------------------------------------------------
    // Enqueue Assets
    // -------------------------------------------------------------------------

    public static function enqueue_assets( $hook ) {
        $aap_pages = [
            'toplevel_page_ai-auto-post',
            'ai-auto-post_page_aap-generate',
            'ai-auto-post_page_aap-planner',
            'ai-auto-post_page_aap-scheduler',
            'ai-auto-post_page_aap-settings',
            'ai-auto-post_page_aap-thumbnails',
            'ai-auto-post_page_aap-tags',
            'ai-auto-post_page_aap-translator',
            'ai-auto-post_page_aap-gsc',
            'ai-auto-post_page_aap-rewriter',
            'ai-auto-post_page_aap-speed',
            'ai-auto-post_page_aap-sitemap',
            'ai-auto-post_page_aap-pages',
            'ai-auto-post_page_aap-redirects',
            'ai-auto-post_page_aap-codes',
        ];
        if ( ! in_array( $hook, $aap_pages, true ) && strpos( $hook, 'aap' ) === false ) return;

        $css_path = AAP_PLUGIN_DIR . 'admin/css/admin.css';
        $css_ver  = file_exists( $css_path ) ? filemtime( $css_path ) : AAP_VERSION;

        wp_enqueue_style(
            'aap-admin-v2',
            AAP_PLUGIN_URL . 'admin/css/admin.css',
            [],
            $css_ver
        );

        $js_path = AAP_PLUGIN_DIR . 'admin/js/admin.js';
        $js_ver  = file_exists( $js_path ) ? filemtime( $js_path ) : AAP_VERSION;

        wp_enqueue_script(
            'aap-admin-v2',
            AAP_PLUGIN_URL . 'admin/js/admin.js',
            [ 'jquery' ],
            $js_ver,
            true
        );

        wp_localize_script( 'aap-admin-v2', 'aapData', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'aap_nonce' ),
            'strings' => [
                'generating'      => __( 'Generating...', 'ai-auto-post' ),
                'switchedKey'     => __( 'API key exhausted — switching to next key...', 'ai-auto-post' ),
                'allExhausted'    => __( 'All API keys exhausted. Please add more keys or wait for reset.', 'ai-auto-post' ),
                'success'         => __( 'Post created successfully!', 'ai-auto-post' ),
                'error'           => __( 'An error occurred. Please try again.', 'ai-auto-post' ),
                'confirmDelete'   => __( 'Are you sure you want to delete this?', 'ai-auto-post' ),
                'duplicate'       => __( 'A similar post already exists. Do you want to continue anyway?', 'ai-auto-post' ),
            ],
        ] );
    }

    // -------------------------------------------------------------------------
    // Page Renderers
    // -------------------------------------------------------------------------

    public static function render_generate_page() {
        require_once AAP_PLUGIN_DIR . 'admin/views/generate-page.php';
    }

    public static function render_planner_page() {
        require_once AAP_PLUGIN_DIR . 'admin/views/planner-page.php';
    }

    public static function render_tags_page() {
        require_once AAP_PLUGIN_DIR . 'admin/views/tags-page.php';
    }

    public static function render_translator_page() {
        require_once AAP_PLUGIN_DIR . 'admin/views/translator-page.php';
    }

    public static function render_gsc_page() {
        require_once AAP_PLUGIN_DIR . 'admin/views/gsc-page.php';
    }

    public static function render_rewriter_page() {
        require_once AAP_PLUGIN_DIR . 'admin/views/rewriter-page.php';
    }

    public static function render_speed_page() {
        require_once AAP_PLUGIN_DIR . 'admin/views/speed-optimizer-page.php';
    }

    public static function render_sitemap_page() {
        require_once AAP_PLUGIN_DIR . 'admin/views/sitemap-page.php';
    }

    public static function render_pages_generator_page() {
        require_once AAP_PLUGIN_DIR . 'admin/views/pages-generator-page.php';
    }

    public static function render_redirects_page() {
        require_once AAP_PLUGIN_DIR . 'admin/views/redirects-page.php';
    }

    public static function render_codes_page() {
        require_once AAP_PLUGIN_DIR . 'admin/views/codes-page.php';
    }

    public static function render_settings_page() {
        require_once AAP_PLUGIN_DIR . 'admin/views/settings-page.php';
    }

    public static function render_scheduler_page() {
        require_once AAP_PLUGIN_DIR . 'admin/views/scheduler-page.php';
    }

    public static function render_dashboard_page() {
        require_once AAP_PLUGIN_DIR . 'admin/views/dashboard-page.php';
    }

    // -------------------------------------------------------------------------
    // Essential Pages & Cookie Handlers
    // -------------------------------------------------------------------------

    public static function handle_generate_essential_pages() {
        check_admin_referer( 'aap_generate_essential_pages' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        $site_name = sanitize_text_field( $_POST['aap_site_name'] ?? '' );
        $site_url  = esc_url_raw( $_POST['aap_site_url'] ?? '' );
        $email     = sanitize_email( $_POST['aap_contact_email'] ?? '' );

        update_option( 'aap_site_name', $site_name );
        update_option( 'aap_site_url', $site_url );
        update_option( 'aap_contact_email', $email );

        $selected_pages = isset( $_POST['pages'] ) && is_array( $_POST['pages'] ) ? $_POST['pages'] : [];
        $created_count = 0;

        foreach ( $selected_pages as $page_type ) {
            $res = AAP_Pages_Generator::create_page( sanitize_text_field( $page_type ), $site_name, $site_url, $email );
            if ( ! is_wp_error( $res ) ) {
                $created_count++;
            }
        }

        wp_safe_redirect( admin_url( 'admin.php?page=aap-pages&pages_created=' . $created_count ) );
        exit;
    }

    public static function handle_save_cookie_settings() {
        check_admin_referer( 'aap_save_cookie_settings' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        $enable        = isset( $_POST['aap_cookie_enable'] ) ? '1' : '0';
        $style         = sanitize_text_field( $_POST['aap_cookie_style'] ?? 'bottom_banner' );
        $text          = sanitize_textarea_field( $_POST['aap_cookie_text'] ?? '' );
        $btn_text      = sanitize_text_field( $_POST['aap_cookie_btn_text'] ?? 'Accept All' );
        $enable_reject = isset( $_POST['aap_cookie_enable_reject'] ) ? '1' : '0';
        $reject_text   = sanitize_text_field( $_POST['aap_cookie_reject_btn_text'] ?? 'Decline Non-Essential' );
        $privacy_url   = esc_url_raw( $_POST['aap_cookie_privacy_url'] ?? '' );

        update_option( 'aap_cookie_enable', $enable );
        update_option( 'aap_cookie_style', $style );
        update_option( 'aap_cookie_text', $text );
        update_option( 'aap_cookie_btn_text', $btn_text );
        update_option( 'aap_cookie_enable_reject', $enable_reject );
        update_option( 'aap_cookie_reject_btn_text', $reject_text );
        update_option( 'aap_cookie_privacy_url', $privacy_url );

        wp_safe_redirect( admin_url( 'admin.php?page=aap-pages&cookie_updated=true' ) );
        exit;
    }

    // -------------------------------------------------------------------------
    // Sitemap Manager Handlers
    // -------------------------------------------------------------------------

    public static function save_sitemap_settings() {
        check_admin_referer( 'aap_save_sitemap_settings' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        $slug = isset( $_POST['aap_sitemap_slug'] ) ? sanitize_title( $_POST['aap_sitemap_slug'] ) : 'sitemap';
        if ( empty( $slug ) ) $slug = 'sitemap';
        if ( strpos( $slug, '.xml' ) === false ) $slug .= '.xml';
        update_option( 'aap_sitemap_slug', $slug );

        $fields = [
            'aap_sitemap_priority_home'    => 'sanitize_text_field',
            'aap_sitemap_changefreq_home'  => 'sanitize_text_field',
            'aap_sitemap_priority_post'    => 'sanitize_text_field',
            'aap_sitemap_changefreq_post'  => 'sanitize_text_field',
            'aap_sitemap_priority_page'    => 'sanitize_text_field',
            'aap_sitemap_changefreq_page'  => 'sanitize_text_field',
            'aap_sitemap_priority_cat'     => 'sanitize_text_field',
            'aap_sitemap_changefreq_cat'   => 'sanitize_text_field',
            'aap_sitemap_priority_tag'     => 'sanitize_text_field',
            'aap_sitemap_changefreq_tag'   => 'sanitize_text_field',
        ];

        foreach ( $fields as $opt => $sanitizer ) {
            $val = isset( $_POST[ $opt ] ) ? $sanitizer( $_POST[ $opt ] ) : '';
            update_option( $opt, $val );
        }

        $checkboxes = [
            'aap_sitemap_enable_home',
            'aap_sitemap_enable_posts',
            'aap_sitemap_enable_pages',
            'aap_sitemap_enable_cats',
            'aap_sitemap_enable_tags',
            'aap_sitemap_include_images',
            'aap_sitemap_auto_ping_google',
            'aap_sitemap_auto_ping_bing',
        ];

        foreach ( $checkboxes as $cb ) {
            $val = isset( $_POST[ $cb ] ) ? '1' : '0';
            update_option( $cb, $val );
        }

        // Flush rewrite rules for custom sitemap slug
        AAP_Sitemap::add_rewrite_rules();
        flush_rewrite_rules();

        if ( function_exists( 'aap_purge_all_caches' ) ) {
            aap_purge_all_caches();
        }

        wp_safe_redirect( admin_url( 'admin.php?page=aap-sitemap&updated=true' ) );
        exit;
    }

    // -------------------------------------------------------------------------
    // Speed Optimizer Handlers
    // -------------------------------------------------------------------------

    public static function save_speed_settings() {
        check_admin_referer( 'aap_save_speed_settings' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        $speed_options = [
            'aap_speed_lazy_loading',
            'aap_speed_html_minification',
            'aap_speed_webp_compression',
            'aap_speed_auto_cache_purge',
            'aap_speed_preload_assets',
            'aap_speed_defer_js',
        ];

        foreach ( $speed_options as $opt ) {
            $val = isset( $_POST[ $opt ] ) ? '1' : '0';
            update_option( $opt, $val );
        }

        if ( function_exists( 'aap_purge_all_caches' ) ) {
            aap_purge_all_caches();
        }

        wp_safe_redirect( admin_url( 'admin.php?page=aap-speed&updated=true' ) );
        exit;
    }

    public static function handle_purge_speed_cache() {
        check_admin_referer( 'aap_purge_speed_cache' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        if ( function_exists( 'aap_purge_all_caches' ) ) {
            aap_purge_all_caches();
        }

        wp_safe_redirect( admin_url( 'admin.php?page=aap-speed&purged=true' ) );
        exit;
    }

    // -------------------------------------------------------------------------
    // Save Main Settings
    // -------------------------------------------------------------------------

    public static function save_settings() {
        check_admin_referer( 'aap_save_settings' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        $fields = [
            'aap_default_status'          => 'sanitize_text_field',
            'aap_default_author'          => 'intval',
            'aap_word_count'              => 'intval',
            'aap_tag_count'               => 'intval',
            'aap_content_tone'            => 'sanitize_text_field',
            'aap_blacklist_words'         => 'sanitize_textarea_field',
            'aap_key_reset_minutes'       => 'intval',
            'aap_review_mode'             => 'intval',
            'aap_text_model'              => 'sanitize_text_field',
            'aap_image_model'             => 'sanitize_text_field',
            'aap_active_provider'         => 'sanitize_text_field',
            'aap_openai_model'            => 'sanitize_text_field',
            'aap_enable_internal_linking' => 'intval',
            'aap_max_internal_links'      => 'intval',
            'aap_internal_link_style'     => 'sanitize_text_field',
            'aap_enable_outbound_linking' => 'intval',
            'aap_max_outbound_links'      => 'intval',
            'aap_outbound_target'         => 'sanitize_text_field',
            'aap_outbound_rel'            => 'sanitize_text_field',
            'aap_outbound_blacklist'      => 'sanitize_textarea_field',
            'aap_enable_indexnow'         => 'intval',
            'aap_enable_comments'         => 'intval',
            'aap_comments_count'          => 'intval',
            'aap_enable_text_overlay'     => 'intval',
            'aap_overlay_font_size'       => 'intval',
            'aap_overlay_color'           => 'sanitize_text_field',
            'aap_overlay_bg_color'        => 'sanitize_text_field',
            'aap_overlay_bg_opacity'      => 'intval',
            'aap_overlay_position'        => 'sanitize_text_field',
            'aap_thumb_type'              => 'sanitize_text_field',
            'aap_t2i_bg_type'             => 'sanitize_text_field',
            'aap_t2i_bg_val'              => 'sanitize_text_field',
            'aap_t2i_size'                => 'sanitize_text_field',
            'aap_enable_toc'              => 'intval',
            'aap_enable_faq'              => 'intval',
            'aap_faq_count'               => 'intval',
            'aap_gsc_json'                => 'sanitize_textarea_field',
            'aap_enable_gsc_auto_ping'    => 'intval',
            'aap_prompt_titles'           => 'sanitize_textarea_field',
            'aap_prompt_article'          => 'sanitize_textarea_field',
            'aap_prompt_meta'             => 'sanitize_textarea_field',
            'aap_prompt_tags'             => 'sanitize_textarea_field',
            'aap_prompt_faq'              => 'sanitize_textarea_field',
        ];

        foreach ( $fields as $key => $sanitizer ) {
            if ( strpos( $key, 'aap_enable_' ) === 0 || $key === 'aap_review_mode' ) {
                $val = isset( $_POST[ $key ] ) ? 1 : 0;
            } elseif ( $key === 'aap_t2i_bg_val' ) {
                $bg_type = isset( $_POST['aap_t2i_bg_type'] ) ? sanitize_text_field( $_POST['aap_t2i_bg_type'] ) : 'gradient';
                if ( $bg_type === 'gradient' ) {
                    $val = isset( $_POST['aap_t2i_bg_val_gradient'] ) ? sanitize_text_field( $_POST['aap_t2i_bg_val_gradient'] ) : 'blue_purple';
                } else {
                    $val = isset( $_POST['aap_t2i_bg_val_solid'] ) ? sanitize_text_field( $_POST['aap_t2i_bg_val_solid'] ) : 'dark_slate';
                }
            } else {
                $val = isset( $_POST[ $key ] ) ? $sanitizer( $_POST[ $key ] ) : '';
            }
            update_option( $key, $val );
        }

        // Clear LiteSpeed / Autoptimize cache to refresh settings pages
        if ( class_exists( 'LiteSpeed_Cache_API' ) && method_exists( 'LiteSpeed_Cache_API', 'purge_all' ) ) {
            LiteSpeed_Cache_API::purge_all();
        }
        if ( class_exists( 'autoptimizeCache' ) && method_exists( 'autoptimizeCache', 'clearall' ) ) {
            autoptimizeCache::clearall();
        }

        wp_redirect( add_query_arg( [ 'page' => 'aap-settings', 'saved' => '1' ], admin_url( 'admin.php' ) ) );
        exit;
    }

    // -------------------------------------------------------------------------
    // API Key Handlers
    // -------------------------------------------------------------------------

    public static function handle_add_key() {
        check_admin_referer( 'aap_add_key' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        $key      = trim( sanitize_text_field( $_POST['api_key'] ?? '' ) );
        $provider = sanitize_text_field( $_POST['api_key_provider'] ?? 'gemini' );
        if ( $key ) {
            $added = AAP_Key_Manager::add_key( $key, $provider );
            $msg   = $added ? 'key_added' : 'key_exists';
        } else {
            $msg = 'key_empty';
        }

        wp_redirect( add_query_arg( [ 'page' => 'aap-settings', 'msg' => $msg ], admin_url( 'admin.php' ) ) );
        exit;
    }

    public static function handle_delete_key() {
        check_admin_referer( 'aap_delete_key' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        $index = (int) ( $_POST['key_index'] ?? -1 );
        AAP_Key_Manager::delete_key( $index );

        wp_redirect( add_query_arg( [ 'page' => 'aap-settings', 'msg' => 'key_deleted' ], admin_url( 'admin.php' ) ) );
        exit;
    }

    public static function handle_reset_key() {
        check_admin_referer( 'aap_reset_key' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        $index = (int) ( $_POST['key_index'] ?? -1 );
        AAP_Key_Manager::reset_key( $index );

        wp_redirect( add_query_arg( [ 'page' => 'aap-settings', 'msg' => 'key_reset' ], admin_url( 'admin.php' ) ) );
        exit;
    }

    // -------------------------------------------------------------------------
    // Scheduler Handlers
    // -------------------------------------------------------------------------

    public static function save_schedule() {
        check_admin_referer( 'aap_save_schedule' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        $enabled     = isset( $_POST['schedule_enabled'] ) ? 1 : 0;
        $per_day     = (int) ( $_POST['posts_per_day'] ?? 3 );
        $niches_text = sanitize_textarea_field( $_POST['schedule_niches'] ?? '' );

        update_option( AAP_Scheduler::OPTION_PER_DAY, $per_day );
        AAP_Scheduler::save_niches_list( $niches_text );

        if ( $enabled ) {
            AAP_Scheduler::enable();
        } else {
            AAP_Scheduler::disable();
        }

        wp_redirect( add_query_arg( [ 'page' => 'aap-scheduler', 'saved' => '1' ], admin_url( 'admin.php' ) ) );
        exit;
    }

    public static function handle_enqueue_niche() {
        check_admin_referer( 'aap_enqueue_niche' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        $niche = sanitize_text_field( $_POST['niche'] ?? '' );
        if ( $niche ) {
            AAP_Queue::enqueue( $niche );
        }

        wp_redirect( add_query_arg( [ 'page' => 'aap-scheduler', 'msg' => 'queued' ], admin_url( 'admin.php' ) ) );
        exit;
    }

    public static function handle_delete_queue() {
        check_admin_referer( 'aap_delete_queue' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        $id = (int) ( $_POST['queue_id'] ?? 0 );
        if ( $id ) AAP_Queue::delete( $id );

        wp_redirect( add_query_arg( [ 'page' => 'aap-scheduler', 'msg' => 'queue_deleted' ], admin_url( 'admin.php' ) ) );
        exit;
    }

    public static function handle_pause_queue() {
        check_admin_referer( 'aap_pause_queue' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        $id = (int) ( $_POST['queue_id'] ?? 0 );
        if ( $id ) {
            AAP_Queue::mark_paused( $id );
        }

        wp_redirect( add_query_arg( [ 'page' => 'aap-scheduler', 'msg' => 'queue_paused' ], admin_url( 'admin.php' ) ) );
        exit;
    }

    public static function handle_resume_queue() {
        check_admin_referer( 'aap_resume_queue' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        $id = (int) ( $_POST['queue_id'] ?? 0 );
        if ( $id ) {
            AAP_Queue::mark_resumed( $id );
        }

        wp_redirect( add_query_arg( [ 'page' => 'aap-scheduler', 'msg' => 'queue_resumed' ], admin_url( 'admin.php' ) ) );
        exit;
    }

    public static function handle_clear_queue() {
        check_admin_referer( 'aap_clear_queue' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        AAP_Queue::clear_all();

        wp_redirect( add_query_arg( [ 'page' => 'aap-scheduler', 'msg' => 'queue_cleared' ], admin_url( 'admin.php' ) ) );
        exit;
    }

    public static function handle_delete_selected_queue() {
        check_admin_referer( 'aap_delete_selected_queue' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        $ids_str = sanitize_text_field( $_POST['queue_ids'] ?? '' );
        if ( ! empty( $ids_str ) ) {
            $ids = explode( ',', $ids_str );
            AAP_Queue::delete_multiple( $ids );
        }

        wp_redirect( add_query_arg( [ 'page' => 'aap-scheduler', 'msg' => 'queue_selected_deleted' ], admin_url( 'admin.php' ) ) );
        exit;
    }

    // -------------------------------------------------------------------------
    // History Handlers
    // -------------------------------------------------------------------------

    public static function handle_delete_history() {
        check_admin_referer( 'aap_delete_history' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        $id = (int) ( $_POST['history_id'] ?? 0 );
        if ( $id ) AAP_History::delete( $id );

        wp_redirect( add_query_arg( [ 'page' => 'aap-dashboard', 'msg' => 'deleted' ], admin_url( 'admin.php' ) ) );
        exit;
    }

    public static function handle_clear_history() {
        check_admin_referer( 'aap_clear_history' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        AAP_History::clear_all();

        wp_redirect( add_query_arg( [ 'page' => 'aap-dashboard', 'msg' => 'cleared' ], admin_url( 'admin.php' ) ) );
        exit;
    }

    public static function handle_reset_settings() {
        check_admin_referer( 'aap_reset_settings' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        $options_to_delete = [
            'aap_default_status', 'aap_default_author', 'aap_word_count', 'aap_tag_count',
            'aap_content_tone', 'aap_blacklist_words', 'aap_key_reset_minutes', 'aap_review_mode',
            'aap_text_model', 'aap_image_model', 'aap_active_provider', 'aap_openai_model',
            'aap_enable_internal_linking', 'aap_max_internal_links', 'aap_enable_indexnow',
            'aap_enable_comments', 'aap_comments_count', 'aap_enable_text_overlay',
            'aap_overlay_font_size', 'aap_overlay_color', 'aap_overlay_bg_color',
            'aap_overlay_bg_opacity', 'aap_overlay_position', 'aap_thumb_type',
            'aap_t2i_bg_type', 'aap_t2i_bg_val', 'aap_t2i_size', 'aap_enable_faq',
            'aap_faq_count', 'aap_gsc_json', 'aap_enable_gsc_auto_ping', 'aap_prompt_titles',
            'aap_prompt_article', 'aap_prompt_meta', 'aap_prompt_tags', 'aap_prompt_faq',
            'aap_default_reference_image'
        ];

        foreach ( $options_to_delete as $opt ) {
            delete_option( $opt );
        }

        if ( function_exists( 'aap_purge_all_caches' ) ) {
            aap_purge_all_caches();
        }

        wp_redirect( add_query_arg( [ 'page' => 'aap-settings', 'msg' => 'settings_reset' ], admin_url( 'admin.php' ) ) );
        exit;
    }

    public static function handle_clear_plugin_data() {
        check_admin_referer( 'aap_clear_plugin_data' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        // 1. Delete all plugin transients
        global $wpdb;
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_aap_%' OR option_name LIKE '_site_transient_aap_%'" );

        // 2. Clear Queue
        AAP_Queue::clear_all();

        // 3. Clear History Log
        AAP_History::clear_all();

        // 4. Multi-engine Cache Purge
        if ( function_exists( 'aap_purge_all_caches' ) ) {
            aap_purge_all_caches();
        }

        wp_redirect( add_query_arg( [ 'page' => 'aap-settings', 'msg' => 'data_cleared' ], admin_url( 'admin.php' ) ) );
        exit;
    }

    public static function save_sitemap_settings() {
        check_admin_referer( 'aap_save_sitemap_settings' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        $slug = sanitize_text_field( $_POST['aap_sitemap_slug'] ?? 'sitemap.xml' );
        update_option( 'aap_sitemap_slug', $slug );

        $mode = sanitize_text_field( $_POST['aap_sitemap_mode'] ?? 'index' );
        update_option( 'aap_sitemap_mode', $mode );

        update_option( 'aap_sitemap_enable_home', isset( $_POST['aap_sitemap_enable_home'] ) ? '1' : '0' );
        update_option( 'aap_sitemap_priority_home', sanitize_text_field( $_POST['aap_sitemap_priority_home'] ?? '1.0' ) );
        update_option( 'aap_sitemap_changefreq_home', sanitize_text_field( $_POST['aap_sitemap_changefreq_home'] ?? 'daily' ) );

        update_option( 'aap_sitemap_enable_posts', isset( $_POST['aap_sitemap_enable_posts'] ) ? '1' : '0' );
        update_option( 'aap_sitemap_priority_post', sanitize_text_field( $_POST['aap_sitemap_priority_post'] ?? '0.8' ) );
        update_option( 'aap_sitemap_changefreq_post', sanitize_text_field( $_POST['aap_sitemap_changefreq_post'] ?? 'daily' ) );

        update_option( 'aap_sitemap_enable_pages', isset( $_POST['aap_sitemap_enable_pages'] ) ? '1' : '0' );
        update_option( 'aap_sitemap_priority_page', sanitize_text_field( $_POST['aap_sitemap_priority_page'] ?? '0.6' ) );
        update_option( 'aap_sitemap_changefreq_page', sanitize_text_field( $_POST['aap_sitemap_changefreq_page'] ?? 'weekly' ) );

        update_option( 'aap_sitemap_enable_cats', isset( $_POST['aap_sitemap_enable_cats'] ) ? '1' : '0' );
        update_option( 'aap_sitemap_priority_cat', sanitize_text_field( $_POST['aap_sitemap_priority_cat'] ?? '0.5' ) );
        update_option( 'aap_sitemap_changefreq_cat', sanitize_text_field( $_POST['aap_sitemap_changefreq_cat'] ?? 'weekly' ) );

        update_option( 'aap_sitemap_enable_tags', isset( $_POST['aap_sitemap_enable_tags'] ) ? '1' : '0' );
        update_option( 'aap_sitemap_priority_tag', sanitize_text_field( $_POST['aap_sitemap_priority_tag'] ?? '0.4' ) );
        update_option( 'aap_sitemap_changefreq_tag', sanitize_text_field( $_POST['aap_sitemap_changefreq_tag'] ?? 'monthly' ) );

        update_option( 'aap_sitemap_include_images', isset( $_POST['aap_sitemap_include_images'] ) ? '1' : '0' );
        update_option( 'aap_sitemap_auto_ping_google', isset( $_POST['aap_sitemap_auto_ping_google'] ) ? '1' : '0' );
        update_option( 'aap_sitemap_auto_ping_bing', isset( $_POST['aap_sitemap_auto_ping_bing'] ) ? '1' : '0' );

        if ( class_exists( 'AAP_Sitemap' ) ) {
            AAP_Sitemap::add_rewrite_rules();
            flush_rewrite_rules();
        }

        wp_redirect( admin_url( 'admin.php?page=aap-sitemap&updated=true' ) );
        exit;
    }

    public static function render_thumbnails_page() {
        require_once AAP_PLUGIN_DIR . 'admin/views/thumbnails-page.php';
    }
}
