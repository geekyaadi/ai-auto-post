<?php
/**
 * AI Auto Post Uninstall Template
 * Clean up all options, transients, cron jobs, and database tables when the plugin is uninstalled.
 *
 * @package AI_Auto_Post
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// 1. Clear scheduled cron hook
wp_clear_scheduled_hook( 'aap_scheduled_publish' );

// 2. Drop custom database tables
$tables = [
    $wpdb->prefix . 'aap_history',
    $wpdb->prefix . 'aap_queue'
];

foreach ( $tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

// 3. Delete all registered options
$options = [
    'aap_default_status',
    'aap_default_author',
    'aap_word_count',
    'aap_tag_count',
    'aap_content_tone',
    'aap_blacklist_words',
    'aap_key_reset_minutes',
    'aap_review_mode',
    'aap_text_model',
    'aap_image_model',
    'aap_active_provider',
    'aap_openai_model',
    'aap_enable_internal_linking',
    'aap_max_internal_links',
    'aap_enable_indexnow',
    'aap_enable_comments',
    'aap_comments_count',
    'aap_enable_text_overlay',
    'aap_overlay_font_size',
    'aap_overlay_color',
    'aap_overlay_bg_color',
    'aap_overlay_bg_opacity',
    'aap_overlay_position',
    'aap_thumb_type',
    'aap_t2i_bg_type',
    'aap_t2i_bg_val',
    'aap_t2i_size',
    'aap_enable_faq',
    'aap_faq_count',
    'aap_gsc_json',
    'aap_enable_gsc_auto_ping',
    'aap_prompt_titles',
    'aap_prompt_article',
    'aap_prompt_meta',
    'aap_prompt_tags',
    'aap_prompt_faq',
    'aap_db_version',
    'aap_default_reference_image',
    'aap_api_keys',
    'aap_scheduler_enabled',
    'aap_scheduler_niches',
    'aap_last_niche_index',
    'aap_scheduler_per_day'
];

foreach ( $options as $option ) {
    delete_option( $option );
}

// 4. Delete transient data
delete_transient( 'aap_github_release_info' );
delete_site_transient( 'update_plugins' );
