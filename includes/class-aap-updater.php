<?php
/**
 * GitHub Automatic Updater for AI Auto Post
 * Allows direct update from the WordPress plugins screen by pulling releases from GitHub.
 *
 * Author: Anand Soni
 * GitHub: https://github.com/geekyaadi/ai-auto-post
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class AAP_Updater {

    private $plugin_file;
    private $username;
    private $repository;
    private $slug;
    private $github_response;

    public function __construct( $plugin_file ) {
        $this->plugin_file = $plugin_file;
        $this->username    = 'geekyaadi';
        $this->repository  = 'ai-auto-post';
        $this->slug        = plugin_basename( $plugin_file ); // e.g. 'ai-auto-post/ai-auto-post.php'

        // Check for updates
        add_filter( 'pre_set_site_trans_update_plugins', [ $this, 'check_update' ] );
        add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_update' ] );
        
        // Show plugin info modal
        add_filter( 'plugins_api', [ $this, 'plugin_popup' ], 20, 3 );
        
        // Rename folder post-install if zipball name matches GitHub format
        add_filter( 'upgrader_post_install', [ $this, 'post_install' ], 10, 3 );
    }

    /**
     * Fetch latest release info from GitHub API
     */
    private function get_github_release_info() {
        if ( ! empty( $this->github_response ) ) {
            return $this->github_response;
        }

        $transient_key = 'aap_github_release_info';

        // Force check cache bypass if checking updates in WP Admin or update-core page
        if ( isset( $_GET['force-check'] ) || ( is_admin() && isset( $GLOBALS['pagenow'] ) && ( $GLOBALS['pagenow'] === 'update-core.php' || $GLOBALS['pagenow'] === 'plugins.php' ) ) ) {
            delete_transient( $transient_key );
            delete_site_transient( 'update_plugins' );
        } else {
            $cached = get_transient( $transient_key );
            if ( $cached !== false ) {
                $this->github_response = $cached;
                return $cached;
            }
        }

        $url = "https://api.github.com/repos/{$this->username}/{$this->repository}/releases/latest";
        
        $response = wp_remote_get( $url, [
            'headers' => [
                'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
            ],
            'timeout' => 10,
        ] );

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return false;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $data ) ) {
            return false;
        }

        // Cache for 6 hours (lightweight)
        set_transient( $transient_key, $data, 6 * HOUR_IN_SECONDS );
        $this->github_response = $data;
        return $data;
    }

    /**
     * Inject update payload if a newer version exists
     */
    public function check_update( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        $release = $this->get_github_release_info();
        if ( ! $release ) {
            return $transient;
        }

        $new_version     = ltrim( $release['tag_name'], 'v' );
        $current_version = AAP_VERSION;

        if ( version_compare( $new_version, $current_version, '>' ) ) {
            // Find ZIP package URL. Prefer release asset if uploaded, else fallback to zipball
            $package = $release['zipball_url'];
            if ( ! empty( $release['assets'] ) ) {
                foreach ( $release['assets'] as $asset ) {
                    if ( isset( $asset['name'] ) && strpos( $asset['name'], '.zip' ) !== false ) {
                        $package = $asset['browser_download_url'];
                        break;
                    }
                }
            }

            $logo_url = 'https://raw.githubusercontent.com/geekyaadi/ai-auto-post/main/admin/ai-auto-post-by-aadi.png';
            $obj = new stdClass();
            $obj->slug        = 'ai-auto-post';
            $obj->plugin      = $this->slug;
            $obj->new_version = $new_version;
            $obj->url         = $release['html_url'];
            $obj->package     = $package;
            $obj->icons       = [
                'default' => $logo_url,
                '1x'      => $logo_url,
                '2x'      => $logo_url,
            ];

            $transient->response[ $this->slug ] = $obj;
        }

        return $transient;
    }

    /**
     * Show release info modal in Plugins popup
     */
    public function plugin_popup( $result, $action, $args ) {
        if ( $action !== 'plugin_information' ) {
            return $result;
        }

        if ( ! isset( $args->slug ) || $args->slug !== 'ai-auto-post' ) {
            return $result;
        }

        $release = $this->get_github_release_info();
        if ( ! $release ) {
            return $result;
        }

        $new_version = ltrim( $release['tag_name'], 'v' );

        $result = new stdClass();
        $result->name           = 'AI Auto Post';
        $result->slug           = 'ai-auto-post';
        $result->version        = $new_version;
        $result->author         = '<a href="https://github.com/geekyaadi" target="_blank">Anand Soni</a>';
        $result->homepage       = 'https://github.com/geekyaadi/ai-auto-post';
        $download_url = $release['zipball_url'];
        if ( ! empty( $release['assets'] ) ) {
            foreach ( $release['assets'] as $asset ) {
                if ( isset( $asset['name'] ) && strpos( $asset['name'], '.zip' ) !== false ) {
                    $download_url = $asset['browser_download_url'];
                    break;
                }
            }
        }
        $result->download_link  = $download_url;
        $result->last_updated   = $release['published_at'];
        
        $logo_url = 'https://raw.githubusercontent.com/geekyaadi/ai-auto-post/main/admin/ai-auto-post-by-aadi.png';
        $result->icons = [
            '1x' => $logo_url,
            '2x' => $logo_url,
        ];
        $result->banners = [
            'low'  => $logo_url,
            'high' => $logo_url,
        ];

        $result->sections = [
            'description' => 'Auto-generate SEO blog posts using Google Gemini API — with multi-key rotation, scheduling, queue, history log, and full quality controls.',
            'changelog'   => wp_kses_post( wpautop( $release['body'] ) ),
        ];

        return $result;
    }

    /**
     * Rename folder back to 'ai-auto-post' after zipball extraction
     */
    public function post_install( $response, $hook_extra, $result ) {
        if ( isset( $hook_extra['plugin'] ) && $hook_extra['plugin'] === $this->slug ) {
            global $wp_filesystem;
            
            // Ensure WP_Filesystem is initialized safely
            if ( empty( $wp_filesystem ) ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                WP_Filesystem();
            }
            
            $correct_destination = WP_PLUGIN_DIR . '/' . $this->repository;
            
            if ( isset( $result['destination'] ) && $result['destination'] !== $correct_destination ) {
                if ( $wp_filesystem && $wp_filesystem->move( $result['destination'], $correct_destination ) ) {
                    $result['destination'] = $correct_destination;
                }
            }
        }
        return $response;
    }
}
