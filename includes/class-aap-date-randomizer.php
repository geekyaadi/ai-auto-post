<?php
/**
 * Post Date Randomizer & Backdater Engine for AI Auto Post
 * Randomizes post publishing dates and comment timestamps within a custom date range.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class AAP_Date_Randomizer {

    public static function init() {
        add_action( 'admin_post_aap_save_randomizer_settings', [ __CLASS__, 'handle_save_settings' ] );
        add_action( 'admin_post_aap_run_randomizer',           [ __CLASS__, 'handle_run_randomizer' ] );
    }

    public static function handle_save_settings() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
        check_admin_referer( 'aap_randomizer_nonce' );

        $start_date = sanitize_text_field( $_POST['start_date'] ?? '' );
        $end_date   = sanitize_text_field( $_POST['end_date'] ?? '' );

        update_option( 'aap_randomizer_start_date', $start_date );
        update_option( 'aap_randomizer_end_date',   $end_date );
        update_option( 'aap_randomizer_posts',        isset( $_POST['randomize_posts'] ) ? '1' : '0' );
        update_option( 'aap_randomizer_comments',     isset( $_POST['randomize_comments'] ) ? '1' : '0' );
        update_option( 'aap_randomizer_post_type',    sanitize_text_field( $_POST['post_type'] ?? 'post' ) );
        update_option( 'aap_randomizer_modified_date',isset( $_POST['set_modified_date'] ) ? '1' : '0' );

        wp_safe_redirect( admin_url( 'admin.php?page=aap-randomizer&saved=true' ) );
        exit;
    }

    public static function handle_run_randomizer() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
        check_admin_referer( 'aap_randomizer_nonce' );

        global $wpdb;

        $start_str = get_option( 'aap_randomizer_start_date', '' );
        $end_str   = get_option( 'aap_randomizer_end_date', '' );

        if ( empty( $start_str ) ) {
            $start_str = gmdate( 'Y-m-d H:i:s', strtotime( '-90 days' ) );
        }
        if ( empty( $end_str ) ) {
            $end_str = gmdate( 'Y-m-d H:i:s' );
        }

        $start_ts = strtotime( $start_str );
        $end_ts   = strtotime( $end_str );

        if ( ! $start_ts || ! $end_ts || $start_ts >= $end_ts ) {
            wp_safe_redirect( admin_url( 'admin.php?page=aap-randomizer&error=invalid_range' ) );
            exit;
        }

        $randomize_posts    = get_option( 'aap_randomizer_posts', '1' ) === '1';
        $randomize_comments = get_option( 'aap_randomizer_comments', '1' ) === '1';
        $post_type          = get_option( 'aap_randomizer_post_type', 'post' );
        $set_modified       = get_option( 'aap_randomizer_modified_date', '1' ) === '1';

        $affected_count = 0;

        if ( $randomize_posts ) {
            $posts = get_posts([
                'post_type'      => $post_type,
                'post_status'    => [ 'publish', 'draft' ],
                'posts_per_page' => -1,
                'fields'         => 'ids',
            ]);

            foreach ( $posts as $post_id ) {
                $rand_ts   = wp_rand( $start_ts, $end_ts );
                $rand_date = gmdate( 'Y-m-d H:i:s', $rand_ts );
                $rand_gmt  = gmdate( 'Y-m-d H:i:s', $rand_ts );

                $update_data = [
                    'post_date'     => $rand_date,
                    'post_date_gmt' => $rand_gmt,
                ];

                if ( $set_modified ) {
                    $update_data['post_modified']     = $rand_date;
                    $update_data['post_modified_gmt'] = $rand_gmt;
                }

                $wpdb->update(
                    $wpdb->posts,
                    $update_data,
                    [ 'ID' => $post_id ]
                );

                clean_post_cache( $post_id );
                $affected_count++;

                // Randomize attached comments if enabled
                if ( $randomize_comments ) {
                    $comments = $wpdb->get_results( $wpdb->prepare(
                        "SELECT comment_ID FROM {$wpdb->comments} WHERE comment_post_ID = %d AND comment_approved = '1'",
                        $post_id
                    ) );

                    foreach ( $comments as $c ) {
                        // Comment date between post date and end_ts (or post date + 3 days)
                        $comment_start = $rand_ts;
                        $comment_end   = min( $end_ts, $rand_ts + ( 86400 * 3 ) );
                        $c_rand_ts     = ( $comment_start < $comment_end ) ? wp_rand( $comment_start, $comment_end ) : $comment_start;
                        $c_rand_date   = gmdate( 'Y-m-d H:i:s', $c_rand_ts );
                        $c_rand_gmt    = gmdate( 'Y-m-d H:i:s', $c_rand_ts );

                        $wpdb->update(
                            $wpdb->comments,
                            [
                                'comment_date'     => $c_rand_date,
                                'comment_date_gmt' => $c_rand_gmt,
                            ],
                            [ 'comment_ID' => $c->comment_ID ]
                        );
                    }
                }
            }
        }

        if ( function_exists( 'aap_purge_all_caches' ) ) {
            aap_purge_all_caches();
        }

        wp_safe_redirect( admin_url( 'admin.php?page=aap-randomizer&run=success&count=' . $affected_count ) );
        exit;
    }
}
