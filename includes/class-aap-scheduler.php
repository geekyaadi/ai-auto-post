<?php
/**
 * Scheduler — WP Cron integration for auto-publishing posts.
 * Supports "publish X posts per day" from the queue.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class AAP_Scheduler {

    const CRON_HOOK        = 'aap_scheduled_publish';
    const OPTION_ENABLED   = 'aap_schedule_enabled';
    const OPTION_PER_DAY   = 'aap_posts_per_day';
    const OPTION_NICHES    = 'aap_schedule_niches';

    public static function init() {
        add_action( self::CRON_HOOK, [ __CLASS__, 'run_scheduled_generation' ] );
    }

    // -------------------------------------------------------------------------
    // Enable / Disable Scheduler
    // -------------------------------------------------------------------------

    public static function enable() {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time(), 'hourly', self::CRON_HOOK );
        }
        update_option( self::OPTION_ENABLED, 1 );
    }

    public static function disable() {
        $timestamp = wp_next_scheduled( self::CRON_HOOK );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::CRON_HOOK );
        }
        update_option( self::OPTION_ENABLED, 0 );
    }

    public static function deactivate() {
        self::disable();
    }

    public static function is_enabled() {
        return (bool) get_option( self::OPTION_ENABLED, 0 );
    }

    // -------------------------------------------------------------------------
    // WP Cron Job Handler
    // -------------------------------------------------------------------------

    /**
     * Runs every hour. Checks how many posts were published today; if under
     * the daily cap, generates and publishes one more.
     */
    public static function run_scheduled_generation() {
        if ( ! self::is_enabled() ) return;

        $posts_per_day = (int) get_option( self::OPTION_PER_DAY, 3 );
        $today_count   = self::count_posts_today();

        if ( $today_count >= $posts_per_day ) return;

        // How many slots left today?
        $slots_left    = $posts_per_day - $today_count;
        // We generate 1 post per cron run to avoid timeouts
        $slots_to_run  = min( $slots_left, 1 );

        for ( $i = 0; $i < $slots_to_run; $i++ ) {
            // Try queue first, then auto-generate from niche list
            $queued = AAP_Queue::get_next_queued();

            if ( $queued ) {
                self::publish_queued_post( $queued );
            } else {
                self::auto_generate_post();
            }
        }
    }

    // -------------------------------------------------------------------------
    // Publish a Pre-Queued Post
    // -------------------------------------------------------------------------

    private static function publish_queued_post( $queued ) {
        AAP_Queue::mark_processing( $queued->id );

        // If post_id already set (draft in WP), just publish it
        if ( ! empty( $queued->post_id ) ) {
            $result = wp_update_post( [
                'ID'          => $queued->post_id,
                'post_status' => 'publish',
            ], true );

            if ( is_wp_error( $result ) ) {
                AAP_Queue::mark_failed( $queued->id, $result->get_error_message() );
            } else {
                AAP_Queue::mark_published( $queued->id, $queued->post_id );
            }
            return;
        }

        $meta = ! empty( $queued->meta ) ? maybe_unserialize( $queued->meta ) : [];
        if ( ! is_array( $meta ) ) {
            $meta = [];
        }

        // Otherwise, generate fresh post for the niche
        $post_id = self::generate_and_publish(
            $queued->niche,
            $queued->title,
            '',
            $queued->language ?? 'English',
            $queued->category ?? '',
            $queued->silo_id ?? null,
            $queued->silo_role ?? null,
            (int) ( $queued->tag_count ?? 0 ),
            $meta
        );

        if ( is_wp_error( $post_id ) ) {
            AAP_Queue::mark_failed( $queued->id, $post_id->get_error_message() );
        } else {
            AAP_Queue::mark_published( $queued->id, $post_id );
        }
    }

    // -------------------------------------------------------------------------
    // Auto-Generate from Niche Rotation List
    // -------------------------------------------------------------------------

    private static function auto_generate_post() {
        $niches = self::get_niches_list();
        if ( empty( $niches ) ) return;

        // Rotate: pick the niche used least recently
        $niche_line = self::pick_next_niche( $niches );
        if ( ! $niche_line ) return;

        $parts          = explode( '|', $niche_line );
        $clean_niche    = trim( $parts[0] );
        $focus_keywords = isset( $parts[1] ) ? trim( $parts[1] ) : '';

        $post_id = self::generate_and_publish( $clean_niche, '', $focus_keywords );
        self::record_niche_used( $niche_line );

        return $post_id;
    }

    // -------------------------------------------------------------------------
    // Core Generation & Publish
    // -------------------------------------------------------------------------

    public static function generate_and_publish(
        string $niche,
        string $preferred_title = '',
        string $focus_keywords = '',
        string $language = 'English',
        string $category = '',
        ?int $silo_id = null,
        ?string $silo_role = null,
        int $tag_count = 0,
        array $t2i_opts = []
    ) {
        $session_id = 'cron_' . uniqid();

        // Step 1: Titles
        if ( $preferred_title ) {
            $title = $preferred_title;
        } else {
            $titles_result = AAP_Gemini::get_title_suggestions( $session_id, $niche, $focus_keywords, $language );
            if ( is_wp_error( $titles_result ) ) return $titles_result;
            $titles = $titles_result['titles'];
            if ( empty( $titles ) ) return new WP_Error( 'no_titles', 'No titles generated.' );
            $title = $titles[0]; // Pick first title for scheduled posts
        }

        // Duplicate check
        if ( AAP_Duplicate_Check::is_duplicate( $title ) ) {
            return new WP_Error( 'duplicate', "Duplicate title skipped: {$title}" );
        }

        // Step 2: Article
        $article_result = AAP_Gemini::generate_article( $session_id, $title, $focus_keywords, $language );
        if ( is_wp_error( $article_result ) ) return $article_result;
        $article_content = $article_result['article'];

        // Step 3: Tags
        $tags_result = AAP_Gemini::generate_tags( $session_id, $title, $language, $tag_count );
        $tags        = is_wp_error( $tags_result ) ? [] : $tags_result['tags'];

        // Step 4: Meta
        $meta_result = AAP_Gemini::generate_meta_description( $session_id, $title, $focus_keywords, $language );
        $meta        = is_wp_error( $meta_result ) ? '' : $meta_result['meta'];

        // Step 5: Category
        if ( empty( $category ) ) {
            $cat_result = AAP_Gemini::suggest_category( $session_id, $title, $language );
            $category   = is_wp_error( $cat_result ) ? '' : $cat_result['category'];
        }

        // Step 6: Thumbnail
        $thumb_result = AAP_Gemini::generate_thumbnail( $session_id, $title, [], $t2i_opts );
        $thumb_data   = is_wp_error( $thumb_result ) ? null : $thumb_result['image_data'];

        // Step 7: OG Image
        $og_result  = AAP_Gemini::generate_og_image( $session_id, $title, $t2i_opts );
        $og_data    = is_wp_error( $og_result ) ? null : $og_result['image_data'];

        // Step 8: Alt Text
        $alt_result = AAP_Gemini::generate_alt_text( $session_id, $title, $language );
        $alt_text   = is_wp_error( $alt_result ) ? $title : $alt_result['alt_text'];

        // Silo Interlinking: Cluster to Pillar
        if ( $silo_role === 'cluster' && ! empty( $silo_id ) ) {
            global $wpdb;
            $queue_table = AAP_Queue::table_name();
            $pillar_item = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$queue_table} WHERE id = %d AND status = 'published' AND post_id IS NOT NULL LIMIT 1",
                $silo_id
            ) );

            if ( $pillar_item ) {
                $pillar_url = get_permalink( $pillar_item->post_id );
                if ( $pillar_url ) {
                    $backlink_html = "\n\n<p class=\"aap-silo-backlink\">Learn more about this topic in our comprehensive guide: <a href=\"" . esc_url( $pillar_url ) . "\">" . esc_html( $pillar_item->title ) . "</a>.</p>";
                    $article_content .= $backlink_html;
                }
            }
        }

        // Create post
        $post_id = AAP_Post_Creator::create( [
            'title'            => $title,
            'article'          => $article_content,
            'tags'             => $tags,
            'meta_description' => $meta,
            'category'         => $category,
            'alt_text'         => $alt_text,
            'thumbnail_data'   => $thumb_data,
            'og_image_data'    => $og_data,
            'post_status'      => 'publish',
        ] );

        // Silo Interlinking: Update Pillar content to link to this new Cluster post
        if ( $silo_role === 'cluster' && ! empty( $silo_id ) && ! is_wp_error( $post_id ) ) {
            global $wpdb;
            $queue_table = AAP_Queue::table_name();
            $pillar_item = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$queue_table} WHERE id = %d AND status = 'published' AND post_id IS NOT NULL LIMIT 1",
                $silo_id
            ) );

            if ( $pillar_item ) {
                $pillar_post_id = $pillar_item->post_id;
                $pillar_post = get_post( $pillar_post_id );
                if ( $pillar_post ) {
                    $pillar_content = $pillar_post->post_content;
                    $cluster_url = get_permalink( $post_id );
                    $cluster_title = get_the_title( $post_id );
                    $new_link_html = "<li><a href=\"" . esc_url( $cluster_url ) . "\">" . esc_html( $cluster_title ) . "</a></li>";

                    if ( strpos( $pillar_content, 'class="aap-silo-links"' ) !== false ) {
                        $pos = strrpos( $pillar_content, '</ul>' );
                        if ( $pos !== false ) {
                            $pillar_content = substr_replace( $pillar_content, $new_link_html . '</ul>', $pos, 5 );
                        }
                    } else {
                        $pillar_content .= "\n\n<h3>Related Articles</h3><ul class=\"aap-silo-links\">" . $new_link_html . "</ul>";
                    }

                    wp_update_post( [
                        'ID'           => $pillar_post_id,
                        'post_content' => $pillar_content,
                    ] );
                }
            }
        }

        // History log
        $token_est = AAP_History::estimate_tokens(
            $article_result['article'],
            implode( ',', $tags ),
            $meta
        );
        AAP_History::insert( [
            'session_id'    => $session_id,
            'niche'         => $niche,
            'title'         => $title,
            'post_id'       => is_wp_error( $post_id ) ? null : $post_id,
            'status'        => is_wp_error( $post_id ) ? 'failed' : 'success',
            'key_used'      => AAP_Gemini::get_current_key( $session_id ) ?? '',
            'token_estimate'=> $token_est,
            'error_message' => is_wp_error( $post_id ) ? $post_id->get_error_message() : null,
        ] );

        AAP_Gemini::clear_session_cache( $session_id );

        return $post_id;
    }

    // -------------------------------------------------------------------------
    // Niche Rotation Helpers
    // -------------------------------------------------------------------------

    public static function get_niches_list() {
        $raw = get_option( self::OPTION_NICHES, '' );
        return array_filter( array_map( 'trim', explode( "\n", $raw ) ) );
    }

    public static function save_niches_list( string $niches_text ) {
        update_option( self::OPTION_NICHES, sanitize_textarea_field( $niches_text ) );
    }

    private static function pick_next_niche( array $niches ) {
        $last_used = get_option( 'aap_last_niche_index', -1 );
        $next      = ( (int) $last_used + 1 ) % count( $niches );
        return $niches[ $next ] ?? $niches[0];
    }

    private static function record_niche_used( string $niche ) {
        $niches = self::get_niches_list();
        $index  = array_search( $niche, $niches );
        if ( $index !== false ) {
            update_option( 'aap_last_niche_index', $index );
        }
    }

    // -------------------------------------------------------------------------
    // Post Count Today
    // -------------------------------------------------------------------------

    private static function count_posts_today() {
        $today = current_time( 'Y-m-d' );
        $query = new WP_Query( [
            'post_type'      => 'post',
            'post_status'    => [ 'publish', 'draft' ],
            'date_query'     => [ [ 'after' => $today . ' 00:00:00', 'inclusive' => true ] ],
            'meta_key'       => '_aap_generated',
            'meta_value'     => '1',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ] );
        return $query->found_posts;
    }

    // -------------------------------------------------------------------------
    // Next Scheduled Time
    // -------------------------------------------------------------------------

    public static function get_next_run() {
        $ts = wp_next_scheduled( self::CRON_HOOK );
        return $ts ? date_i18n( 'Y-m-d H:i:s', $ts ) : __( 'Not scheduled', 'ai-auto-post' );
    }
}
