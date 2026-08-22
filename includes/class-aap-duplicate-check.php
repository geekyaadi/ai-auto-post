<?php
/**
 * Handles duplicate post detection before publishing.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class AAP_Duplicate_Check {

    /**
     * Checks if a post with a similar title already exists.
     * Returns matching post object or null.
     */
    public static function find_duplicate( string $title ) {
        global $wpdb;

        $sanitized = sanitize_text_field( $title );

        // Exact title match
        $query = new WP_Query( [
            'title'                  => $sanitized,
            'post_type'              => 'post',
            'post_status'            => 'any',
            'posts_per_page'         => 1,
            'no_found_rows'          => true,
            'ignore_sticky_posts'    => true,
            'update_post_term_cache' => false,
            'update_post_meta_cache' => false,
        ] );
        $exact = ! empty( $query->posts ) ? $query->posts[0] : null;
        if ( $exact ) return $exact;

        // Fuzzy: check if title slug already exists
        $slug   = sanitize_title( $sanitized );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $result = $wpdb->get_row( $wpdb->prepare(
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            "SELECT ID, post_title FROM {$wpdb->posts}
             WHERE post_status IN ('publish','draft','pending','future')
             AND post_type = 'post'
             AND post_name = %s
             LIMIT 1",
            $slug
        ) );

        return $result ?: null;
    }

    /**
     * Returns true if a duplicate exists.
     */
    public static function is_duplicate( string $title ) {
        return self::find_duplicate( $title ) !== null;
    }
}
