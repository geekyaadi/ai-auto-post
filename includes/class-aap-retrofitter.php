<?php
/**
 * Retrofit Old Posts Engine for AI Auto Post
 * Allows bulk injection of Auto-Internal Links, High-DA Outbound Links, and Table of Contents (TOC) into EXISTING/OLD published posts.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class AAP_Retrofitter {

    public static function init() {
        add_action( 'admin_post_aap_retrofit_old_posts', [ __CLASS__, 'handle_retrofit_old_posts' ] );
    }

    public static function inject_toc( $content ) {
        if ( empty( $content ) ) return $content;

        // Skip if TOC already exists
        if ( strpos( $content, 'class="aap-toc"' ) !== false || strpos( $content, 'id="aap-toc"' ) !== false ) {
            return $content;
        }

        preg_match_all( '/<h2[^>]*>(.*?)<\/h2>/i', $content, $matches, PREG_SET_ORDER );
        if ( count( $matches ) < 2 ) {
            return $content; // At least 2 H2 headings required
        }

        $toc_html = "\n<div class=\"aap-toc\" style=\"background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:18px 22px; margin:24px 0; font-family: -apple-system, BlinkMacSystemFont, sans-serif;\">\n";
        $toc_html .= "  <h3 style=\"margin-top:0; margin-bottom:12px; font-size:16px; font-weight:700; color:#0f172a;\">📌 Table of Contents</h3>\n";
        $toc_html .= "  <ul style=\"margin:0; padding-left:20px; list-style-type:disc;\">\n";

        $replacements = [];
        foreach ( $matches as $index => $match ) {
            $raw_h2       = $match[0];
            $inner_text   = strip_tags( $match[1] );
            $anchor_id    = 'toc-head-' . ( $index + 1 );

            $new_h2 = '<h2 id="' . esc_attr( $anchor_id ) . '">' . $match[1] . '</h2>';
            $replacements[$raw_h2] = $new_h2;

            $toc_html .= '    <li style="margin-bottom:6px;"><a href="#' . esc_attr( $anchor_id ) . '" style="color:#2563eb; font-weight:500; text-decoration:none;">' . esc_html( $inner_text ) . '</a></li>' . "\n";
        }

        $toc_html .= "  </ul>\n</div>\n\n";

        // Add IDs to H2 tags
        foreach ( $replacements as $old_h2 => $new_h2 ) {
            $content = preg_replace( '/' . preg_quote( $old_h2, '/' ) . '/', $new_h2, $content, 1 );
        }

        // Insert TOC block right before the first <h2>
        $first_h2_pos = strpos( $content, '<h2' );
        if ( $first_h2_pos !== false ) {
            $content = substr_replace( $content, $toc_html, $first_h2_pos, 0 );
        } else {
            $content = $toc_html . $content;
        }

        return $content;
    }

    public static function process_single_post( $post_id, $do_internal = true, $do_outbound = true, $do_toc = true ) {
        $post = get_post( $post_id );
        if ( ! $post || $post->post_type !== 'post' ) return false;

        $original_content = $post->post_content;
        $updated_content  = $original_content;

        // 1. Table of Contents
        if ( $do_toc ) {
            $updated_content = self::inject_toc( $updated_content );
        }

        // 2. Auto-Internal Linking
        if ( $do_internal && class_exists( 'AAP_Post_Creator' ) && method_exists( 'AAP_Post_Creator', 'apply_internal_linking' ) ) {
            $updated_content = AAP_Post_Creator::apply_internal_linking( $post_id, $updated_content );
        }

        // 3. Auto Outbound High-DA Linking
        if ( $do_outbound && class_exists( 'AAP_Outbound_Linker' ) && method_exists( 'AAP_Outbound_Linker', 'process' ) ) {
            $updated_content = AAP_Outbound_Linker::process( $updated_content, $post->post_title );
        }

        if ( $updated_content !== $original_content ) {
            wp_update_post( [
                'ID'           => $post_id,
                'post_content' => $updated_content,
            ] );
            return true;
        }

        return false;
    }

    public static function handle_retrofit_old_posts() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
        check_admin_referer( 'aap_retrofit_nonce' );

        $limit       = intval( $_POST['post_limit'] ?? 20 );
        $category_id = intval( $_POST['category_id'] ?? 0 );
        $do_internal = isset( $_POST['do_internal'] ) && $_POST['do_internal'] === '1';
        $do_outbound = isset( $_POST['do_outbound'] ) && $_POST['do_outbound'] === '1';
        $do_toc      = isset( $_POST['do_toc'] ) && $_POST['do_toc'] === '1';

        $args = [
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => $limit > 0 ? $limit : 50,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        if ( $category_id > 0 ) {
            $args['cat'] = $category_id;
        }

        $query = new WP_Query( $args );
        $updated_count = 0;

        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $id = get_the_ID();
                if ( self::process_single_post( $id, $do_internal, $do_outbound, $do_toc ) ) {
                    $updated_count++;
                }
            }
            wp_reset_postdata();
        }

        wp_redirect( admin_url( 'admin.php?page=aap-settings&retrofitted=' . $updated_count ) );
        exit;
    }
}
