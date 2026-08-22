<?php
/**
 * Auto External Outbound High-DA Links Engine
 * Inject contextual high-authority external links (Wikipedia, MDN, WebMD, Investopedia, etc.) into post content for E-E-A-T and SEO trust.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class AAP_Outbound_Linker {

    /**
     * High-DA Authority Mapping Matrix (DA 80+)
     */
    private static $high_da_database = [
        // Tech & Programming
        'javascript'     => 'https://developer.mozilla.org/en-US/docs/Web/JavaScript',
        'css'            => 'https://developer.mozilla.org/en-US/docs/Web/CSS',
        'html'           => 'https://developer.mozilla.org/en-US/docs/Web/HTML',
        'python'         => 'https://www.python.org',
        'php'            => 'https://www.php.net',
        'wordpress'      => 'https://wordpress.org',
        'artificial intelligence' => 'https://en.wikipedia.org/wiki/Artificial_intelligence',
        'machine learning'        => 'https://en.wikipedia.org/wiki/Machine_learning',
        'cybersecurity'  => 'https://en.wikipedia.org/wiki/Computer_security',
        'cloud computing'=> 'https://en.wikipedia.org/wiki/Cloud_computing',
        'database'       => 'https://en.wikipedia.org/wiki/Database',

        // Business & Finance
        'cryptocurrency' => 'https://www.investopedia.com/terms/c/cryptocurrency.asp',
        'blockchain'     => 'https://www.investopedia.com/terms/b/blockchain.asp',
        'stock market'   => 'https://www.investopedia.com/terms/s/stockmarket.asp',
        'inflation'      => 'https://www.investopedia.com/terms/i/inflation.asp',
        'real estate'    => 'https://www.investopedia.com/terms/r/realestate.asp',
        'entrepreneurship' => 'https://www.forbes.com',

        // Health & Wellness
        'nutrition'      => 'https://www.healthline.com/nutrition',
        'mental health'  => 'https://www.webmd.com/mental-health/default.htm',
        'cardiovascular' => 'https://www.mayoclinic.org/diseases-conditions/heart-disease/symptoms-causes/syc-20353118',
        'immune system'  => 'https://www.webmd.com',
        'fitness'        => 'https://www.healthline.com/fitness',

        // General Knowledge & Science
        'climate change' => 'https://climate.nasa.gov',
        'renewable energy'=> 'https://en.wikipedia.org/wiki/Renewable_energy',
        'space exploration' => 'https://www.nasa.gov',
        'sustainability' => 'https://en.wikipedia.org/wiki/Sustainability',
        'search engine optimization' => 'https://moz.com/beginners-guide-to-seo',
        'seo'            => 'https://searchenginejournal.com',
    ];

    /**
     * Process post content and inject outbound high-DA links.
     */
    public static function process( $content, $title = '' ) {
        if ( get_option( 'aap_enable_outbound_linking', '1' ) !== '1' ) {
            return $content;
        }

        $max_links  = max( 1, min( 10, intval( get_option( 'aap_max_outbound_links', 2 ) ) ) );
        $target     = sanitize_text_field( get_option( 'aap_outbound_target', '_blank' ) );
        $rel        = sanitize_text_field( get_option( 'aap_outbound_rel', 'nofollow noopener' ) );
        $blacklist  = array_filter( array_map( 'trim', explode( "\n", strtolower( get_option( 'aap_outbound_blacklist', '' ) ) ) ) );

        $inserted = 0;
        $used_urls = [];

        // 1. Check DB Keyword Matches first
        foreach ( self::$high_da_database as $keyword => $url ) {
            if ( $inserted >= $max_links ) break;

            // Check if domain in blacklist
            $domain = wp_parse_url( $url, PHP_URL_HOST );
            if ( $domain && in_array( strtolower( $domain ), $blacklist, true ) ) {
                continue;
            }

            if ( in_array( $url, $used_urls, true ) ) continue;

            // Regex pattern to replace exact keyword outside HTML tags/links/headings
            $pattern = '/(?<![\w\-\'\"])(?<!<a[^>]*>)(?<!<h[1-6][^>]*>)(?<!<code[^>]*>)\b(' . preg_quote( $keyword, '/' ) . ')\b(?![^<]*<\/a>)(?![^<]*<\/h[1-6]>)(?![^<]*<\/code>)(?![^<]*">)/i';

            if ( preg_match( $pattern, $content ) ) {
                $link_html = sprintf(
                    '<a href="%s" target="%s" rel="%s" class="aap-outbound-link">%s</a>',
                    esc_url( $url ),
                    esc_attr( $target ),
                    esc_attr( $rel ),
                    '$1'
                );

                $content = preg_replace( $pattern, $link_html, $content, 1, $count );
                if ( $count > 0 ) {
                    $inserted++;
                    $used_urls[] = $url;
                }
            }
        }

        // 2. Wikipedia Fallback search if slots remaining
        if ( $inserted < $max_links && ! empty( $title ) ) {
            $wiki_url = self::fetch_wikipedia_url( $title );
            if ( $wiki_url && ! in_array( $wiki_url, $used_urls, true ) ) {
                // Find a key noun in title to wrap
                $title_words = explode( ' ', $title );
                foreach ( $title_words as $word ) {
                    $clean_word = trim( preg_replace( '/[^a-zA-Z0-9]/', '', $word ) );
                    if ( strlen( $clean_word ) > 4 ) {
                        $pattern = '/(?<![\w\-\'\"])(?<!<a[^>]*>)(?<!<h[1-6][^>]*>)\b(' . preg_quote( $clean_word, '/' ) . ')\b(?![^<]*<\/a>)(?![^<]*<\/h[1-6]>)/i';
                        if ( preg_match( $pattern, $content ) ) {
                            $link_html = sprintf(
                                '<a href="%s" target="%s" rel="%s" class="aap-outbound-link">%s</a>',
                                esc_url( $wiki_url ),
                                esc_attr( $target ),
                                esc_attr( $rel ),
                                '$1'
                            );
                            $content = preg_replace( $pattern, $link_html, $content, 1, $count );
                            if ( $count > 0 ) {
                                $inserted++;
                                break;
                            }
                        }
                    }
                }
            }
        }

        return $content;
    }

    /**
     * Query Wikipedia OpenSearch API for relevant high-DA reference link.
     */
    private static function fetch_wikipedia_url( $query ) {
        $transient_key = 'aap_wiki_' . md5( strtolower( $query ) );
        $cached = get_transient( $transient_key );
        if ( $cached !== false ) return $cached;

        $api_url = 'https://en.wikipedia.org/w/api.php?action=opensearch&search=' . urlencode( $query ) . '&limit=1&format=json';
        $response = wp_remote_get( $api_url, [ 'timeout' => 3 ] );

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            set_transient( $transient_key, '', DAY_IN_SECONDS );
            return '';
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( isset( $data[3][0] ) && ! empty( $data[3][0] ) ) {
            $url = esc_url_raw( $data[3][0] );
            set_transient( $transient_key, $url, WEEK_IN_SECONDS );
            return $url;
        }

        set_transient( $transient_key, '', DAY_IN_SECONDS );
        return '';
    }
}
