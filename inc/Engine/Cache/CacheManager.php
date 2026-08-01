<?php
namespace Ultimate_WP_Booster\Engine\Cache;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class CacheManager {

    public static function get_cache_dir() {
        return WP_CONTENT_DIR . '/cache/wp-rocket';
    }

    public function purge_all() {
        $cache_dir = self::get_cache_dir();
        if ( file_exists( $cache_dir ) ) {
            $this->recursive_delete( $cache_dir, false );
        } else {
            @mkdir( $cache_dir, 0755, true );
        }

        $minify_dir = WP_CONTENT_DIR . '/cache/ultimate-wp-booster/minify';
        if ( file_exists( $minify_dir ) ) {
            $this->recursive_delete( $minify_dir, true );
        }

        $combine_dir = WP_CONTENT_DIR . '/cache/ultimate-wp-booster/combine';
        if ( file_exists( $combine_dir ) ) {
            $this->recursive_delete( $combine_dir, true );
        }
    }

    public function purge_url( $url ) {
        $host = wp_parse_url( $url, PHP_URL_HOST );
        $path = wp_parse_url( $url, PHP_URL_PATH );
        
        if ( empty( $host ) ) {
            return;
        }

        $host = strtolower( $host );
        $normalized_path = trim( $path, '/' );

        $dir_path = self::get_cache_dir() . '/' . $host . '/' . $normalized_path;
        if ( $normalized_path === '' ) {
            $dir_path = self::get_cache_dir() . '/' . $host;
        }

        if ( file_exists( $dir_path ) && is_dir( $dir_path ) ) {
            $files_to_delete = glob( $dir_path . '/index*.html*' );
            if ( is_array( $files_to_delete ) ) {
                foreach ( $files_to_delete as $file_path ) {
                    if ( file_exists( $file_path ) ) {
                        @unlink( $file_path );
                    }
                }
            }

            $subdirs = glob( $dir_path . '/user-*', GLOB_ONLYDIR );
            if ( is_array( $subdirs ) ) {
                foreach ( $subdirs as $subdir ) {
                    $user_files = glob( $subdir . '/index*.html*' );
                    if ( is_array( $user_files ) ) {
                        foreach ( $user_files as $file_path ) {
                            if ( file_exists( $file_path ) ) {
                                @unlink( $file_path );
                            }
                        }
                    }
                    $this->remove_empty_dirs( $subdir );
                }
            }

            $this->remove_empty_dirs( $dir_path );
        }
    }

    public function purge_post_cache( $post_id, $post = null, $update = null ) {
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return;
        }

        if ( ! $post ) {
            $post = get_post( $post_id );
        }

        if ( ! $post || $post->post_status !== 'publish' ) {
            return;
        }

        $permalink = get_permalink( $post_id );
        if ( $permalink ) {
            $this->purge_url( $permalink );
        }

        $this->purge_url( home_url( '/' ) );
        \Ultimate_WP_Booster\Engine\Preload\Preloader::invalidate_homepage_links_cache();

        if ( $post->post_parent ) {
            $parent_permalink = get_permalink( $post->post_parent );
            if ( $parent_permalink ) {
                $this->purge_url( $parent_permalink );
            }
        }

        $author_link = get_author_posts_url( $post->post_author );
        if ( $author_link ) {
            $this->purge_url( $author_link );
        }

