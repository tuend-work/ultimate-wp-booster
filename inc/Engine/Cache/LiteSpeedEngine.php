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
     * Send X-LiteSpeed-Cache-Control header for current response.
     *
     * @param int  $lifespan Cache lifespan in seconds.
     * @param bool $is_no_cache Whether to bypass cache.
     */
    public static function send_cache_control_headers( $lifespan = 86400, $is_no_cache = false ) {
        if ( ! self::is_litespeed_server() || headers_sent() ) {
            return;
        }

        if ( $is_no_cache || $lifespan <= 0 ) {
            header( 'X-LiteSpeed-Cache-Control: no-cache' );
        } else {
            header( 'X-LiteSpeed-Cache-Control: public, max-age=' . intval( $lifespan ) );
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
}
