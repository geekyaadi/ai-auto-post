<?php
/**
 * XML Sitemap Manager & Controller Engine for AI Auto Post
 * Supports: Homepage (Site URL), Posts, Pages, Categories, Tags with individual Priority & Change Frequency controls.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class AAP_Sitemap {

    public static function init() {
        add_action( 'init', [ __CLASS__, 'add_rewrite_rules' ] );
        add_filter( 'query_vars', [ __CLASS__, 'add_query_vars' ] );
        add_action( 'parse_request', [ __CLASS__, 'check_request_sitemap' ] );
        add_action( 'template_redirect', [ __CLASS__, 'render_sitemap' ], 1 );
        add_action( 'publish_post', [ __CLASS__, 'ping_search_engines' ] );
    }

    public static function add_rewrite_rules() {
        $slug = get_option( 'aap_sitemap_slug', 'sitemap.xml' );
        $slug_clean = trim( $slug, '/' );
        $base_name  = preg_replace( '/\.xml$/i', '', $slug_clean );

        add_rewrite_rule( '^' . preg_quote( $slug_clean, '#' ) . '$', 'index.php?aap_sitemap=1', 'top' );
        add_rewrite_rule( '^' . preg_quote( $base_name, '#' ) . '\.xml$', 'index.php?aap_sitemap=1', 'top' );
        add_rewrite_rule( '^sitemap\.xml$', 'index.php?aap_sitemap=1', 'top' );
        add_rewrite_rule( '^sitemap-xml\.xml$', 'index.php?aap_sitemap=1', 'top' );
    }

    public static function add_query_vars( $vars ) {
        $vars[] = 'aap_sitemap';
        return $vars;
    }

    public static function check_request_sitemap( $wp ) {
        if ( isset( $wp->query_vars['aap_sitemap'] ) || isset( $_GET['aap_sitemap'] ) ) {
            self::render_sitemap();
        }

        $uri  = isset( $_SERVER['REQUEST_URI'] ) ? strtok( $_SERVER['REQUEST_URI'], '?' ) : '';
        $slug = get_option( 'aap_sitemap_slug', 'sitemap.xml' );
        $slug_clean = trim( $slug, '/' );

        if ( ! empty( $uri ) ) {
            $path_clean = trim( $uri, '/' );
            if ( $path_clean === $slug_clean || $path_clean === 'sitemap.xml' || $path_clean === 'sitemap-xml.xml' ) {
                self::render_sitemap();
            }
        }
    }

    public static function render_sitemap() {
        // Prevent duplicate execution if called from both parse_request & template_redirect
        static $rendered = false;
        if ( $rendered ) return;

        $uri  = isset( $_SERVER['REQUEST_URI'] ) ? strtok( $_SERVER['REQUEST_URI'], '?' ) : '';
        $slug = get_option( 'aap_sitemap_slug', 'sitemap.xml' );
        $slug_clean = trim( $slug, '/' );
        $path_clean = trim( $uri, '/' );

        $is_sitemap_req = ( get_query_var( 'aap_sitemap' ) === '1' ) ||
                          isset( $_GET['aap_sitemap'] ) ||
                          ( $path_clean === $slug_clean ) ||
                          ( $path_clean === 'sitemap.xml' ) ||
                          ( $path_clean === 'sitemap-xml.xml' );

        if ( ! $is_sitemap_req ) return;

        $rendered = true;

        // Clean all previous output buffers to avoid blank XML or "text declaration not at start of entity" errors
        while ( ob_get_level() > 0 ) {
            @ob_end_clean();
        }

        status_header( 200 );
        header( 'Content-Type: text/xml; charset=utf-8' );
        header( 'X-Robots-Tag: noindex, follow', true );

        // Options & Priorities
        $enable_home   = get_option( 'aap_sitemap_enable_home', '1' ) === '1';
        $home_priority = get_option( 'aap_sitemap_priority_home', '1.0' );
        $home_freq     = get_option( 'aap_sitemap_changefreq_home', 'daily' );

        $enable_posts  = get_option( 'aap_sitemap_enable_posts', '1' ) === '1';
        $post_priority = get_option( 'aap_sitemap_priority_post', '0.8' );
        $post_freq     = get_option( 'aap_sitemap_changefreq_post', 'daily' );

        $enable_pages  = get_option( 'aap_sitemap_enable_pages', '1' ) === '1';
        $page_priority = get_option( 'aap_sitemap_priority_page', '0.6' );
        $page_freq     = get_option( 'aap_sitemap_changefreq_page', 'weekly' );

        $enable_cats   = get_option( 'aap_sitemap_enable_cats', '1' ) === '1';
        $cat_priority  = get_option( 'aap_sitemap_priority_cat', '0.5' );
        $cat_freq      = get_option( 'aap_sitemap_changefreq_cat', 'weekly' );

        $enable_tags   = get_option( 'aap_sitemap_enable_tags', '1' ) === '1';
        $tag_priority  = get_option( 'aap_sitemap_priority_tag', '0.4' );
        $tag_freq      = get_option( 'aap_sitemap_changefreq_tag', 'monthly' );

        $include_imgs  = get_option( 'aap_sitemap_include_images', '1' ) === '1';

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<?xml-stylesheet type="text/xsl" href="' . esc_url( AAP_PLUGIN_URL . 'admin/sitemap-style.xsl' ) . '"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"';
        if ( $include_imgs ) {
            $xml .= ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"';
        }
        $xml .= '>' . "\n";

        // 1. Homepage Entry (Site URL)
        if ( $enable_home ) {
            $xml .= '  <url>' . "\n";
            $xml .= '    <loc>' . esc_xml( home_url( '/' ) ) . '</loc>' . "\n";
            $xml .= '    <lastmod>' . date( 'c', strtotime( get_lastpostmodified( 'GMT' ) ?: current_time('mysql') ) ) . '</lastmod>' . "\n";
            $xml .= '    <changefreq>' . esc_xml( $home_freq ) . '</changefreq>' . "\n";
            $xml .= '    <priority>' . esc_xml( $home_priority ) . '</priority>' . "\n";
            $xml .= '  </url>' . "\n";
        }

        // 2. Posts Entries
        if ( $enable_posts ) {
            $posts = get_posts( [
                'numberposts' => 500,
                'post_type'   => 'post',
                'post_status' => 'publish',
                'orderby'     => 'date',
                'order'       => 'DESC',
            ] );

            foreach ( $posts as $p ) {
                $permalink = get_permalink( $p->ID );
                $lastmod   = date( 'c', strtotime( $p->post_modified_gmt ) );

                $xml .= '  <url>' . "\n";
                $xml .= '    <loc>' . esc_xml( $permalink ) . '</loc>' . "\n";
                $xml .= '    <lastmod>' . esc_xml( $lastmod ) . '</lastmod>' . "\n";
                $xml .= '    <changefreq>' . esc_xml( $post_freq ) . '</changefreq>' . "\n";
                $xml .= '    <priority>' . esc_xml( $post_priority ) . '</priority>' . "\n";

                if ( $include_imgs && has_post_thumbnail( $p->ID ) ) {
                    $thumb_url = wp_get_attachment_image_url( get_post_thumbnail_id( $p->ID ), 'full' );
                    if ( $thumb_url ) {
                        $xml .= '    <image:image>' . "\n";
                        $xml .= '      <image:loc>' . esc_xml( $thumb_url ) . '</image:loc>' . "\n";
                        $xml .= '      <image:title>' . esc_xml( $p->post_title ) . '</image:title>' . "\n";
                        $xml .= '    </image:image>' . "\n";
                    }
                }

                $xml .= '  </url>' . "\n";
            }
        }

        // 3. Pages Entries
        if ( $enable_pages ) {
            $pages = get_posts( [
                'numberposts' => 200,
                'post_type'   => 'page',
                'post_status' => 'publish',
                'orderby'     => 'date',
                'order'       => 'DESC',
            ] );

            foreach ( $pages as $page ) {
                $xml .= '  <url>' . "\n";
                $xml .= '    <loc>' . esc_xml( get_permalink( $page->ID ) ) . '</loc>' . "\n";
                $xml .= '    <lastmod>' . date( 'c', strtotime( $page->post_modified_gmt ) ) . '</lastmod>' . "\n";
                $xml .= '    <changefreq>' . esc_xml( $page_freq ) . '</changefreq>' . "\n";
                $xml .= '    <priority>' . esc_xml( $page_priority ) . '</priority>' . "\n";
                $xml .= '  </url>' . "\n";
            }
        }

        // 4. Categories Entries
        if ( $enable_cats ) {
            $categories = get_categories( [ 'hide_empty' => true ] );
            foreach ( $categories as $cat ) {
                $xml .= '  <url>' . "\n";
                $xml .= '    <loc>' . esc_xml( get_category_link( $cat->term_id ) ) . '</loc>' . "\n";
                $xml .= '    <changefreq>' . esc_xml( $cat_freq ) . '</changefreq>' . "\n";
                $xml .= '    <priority>' . esc_xml( $cat_priority ) . '</priority>' . "\n";
                $xml .= '  </url>' . "\n";
            }
        }

        // 5. Tags Entries
        if ( $enable_tags ) {
            $tags = get_tags( [ 'hide_empty' => true ] );
            foreach ( $tags as $tag ) {
                $xml .= '  <url>' . "\n";
                $xml .= '    <loc>' . esc_xml( get_tag_link( $tag->term_id ) ) . '</loc>' . "\n";
                $xml .= '    <changefreq>' . esc_xml( $tag_freq ) . '</changefreq>' . "\n";
                $xml .= '    <priority>' . esc_xml( $tag_priority ) . '</priority>' . "\n";
                $xml .= '  </url>' . "\n";
            }
        }

        $xml .= '</urlset>';

        echo $xml;
        exit;
    }

    public static function ping_search_engines( $post_id ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

        $sitemap_url = home_url( '/' . get_option( 'aap_sitemap_slug', 'sitemap.xml' ) );

        // Ping Google
        if ( get_option( 'aap_sitemap_auto_ping_google', '1' ) === '1' ) {
            wp_remote_get( 'https://www.google.com/ping?sitemap=' . urlencode( $sitemap_url ), [ 'timeout' => 5 ] );
        }

        // Ping Bing
        if ( get_option( 'aap_sitemap_auto_ping_bing', '1' ) === '1' ) {
            wp_remote_get( 'https://www.bing.com/ping?sitemap=' . urlencode( $sitemap_url ), [ 'timeout' => 5 ] );
        }
    }
}
