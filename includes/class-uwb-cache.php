<?php
/**
 * Cache Management Engine
 */

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class Uwb_Cache {

    public function __construct() {
        // Purge cache hooks on content changes
        add_action( 'save_post', array( $this, 'purge_post_cache' ), 10, 3 );
        add_action( 'wp_update_nav_menu', array( $this, 'purge_all' ) );
        add_action( 'switch_theme', array( $this, 'purge_all' ) );
        add_action( 'update_option_sidebars_widgets', array( $this, 'purge_all' ) );

        // Admin actions for purging
        add_action( 'admin_init', array( $this, 'handle_purge_actions' ) );

        // Cron actions for cleaning expired cache
        add_action( 'uwb_clean_expired_cache', array( $this, 'clean_expired_cache' ) );
        add_action( 'init', array( $this, 'schedule_cleanup_cron' ) );
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
        $lifespan_hours = floatval( get_option( 'uwb_cache_lifespan', 10 ) );
        $lifespan_seconds = $lifespan_hours > 0 ? intval( $lifespan_hours * 3600 ) : 0;
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
                if ( 
                    $filename === 'index.html' || 
                    $filename === 'index-https.html' || 
                    $filename === 'index.html_gzip' || 
                    $filename === 'index-https.html_gzip' 
                ) {
                    // Check if this file is in a logged-in user folder (e.g. parent folder name starts with "user-")
                    $parent_folder = basename( dirname( $path ) );
                    $is_user_cache = ( strpos( $parent_folder, 'user-' ) === 0 );

                    if ( $is_user_cache ) {
                        // User cache is always capped at 10 minutes (600 seconds)
                        $file_lifespan = 600;
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
        
        $lifespan_hours = floatval( get_option( 'uwb_cache_lifespan', 10 ) );
        $lifespan_seconds = intval( $lifespan_hours * 3600 );

        $exclusions_raw = get_option( 'uwb_excluded_urls', '' );
        $exclusions = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", "", $exclusions_raw ) ) ) );

        $timezone = get_option( 'timezone_string' );
        if ( empty( $timezone ) ) {
            $timezone = get_option( 'gmt_offset', 0 );
        }

        $browser_cache_hours = floatval( get_option( 'uwb_browser_cache_lifespan', 1.0 ) );
        $browser_cache_seconds = intval( $browser_cache_hours * 3600 );

        $ignored_query_raw = get_option( 'uwb_ignored_query', "utm_source\nutm_medium\nutm_campaign\nfbclid\ngclid\nage-verified" );
        $ignored_queries = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", "", $ignored_query_raw ) ) ) );

        $config = array(
            'cache_lifespan'         => $lifespan_seconds,
            'cache_logged_in'        => intval( get_option( 'uwb_cache_logged_in', 0 ) ),
            'browser_cache_enabled'  => intval( get_option( 'uwb_browser_cache_enabled', 1 ) ),
            'browser_cache_lifespan' => $browser_cache_seconds,
            'excluded_urls'          => array_values( $exclusions ),
            'ignored_query'          => array_values( $ignored_queries ),
            'timezone'               => $timezone,
            'redis_enabled'          => intval( get_option( 'uwb_redis_enabled', 0 ) ),
            'redis_conn_type'        => get_option( 'uwb_redis_conn_type', 'tcp' ),
            'redis_host'             => get_option( 'uwb_redis_host', '127.0.0.1' ),
            'redis_port'             => intval( get_option( 'uwb_redis_port', 6379 ) ),
            'redis_socket'           => get_option( 'uwb_redis_socket', '/var/run/redis/redis.sock' ),
            'redis_password'         => get_option( 'uwb_redis_password', '' ),
            'redis_db'               => intval( get_option( 'uwb_redis_db', 0 ) )
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
    }

    /**
     * Purge all cached files
     */
    public function purge_all() {
        $cache_dir = self::get_cache_dir();
        if ( file_exists( $cache_dir ) ) {
            $this->recursive_delete( $cache_dir );
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
            $files = array(
                'index-https.html',
                'index-https.html_gzip',
                'index.html',
                'index.html_gzip'
            );

            // 1. Delete files in the main directory
            foreach ( $files as $file ) {
                $file_path = $dir_path . '/' . $file;
                if ( file_exists( $file_path ) ) {
                    @unlink( $file_path );
                }
            }

            // 2. Delete files in user-specific subdirectories (e.g. user-*)
            $subdirs = glob( $dir_path . '/user-*', GLOB_ONLYDIR );
            if ( is_array( $subdirs ) ) {
                foreach ( $subdirs as $subdir ) {
                    foreach ( $files as $file ) {
                        $file_path = $subdir . '/' . $file;
                        if ( file_exists( $file_path ) ) {
                            @unlink( $file_path );
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

        // 2. Purge home page cache
        $this->purge_url( home_url( '/' ) );

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
    private function recursive_delete( $dir ) {
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
                $this->recursive_delete( $path );
            } else {
                @unlink( $path );
            }
        }

        @rmdir( $dir );
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
}