        $taxonomies = get_object_taxonomies( $post->post_type );
        foreach ( $taxonomies as $taxonomy ) {
            $terms = wp_get_post_terms( $post_id, $taxonomy );
            if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
                foreach ( $terms as $term ) {
                    $term_link = get_term_link( $term );
                    if ( ! is_wp_error( $term_link ) ) {
                        $this->purge_url( $term_link );
                    }
                }
            }
        }

        $archive_link = get_post_type_archive_link( $post->post_type );
        if ( $archive_link ) {
            $this->purge_url( $archive_link );
        }

        $always_purge_raw = get_option( 'uwb_always_purge_urls', '' );
        if ( ! empty( $always_purge_raw ) ) {
            $always_purge_lines = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", "", $always_purge_raw ) ) ) );
            foreach ( $always_purge_lines as $url_line ) {
                if ( empty( $url_line ) ) {
                    continue;
                }
                if ( strpos( $url_line, 'http://' ) !== 0 && strpos( $url_line, 'https://' ) !== 0 ) {
                    $url_line = home_url( '/' . ltrim( $url_line, '/' ) );
                }
                $this->purge_url( $url_line );
            }
        }
    }

    public function clean_expired_cache() {
        $lifespan_minutes = intval( get_option( 'uwb_cache_lifespan', 0 ) );
        $lifespan_seconds = $lifespan_minutes * 60;
        $cache_dir = self::get_cache_dir();

        if ( file_exists( $cache_dir ) && is_dir( $cache_dir ) ) {
            $this->delete_expired_files_recursive( $cache_dir, $lifespan_seconds );
        }
    }

    private function delete_expired_files_recursive( $dir, $lifespan_seconds ) {
        if ( ! is_dir( $dir ) ) {
            return;
        }

        $now = time();
        $items = scandir( $dir );
        foreach ( $items as $item ) {
            if ( $item === '.' || $item === '..' ) {
                continue;
            }

            $path = $dir . '/' . $item;
            if ( is_dir( $path ) ) {
                $this->delete_expired_files_recursive( $path, $lifespan_seconds );
            } else {
                $filename = basename( $path );
                $is_cache_file = ( strpos( $filename, 'index' ) === 0 ) && 
                                 ( substr( $filename, -5 ) === '.html' || substr( $filename, -10 ) === '.html_gzip' );

                if ( $is_cache_file ) {
                    $parent_folder = basename( dirname( $path ) );
                    $is_user_cache = ( strpos( $parent_folder, 'user-' ) === 0 );
                    $is_xml_cache = ( stripos( $path, '.xml/' ) !== false || stripos( $path, '.xml\\' ) !== false );
                    $is_php_cache = ( stripos( $path, '.php/' ) !== false || stripos( $path, '.php\\' ) !== false );

                    if ( $is_user_cache ) {
                        $user_lifespan_mins = intval( get_option( 'uwb_cache_logged_in_lifespan', 10 ) );
                        $file_lifespan = $user_lifespan_mins * 60;
                    } elseif ( $is_xml_cache ) {
                        $xml_lifespan_minutes = intval( get_option( 'uwb_cache_xml_sitemaps_lifespan', 10 ) );
                        $file_lifespan = $xml_lifespan_minutes * 60;
                    } elseif ( $is_php_cache ) {
                        $php_lifespan_minutes = intval( get_option( 'uwb_cache_php_lifespan', 10 ) );
                        $file_lifespan = $php_lifespan_minutes * 60;
                    } else {
                        $file_lifespan = $lifespan_seconds;
                    }

                    if ( $file_lifespan > 0 ) {
                        $file_time = @filemtime( $path );
                        if ( $file_time && ( $now - $file_time ) >= $file_lifespan ) {
                            @unlink( $path );
                        }
                    }
                }
            }
        }

        $this->remove_empty_dirs( $dir );
    }

    public static function write_config_file() {
        $cache_dir = WP_CONTENT_DIR . '/cache';
        if ( ! file_exists( $cache_dir ) ) {
            @mkdir( $cache_dir, 0755, true );
        }
        $wp_rocket_dir = $cache_dir . '/wp-rocket';
        if ( ! file_exists( $wp_rocket_dir ) ) {
            @mkdir( $wp_rocket_dir, 0755, true );
        }

        $config_path = $cache_dir . '/ultimate-wp-booster-config.php';
        
        $lifespan_minutes = intval( get_option( 'uwb_cache_lifespan', 0 ) );
        $lifespan_seconds = $lifespan_minutes * 60;

        $exclusions_raw = get_option( 'uwb_excluded_urls', '' );
        $exclusions = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", "", $exclusions_raw ) ) ) );

        $timezone = get_option( 'timezone_string' );
        if ( empty( $timezone ) ) {
            $timezone = get_option( 'gmt_offset', 0 );
        }

        $browser_cache_minutes = intval( get_option( 'uwb_browser_cache_html_lifespan', 525600 ) );
        $browser_cache_seconds = $browser_cache_minutes * 60;

        $ignored_query_raw = get_option( 'uwb_ignored_query', "utm_source\nutm_medium\nutm_campaign\nfbclid\ngclid\nage-verified" );
        $ignored_queries = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", "", $ignored_query_raw ) ) ) );
        if ( ! in_array( 'uwb_preload_key', $ignored_queries, true ) ) {
            $ignored_queries[] = 'uwb_preload_key';
        }

        $exclude_cookies_raw = get_option( 'uwb_exclude_cookies', '' );
        $exclude_cookies = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", "", $exclude_cookies_raw ) ) ) );

        $exclude_uas_raw = get_option( 'uwb_exclude_user_agents', '' );
        $exclude_uas = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", "", $exclude_uas_raw ) ) ) );

        $always_purge_raw = get_option( 'uwb_always_purge_urls', '' );
        $always_purges = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", "", $always_purge_raw ) ) ) );

        $cache_qs_raw = get_option( 'uwb_cache_query_strings', '' );
        $cache_qs = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", "", $cache_qs_raw ) ) ) );

        $bypass_queries = array( 'wc-ajax', 'add-to-cart', 'pay_for_order', 'change_payment_method', 'logout', 'wc-api', 'magic_login' );

        $xml_lifespan_minutes = intval( get_option( 'uwb_cache_xml_sitemaps_lifespan', 10 ) );
        $xml_lifespan_seconds = $xml_lifespan_minutes * 60;

        $php_lifespan_minutes = intval( get_option( 'uwb_cache_php_lifespan', 10 ) );
        $php_lifespan_seconds = $php_lifespan_minutes * 60;

        $config = array(
            'plugin_dir'               => defined( 'UWB_PLUGIN_DIR' ) ? UWB_PLUGIN_DIR : '',
            'cache_page_enabled'       => intval( get_option( 'uwb_cache_page_enabled', 1 ) ),
            'cache_lifespan'           => $lifespan_seconds,
            'cache_logged_in'          => intval( get_option( 'uwb_cache_logged_in', 0 ) ),
            'cache_logged_in_lifespan' => intval( get_option( 'uwb_cache_logged_in_lifespan', 10 ) ) * 60,
            'browser_cache_enabled'    => intval( get_option( 'uwb_browser_cache_html', 1 ) ) && intval( get_option( 'uwb_browser_cache_enabled', 1 ) ),
            'browser_cache_lifespan'   => $browser_cache_seconds,
            'excluded_urls'            => array_values( $exclusions ),
            'ignored_query'            => array_values( $ignored_queries ),
            'exclude_cookies'          => array_values( $exclude_cookies ),
            'exclude_user_agents'      => array_values( $exclude_uas ),
            'always_purge_urls'        => array_values( $always_purges ),
            'cache_query_strings'      => array_values( $cache_qs ),
            'bypass_query_params'      => array_values( $bypass_queries ),
            'cache_xml_sitemaps'       => intval( get_option( 'uwb_cache_xml_sitemaps', 0 ) ),
            'cache_xml_sitemaps_lifespan' => $xml_lifespan_seconds,
            'cache_php'                => intval( get_option( 'uwb_cache_php', 0 ) ),
            'cache_php_lifespan'       => $php_lifespan_seconds,
            'timezone'                 => $timezone,
            'cache_404'                => intval( get_option( 'uwb_cache_404', 0 ) ),
            'redis_enabled'            => intval( get_option( 'uwb_redis_enabled', 0 ) ),
            'redis_conn_type'          => get_option( 'uwb_redis_conn_type', 'tcp' ),
            'redis_host'               => get_option( 'uwb_redis_host', '127.0.0.1' ),
            'redis_port'               => intval( get_option( 'uwb_redis_port', 6379 ) ),
            'redis_socket'             => get_option( 'uwb_redis_socket', '/var/run/redis/redis.sock' ),
            'redis_password'           => get_option( 'uwb_redis_password', '' ),
            'redis_db'                 => intval( get_option( 'uwb_redis_db', 0 ) ),
            'redis_prefix'             => get_option( 'uwb_redis_prefix', 'uwb_oc:' ),
            'html_minify'                 => intval( get_option( 'uwb_html_minify', 0 ) ),
            'html_remove_qs'              => intval( get_option( 'uwb_html_remove_qs', 0 ) ),
            'html_remove_gfonts'          => intval( get_option( 'uwb_html_remove_gfonts', 0 ) ),
            'html_remove_emoji'           => intval( get_option( 'uwb_html_remove_emoji', 0 ) ),
            'html_remove_noscript'        => intval( get_option( 'uwb_html_remove_noscript', 0 ) ),
            'media_lazy_load_images'      => intval( get_option( 'uwb_media_lazy_load_images', 0 ) ),
            'media_lazy_load_iframes'     => intval( get_option( 'uwb_media_lazy_load_iframes', 0 ) ),
            'media_lazy_load_excludes'    => get_option( 'uwb_media_lazy_load_excludes', '' ),
            'media_lazy_load_class_excludes' => get_option( 'uwb_media_lazy_load_class_excludes', '' ),
            'media_image_placeholder'     => intval( get_option( 'uwb_media_image_placeholder', 0 ) ),
            'media_add_missing_sizes'     => intval( get_option( 'uwb_media_add_missing_sizes', 0 ) ),
            'css_minify'                  => intval( get_option( 'uwb_css_minify', 0 ) ),
            'css_combine'                 => intval( get_option( 'uwb_css_combine', 0 ) ),
            'css_combine_ext_inline'      => intval( get_option( 'uwb_css_combine_ext_inline', 0 ) ),
            'css_load_async'              => intval( get_option( 'uwb_css_load_async', 0 ) ),
            'css_font_display_opt'        => intval( get_option( 'uwb_css_font_display_opt', 1 ) ),
            'js_minify'                   => intval( get_option( 'uwb_js_minify', 0 ) ),
            'js_combine'                  => intval( get_option( 'uwb_js_combine', 0 ) ),
            'js_combine_ext_inline'       => intval( get_option( 'uwb_js_combine_ext_inline', 0 ) ),
            'js_load_defer'               => intval( get_option( 'uwb_js_load_defer', 0 ) ),
            'tuning_critical_css'         => get_option( 'uwb_tuning_critical_css', '' ),
            'tuning_css_excludes'         => get_option( 'uwb_tuning_css_excludes', '' ),
            'tuning_js_excludes'          => get_option( 'uwb_tuning_js_excludes', "jquery.js\njquery.min.js\njquery-migrate\nflatsome\nflatsomeVars\nwp-i18n\nwp-hooks\nwp-polyfill" ),
            'tuning_js_defer_excludes'    => get_option( 'uwb_tuning_js_defer_excludes', "jquery.js\njquery.min.js\njquery-migrate\nflatsome\nflatsomeVars\nwp-i18n\nwp-hooks\nwp-polyfill" ),
            'ignore_all_query_strings'    => intval( get_option( 'uwb_ignore_all_query_strings', 1 ) ),
            'auto_collect_params'         => intval( get_option( 'uwb_auto_collect_params', 0 ) ),
            'collected_params'            => get_option( 'uwb_collected_params', '' ),
            'debug_mode'                  => intval( get_option( 'uwb_debug_mode', 0 ) ),
            'preconnect_domains'          => get_option( 'uwb_preconnect_domains', '' ),
            'preload_fonts'               => get_option( 'uwb_preload_fonts', '' ),
            'delay_js'                    => intval( get_option( 'uwb_delay_js', 0 ) ),
            'delay_js_exclusions'         => get_option( 'uwb_delay_js_exclusions', "jquery.js\njquery.min.js\njquery-migrate\nflatsome\nflatsomeVars\nwp-i18n\nwp-hooks\nwp-polyfill\nnoscript\nuwb-lazy" ),
            'preload_secret_key'          => get_option( 'uwb_preload_secret_key', '' ),
        );

        $config_content = "<?php\n" .
                           "defined( 'ABSPATH' ) or die( 'Forbidden' );\n" .
                           "return " . var_export( $config, true ) . ";\n";

        @file_put_contents( $config_path, $config_content );
        if ( function_exists( 'opcache_invalidate' ) ) {
            @opcache_invalidate( $config_path, true );
        }

        \Ultimate_WP_Booster\Engine\Activation\Activation::copy_advanced_cache_dropin();
        if ( ! empty( $config['redis_enabled'] ) ) {
            \Ultimate_WP_Booster\Engine\Activation\Activation::copy_object_cache_dropin();
        } else {
            \Ultimate_WP_Booster\Engine\Activation\Activation::remove_object_cache_dropin();
        }

        self::write_valid_post_ids_json();
    }

    public static function write_valid_post_ids_json() {
        global $wpdb;
        $cache_dir = self::get_cache_dir();
        if ( ! file_exists( $cache_dir ) ) {
            @mkdir( $cache_dir, 0755, true );
        }

        $json_path = dirname( $cache_dir ) . '/uwb-valid-post-ids.json';
        $ids = $wpdb->get_col(
            "SELECT ID FROM {$wpdb->posts} 
             WHERE post_status = 'publish' 
               AND post_type NOT IN ('revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request')"
        );

        if ( ! is_array( $ids ) ) {
            $ids = array();
        }

        $ids = array_map( 'intval', $ids );
        @file_put_contents( $json_path, json_encode( $ids ) );
    }

    private function recursive_delete( $dir, $delete_self = true ) {
        if ( ! file_exists( $dir ) ) {
            return;
        }

        if ( ! is_dir( $dir ) ) {
            @unlink( $dir );
            return;
        }

        $items = scandir( $dir );
        foreach ( $items as $item ) {
            if ( $item === '.' || $item === '..' ) {
                continue;
            }

            $path = $dir . '/' . $item;
            if ( is_dir( $path ) ) {
                $this->recursive_delete( $path, true );
            } else {
                @unlink( $path );
            }
        }

        if ( $delete_self ) {
            @rmdir( $dir );
        }
    }

    private function remove_empty_dirs( $dir ) {
        $cache_dir = self::get_cache_dir();
        if ( strpos( $dir, $cache_dir ) !== 0 || $dir === $cache_dir ) {
            return;
        }

        if ( is_dir( $dir ) ) {
            $items = array_diff( scandir( $dir ), array( '.', '..' ) );
            if ( empty( $items ) ) {
                if ( @rmdir( $dir ) ) {
                    $parent = dirname( $dir );
                    $this->remove_empty_dirs( $parent );
                }
            }
        }
    }
}
