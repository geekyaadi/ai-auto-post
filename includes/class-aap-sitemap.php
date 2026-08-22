<?php
/**
 * XML Sitemap Manager & Controller Engine for AI Auto Post
 * Supports RankMath/Yoast Style Modular XML Sitemap Index Architecture:
 * - sitemap.xml / sitemap_index.xml (Master Index)
 * - post-sitemap.xml (Posts)
 * - page-sitemap.xml (Pages)
 * - category-sitemap.xml (Categories)
 * - tag-sitemap.xml (Tags)
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

        add_rewrite_rule( '^' . preg_quote( $slug_clean, '#' ) . '$', 'index.php?aap_sitemap=index', 'top' );
        add_rewrite_rule( '^sitemap_index\.xml$', 'index.php?aap_sitemap=index', 'top' );
        add_rewrite_rule( '^sitemap\.xml$', 'index.php?aap_sitemap=index', 'top' );
        add_rewrite_rule( '^post-sitemap\.xml$', 'index.php?aap_sitemap=posts', 'top' );
        add_rewrite_rule( '^page-sitemap\.xml$', 'index.php?aap_sitemap=pages', 'top' );
        add_rewrite_rule( '^category-sitemap\.xml$', 'index.php?aap_sitemap=categories', 'top' );
        add_rewrite_rule( '^tag-sitemap\.xml$', 'index.php?aap_sitemap=tags', 'top' );
    }

    public static function add_query_vars( $vars ) {
        $vars[] = 'aap_sitemap';
        return $vars;
    }

    public static function check_request_sitemap( $wp ) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        if ( isset( $wp->query_vars['aap_sitemap'] ) || isset( $_GET['aap_sitemap'] ) ) {
            self::render_sitemap();
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $uri = isset( $_SERVER['REQUEST_URI'] ) ? strtok( $_SERVER['REQUEST_URI'], '?' ) : '';
        if ( empty( $uri ) ) return;

        $path_clean = trim( $uri, '/' );
        $slug = get_option( 'aap_sitemap_slug', 'sitemap.xml' );
        $slug_clean = trim( $slug, '/' );

        $valid_paths = [
            $slug_clean,
            'sitemap.xml',
            'sitemap_index.xml',
            'post-sitemap.xml',
            'page-sitemap.xml',
            'category-sitemap.xml',
            'tag-sitemap.xml',
        ];

        if ( in_array( $path_clean, $valid_paths, true ) ) {
            self::render_sitemap();
        }
    }

    public static function render_sitemap() {
        static $rendered = false;
        if ( $rendered ) return;

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $uri        = isset( $_SERVER['REQUEST_URI'] ) ? strtok( $_SERVER['REQUEST_URI'], '?' ) : '';
        $path_clean = trim( $uri, '/' );
        $slug       = get_option( 'aap_sitemap_slug', 'sitemap.xml' );
        $slug_clean = trim( $slug, '/' );

        $type = get_query_var( 'aap_sitemap' );
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        if ( empty( $type ) && isset( $_GET['aap_sitemap'] ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $type = sanitize_text_field( $_GET['aap_sitemap'] );
        }

        if ( empty( $type ) ) {
            if ( $path_clean === 'post-sitemap.xml' ) $type = 'posts';
            elseif ( $path_clean === 'page-sitemap.xml' ) $type = 'pages';
            elseif ( $path_clean === 'category-sitemap.xml' ) $type = 'categories';
            elseif ( $path_clean === 'tag-sitemap.xml' ) $type = 'tags';
            elseif ( $path_clean === 'sitemap_index.xml' || $path_clean === 'sitemap.xml' || $path_clean === $slug_clean ) $type = 'index';
        }

        if ( empty( $type ) ) return;

        $rendered = true;

        // Clear output buffer
        while ( ob_get_level() > 0 ) {
            @ob_end_clean();
        }

        status_header( 200 );
        header( 'Content-Type: text/xml; charset=utf-8' );
        header( 'X-Robots-Tag: noindex, follow', true );

        $mode = get_option( 'aap_sitemap_mode', 'index' ); // 'index' or 'single'

        if ( $type === 'index' && $mode === 'index' ) {
            self::render_index_sitemap();
        } elseif ( $type === 'posts' ) {
            self::render_posts_sitemap();
        } elseif ( $type === 'pages' ) {
            self::render_pages_sitemap();
        } elseif ( $type === 'categories' ) {
            self::render_categories_sitemap();
        } elseif ( $type === 'tags' ) {
            self::render_tags_sitemap();
        } else {
            self::render_combined_sitemap();
        }

        exit;
    }

    private static function render_index_sitemap() {
        $lastmod = date( 'c', strtotime( get_lastpostmodified( 'GMT' ) ?: current_time( 'mysql' ) ) );

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<?xml-stylesheet type="text/xsl" href="' . esc_url( AAP_PLUGIN_URL . 'admin/sitemap-style.xsl' ) . '"?>' . "\n";
        echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        if ( get_option( 'aap_sitemap_enable_posts', '1' ) === '1' ) {
            echo '  <sitemap>' . "\n";
            echo '    <loc>' . esc_url( home_url( '/post-sitemap.xml' ) ) . '</loc>' . "\n";
            echo '    <lastmod>' . esc_html( $lastmod ) . '</lastmod>' . "\n";
            echo '  </sitemap>' . "\n";
        }

        if ( get_option( 'aap_sitemap_enable_pages', '1' ) === '1' ) {
            echo '  <sitemap>' . "\n";
            echo '    <loc>' . esc_url( home_url( '/page-sitemap.xml' ) ) . '</loc>' . "\n";
            echo '    <lastmod>' . esc_html( $lastmod ) . '</lastmod>' . "\n";
            echo '  </sitemap>' . "\n";
        }

        if ( get_option( 'aap_sitemap_enable_cats', '1' ) === '1' ) {
            echo '  <sitemap>' . "\n";
            echo '    <loc>' . esc_url( home_url( '/category-sitemap.xml' ) ) . '</loc>' . "\n";
            echo '    <lastmod>' . esc_html( $lastmod ) . '</lastmod>' . "\n";
            echo '  </sitemap>' . "\n";
        }

        if ( get_option( 'aap_sitemap_enable_tags', '1' ) === '1' ) {
            echo '  <sitemap>' . "\n";
            echo '    <loc>' . esc_url( home_url( '/tag-sitemap.xml' ) ) . '</loc>' . "\n";
            echo '    <lastmod>' . esc_html( $lastmod ) . '</lastmod>' . "\n";
            echo '  </sitemap>' . "\n";
        }

        echo '</sitemapindex>';
    }

    private static function render_posts_sitemap() {
        $post_priority = get_option( 'aap_sitemap_priority_post', '0.8' );
        $post_freq     = get_option( 'aap_sitemap_changefreq_post', 'daily' );
        $include_imgs  = get_option( 'aap_sitemap_include_images', '1' ) === '1';

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<?xml-stylesheet type="text/xsl" href="' . esc_url( AAP_PLUGIN_URL . 'admin/sitemap-style.xsl' ) . '"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"';
        if ( $include_imgs ) echo ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"';
        echo '>' . "\n";

        $posts = get_posts( [
            'numberposts' => 1000,
            'post_type'   => 'post',
            'post_status' => 'publish',
            'orderby'     => 'date',
            'order'       => 'DESC',
        ] );

        foreach ( $posts as $p ) {
            echo '  <url>' . "\n";
            echo '    <loc>' . esc_xml( get_permalink( $p->ID ) ) . '</loc>' . "\n";
            echo '    <lastmod>' . date( 'c', strtotime( $p->post_modified_gmt ) ) . '</lastmod>' . "\n";
            echo '    <changefreq>' . esc_xml( $post_freq ) . '</changefreq>' . "\n";
            echo '    <priority>' . esc_xml( $post_priority ) . '</priority>' . "\n";

            if ( $include_imgs && has_post_thumbnail( $p->ID ) ) {
                $thumb_url = wp_get_attachment_image_url( get_post_thumbnail_id( $p->ID ), 'full' );
                if ( $thumb_url ) {
                    echo '    <image:image>' . "\n";
                    echo '      <image:loc>' . esc_xml( $thumb_url ) . '</image:loc>' . "\n";
                    echo '      <image:title>' . esc_xml( $p->post_title ) . '</image:title>' . "\n";
                    echo '    </image:image>' . "\n";
                }
            }

            echo '  </url>' . "\n";
        }

        echo '</urlset>';
    }

    private static function render_pages_sitemap() {
        $page_priority = get_option( 'aap_sitemap_priority_page', '0.6' );
        $page_freq     = get_option( 'aap_sitemap_changefreq_page', 'weekly' );

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<?xml-stylesheet type="text/xsl" href="' . esc_url( AAP_PLUGIN_URL . 'admin/sitemap-style.xsl' ) . '"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        $pages = get_posts( [
            'numberposts' => 500,
            'post_type'   => 'page',
            'post_status' => 'publish',
            'orderby'     => 'date',
            'order'       => 'DESC',
        ] );

        foreach ( $pages as $page ) {
            echo '  <url>' . "\n";
            echo '    <loc>' . esc_xml( get_permalink( $page->ID ) ) . '</loc>' . "\n";
            echo '    <lastmod>' . date( 'c', strtotime( $page->post_modified_gmt ) ) . '</lastmod>' . "\n";
            echo '    <changefreq>' . esc_xml( $page_freq ) . '</changefreq>' . "\n";
            echo '    <priority>' . esc_xml( $page_priority ) . '</priority>' . "\n";
            echo '  </url>' . "\n";
        }

        echo '</urlset>';
    }

    private static function render_categories_sitemap() {
        $cat_priority = get_option( 'aap_sitemap_priority_cat', '0.5' );
        $cat_freq     = get_option( 'aap_sitemap_changefreq_cat', 'weekly' );

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<?xml-stylesheet type="text/xsl" href="' . esc_url( AAP_PLUGIN_URL . 'admin/sitemap-style.xsl' ) . '"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        $categories = get_categories( [ 'hide_empty' => true ] );
        foreach ( $categories as $cat ) {
            echo '  <url>' . "\n";
            echo '    <loc>' . esc_xml( get_category_link( $cat->term_id ) ) . '</loc>' . "\n";
            echo '    <changefreq>' . esc_xml( $cat_freq ) . '</changefreq>' . "\n";
            echo '    <priority>' . esc_xml( $cat_priority ) . '</priority>' . "\n";
            echo '  </url>' . "\n";
        }

        echo '</urlset>';
    }

    private static function render_tags_sitemap() {
        $tag_priority = get_option( 'aap_sitemap_priority_tag', '0.4' );
        $tag_freq     = get_option( 'aap_sitemap_changefreq_tag', 'monthly' );

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<?xml-stylesheet type="text/xsl" href="' . esc_url( AAP_PLUGIN_URL . 'admin/sitemap-style.xsl' ) . '"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        $tags = get_tags( [ 'hide_empty' => true ] );
        foreach ( $tags as $tag ) {
            echo '  <url>' . "\n";
            echo '    <loc>' . esc_xml( get_tag_link( $tag->term_id ) ) . '</loc>' . "\n";
            echo '    <changefreq>' . esc_xml( $tag_freq ) . '</changefreq>' . "\n";
            echo '    <priority>' . esc_xml( $tag_priority ) . '</priority>' . "\n";
            echo '  </url>' . "\n";
        }

        echo '</urlset>';
    }

    private static function render_combined_sitemap() {
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

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<?xml-stylesheet type="text/xsl" href="' . esc_url( AAP_PLUGIN_URL . 'admin/sitemap-style.xsl' ) . '"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"';
        if ( $include_imgs ) echo ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"';
        echo '>' . "\n";

        if ( $enable_home ) {
            echo '  <url>' . "\n";
            echo '    <loc>' . esc_xml( home_url( '/' ) ) . '</loc>' . "\n";
            echo '    <lastmod>' . date( 'c', strtotime( get_lastpostmodified( 'GMT' ) ?: current_time('mysql') ) ) . '</lastmod>' . "\n";
            echo '    <changefreq>' . esc_xml( $home_freq ) . '</changefreq>' . "\n";
            echo '    <priority>' . esc_xml( $home_priority ) . '</priority>' . "\n";
            echo '  </url>' . "\n";
        }

        if ( $enable_posts ) {
            $posts = get_posts( [ 'numberposts' => 500, 'post_type' => 'post', 'post_status' => 'publish' ] );
            foreach ( $posts as $p ) {
                echo '  <url>' . "\n";
                echo '    <loc>' . esc_xml( get_permalink( $p->ID ) ) . '</loc>' . "\n";
                echo '    <lastmod>' . date( 'c', strtotime( $p->post_modified_gmt ) ) . '</lastmod>' . "\n";
                echo '    <changefreq>' . esc_xml( $post_freq ) . '</changefreq>' . "\n";
                echo '    <priority>' . esc_xml( $post_priority ) . '</priority>' . "\n";
                echo '  </url>' . "\n";
            }
        }

        if ( $enable_pages ) {
            $pages = get_posts( [ 'numberposts' => 200, 'post_type' => 'page', 'post_status' => 'publish' ] );
            foreach ( $pages as $page ) {
                echo '  <url>' . "\n";
                echo '    <loc>' . esc_xml( get_permalink( $page->ID ) ) . '</loc>' . "\n";
                echo '    <lastmod>' . date( 'c', strtotime( $page->post_modified_gmt ) ) . '</lastmod>' . "\n";
                echo '    <changefreq>' . esc_xml( $page_freq ) . '</changefreq>' . "\n";
                echo '    <priority>' . esc_xml( $page_priority ) . '</priority>' . "\n";
                echo '  </url>' . "\n";
            }
        }

        if ( $enable_cats ) {
            $categories = get_categories( [ 'hide_empty' => true ] );
            foreach ( $categories as $cat ) {
                echo '  <url>' . "\n";
                echo '    <loc>' . esc_xml( get_category_link( $cat->term_id ) ) . '</loc>' . "\n";
                echo '    <changefreq>' . esc_xml( $cat_freq ) . '</changefreq>' . "\n";
                echo '    <priority>' . esc_xml( $cat_priority ) . '</priority>' . "\n";
                echo '  </url>' . "\n";
            }
        }

        if ( $enable_tags ) {
            $tags = get_tags( [ 'hide_empty' => true ] );
            foreach ( $tags as $tag ) {
                echo '  <url>' . "\n";
                echo '    <loc>' . esc_xml( get_tag_link( $tag->term_id ) ) . '</loc>' . "\n";
                echo '    <changefreq>' . esc_xml( $tag_freq ) . '</changefreq>' . "\n";
                echo '    <priority>' . esc_xml( $tag_priority ) . '</priority>' . "\n";
                echo '  </url>' . "\n";
            }
        }

        echo '</urlset>';
    }

    public static function ping_search_engines( $post_id ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        $sitemap_url = home_url( '/' . get_option( 'aap_sitemap_slug', 'sitemap.xml' ) );

        if ( get_option( 'aap_sitemap_auto_ping_google', '1' ) === '1' ) {
            wp_remote_get( 'https://www.google.com/ping?sitemap=' . urlencode( $sitemap_url ), [ 'timeout' => 5 ] );
        }
        if ( get_option( 'aap_sitemap_auto_ping_bing', '1' ) === '1' ) {
            wp_remote_get( 'https://www.bing.com/ping?sitemap=' . urlencode( $sitemap_url ), [ 'timeout' => 5 ] );
        }
    }
}
