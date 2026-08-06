<?php
namespace Ultimate_WP_Booster\Engine\Cache;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

/**
 * LiteSpeed & OpenLiteSpeed LSCache Web Server Engine Integration
 */
class LiteSpeedEngine {

    /**
     * Check if the host web server is LiteSpeed or OpenLiteSpeed.
     *
     * @return bool
     */
    public static function is_litespeed_server() {
        if ( defined( 'LITESPEED_SERVER' ) && LITESPEED_SERVER ) {
            return true;
        }

        if ( function_exists( 'litespeed_finish_request' ) ) {
            return true;
        }

        $server = isset( $_SERVER['SERVER_SOFTWARE'] ) ? $_SERVER['SERVER_SOFTWARE'] : '';
        if ( ! empty( $server ) && ( stripos( $server, 'litespeed' ) !== false || stripos( $server, 'openlitespeed' ) !== false ) ) {
            return true;
        }

        return false;
    }

    /**
     * Check specifically if OpenLiteSpeed is running.
     *
     * @return bool
     */
    public static function is_openlitespeed() {
        $server = isset( $_SERVER['SERVER_SOFTWARE'] ) ? $_SERVER['SERVER_SOFTWARE'] : '';
        return ( ! empty( $server ) && stripos( $server, 'openlitespeed' ) !== false );
    }

    /**
     * Check if current request is a page builder editing/preview session (UX Builder, Elementor, Divi, WPBakery, etc.)
     *
     * @return bool
     */
    public static function is_page_builder_request() {
        if ( ! empty( $_GET ) ) {
            if ( isset( $_GET['app'] ) && $_GET['app'] === 'uxbuilder' ) return true;
            if ( isset( $_GET['uxbuilder'] ) || isset( $_GET['uxbuilder_action'] ) || isset( $_GET['uxbuilder_iframe'] ) || isset( $_GET['uxb_iframe'] ) ) return true;
            foreach ( array_keys( $_GET ) as $g_key ) {
                if ( strpos( $g_key, 'uxb' ) === 0 || strpos( $g_key, 'uxbuilder' ) !== false ) {
                    return true;
                }
            }
            if ( isset( $_GET['elementor-preview'] ) || ( isset( $_GET['ver'] ) && $_GET['ver'] === 'elementor' ) ) return true;
            if ( isset( $_GET['et_fb'] ) || isset( $_GET['et_pb_preview'] ) ) return true;
            if ( isset( $_GET['vc_editable'] ) || isset( $_GET['vc_action'] ) ) return true;
            if ( isset( $_GET['ct_builder'] ) || isset( $_GET['bricks'] ) || isset( $_GET['fl_builder'] ) ) return true;
        }

        $uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
        if ( ! empty( $uri ) ) {
            if ( strpos( $uri, 'uxbuilder' ) !== false || strpos( $uri, 'uxb_iframe' ) !== false || strpos( $uri, 'elementor-preview' ) !== false || strpos( $uri, 'et_fb' ) !== false || strpos( $uri, 'vc_editable' ) !== false ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Send X-LiteSpeed-Cache-Control header for current response.
     *
     * @param int  $lifespan Cache lifespan in seconds.
     * @param bool $is_no_cache Whether to bypass cache.
     */
    public static function send_cache_control_headers( $lifespan = 86400, $is_no_cache = false ) {
        if ( ! self::is_litespeed_server() || headers_sent() ) {
            return;
        }

        $cache_logged_in = (int) get_option( 'uwb_cache_logged_in', 0 );
        $is_logged_in    = ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() );

        if ( ! $is_logged_in && ! empty( $_COOKIE ) ) {
            foreach ( $_COOKIE as $key => $val ) {
                if ( strpos( $key, 'wordpress_logged_in_' ) === 0 ) {
                    $is_logged_in = true;
                    break;
                }
            }
        }

        if ( $is_no_cache || $lifespan <= 0 || self::is_page_builder_request() || ( $is_logged_in && $cache_logged_in !== 2 ) ) {
            header( 'X-LiteSpeed-Cache-Control: no-cache' );
            header( 'Cache-Control: no-cache, no-store, must-revalidate, max-age=0' );
        } else {
            if ( $is_logged_in && $cache_logged_in === 2 ) {
                $user_lifespan = intval( get_option( 'uwb_cache_logged_in_lifespan', 10 ) ) * 60;
                header( 'X-LiteSpeed-Cache-Control: private, max-age=' . intval( $user_lifespan ) );
                header( 'X-LiteSpeed-Vary: cookie=wordpress_logged_in_*' );
            } else {
                header( 'X-LiteSpeed-Cache-Control: public, max-age=' . intval( $lifespan ) );
                header( 'X-LiteSpeed-Vary: cookie=wordpress_logged_in_*' );
            }
        }
    }

    /**
     * Build tag array based on current WordPress query.
     *
     * @return array
     */
    public static function get_page_tags() {
        $tags = array();

        if ( is_front_page() || is_home() ) {
            $tags[] = 'front';
            $tags[] = 'home';
        }

        if ( is_singular() ) {
            $post_id = get_the_ID();
            if ( $post_id ) {
                $tags[] = 'P_' . $post_id;
                $taxonomies = get_post_taxonomies( $post_id );
                if ( ! empty( $taxonomies ) ) {
                    $terms = wp_get_post_terms( $post_id, $taxonomies, array( 'fields' => 'ids' ) );
                    if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
                        foreach ( $terms as $term_id ) {
                            $tags[] = 'C_' . intval( $term_id );
                        }
                    }
                }
            }
        } elseif ( is_category() || is_tag() || is_tax() ) {
            $term_id = get_queried_object_id();
            if ( $term_id ) {
                $tags[] = 'C_' . $term_id;
            }
        } elseif ( is_author() ) {
            $author_id = get_queried_object_id();
            if ( $author_id ) {
                $tags[] = 'A_' . $author_id;
            }
        } elseif ( is_404() ) {
            $tags[] = '404';
        }

        return array_unique( $tags );
    }

    /**
     * Send X-LiteSpeed-Tag header for current response.
     */
    public static function send_tag_headers() {
        if ( ! self::is_litespeed_server() || headers_sent() ) {
            return;
        }

        $tags = self::get_page_tags();
        if ( ! empty( $tags ) ) {
            header( 'X-LiteSpeed-Tag: ' . implode( ',', $tags ) );
        }
    }

    /**
     * Issue X-LiteSpeed-Purge header for a updated post.
     *
     * @param int $post_id
     */
    public static function purge_post_tag( $post_id ) {
        if ( ! self::is_litespeed_server() || headers_sent() ) {
            return;
        }

        $tags = array( 'P_' . intval( $post_id ), 'front', 'home' );
        $taxonomies = get_post_taxonomies( $post_id );
        if ( ! empty( $taxonomies ) ) {
            $terms = wp_get_post_terms( $post_id, $taxonomies, array( 'fields' => 'ids' ) );
            if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
                foreach ( $terms as $term_id ) {
                    $tags[] = 'C_' . intval( $term_id );
                }
            }
        }

        header( 'X-LiteSpeed-Purge: ' . implode( ',', array_unique( $tags ) ) );
    }

