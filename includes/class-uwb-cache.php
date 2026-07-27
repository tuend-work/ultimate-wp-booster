<?php
/**
 * Cache Management Engine
 */

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class Uwb_Cache {

    public function __construct() {
        // Purge cache hooks on content changes
        add_action( 'save_post', array( $this, 'purge_post_cache' ), 10, 3 );
        add_action( 'save_post', array( 'Uwb_Cache', 'write_valid_post_ids_json' ), 20 );
        add_action( 'delete_post', array( 'Uwb_Cache', 'write_valid_post_ids_json' ), 20 );
        add_action( 'wp_update_nav_menu', array( $this, 'purge_all' ) );
        add_action( 'switch_theme', array( $this, 'purge_all' ) );
        add_action( 'update_option_sidebars_widgets', array( $this, 'purge_all' ) );

        // Admin actions for purging
        add_action( 'admin_init', array( $this, 'handle_purge_actions' ) );

        // Cron actions for cleaning expired cache
        add_action( 'uwb_clean_expired_cache', array( $this, 'clean_expired_cache' ) );
        add_action( 'init', array( $this, 'schedule_cleanup_cron' ) );

        // Invalidate homepage links transient whenever homepage cache is purged
        add_action( 'wp_update_nav_menu', array( 'Uwb_Preloader', 'invalidate_homepage_links_cache' ) );
        add_action( 'switch_theme', array( 'Uwb_Preloader', 'invalidate_homepage_links_cache' ) );
        add_action( 'update_option_sidebars_widgets', array( 'Uwb_Preloader', 'invalidate_homepage_links_cache' ) );
    }

    /**
     * Schedule the expired cache cleanup cron job
     */
    public function schedule_cleanup_cron() {
        if ( ! wp_next_scheduled( 'uwb_clean_expired_cache' ) ) {
            wp_schedule_event( time(), 'hourly', 'uwb_clean_expired_cache' );
        }
    }

    /**
     * Clean expired cache files from the disk
     */
    public function clean_expired_cache() {
        $lifespan_minutes = intval( get_option( 'uwb_cache_lifespan', 0 ) );
        $lifespan_seconds = $lifespan_minutes * 60;
        $cache_dir = self::get_cache_dir();

        if ( file_exists( $cache_dir ) && is_dir( $cache_dir ) ) {
            $this->delete_expired_files_recursive( $cache_dir, $lifespan_seconds );
        }
    }

    /**
     * Helper to recursively delete expired cache files and empty folders
     */
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
                    // Check if this file is in a logged-in user folder (e.g. parent folder name starts with "user-")
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
                        // Guest cache uses global lifespan
                        $file_lifespan = $lifespan_seconds;
                    }

                    // If file_lifespan is 0, it means unlimited lifespan, so do not delete
                    if ( $file_lifespan > 0 ) {
                        $file_time = @filemtime( $path );
                        if ( $file_time && ( $now - $file_time ) >= $file_lifespan ) {
                            @unlink( $path );
                        }
                    }
                }
            }
        }

        // Clean up empty directories
        $this->remove_empty_dirs( $dir );
    }

    /**
     * Get the base cache directory path
     */
    public static function get_cache_dir() {
        return WP_CONTENT_DIR . '/cache/wp-rocket';
    }

    /**
     * Save plugin settings to JSON config file in wp-content/cache/
     * so advanced-cache.php can read it without database bootstrap.
     */
    public static function write_config_file() {
        $cache_dir = WP_CONTENT_DIR . '/cache';
        if ( ! file_exists( $cache_dir ) ) {
            @mkdir( $cache_dir, 0755, true );
        }
        $wp_rocket_dir = $cache_dir . '/wp-rocket';
        if ( ! file_exists( $wp_rocket_dir ) ) {
            @mkdir( $wp_rocket_dir, 0755, true );
        }

        // Clean up old JSON file if it exists
        $old_json_path = $cache_dir . '/ultimate-wp-booster-config.json';
        if ( file_exists( $old_json_path ) ) {
            @unlink( $old_json_path );
        }

        // Also clean up htaccess rule for json if it was created
        $htaccess_path = $cache_dir . '/.htaccess';
        if ( file_exists( $htaccess_path ) ) {
            $htaccess_content = @file_get_contents( $htaccess_path );
            if ( $htaccess_content && strpos( $htaccess_content, 'ultimate-wp-booster-config.json' ) !== false ) {
                @unlink( $htaccess_path );
            }
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

        $exclude_cookies_raw = get_option( 'uwb_exclude_cookies', '' );
        $exclude_cookies = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", "", $exclude_cookies_raw ) ) ) );

        $exclude_uas_raw = get_option( 'uwb_exclude_user_agents', '' );
        $exclude_uas = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", "", $exclude_uas_raw ) ) ) );

        $always_purge_raw = get_option( 'uwb_always_purge_urls', '' );
        $always_purges = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", "", $always_purge_raw ) ) ) );

        $cache_qs_raw = get_option( 'uwb_cache_query_strings', '' );
        $cache_qs = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", "", $cache_qs_raw ) ) ) );

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
            'redis_timeout'            => floatval( get_option( 'uwb_redis_timeout', 1.0 ) ),
            'redis_read_timeout'       => floatval( get_option( 'uwb_redis_read_timeout', 1.0 ) ),
            'redis_retry_interval'     => get_option( 'uwb_redis_retry_interval', '' ),
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
            'js_minify'                   => intval( get_option( 'uwb_js_minify', 0 ) ),
            'js_combine'                  => intval( get_option( 'uwb_js_combine', 0 ) ),
            'js_load_defer'               => intval( get_option( 'uwb_js_load_defer', 0 ) ),
            'loc_gravatar_cache'          => intval( get_option( 'uwb_loc_gravatar_cache', 0 ) ),
            'tuning_critical_css'         => get_option( 'uwb_tuning_critical_css', '' ),
            'tuning_css_excludes'         => get_option( 'uwb_tuning_css_excludes', '' ),
            'tuning_js_excludes'          => get_option( 'uwb_tuning_js_excludes', '' ),
            'ignore_all_query_strings'    => intval( get_option( 'uwb_ignore_all_query_strings', 1 ) )
        );

        $config_content = "<?php\n" .
                           "defined( 'ABSPATH' ) or die( 'Forbidden' );\n" .
                           "return " . var_export( $config, true ) . ";\n";

        @file_put_contents( $config_path, $config_content );

        // Auto-sync advanced-cache.php drop-in file to wp-content/
        require_once dirname( __FILE__ ) . '/class-uwb-activator.php';
        Uwb_Activator::copy_advanced_cache_dropin();

        // Auto-sync object-cache.php drop-in based on Redis status
        if ( ! empty( $config['redis_enabled'] ) ) {
            Uwb_Activator::copy_object_cache_dropin();
        } else {
            Uwb_Activator::remove_object_cache_dropin();
        }

        // Auto-sync browser caching rules to root .htaccess
        self::write_htaccess_browser_cache();

        // Write valid Post & Page IDs to whitelist JSON file
        self::write_valid_post_ids_json();
    }

    /**
     * Export all published Post and Page IDs to a static JSON file
     * to protect against random ID parameter DDoS attacks.
     */
    public static function write_valid_post_ids_json() {
        global $wpdb;
        $cache_dir = self::get_cache_dir();
        if ( ! file_exists( $cache_dir ) ) {
            @mkdir( $cache_dir, 0755, true );
        }

        $json_path = dirname( $cache_dir ) . '/uwb-valid-post-ids.json';

        // Query IDs of all published posts, pages, and custom post types
        $ids = $wpdb->get_col(
            "SELECT ID FROM {$wpdb->posts} 
             WHERE post_status = 'publish' 
               AND post_type NOT IN ('revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request')"
        );

        if ( ! is_array( $ids ) ) {
            $ids = array();
        }

        // Convert values to integer
        $ids = array_map( 'intval', $ids );

        @file_put_contents( $json_path, json_encode( $ids ) );
    }

    /**
     * Purge all cached files
     */
    public function purge_all() {
        $cache_dir = self::get_cache_dir();
        if ( file_exists( $cache_dir ) ) {
            $this->recursive_delete( $cache_dir, false );
        } else {
            @mkdir( $cache_dir, 0755, true );
        }

        // Also delete minified css/js cache folder
        $minify_dir = WP_CONTENT_DIR . '/cache/ultimate-wp-booster/minify';
        if ( file_exists( $minify_dir ) ) {
            $this->recursive_delete( $minify_dir, true );
        }
    }

    /**
     * Purge cache for a specific URL
     */
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
            // 1. Delete files in the main directory
            $files_to_delete = glob( $dir_path . '/index*.html*' );
            if ( is_array( $files_to_delete ) ) {
                foreach ( $files_to_delete as $file_path ) {
                    if ( file_exists( $file_path ) ) {
                        @unlink( $file_path );
                    }
                }
            }

            // 2. Delete files in user-specific subdirectories (e.g. user-*)
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
                    // Remove empty user directory
                    $this->remove_empty_dirs( $subdir );
                }
            }

            // Remove folder if it is empty
            $this->remove_empty_dirs( $dir_path );
        }
    }

    /**
     * Purge cache for a post when it is created or updated
     */
    public function purge_post_cache( $post_id, $post = null, $update = null ) {
        // Don't purge if it's a revision or auto-draft
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return;
        }

        if ( ! $post ) {
            $post = get_post( $post_id );
        }

        if ( ! $post || $post->post_status !== 'publish' ) {
            return;
        }

        // 1. Purge post URL cache
        $permalink = get_permalink( $post_id );
        if ( $permalink ) {
            $this->purge_url( $permalink );
        }

        // 2. Purge home page cache & invalidate homepage links transient
        $this->purge_url( home_url( '/' ) );
        Uwb_Preloader::invalidate_homepage_links_cache();

        // 3. Purge parent post cache if exists
        if ( $post->post_parent ) {
            $parent_permalink = get_permalink( $post->post_parent );
            if ( $parent_permalink ) {
                $this->purge_url( $parent_permalink );
            }
        }

        // 4. Purge author archive cache
        $author_link = get_author_posts_url( $post->post_author );
        if ( $author_link ) {
            $this->purge_url( $author_link );
        }

        // 5. Purge terms (categories, tags, custom taxonomies) archives
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

        // 6. Purge post type archive cache
        $archive_link = get_post_type_archive_link( $post->post_type );
        if ( $archive_link ) {
            $this->purge_url( $archive_link );
        }

        // 7. Purge always purge URLs
        $always_purge_raw = get_option( 'uwb_always_purge_urls', '' );
        if ( ! empty( $always_purge_raw ) ) {
            $always_purge_lines = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", "", $always_purge_raw ) ) ) );
            foreach ( $always_purge_lines as $url_line ) {
                if ( empty( $url_line ) ) {
                    continue;
                }
                // If it's a relative URL/path, prepend home_url()
                if ( strpos( $url_line, 'http://' ) !== 0 && strpos( $url_line, 'https://' ) !== 0 ) {
                    $url_line = home_url( '/' . ltrim( $url_line, '/' ) );
                }
                $this->purge_url( $url_line );
            }
        }
    }

    /**
     * Handle manual purge requests from admin dashboard
     */
    public function handle_purge_actions() {
        if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( isset( $_GET['action'] ) && $_GET['action'] === 'uwb_purge_cache' ) {
            check_admin_referer( 'uwb_purge_cache_action' );
            $this->purge_all();
            
            add_action( 'admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p><strong>Ultimate WP Booster:</strong> All cache cleared successfully!</p></div>';
            } );
        }
    }

    /**
     * Recursively delete directory or files
     */
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

    /**
     * Recursively remove empty directories up to the host level
     */
    private function remove_empty_dirs( $dir ) {
        $cache_dir = self::get_cache_dir();
        if ( strpos( $dir, $cache_dir ) !== 0 || $dir === $cache_dir ) {
            return;
        }

        if ( is_dir( $dir ) ) {
            $items = array_diff( scandir( $dir ), array( '.', '..' ) );
            if ( empty( $items ) ) {
                if ( @rmdir( $dir ) ) {
                    // Check parent dir
                    $parent = dirname( $dir );
                    $this->remove_empty_dirs( $parent );
                }
            }
        }
    }

    /**
     * Generate and write .htaccess browser caching rules for Apache/LiteSpeed
     */
    public static function write_htaccess_browser_cache() {
        $htaccess_path = ABSPATH . '.htaccess';
        $rules = array();
        
        $browser_cache_enabled = intval( get_option( 'uwb_browser_cache_enabled', 1 ) );
        
        if ( $browser_cache_enabled ) {
            $rules[] = '<IfModule mod_expires.c>';
            $rules[] = '    ExpiresActive On';
            $rules[] = '    ExpiresDefault "access plus 1 month"';
            
            // HTML
            $html_enabled = intval( get_option( 'uwb_browser_cache_html', 1 ) );
            $html_lifespan = intval( get_option( 'uwb_browser_cache_html_lifespan', 525600 ) ); // 365 days default
            if ( $html_enabled ) {
                $rules[] = '    ExpiresByType text/html "access plus ' . $html_lifespan . ' minutes"';
                $rules[] = '    ExpiresByType text/xml "access plus ' . $html_lifespan . ' minutes"';
            }
            
            // CSS
            $css_enabled = intval( get_option( 'uwb_browser_cache_css', 1 ) );
            $css_lifespan = intval( get_option( 'uwb_browser_cache_css_lifespan', 525600 ) );
            if ( $css_enabled ) {
                $rules[] = '    ExpiresByType text/css "access plus ' . $css_lifespan . ' minutes"';
            }
            
            // JS
            $js_enabled = intval( get_option( 'uwb_browser_cache_js', 1 ) );
            $js_lifespan = intval( get_option( 'uwb_browser_cache_js_lifespan', 525600 ) );
            if ( $js_enabled ) {
                $rules[] = '    ExpiresByType application/javascript "access plus ' . $js_lifespan . ' minutes"';
                $rules[] = '    ExpiresByType text/javascript "access plus ' . $js_lifespan . ' minutes"';
                $rules[] = '    ExpiresByType application/x-javascript "access plus ' . $js_lifespan . ' minutes"';
            }
            
            // Image
            $image_enabled = intval( get_option( 'uwb_browser_cache_image', 1 ) );
            $image_lifespan = intval( get_option( 'uwb_browser_cache_image_lifespan', 525600 ) );
            if ( $image_enabled ) {
                $rules[] = '    ExpiresByType image/jpeg "access plus ' . $image_lifespan . ' minutes"';
                $rules[] = '    ExpiresByType image/png "access plus ' . $image_lifespan . ' minutes"';
                $rules[] = '    ExpiresByType image/gif "access plus ' . $image_lifespan . ' minutes"';
                $rules[] = '    ExpiresByType image/webp "access plus ' . $image_lifespan . ' minutes"';
                $rules[] = '    ExpiresByType image/svg+xml "access plus ' . $image_lifespan . ' minutes"';
                $rules[] = '    ExpiresByType image/x-icon "access plus ' . $image_lifespan . ' minutes"';
            }
            
            // Font
            $font_enabled = intval( get_option( 'uwb_browser_cache_font', 1 ) );
            $font_lifespan = intval( get_option( 'uwb_browser_cache_font_lifespan', 525600 ) );
            if ( $font_enabled ) {
                $rules[] = '    ExpiresByType font/ttf "access plus ' . $font_lifespan . ' minutes"';
                $rules[] = '    ExpiresByType font/otf "access plus ' . $font_lifespan . ' minutes"';
                $rules[] = '    ExpiresByType font/woff "access plus ' . $font_lifespan . ' minutes"';
                $rules[] = '    ExpiresByType font/woff2 "access plus ' . $font_lifespan . ' minutes"';
                $rules[] = '    ExpiresByType application/vnd.ms-fontobject "access plus ' . $font_lifespan . ' minutes"';
            }
            
            // Other / Static resources
            $other_enabled = intval( get_option( 'uwb_browser_cache_other', 1 ) );
            $other_lifespan = intval( get_option( 'uwb_browser_cache_other_lifespan', 525600 ) );
            if ( $other_enabled ) {
                $rules[] = '    ExpiresByType application/pdf "access plus ' . $other_lifespan . ' minutes"';
                $rules[] = '    ExpiresByType audio/mpeg "access plus ' . $other_lifespan . ' minutes"';
                $rules[] = '    ExpiresByType video/mp4 "access plus ' . $other_lifespan . ' minutes"';
            }
            
            $rules[] = '</IfModule>';
        }
        
        require_once ABSPATH . 'wp-admin/includes/file.php';
        insert_with_markers( $htaccess_path, 'Ultimate WP Booster Browser Cache', $rules );
    }
}
