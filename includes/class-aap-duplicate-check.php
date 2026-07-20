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
        $exact = get_page_by_title( $sanitized, OBJECT, 'post' );
        if ( $exact ) return $exact;

        // Fuzzy: check if title slug already exists
        $slug   = sanitize_title( $sanitized );
        $result = $wpdb->get_row( $wpdb->prepare(
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
