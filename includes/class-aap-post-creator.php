<?php
/**
 * Handles creating WordPress posts with all generated content:
 * article, tags, thumbnail, OG image, alt text, meta description, and category.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class AAP_Post_Creator {

    /**
     * Creates a full WP post from generated content.
     *
     * @param array $data {
     *   title, article, tags[], meta_description, category, alt_text,
     *   thumbnail_data (base64+mime), og_image_data (base64+mime),
     *   author_id, post_status
     * }
     * @return int|WP_Error Post ID on success.
     */
    public static function create( array $data ) {
        $post_status = $data['post_status'] ?? get_option( 'aap_default_status', 'draft' );
        $author_id   = $data['author_id']   ?? (int) get_option( 'aap_default_author', get_current_user_id() );

        // 1. Resolve / create category
        $category_id = self::resolve_category( $data['category'] ?? '' );

        // 2. FAQ and Schema Generation
        $article_content = $data['article'];
        $schema_script   = '';

        if ( get_option( 'aap_enable_faq', 1 ) ) {
            $session_id = 'faq_' . uniqid();
            $language   = $data['language'] ?? 'English';
            $faq_count  = (int) get_option( 'aap_faq_count', 3 );
            $faq_res    = AAP_Gemini::generate_faq( $session_id, $data['title'], $language, $faq_count );

            if ( ! is_wp_error( $faq_res ) && ! empty( $faq_res['faqs'] ) ) {
                $faqs = $faq_res['faqs'];
                $faq_html = "\n\n<div class=\"aap-faq-wrapper\" style=\"margin-top: 30px; border-top: 1px solid #e2e8f0; padding-top: 20px;\">";
                $faq_html .= "<h3 class=\"aap-faq-heading\" style=\"margin-bottom: 15px; font-weight: 700; color: #1e293b;\">💡 Frequently Asked Questions</h3>";
                $faq_html .= "<div class=\"aap-faq-list\">";

                $schema_questions = [];
                foreach ( $faqs as $faq ) {
                    $q = esc_html( $faq['question'] ?? '' );
                    $a = esc_html( $faq['answer'] ?? '' );
                    if ( empty($q) || empty($a) ) continue;

                    $faq_html .= "<details class=\"aap-faq-item\" style=\"border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 10px; padding: 12px 16px; background: #f8fafc;\">";
                    $faq_html .= "<summary class=\"aap-faq-question\" style=\"font-weight: 600; color: #0f172a; cursor: pointer; outline: none;\">" . $q . "</summary>";
                    $faq_html .= "<div class=\"aap-faq-answer\" style=\"margin-top: 8px; color: #475569; line-height: 1.6;\">" . $a . "</div>";
                    $faq_html .= "</details>";

                    $schema_questions[] = [
                        '@type' => 'Question',
                        'name' => $q,
                        'acceptedAnswer' => [
                            '@type' => 'Answer',
                            'text' => $a
                        ]
                    ];
                }
                $faq_html .= "</div></div>";

                if ( ! empty( $schema_questions ) ) {
                    $schema_json = json_encode( [
                        '@context' => 'https://schema.org',
                        '@type' => 'FAQPage',
                        'mainEntity' => $schema_questions
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

                    $schema_script = "\n<script type=\"application/ld+json\">" . $schema_json . "</script>";
                }

                $article_content .= $faq_html;
            }
        }

        $sanitized_content = wp_kses_post( $article_content );
        if ( ! empty( $schema_script ) ) {
            $sanitized_content .= $schema_script;
        }

        // 3. Insert post
        $post_id = wp_insert_post( [
            'post_title'   => sanitize_text_field( $data['title'] ),
            'post_content' => $sanitized_content,
            'post_status'  => $post_status,
            'post_author'  => $author_id,
            'post_category'=> $category_id ? [ $category_id ] : [],
        ], true );

        if ( is_wp_error( $post_id ) ) return $post_id;

        // Apply Auto-Internal Linking
        if ( get_option( 'aap_enable_internal_linking', 0 ) ) {
            $linked_content = self::apply_internal_linking( $post_id, $data['article'] );
            if ( $linked_content !== $data['article'] ) {
                wp_update_post( [
                    'ID'           => $post_id,
                    'post_content' => wp_kses_post( $linked_content ),
                ] );
            }
        }

        // 3. Tags
        if ( ! empty( $data['tags'] ) ) {
            wp_set_post_tags( $post_id, $data['tags'], false );
        }

        // 4. Meta description (Yoast, RankMath, fallback custom field)
        if ( ! empty( $data['meta_description'] ) ) {
            self::set_meta_description( $post_id, $data['meta_description'] );
        }

        // 5. Thumbnail
        if ( ! empty( $data['thumbnail_data'] ) ) {
            $attachment_id = self::upload_image(
                $data['thumbnail_data']['base64'],
                $data['thumbnail_data']['mime_type'],
                $data['title'] . '-thumbnail',
                $data['alt_text'] ?? $data['title']
            );
            if ( ! is_wp_error( $attachment_id ) ) {
                set_post_thumbnail( $post_id, $attachment_id );
            }
        }

        // 6. OG Image (stored as custom field)
        if ( ! empty( $data['og_image_data'] ) ) {
            $og_id = self::upload_image(
                $data['og_image_data']['base64'],
                $data['og_image_data']['mime_type'],
                $data['title'] . '-og-image',
                $data['title'] . ' social share image'
            );
            if ( ! is_wp_error( $og_id ) ) {
                update_post_meta( $post_id, '_aap_og_image_id', $og_id );
                $og_url = wp_get_attachment_url( $og_id );
                // Yoast
                update_post_meta( $post_id, '_yoast_wpseo_opengraph-image', $og_url );
                // RankMath
                update_post_meta( $post_id, 'rank_math_facebook_image', $og_url );
            }
        }

        // 7. Mark as AI-generated
        update_post_meta( $post_id, '_aap_generated', true );
        update_post_meta( $post_id, '_aap_generated_at', current_time( 'mysql' ) );

        // 8. Auto-Comment Generation
        self::generate_auto_comments( $post_id, $data['title'] );

        return $post_id;
    }

    // -------------------------------------------------------------------------
    // Resolve / create WP category
    // -------------------------------------------------------------------------

    private static function resolve_category( string $category_name ) {
        if ( empty( $category_name ) ) return null;

        // Try exact match first
        $term = get_term_by( 'name', $category_name, 'category' );
        if ( $term ) return $term->term_id;

        // Try case-insensitive match
        $all = get_categories( [ 'hide_empty' => false ] );
        foreach ( $all as $cat ) {
            if ( strtolower( $cat->name ) === strtolower( $category_name ) ) {
                return $cat->term_id;
            }
        }

        // Create new category
        $result = wp_insert_term( sanitize_text_field( $category_name ), 'category' );
        if ( is_wp_error( $result ) ) return null;
        return $result['term_id'];
    }

    // -------------------------------------------------------------------------
    // Meta description — supports Yoast, RankMath, and custom fallback
    // -------------------------------------------------------------------------

    private static function set_meta_description( int $post_id, string $meta ) {
        $meta = sanitize_text_field( $meta );
        // Yoast SEO
        update_post_meta( $post_id, '_yoast_wpseo_metadesc', $meta );
        // RankMath
        update_post_meta( $post_id, 'rank_math_description', $meta );
        // Generic fallback
        update_post_meta( $post_id, '_aap_meta_description', $meta );
    }

    // -------------------------------------------------------------------------
    // Upload base64 image to WP media library
    // -------------------------------------------------------------------------

    public static function upload_image( string $base64, string $mime_type, string $filename_base, string $alt_text = '' ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $ext      = self::mime_to_ext( $mime_type );
        $filename = sanitize_file_name( $filename_base . '.' . $ext );

        $decoded = base64_decode( $base64 );
        if ( ! $decoded ) {
            return new WP_Error( 'decode_failed', 'Failed to decode image data.' );
        }

        // Apply Custom Text Overlay on Thumbnails if enabled
        if ( get_option( 'aap_enable_text_overlay', 0 ) && strpos( $filename_base, '-thumbnail' ) !== false ) {
            $image = @imagecreatefromstring( $decoded );
            if ( $image ) {
                $overlay_text = $alt_text ?: get_bloginfo( 'name' );
                $image = self::draw_text_overlay( $image, $overlay_text );

                ob_start();
                if ( $mime_type === 'image/png' ) {
                    imagepng( $image );
                } else {
                    imagejpeg( $image, null, 90 );
                }
                $new_decoded = ob_get_clean();
                if ( $new_decoded ) {
                    $decoded = $new_decoded;
                }
                imagedestroy( $image );
            }
        }

        // Write to temp file
        $tmp = wp_tempnam( $filename );
        file_put_contents( $tmp, $decoded );

        $file_array = [
            'name'     => $filename,
            'tmp_name' => $tmp,
            'type'     => $mime_type,
            'error'    => 0,
            'size'     => filesize( $tmp ),
        ];

        $attachment_id = media_handle_sideload( $file_array, 0, $filename_base );

        @unlink( $tmp );

        if ( is_wp_error( $attachment_id ) ) return $attachment_id;

        // Set alt text
        if ( $alt_text ) {
            update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $alt_text ) );
        }

        return $attachment_id;
    }

    private static function mime_to_ext( string $mime ) {
        $map = [
            'image/png'  => 'png',
            'image/jpeg' => 'jpg',
            'image/jpg'  => 'jpg',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
        ];
        return $map[ $mime ] ?? 'png';
    }

    // -------------------------------------------------------------------------
    // Advanced SEO: Auto-Internal Linking & Comments
    // -------------------------------------------------------------------------

    private static function apply_internal_linking( int $post_id, string $content ) {
        if ( ! get_option( 'aap_enable_internal_linking', 0 ) ) {
            return $content;
        }

        $max_links = (int) get_option( 'aap_max_internal_links', 3 );
        if ( $max_links <= 0 ) {
            return $content;
        }

        $posts = get_posts( [
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => 100,
            'exclude'        => [ $post_id ],
        ] );

        if ( empty( $posts ) ) {
            return $content;
        }

        $link_count = 0;
        foreach ( $posts as $p ) {
            if ( $link_count >= $max_links ) {
                break;
            }

            $title = html_entity_decode( $p->post_title, ENT_QUOTES, 'UTF-8' );
            if ( strlen( $title ) < 4 ) {
                continue;
            }

            $url = get_permalink( $p->ID );
            if ( ! $url ) {
                continue;
            }

            $pattern = '/<a[^>]*>.*?<\/a>|<[^>]+>|(?:\b)(' . preg_quote( $title, '/' ) . ')(?:\b)/isu';
            $replaced = false;

            $content = preg_replace_callback( $pattern, function( $matches ) use ( &$link_count, &$replaced, $max_links, $url ) {
                if ( isset( $matches[1] ) && $matches[1] !== '' ) {
                    if ( $link_count < $max_links && ! $replaced ) {
                        $link_count++;
                        $replaced = true;
                        return '<a href="' . esc_url( $url ) . '">' . esc_html( $matches[1] ) . '</a>';
                    }
                }
                return $matches[0];
            }, $content );
        }

        return $content;
    }

    private static function generate_auto_comments( int $post_id, string $title ) {
        if ( ! get_option( 'aap_enable_comments', 0 ) ) {
            return;
        }

        $count = (int) get_option( 'aap_comments_count', 2 );
        if ( $count <= 0 ) {
            return;
        }

        $session_id = 'comments_' . uniqid();
        $result = AAP_Gemini::generate_comments( $session_id, $title, $count );

        if ( is_wp_error( $result ) || empty( $result['comments'] ) ) {
            return;
        }

        foreach ( $result['comments'] as $c ) {
            $name    = sanitize_text_field( $c['name'] ?? '' );
            $email   = sanitize_email( $c['email'] ?? 'user@example.com' );
            $content = sanitize_textarea_field( $c['comment'] ?? '' );

            if ( $name && $content ) {
                wp_insert_comment( [
                    'comment_post_ID'      => $post_id,
                    'comment_author'       => $name,
                    'comment_author_email' => $email,
                    'comment_content'      => $content,
                    'comment_type'         => 'comment',
                    'comment_approved'     => 1,
                    'comment_date'         => current_time( 'mysql' ),
                ] );
            }
        }
    }

    private static function draw_text_overlay( $image, string $text ) {
        if ( ! function_exists( 'imagettftext' ) || ! function_exists( 'imagettfbbox' ) ) {
            return $image;
        }

        $font_size = (int) get_option( 'aap_overlay_font_size', 24 );
        $text_color_hex = get_option( 'aap_overlay_color', '#ffffff' );
        $bg_color_hex   = get_option( 'aap_overlay_bg_color', '#000000' );
        $bg_opacity     = (int) get_option( 'aap_overlay_bg_opacity', 60 );
        $position       = get_option( 'aap_overlay_position', 'bottom' );

        $font_path = AAP_PLUGIN_DIR . 'admin/fonts/Roboto-Bold.ttf';
        if ( ! file_exists( $font_path ) ) {
            return $image;
        }

        $w = imagesx( $image );
        $h = imagesy( $image );

        $hex_to_rgb = function( $hex ) {
            $hex = ltrim( $hex, '#' );
            if ( strlen( $hex ) === 6 ) {
                list( $r, $g, $b ) = [ hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) ];
            } else {
                list( $r, $g, $b ) = [ 255, 255, 255 ];
            }
            return [ $r, $g, $b ];
        };

        list( $tr, $tg, $tb ) = $hex_to_rgb( $text_color_hex );
        list( $br, $bg, $bb ) = $hex_to_rgb( $bg_color_hex );

        $text_color = imagecolorallocate( $image, $tr, $tg, $tb );
        
        $gd_alpha = (int) ( ( 100 - $bg_opacity ) * 1.27 );
        $bg_color = imagecolorallocatealpha( $image, $br, $bg, $bb, $gd_alpha );

        $max_width = (int) ( $w * 0.9 );
        $words = explode( ' ', $text );
        $lines = [];
        $current_line = '';

        foreach ( $words as $word ) {
            $test_line = $current_line === '' ? $word : $current_line . ' ' . $word;
            $box = imagettfbbox( $font_size, 0, $font_path, $test_line );
            $line_width = $box[2] - $box[0];

            if ( $line_width > $max_width ) {
                if ( $current_line !== '' ) {
                    $lines[] = $current_line;
                    $current_line = $word;
                } else {
                    $lines[] = $test_line;
                    $current_line = '';
                }
            } else {
                $current_line = $test_line;
            }
        }
        if ( $current_line !== '' ) {
            $lines[] = $current_line;
        }

        $line_height = (int) ( $font_size * 1.4 );
        $total_text_height = count( $lines ) * $line_height;
        $padding = 20;
        $banner_height = $total_text_height + ( $padding * 2 );

        if ( $position === 'top' ) {
            $by1 = 0;
            $by2 = $banner_height;
            $ty = $padding + $font_size;
        } elseif ( $position === 'center' ) {
            $by1 = (int) ( ( $h - $banner_height ) / 2 );
            $by2 = $by1 + $banner_height;
            $ty = $by1 + $padding + $font_size;
        } else {
            $by1 = $h - $banner_height;
            $by2 = $h;
            $ty = $by1 + $padding + $font_size;
        }

        imagefilledrectangle( $image, 0, $by1, $w, $by2, $bg_color );

        foreach ( $lines as $line ) {
            $box = imagettfbbox( $font_size, 0, $font_path, $line );
            $line_width = $box[2] - $box[0];
            $tx = (int) ( ( $w - $line_width ) / 2 );

            imagettftext( $image, $font_size, 0, $tx, $ty, $text_color, $font_path, $line );
            $ty += $line_height;
        }

        return $image;
    }
}