    /**
     * Issue X-LiteSpeed-Purge header for a specific URL path.
     *
     * @param string $url
     */
    public static function purge_url( $url ) {
        if ( ! self::is_litespeed_server() || headers_sent() ) {
            return;
        }

        $path = wp_parse_url( $url, PHP_URL_PATH );
        if ( empty( $path ) ) {
            $path = '/';
        }

        $purge_targets = array( $path );
        $normalized_path = trim( $path, '/' );
        if ( $normalized_path !== '' ) {
            $purge_targets[] = '/' . $normalized_path . '/';
        }

        header( 'X-LiteSpeed-Purge: ' . implode( ',', array_unique( $purge_targets ) ) );
    }

    /**
     * Issue X-LiteSpeed-Purge: * header to purge all LiteSpeed server cache.
     */
    public static function purge_all() {
        if ( ! self::is_litespeed_server() || headers_sent() ) {
            return;
        }

        header( 'X-LiteSpeed-Purge: *' );
    }

    /**
     * Touch .htaccess file timestamp for OpenLiteSpeed auto-reload.
     */
    public static function touch_htaccess() {
        $htaccess = ABSPATH . '.htaccess';
        if ( file_exists( $htaccess ) && is_writable( $htaccess ) ) {
            @touch( $htaccess );
        }
    }

    /**
     * Detect LiteSpeed Server Native Crawler status.
     *
     * @return array
     */
    public static function get_crawler_status() {
        $is_litespeed = self::is_litespeed_server();
        $htaccess_path = ABSPATH . '.htaccess';
        $htaccess_enabled = false;

        if ( file_exists( $htaccess_path ) ) {
            $content = @file_get_contents( $htaccess_path );
            if ( $content && preg_match( '/CacheEngine\s+on\s+crawler/i', $content ) ) {
                $htaccess_enabled = true;
            }
        }

        $env_active = ! empty( $_SERVER['CRAWLER_LOAD_LIMIT'] ) || ! empty( getenv( 'CRAWLER_LOAD_LIMIT' ) );

        $state = 'disabled';
        $label = 'Non-LiteSpeed Server Detected';
        $color = '#ef4444';
        $bg    = '#fef2f2';

        if ( $is_litespeed ) {
            if ( $htaccess_enabled || $env_active ) {
                $state = 'active';
                $label = 'Active & Running';
                $color = '#10b981';
                $bg    = '#ecfdf5';
            } else {
                $state = 'ready';
                $label = 'LiteSpeed Server Detected (Not Activated)';
                $color = '#f59e0b';
                $bg    = '#fffbeb';
            }
        }

        return array(
            'is_litespeed'     => $is_litespeed,
            'htaccess_enabled' => $htaccess_enabled,
            'env_active'       => $env_active,
            'state'            => $state,
            'label'            => $label,
            'color'            => $color,
            'bg'               => $bg,
        );
    }
}
