<?php
/**
 * PHP GD Text-to-Image Generator for AI Auto Post
 * Generates custom featured images/thumbnails locally using PHP's GD library.
 *
 * Author: Anand Soni
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class AAP_Text_To_Image {

    /**
     * Available gradients for background.
     */
    public static function get_gradients() {
        return [
            'blue_purple'   => [ 'label' => 'Blue to Purple 🟣',   'from' => '#4f46e5', 'to' => '#7c3aed' ],
            'orange_red'    => [ 'label' => 'Sunset Crimson 🌅',  'from' => '#ea580c', 'to' => '#dc2626' ],
            'emerald_teal'  => [ 'label' => 'Emerald Teal 🟢',     'from' => '#059669', 'to' => '#0d9488' ],
            'deep_ocean'    => [ 'label' => 'Deep Ocean 🔵',      'from' => '#1e3a8a', 'to' => '#0f172a' ],
            'sunset_violet' => [ 'label' => 'Sunset Violet 🌆',   'from' => '#db2777', 'to' => '#4c1d95' ],
            'dark_charcoal' => [ 'label' => 'Dark Charcoal ⚫',   'from' => '#374151', 'to' => '#111827' ],
            'midnight_neon' => [ 'label' => 'Midnight Neon 🌌',   'from' => '#0f172a', 'to' => '#06b6d4' ],
            'rose_gold'     => [ 'label' => 'Rose Gold 🌸',       'from' => '#ec4899', 'to' => '#f59e0b' ],
            'forest_aurora' => [ 'label' => 'Forest Aurora 🌲',   'from' => '#064e3b', 'to' => '#10b981' ],
            'cyberpunk_red' => [ 'label' => 'Cyberpunk Red ⚡',   'from' => '#312e81', 'to' => '#f43f5e' ],
            'tropical_mango' => [ 'label' => 'Tropical Mango 🥭',  'from' => '#f59e0b', 'to' => '#ef4444' ],
        ];
    }

    /**
     * Available solid colors for background.
     */
    public static function get_solid_colors() {
        return [
            'dark_slate'    => [ 'label' => 'Dark Slate 🔘',  'color' => '#1e293b' ],
            'pure_black'    => [ 'label' => 'Pure Black ⬛',  'color' => '#000000' ],
            'royal_blue'    => [ 'label' => 'Royal Blue 🔵',  'color' => '#1d4ed8' ],
            'emerald_green' => [ 'label' => 'Emerald Green 🟢', 'color' => '#047857' ],
            'rich_purple'   => [ 'label' => 'Rich Purple 🟣',  'color' => '#6d28d9' ],
            'crimson_red'   => [ 'label' => 'Crimson Red 🔴',  'color' => '#b91c1c' ],
            'warm_cream'    => [ 'label' => 'Warm Cream 🍦',   'color' => '#fef08a' ],
            'soft_grey'     => [ 'label' => 'Soft Grey ⚪',    'color' => '#f1f5f9' ],
        ];
    }

    /**
     * Check if TTF rendering actually works with a given font path.
     */
    private static function ttf_works( $font_path ) {
        if ( ! function_exists( 'imagettfbbox' ) ) {
            return false;
        }
        $bbox = @imagettfbbox( 20, 0, $font_path, 'Test' );
        return is_array( $bbox ) && isset( $bbox[2] );
    }

    /**
     * Get a working font path. Tries bundled fonts, then Windows system fonts.
     */
    private static function get_font_path() {
        // Try bundled Poppins Bold (proper static bold — best for thumbnails)
        $font = AAP_PLUGIN_DIR . 'admin/fonts/Poppins-Bold.ttf';
        if ( file_exists( $font ) && self::ttf_works( $font ) ) {
            return $font;
        }

        // Try bundled Roboto Bold
        $font = AAP_PLUGIN_DIR . 'admin/fonts/Roboto-Bold.ttf';
        if ( file_exists( $font ) && self::ttf_works( $font ) ) {
            return $font;
        }

        // Try bundled Outfit Bold (variable font — may render thin)
        $font = AAP_PLUGIN_DIR . 'admin/fonts/Outfit-Bold.ttf';
        if ( file_exists( $font ) && self::ttf_works( $font ) ) {
            return $font;
        }

        // Try Windows system fonts
        $sys_fonts = [
            'C:/Windows/Fonts/arialbd.ttf',
            'C:/Windows/Fonts/arial.ttf',
            'C:/Windows/Fonts/calibrib.ttf',
            'C:/Windows/Fonts/segoeui.ttf',
            'C:/Windows/Fonts/verdana.ttf',
        ];
        foreach ( $sys_fonts as $sf ) {
            if ( file_exists( $sf ) && self::ttf_works( $sf ) ) {
                return $sf;
            }
        }

        // Try 'arial' as bare name (some GD builds resolve this)
        if ( self::ttf_works( 'arial' ) ) {
            return 'arial';
        }

        return false; // No TTF support
    }

    /**
     * Local text to image generator.
     *
     * @param string $text      The text overlay (post title)
     * @param string $bg_type   'gradient' or 'solid'
     * @param string $bg_val    Gradient key or solid color key
     * @param string $size_val  '500x500', '1000x1000', '1200x630', or '600x315'
     * @return array|false      Returns array ['base64' => '...', 'mime_type' => 'image/png'] or false.
     */
    public static function generate( string $text, string $bg_type, string $bg_val, string $size_val = '1200x630' ) {
        // Parse size
        $dims = explode( 'x', $size_val );
        $width  = isset( $dims[0] ) ? (int) $dims[0] : 1200;
        $height = isset( $dims[1] ) ? (int) $dims[1] : 630;

        // Verify GD extension
        if ( ! extension_loaded( 'gd' ) ) {
            return false;
        }

        // Resolve "mix" background to a random choice
        if ( $bg_type === 'mix' ) {
            $choices = [ 'gradient', 'solid' ];
            if ( file_exists( AAP_PLUGIN_DIR . 'admin/default-thumbnail.jpg' ) ) {
                $choices[] = 'image';
            }
            $bg_type = $choices[ array_rand( $choices ) ];
            
            if ( $bg_type === 'gradient' ) {
                $bg_val = array_rand( self::get_gradients() );
            } elseif ( $bg_type === 'solid' ) {
                $bg_val = array_rand( self::get_solid_colors() );
            }
        }

        // Create canvas and draw background
        if ( $bg_type === 'image' ) {
            $bg_img_path = AAP_PLUGIN_DIR . 'admin/default-thumbnail.jpg';
            $loaded = false;
            if ( file_exists( $bg_img_path ) ) {
                $src_im = @imagecreatefromjpeg( $bg_img_path );
                if ( $src_im ) {
                    $im = imagecreatetruecolor( $width, $height );
                    if ( $im ) {
                        list( $src_w, $src_h ) = getimagesize( $bg_img_path );
                        imagecopyresampled( $im, $src_im, 0, 0, 0, 0, $width, $height, $src_w, $src_h );
                        imagedestroy( $src_im );
                        $loaded = true;
                    }
                }
            }
            if ( ! $loaded ) {
                $im = imagecreatetruecolor( $width, $height );
                if ( ! $im ) return false;
                self::draw_solid( $im, $width, $height, '#1e293b' );
            }
            $accent_hex = '#818cf8';
        } elseif ( $bg_type === 'gradient' ) {
            $im = imagecreatetruecolor( $width, $height );
            if ( ! $im ) return false;
            $gradients = self::get_gradients();
            $gradient  = $gradients[ $bg_val ] ?? $gradients['blue_purple'];
            self::draw_gradient( $im, $width, $height, $gradient['from'], $gradient['to'] );
            
            // Map accent hex
            $accent_map = [
                'blue_purple'    => '#a78bfa',
                'orange_red'     => '#fb7185',
                'emerald_teal'   => '#34d399',
                'deep_ocean'     => '#38bdf8',
                'sunset_violet'  => '#f472b6',
                'dark_charcoal'  => '#60a5fa',
                'midnight_neon'  => '#22d3ee',
                'rose_gold'      => '#f472b6',
                'forest_aurora'  => '#34d399',
                'cyberpunk_red'  => '#fda4af',
                'tropical_mango' => '#fca5a5',
            ];
            $accent_hex = $accent_map[ $bg_val ] ?? '#818cf8';
        } else {
            $im = imagecreatetruecolor( $width, $height );
            if ( ! $im ) return false;
            $solids = self::get_solid_colors();
            $solid  = $solids[ $bg_val ] ?? $solids['dark_slate'];
            self::draw_solid( $im, $width, $height, $solid['color'] );
            
            // Map accent hex
            $accent_map = [
                'dark_slate'    => '#818cf8',
                'pure_black'    => '#fbbf24',
                'royal_blue'    => '#f472b6',
                'emerald_green' => '#34d399',
                'rich_purple'   => '#a78bfa',
                'crimson_red'   => '#fca5a5',
                'warm_cream'    => '#ca8a04', // Darker yellow/gold for visibility
                'soft_grey'     => '#2563eb', // Royal Blue accent
            ];
            $accent_hex = $accent_map[ $bg_val ] ?? '#818cf8';
        }

        // Draw vignette / subtle shading overlay if it's an image background (for better text contrast)
        if ( $bg_type === 'image' ) {
            // Draw a semi-transparent black overlay over the whole background image
            $overlay_color = imagecolorallocatealpha( $im, 0, 0, 0, 40 ); // ~30% black opacity
            imagefilledrectangle( $im, 0, 0, $width, $height, $overlay_color );
        }
        // Detect if background is light or dark to establish good contrast
        $is_light = false;
        if ( $bg_type === 'gradient' ) {
            $is_light = self::is_light_color( $gradient['from'] ) || self::is_light_color( $gradient['to'] );
        } elseif ( $bg_type === 'solid' ) {
            $is_light = self::is_light_color( $solid['color'] );
        }

        // Detect working font
        $font_path = self::get_font_path();
        $use_ttf   = ( $font_path !== false );

        // Card specs
        $pad_x = (int) ( $width * 0.08 );
        $pad_y = (int) ( $height * 0.10 );
        $card_w = $width - ( $pad_x * 2 );
        $card_h = $height - ( $pad_y * 2 );

        // Configure card background, border, text, and shadow colors based on contrast
        if ( $is_light ) {
            // Light background -> Dark text card
            $card_bg      = imagecolorallocatealpha( $im, 255, 255, 255, 36 ); // rgba(255, 255, 255, 0.72)
            $card_border  = imagecolorallocatealpha( $im, 15, 23, 42, 112 );  // rgba(15, 23, 42, 0.12)
            $text_color   = imagecolorallocate( $im, 15, 23, 42 );            // Dark Charcoal text
            $shadow_color = imagecolorallocatealpha( $im, 255, 255, 255, 80 ); // Light shadow
        } else {
            // Dark background -> Light text card
            $card_bg      = imagecolorallocatealpha( $im, 15, 23, 42, 36 );   // rgba(15, 23, 42, 0.72)
            $card_border  = imagecolorallocatealpha( $im, 255, 255, 255, 112 ); // rgba(255, 255, 255, 0.12)
            $text_color   = imagecolorallocate( $im, 255, 255, 255 );         // White text
            $shadow_color = imagecolorallocatealpha( $im, 0, 0, 0, 60 );       // Dark shadow
        }

        // Draw card background
        imagefilledrectangle( $im, $pad_x, $pad_y, $width - $pad_x, $height - $pad_y, $card_bg );

        // Draw card border
        imagerectangle( $im, $pad_x, $pad_y, $width - $pad_x, $height - $pad_y, $card_border );

        // Draw 4px top accent bar
        list( $ar, $ag, $ab ) = self::hex2rgb( $accent_hex );
        $accent_color = imagecolorallocate( $im, $ar, $ag, $ab );
        imagefilledrectangle( $im, $pad_x, $pad_y, $width - $pad_x, $pad_y + 4, $accent_color );

        if ( $use_ttf ) {
            // ===== TTF RENDERING (Preferred — crisp, scaled) =====
            self::draw_text_ttf( $im, $text, $font_path, $width, $height, $pad_x, $pad_y, $card_w, $card_h, $text_color, $shadow_color, $accent_hex, $is_light );
        } else {
            // ===== GD BITMAP FALLBACK (No TTF support) =====
            self::draw_text_gd( $im, $text, $width, $height, $pad_x, $pad_y, $card_w, $card_h, $text_color, $is_light );
        }

        // Fetch Base64 data
        ob_start();
        imagepng( $im );
        $raw_image = ob_get_clean();
        imagedestroy( $im );

        return [
            'base64'    => base64_encode( $raw_image ),
            'mime_type' => 'image/png',
        ];
    }

    /**
     * Draw title text using TTF fonts (crisp, scalable).
     */
    private static function draw_text_ttf( $im, $text, $font_path, $w, $h, $pad_x, $pad_y, $card_w, $card_h, $text_color, $shadow_color, $accent_hex, $is_light = false ) {
        // Font size proportional to image width — bold and large for readability
        $font_size = max( 20, (int) ( $w * 0.045 ) );

        // Maximum text width inside card
        $text_padding = max( 30, (int) ( $w * 0.06 ) );
        $max_text_width = $card_w - ( $text_padding * 2 );

        // Wrap text into lines
        $lines = self::wrap_text( $font_size, 0, $font_path, $text, $max_text_width );

        // If too many lines, shrink slightly — but never below a readable size
        $max_lines = 5;
        if ( count( $lines ) > $max_lines ) {
            $font_size = max( 16, (int) ( $font_size * 0.78 ) );
            $lines = self::wrap_text( $font_size, 0, $font_path, $text, $max_text_width );
        }

        // Line height
        $line_height = (int) ( $font_size * 1.6 );
        $total_text_height = count( $lines ) * $line_height;

        // Reserve space for footer
        $footer_space = (int) ( $card_h * 0.18 );
        $available_h = $card_h - $footer_space;

        // Vertically center text in upper part of card
        $y_start = $pad_y + ( $available_h - $total_text_height ) / 2 + $font_size;

        // Draw title text lines — centered horizontally
        $y = $y_start;
        foreach ( $lines as $line ) {
            $bbox = imagettfbbox( $font_size, 0, $font_path, $line );
            $text_width = $bbox[2] - $bbox[0];
            $x = $pad_x + ( $card_w - $text_width ) / 2;

            // Draw shadow
            imagettftext( $im, $font_size, 0, (int)($x + 2), (int)($y + 2), $shadow_color, $font_path, $line );
            // Draw main text
            imagettftext( $im, $font_size, 0, (int)$x, (int)$y, $text_color, $font_path, $line );
            $y += $line_height;
        }

        // Draw dynamic site domain footer at the bottom center of the card
        $domain = wp_parse_url( home_url(), PHP_URL_HOST );
        if ( $domain ) {
            $footer_text = strtoupper( $domain );
            $footer_font_size = max( 10, (int) ( $font_size * 0.38 ) );
            $footer_color = $is_light ? imagecolorallocatealpha( $im, 15, 23, 42, 60 ) : imagecolorallocatealpha( $im, 255, 255, 255, 45 );

            $f_bbox = imagettfbbox( $footer_font_size, 0, $font_path, $footer_text );
            $f_w = $f_bbox[2] - $f_bbox[0];
            $f_x = $pad_x + ( $card_w - $f_w ) / 2;
            $f_y = ( $h - $pad_y ) - (int)( $footer_space * 0.35 );

            // Draw small separator line above footer
            list( $ar, $ag, $ab ) = self::hex2rgb( $accent_hex );
            $sep_color = $is_light ? imagecolorallocatealpha( $im, $ar, $ag, $ab, 90 ) : imagecolorallocatealpha( $im, $ar, $ag, $ab, 80 );
            $sep_w = min( 80, (int)( $card_w * 0.08 ) );
            $sep_x = $pad_x + ( $card_w - $sep_w ) / 2;
            $sep_y = $f_y - (int)( $footer_font_size * 1.5 );
            imagefilledrectangle( $im, (int)$sep_x, $sep_y, (int)($sep_x + $sep_w), $sep_y + 2, $sep_color );

            imagettftext( $im, $footer_font_size, 0, (int)$f_x, (int)$f_y, $footer_color, $font_path, $footer_text );
        }
    }

    /**
     * Draw title text using GD built-in bitmap fonts (fallback when TTF is unavailable).
     * Uses the largest available GD font (font 5 = 15px height) and scales dynamically.
     */
    private static function draw_text_gd( $im, $text, $w, $h, $pad_x, $pad_y, $card_w, $card_h, $text_color, $is_light = false ) {
        // GD built-in font 5 is the largest: 9x15 pixels per character
        $gd_font    = 5;
        $char_w     = imagefontwidth( $gd_font );
        $char_h     = imagefontheight( $gd_font );

        // Determine scale factor for this canvas size
        // Target: title text should be roughly 4-5% of image width per character
        $target_char_w = max( $char_w, (int)( $w * 0.022 ) );
        $scale         = max( 1, (int) round( $target_char_w / $char_w ) );
        $scaled_char_w = $char_w * $scale;
        $scaled_char_h = $char_h * $scale;

        // Text padding inside the card
        $text_pad = max( 30, (int)( $w * 0.06 ) );
        $max_chars_per_line = max( 10, (int) floor( ( $card_w - $text_pad * 2 ) / $scaled_char_w ) );

        // Wrap text by word into lines
        $lines = self::wrap_text_by_chars( $text, $max_chars_per_line );

        // Line height
        $line_h = (int)( $scaled_char_h * 1.6 );
        $total_h = count( $lines ) * $line_h;

        // Reserve space for footer
        $footer_space = (int)( $card_h * 0.18 );
        $available_h = $card_h - $footer_space;

        // Vertically center
        $y_start = $pad_y + (int)( ( $available_h - $total_h ) / 2 );

        // Shadow color
        $shadow = $is_light ? imagecolorallocatealpha( $im, 255, 255, 255, 80 ) : imagecolorallocatealpha( $im, 0, 0, 0, 60 );

        $y = $y_start;
        foreach ( $lines as $line ) {
            $line_px_w = strlen( $line ) * $scaled_char_w;
            $x = $pad_x + (int)( ( $card_w - $line_px_w ) / 2 );

            if ( $scale <= 1 ) {
                // No scaling — draw directly with shadow
                imagestring( $im, $gd_font, $x + 1, $y + 1, $line, $shadow );
                imagestring( $im, $gd_font, $x, $y, $line, $text_color );
            } else {
                // Create a small temp image, draw text at native 1x, then copy-resize scaled
                $temp_w = strlen( $line ) * $char_w + 4;
                $temp_h = $char_h + 4;
                $temp = imagecreatetruecolor( $temp_w, $temp_h );
                if ( $temp ) {
                    // Transparent bg
                    imagealphablending( $temp, false );
                    imagesavealpha( $temp, true );
                    $trans = imagecolorallocatealpha( $temp, 0, 0, 0, 127 );
                    imagefilledrectangle( $temp, 0, 0, $temp_w, $temp_h, $trans );
                    imagealphablending( $temp, true );

                    // Draw 1x text white
                    $white = imagecolorallocate( $temp, 255, 255, 255 );
                    imagestring( $temp, $gd_font, 2, 2, $line, $white );

                    // Shadow: copy scaled with offset
                    $dest_w = $temp_w * $scale;
                    $dest_h = $temp_h * $scale;
                    imagecopyresized( $im, $temp, $x + 2, $y + 2, 0, 0, $dest_w, $dest_h, $temp_w, $temp_h );

                    // Redraw 1x text with actual colors and copy-resize
                    imagealphablending( $temp, false );
                    imagefilledrectangle( $temp, 0, 0, $temp_w, $temp_h, $trans );
                    imagealphablending( $temp, true );
                    
                    list( $tr, $tg, $tb ) = $is_light ? [15, 23, 42] : [255, 255, 255];
                    $actual_text_color = imagecolorallocate( $temp, $tr, $tg, $tb );
                    imagestring( $temp, $gd_font, 2, 2, $line, $actual_text_color );
                    imagecopyresized( $im, $temp, $x, $y, 0, 0, $dest_w, $dest_h, $temp_w, $temp_h );

                    imagedestroy( $temp );
                } else {
                    // Simple fallback
                    imagestring( $im, $gd_font, $x + 1, $y + 1, $line, $shadow );
                    imagestring( $im, $gd_font, $x, $y, $line, $text_color );
                }
            }
            $y += $line_h;
        }

        // Draw domain footer using GD font (smaller font 3)
        $domain = wp_parse_url( home_url(), PHP_URL_HOST );
        if ( $domain ) {
            $footer_text = strtoupper( $domain );
            $f_font = 3;
            $f_char_w = imagefontwidth( $f_font );
            $f_scale = max( 1, (int) round( $scale * 0.6 ) );
            $f_line_w = strlen( $footer_text ) * $f_char_w * $f_scale;
            $f_x = $pad_x + (int)( ( $card_w - $f_line_w ) / 2 );
            $f_y = ( $h - $pad_y ) - (int)( $footer_space * 0.5 );
            $footer_color = $is_light ? imagecolorallocatealpha( $im, 15, 23, 42, 60 ) : imagecolorallocatealpha( $im, 255, 255, 255, 50 );

            if ( $f_scale <= 1 ) {
                imagestring( $im, $f_font, $f_x, $f_y, $footer_text, $footer_color );
            } else {
                $tf_w = strlen( $footer_text ) * $f_char_w + 4;
                $tf_h = imagefontheight( $f_font ) + 4;
                $tf = imagecreatetruecolor( $tf_w, $tf_h );
                if ( $tf ) {
                    imagealphablending( $tf, false );
                    imagesavealpha( $tf, true );
                    $trans = imagecolorallocatealpha( $tf, 0, 0, 0, 127 );
                    imagefilledrectangle( $tf, 0, 0, $tf_w, $tf_h, $trans );
                    imagealphablending( $tf, true );
                    list( $fr, $fg, $fb ) = $is_light ? [15, 23, 42] : [200, 200, 200];
                    $fw = imagecolorallocate( $tf, $fr, $fg, $fb );
                    imagestring( $tf, $f_font, 2, 2, $footer_text, $fw );
                    imagecopyresized( $im, $tf, $f_x, $f_y, 0, 0, $tf_w * $f_scale, $tf_h * $f_scale, $tf_w, $tf_h );
                    imagedestroy( $tf );
                } else {
                    imagestring( $im, $f_font, $f_x, $f_y, $footer_text, $footer_color );
                }
            }
        }
    }

    /**
     * Draw solid background color.
     */
    private static function draw_solid( $im, $w, $h, $hex ) {
        list( $r, $g, $b ) = self::hex2rgb( $hex );
        $color = imagecolorallocate( $im, $r, $g, $b );
        imagefilledrectangle( $im, 0, 0, $w, $h, $color );
    }

    /**
     * Draw diagonal/linear gradient background.
     */
    private static function draw_gradient( $im, $w, $h, $hex_from, $hex_to ) {
        list( $r1, $g1, $b1 ) = self::hex2rgb( $hex_from );
        list( $r2, $g2, $b2 ) = self::hex2rgb( $hex_to );

        for ( $i = 0; $i < $w; $i++ ) {
            $ratio = $i / $w;
            $r = $r1 + ( $r2 - $r1 ) * $ratio;
            $g = $g1 + ( $g2 - $g1 ) * $ratio;
            $b = $b1 + ( $b2 - $b1 ) * $ratio;
            $color = imagecolorallocate( $im, (int) $r, (int) $g, (int) $b );
            imageline( $im, $i, 0, $i, $h, $color );
        }
    }

    /**
     * Convert Hex string to RGB.
     */
    private static function hex2rgb( $hex ) {
        $hex = str_replace( '#', '', $hex );
        if ( strlen( $hex ) === 3 ) {
            $r = hexdec( substr( $hex, 0, 1 ) . substr( $hex, 0, 1 ) );
            $g = hexdec( substr( $hex, 1, 1 ) . substr( $hex, 1, 1 ) );
            $b = hexdec( substr( $hex, 2, 1 ) . substr( $hex, 2, 1 ) );
        } else {
            $r = hexdec( substr( $hex, 0, 2 ) );
            $g = hexdec( substr( $hex, 2, 2 ) );
            $b = hexdec( substr( $hex, 4, 2 ) );
        }
        return [ $r, $g, $b ];
    }

    /**
     * Wrap text into multiple lines keeping word-boundaries using TTF bounding box.
     */
    private static function wrap_text( $font_size, $angle, $font_path, $text, $max_width ) {
        $words = explode( ' ', $text );
        $lines = [];
        $current_line = '';

        foreach ( $words as $word ) {
            $test_line = ( $current_line === '' ) ? $word : $current_line . ' ' . $word;
            $bbox = @imagettfbbox( $font_size, $angle, $font_path, $test_line );
            
            if ( is_array( $bbox ) ) {
                $width = $bbox[2] - $bbox[0];
                if ( $width > $max_width && $current_line !== '' ) {
                    $lines[] = $current_line;
                    $current_line = $word;
                } else {
                    $current_line = $test_line;
                }
            } else {
                // Bounding box fail fallback — use approximate char width
                if ( strlen( $test_line ) > ( $max_width / ( $font_size * 0.55 ) ) ) {
                    $lines[] = $current_line;
                    $current_line = $word;
                } else {
                    $current_line = $test_line;
                }
            }
        }
        
        if ( $current_line !== '' ) {
            $lines[] = $current_line;
        }
        return $lines;
    }

    /**
     * Check if a hex color is light or dark based on YIQ formula.
     */
    public static function is_light_color( $hex ) {
        list( $r, $g, $b ) = self::hex2rgb( $hex );
        $yiq = ( ( $r * 299 ) + ( $g * 587 ) + ( $b * 114 ) ) / 1000;
        return ( $yiq >= 170 ); // True if light color, false if dark
    }

    /**
     * Wrap text by character count (word-aware) for GD bitmap font fallback.
     */
    private static function wrap_text_by_chars( $text, $max_chars ) {
        $words = explode( ' ', $text );
        $lines = [];
        $current = '';

        foreach ( $words as $word ) {
            $test = ( $current === '' ) ? $word : $current . ' ' . $word;
            if ( strlen( $test ) > $max_chars && $current !== '' ) {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $test;
            }
        }
        if ( $current !== '' ) {
            $lines[] = $current;
        }
        return $lines;
    }
}
