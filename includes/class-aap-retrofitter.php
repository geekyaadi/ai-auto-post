<?php
/**
 * Retrofit Old Posts Engine & Dynamic Shortcode Content Filter for AI Auto Post
 * Dynamic Non-Destructive Content Filter & Shortcode engine for TOC, Auto-Internal Links, and High-DA Outbound Links.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class AAP_Retrofitter {

    public static function init() {
        add_action( 'admin_post_aap_retrofit_old_posts', [ __CLASS__, 'handle_retrofit_old_posts' ] );
        add_filter( 'the_content',                       [ __CLASS__, 'filter_the_content' ], 12 );

        // Shortcodes registration
        add_shortcode( 'aap_toc',            [ __CLASS__, 'shortcode_toc' ] );
        add_shortcode( 'aap_internal_links', [ __CLASS__, 'shortcode_internal_links' ] );
        add_shortcode( 'aap_outbound_links', [ __CLASS__, 'shortcode_outbound_links' ] );
    }

    /**
     * Dynamic Content Filter for Single Posts (Non-Destructive DB Storage)
     */
    public static function filter_the_content( $content ) {
        if ( ! is_single() || ! in_the_loop() || ! is_main_query() || empty( $content ) ) {
            return $content;
        }

        $post_id = get_the_ID();

        // 1. Dynamic Table of Contents (TOC)
        if ( get_option( 'aap_enable_toc', 1 ) && strpos( $content, 'class="aap-toc"' ) === false ) {
            $content = self::inject_toc( $content );
        }

        // 2. Dynamic Auto-Internal Linking
        if ( get_option( 'aap_enable_internal_linking', 1 ) && strpos( $content, 'aap-related-' ) === false ) {
            if ( class_exists( 'AAP_Post_Creator' ) && method_exists( 'AAP_Post_Creator', 'apply_internal_linking' ) ) {
                $content = AAP_Post_Creator::apply_internal_linking( $post_id, $content );
            }
        }

        // 3. Dynamic High-DA Outbound Linking
        if ( get_option( 'aap_enable_outbound_linking', 1 ) && strpos( $content, 'aap-outbound-link' ) === false ) {
            if ( class_exists( 'AAP_Outbound_Linker' ) && method_exists( 'AAP_Outbound_Linker', 'process' ) ) {
                $content = AAP_Outbound_Linker::process( $content, get_the_title( $post_id ) );
            }
        }

        return $content;
    }

    /**
     * Generate Collapsible Table of Contents with Show/Hide Accordion Toggle
     */
    public static function inject_toc( $content ) {
        if ( empty( $content ) ) return $content;

        if ( strpos( $content, 'class="aap-toc"' ) !== false || strpos( $content, 'id="aap-toc"' ) !== false ) {
            return $content;
        }

        preg_match_all( '/<h2[^>]*>(.*?)<\/h2>/i', $content, $matches, PREG_SET_ORDER );
        if ( count( $matches ) < 2 ) {
            return $content;
        }

        $default_state = get_option( 'aap_toc_default_state', 'open' );
        $is_closed     = ( $default_state === 'closed' );
        $list_display  = $is_closed ? 'none' : 'block';
        $btn_label     = $is_closed ? '[Show]' : '[Hide]';

        $toc_html  = "\n<div class=\"aap-toc\" style=\"background:#f8fafc; border:1px solid #cbd5e1; border-radius:8px; padding:16px 20px; margin:24px 0; font-family: system-ui, -apple-system, sans-serif;\">\n";
        $toc_html .= "  <div style=\"display:flex; justify-content:space-between; align-items:center;\">\n";
        $toc_html .= "    <h3 style=\"margin:0; font-size:16px; font-weight:700; color:#0f172a;\">📌 Table of Contents</h3>\n";
        $toc_html .= "    <button type=\"button\" class=\"aap-toc-toggle-btn\" onclick=\"var list=this.parentElement.nextElementSibling; if(list.style.display==='none'){list.style.display='block';this.innerText='[Hide]';}else{list.style.display='none';this.innerText='[Show]';}\" style=\"background:none; border:none; color:#0284c7; font-weight:700; font-size:13px; cursor:pointer; padding:0;\">" . esc_html( $btn_label ) . "</button>\n";
        $toc_html .= "  </div>\n";
        $toc_html .= "  <ul class=\"aap-toc-list\" style=\"margin:12px 0 0 0; padding-left:20px; list-style-type:disc; display:" . esc_attr( $list_display ) . ";\">\n";

        $replacements = [];
        foreach ( $matches as $index => $match ) {
            $raw_h2     = $match[0];
            $inner_text = wp_strip_all_tags( $match[1] );
            $anchor_id  = 'toc-head-' . ( $index + 1 );

            $new_h2 = '<h2 id="' . esc_attr( $anchor_id ) . '">' . $match[1] . '</h2>';
            $replacements[ $raw_h2 ] = $new_h2;

            $toc_html .= '    <li style="margin-bottom:6px;"><a href="#' . esc_attr( $anchor_id ) . '" style="color:#0284c7; font-weight:500; text-decoration:none;">' . esc_html( $inner_text ) . '</a></li>' . "\n";
        }

        $toc_html .= "  </ul>\n</div>\n\n";

        foreach ( $replacements as $old_h2 => $new_h2 ) {
            $content = preg_replace( '/' . preg_quote( $old_h2, '/' ) . '/', $new_h2, $content, 1 );
        }

        $first_h2_pos = strpos( $content, '<h2' );
        if ( $first_h2_pos !== false ) {
            $content = substr_replace( $content, $toc_html, $first_h2_pos, 0 );
        } else {
            $content = $toc_html . $content;
        }

        return $content;
    }

    /**
     * Shortcode: [aap_toc]
     */
    public static function shortcode_toc( $atts = [], $content = null ) {
        global $post;
        if ( ! $post ) return '';
        return self::inject_toc( $post->post_content );
    }

    /**
     * Shortcode: [aap_internal_links]
     */
    public static function shortcode_internal_links( $atts = [], $content = null ) {
        global $post;
        if ( ! $post ) return '';
        return AAP_Post_Creator::apply_internal_linking( $post->ID, $post->post_content );
    }

    /**
     * Shortcode: [aap_outbound_links]
     */
    public static function shortcode_outbound_links( $atts = [], $content = null ) {
        global $post;
        if ( ! $post ) return '';
        return AAP_Outbound_Linker::process( $post->post_content, $post->post_title );
    }

    public static function process_single_post( $post_id, $do_internal = true, $do_outbound = true, $do_toc = true ) {
        // Sets post flags so dynamic the_content filter renders them seamlessly
        update_post_meta( $post_id, '_aap_retrofit_toc',      $do_toc ? '1' : '0' );
        update_post_meta( $post_id, '_aap_retrofit_internal', $do_internal ? '1' : '0' );
        update_post_meta( $post_id, '_aap_retrofit_outbound', $do_outbound ? '1' : '0' );
        return true;
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

        if ( function_exists( 'aap_purge_all_caches' ) ) {
            aap_purge_all_caches();
        }

        wp_safe_redirect( admin_url( 'admin.php?page=aap-settings&retrofitted=' . $updated_count ) );
        exit;
    }
}
