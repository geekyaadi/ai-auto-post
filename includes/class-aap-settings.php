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
        add_action( 'admin_post_aap_clear_history',  [ __CLASS__, 'handle_clear_history' ] );
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
        ];
        if ( ! in_array( $hook, $aap_pages, true ) ) return;

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

    public static function render_thumbnails_page() {
        require_once AAP_PLUGIN_DIR . 'admin/views/thumbnails-page.php';
    }
}
