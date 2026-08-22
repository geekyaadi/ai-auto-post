<?php
/**
 * Global Site-Wide Speed Optimizer Engine for AI Auto Post
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class AAP_Speed_Optimizer {

    public static function init() {
        // 1. Global Image & Iframe Lazy Loading across ALL site posts & pages
        if ( get_option( 'aap_speed_lazy_loading', '1' ) === '1' ) {
            add_filter( 'the_content', [ __CLASS__, 'apply_global_lazy_loading' ], 99 );
            add_filter( 'post_thumbnail_html', [ __CLASS__, 'apply_featured_image_priority' ], 99 );
        }

        // 2. Global Site-Wide Resource Hints & Font Preloading
        if ( get_option( 'aap_speed_preload_assets', '1' ) === '1' ) {
            add_action( 'wp_head', [ __CLASS__, 'inject_resource_hints' ], 1 );
        }

        // 3. Defer Non-Critical JavaScript Site-Wide
        if ( get_option( 'aap_speed_defer_js', '1' ) === '1' ) {
            add_filter( 'script_loader_tag', [ __CLASS__, 'defer_non_critical_scripts' ], 10, 3 );
        }

        // 4. Global WebP Upload Handler (Converts ANY uploaded image site-wide to WebP)
        if ( get_option( 'aap_speed_webp_compression', '1' ) === '1' ) {
            add_filter( 'wp_handle_upload', [ __CLASS__, 'convert_uploaded_image_to_webp' ], 10, 2 );
        }
    }

    /**
     * Applies lazy loading to all <img> and <iframe> elements across the entire website
     */
    public static function apply_global_lazy_loading( $content ) {
        if ( is_admin() || empty( $content ) ) return $content;

        // Add loading="lazy" and decoding="async" to images without loading attribute
        $content = preg_replace( '/<img(?![^>]*loading=)/i', '<img loading="lazy" decoding="async"', $content );

        // Add loading="lazy" to iframes without loading attribute
        $content = preg_replace( '/<iframe(?![^>]*loading=)/i', '<iframe loading="lazy"', $content );

        return $content;
    }

    /**
     * Boosts LCP by setting fetchpriority="high" on top featured images
     */
    public static function apply_featured_image_priority( $html ) {
        if ( is_admin() || empty( $html ) ) return $html;
        if ( strpos( $html, 'fetchpriority' ) === false ) {
            $html = str_replace( '<img ', '<img fetchpriority="high" decoding="async" ', $html );
        }
        return $html;
    }

    /**
     * Injects DNS Prefetch, Preconnect, and Font Preload tags in <head>
     */
    public static function inject_resource_hints() {
        echo '<!-- AI Auto Post Speed Turbo Hints -->' . "\n";
        echo '<link rel="dns-prefetch" href="//fonts.googleapis.com">' . "\n";
        echo '<link rel="dns-prefetch" href="//fonts.gstatic.com">' . "\n";
        echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
    }

    /**
     * Adds defer attribute to non-essential scripts on frontend
     */
    public static function defer_non_critical_scripts( $tag, $handle, $src ) {
        if ( is_admin() ) return $tag;

        // Skip jQuery core and essential scripts
        if ( strpos( $handle, 'jquery-core' ) !== false || strpos( $handle, 'jquery-migrate' ) !== false ) {
            return $tag;
        }

        if ( strpos( $tag, 'defer' ) === false && strpos( $tag, 'async' ) === false ) {
            return str_replace( ' src=', ' defer src=', $tag );
        }

        return $tag;
    }

    /**
     * Auto converts any uploaded image on WordPress to WebP format
     */
    public static function convert_uploaded_image_to_webp( $upload, $context ) {
        if ( ! function_exists( 'imagewebp' ) ) return $upload;
        if ( empty( $upload['type'] ) || strpos( $upload['type'], 'image' ) === false ) return $upload;
        if ( $upload['type'] === 'image/webp' ) return $upload;

        $file_path = $upload['file'];
        if ( ! file_exists( $file_path ) ) return $upload;

        $mime = $upload['type'];
        $image = null;

        if ( $mime === 'image/jpeg' || $mime === 'image/jpg' ) {
            $image = @imagecreatefromjpeg( $file_path );
        } elseif ( $mime === 'image/png' ) {
            $image = @imagecreatefrompng( $file_path );
            if ( $image ) {
                imagepalettetotruecolor( $image );
                imagealphablending( $image, true );
                imagesavealpha( $image, true );
            }
        }

        if ( $image ) {
            $webp_path = preg_replace( '/\.(jpe?g|png)$/i', '.webp', $file_path );
            if ( imagewebp( $image, $webp_path, 82 ) ) {
                imagedestroy( $image );
                @wp_delete_file( $file_path );

                $upload['file'] = $webp_path;
                $upload['url']  = preg_replace( '/\.(jpe?g|png)$/i', '.webp', $upload['url'] );
                $upload['type'] = 'image/webp';
            }
        }

        return $upload;
    }
}
