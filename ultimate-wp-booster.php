<?php
/**
 * Plugin Name: Ultimate WP Booster
 * Plugin URI:  https://github.com/tuend-work/ultimate-wp-booster
 * Description: Ultra-fast Static Cache and Sitemap Preloader. High-compatibility with rocket-nginx.
 * Version:     1.13.6
 * Author:      tuend-work
 * Author URI:  https://github.com/tuend-work
 * License:     GPL2
 * Text Domain: ultimate-wp-booster
 */

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

define( 'UWB_VERSION', '1.13.6' );
define( 'UWB_PLUGIN_FILE', __FILE__ );
define( 'UWB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

// 1. Include core files
require_once UWB_PLUGIN_DIR . 'github-updater.php';
require_once UWB_PLUGIN_DIR . 'includes/class-uwb-activator.php';
require_once UWB_PLUGIN_DIR . 'includes/class-uwb-deactivator.php';
require_once UWB_PLUGIN_DIR . 'includes/class-uwb-cache.php';
require_once UWB_PLUGIN_DIR . 'includes/class-uwb-preloader.php';
require_once UWB_PLUGIN_DIR . 'includes/class-uwb-admin.php';

// 1.5. Include compatibility layers
require_once UWB_PLUGIN_DIR . 'compatibility/wp-rocket.php';

// 1.8. Automated Version Upgrade Check (Sync drop-ins on version bump)
add_action( 'init', 'uwb_check_upgrade' );
function uwb_check_upgrade() {
    $db_version = get_option( 'uwb_version' );
    $dropin_file = WP_CONTENT_DIR . '/advanced-cache.php';
    if ( $db_version !== UWB_VERSION || ! file_exists( $dropin_file ) ) {
        Uwb_Activator::copy_advanced_cache_dropin();
        Uwb_Activator::copy_object_cache_dropin();
        Uwb_Activator::toggle_wp_cache( true );
        if ( class_exists( 'Uwb_Cache' ) ) {
            Uwb_Cache::write_config_file();
        }
        update_option( 'uwb_version', UWB_VERSION );
    }
}

// 2. Register activation & deactivation hooks
register_activation_hook( __FILE__, array( 'Uwb_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Uwb_Deactivator', 'deactivate' ) );

// 3. Register custom cron schedules
add_filter( 'cron_schedules', 'uwb_add_cron_schedules' );
function uwb_add_cron_schedules( $schedules ) {
    if ( ! isset( $schedules['every_minute'] ) ) {
        $schedules['every_minute'] = array(
            'interval' => 60,
            'display'  => 'Every minute (1 min)'
        );
    }
    return $schedules;
}

// 4. Initialize Core Classes
function run_ultimate_wp_booster() {
    // Initialize Cache Engine
    $uwb_cache = new Uwb_Cache();

    // Initialize Preloader Engine
    $uwb_preloader = new Uwb_Preloader();

    // Initialize Admin Interface
    if ( is_admin() ) {
        $uwb_admin = new Uwb_Admin();
        
        // Initialize GitHub Updater
        new Uwb_Github_Updater( __FILE__ );
    }
}
run_ultimate_wp_booster();

// Generate secret key if not exists (runs after pluggable functions are loaded)
add_action( 'init', 'uwb_init_preload_secret_key', 9 );
function uwb_init_preload_secret_key() {
    if ( ! get_option( 'uwb_preload_secret_key' ) ) {
        update_option( 'uwb_preload_secret_key', wp_generate_password( 24, false, false ) );
    }
}

// 5.1. Heartbeat API Control
add_action( 'init', 'uwb_heartbeat_control', 10 );
function uwb_heartbeat_control() {
    $mode = get_option( 'uwb_heartbeat_control', 'default' );
    if ( $mode === 'default' ) {
        return;
    }

    if ( $mode === 'disable_all' ) {
        add_action( 'init', function() {
            wp_deregister_script( 'heartbeat' );
        }, 1 );
        return;
    }

    if ( $mode === 'disable_frontend' && ! is_admin() ) {
        add_action( 'wp_enqueue_scripts', function() {
            wp_deregister_script( 'heartbeat' );
        }, 1 );
        return;
    }

    if ( $mode === 'reduce' ) {
        $interval = max( 15, intval( get_option( 'uwb_heartbeat_interval', 60 ) ) );
        add_filter( 'heartbeat_settings', function( $settings ) use ( $interval ) {
            $settings['interval'] = $interval;
            return $settings;
        } );
    }
}

// 5.2. Admin Bar Menu Customization
add_action( 'admin_bar_menu', 'uwb_add_admin_bar_nodes', 999 );
function uwb_add_admin_bar_nodes( $wp_admin_bar ) {
    $can_manage = current_user_can( 'manage_options' );
    $can_edit_current_post = false;
    $current_post_id = 0;

    if ( ! is_admin() && is_singular() ) {
        $current_post_id = get_the_ID();
        if ( $current_post_id && current_user_can( 'edit_post', $current_post_id ) ) {
            $can_edit_current_post = true;
        }
    }

    if ( ! $can_manage && ! $can_edit_current_post ) {
        return;
    }

    // Add main node
    $wp_admin_bar->add_node( array(
        'id'    => 'uwb-admin-bar',
        'title' => 'WP Booster',
        'href'  => $can_manage ? admin_url( 'admin.php?page=ultimate-wp-booster' ) : null,
    ) );

    // Add sub-node: Purge This URL (only on frontend)
    if ( ! is_admin() ) {
        $current_url = ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        $clean_url = strtok( $current_url, '?' );
        
        $action_url = 'admin-post.php?action=uwb_purge_url&url=' . urlencode( $clean_url );
        if ( $current_post_id > 0 ) {
            $action_url .= '&post_id=' . $current_post_id;
        }
        $purge_url = wp_nonce_url( admin_url( $action_url ), 'uwb_purge_url_action' );
        
        $wp_admin_bar->add_node( array(
            'id'     => 'uwb-purge-url',
            'parent' => 'uwb-admin-bar',
            'title'  => 'Purge This URL',
            'href'   => $purge_url,
        ) );
    }

    // Stop here if user only has permission to edit current post
    if ( ! $can_manage ) {
        return;
    }

    // Add sub-node: Clear Cache Page (only clear, no preload)
    $clear_cache_page_url = wp_nonce_url( admin_url( 'admin-post.php?action=uwb_clear_cache_page' ), 'uwb_clear_cache_page_action' );
    $wp_admin_bar->add_node( array(
        'id'     => 'uwb-clear-cache-page',
        'parent' => 'uwb-admin-bar',
        'title'  => 'Clear Cache Page',
        'href'   => $clear_cache_page_url,
    ) );

    // Add sub-node: Flush OPCache
    $flush_op_url = wp_nonce_url( admin_url( 'admin-post.php?action=uwb_flush_opcache' ), 'uwb_flush_opcache_action' );
    $wp_admin_bar->add_node( array(
        'id'     => 'uwb-flush-opcache',
        'parent' => 'uwb-admin-bar',
        'title'  => 'Clear OPCache',
        'href'   => $flush_op_url,
    ) );

    if ( wp_using_ext_object_cache() ) {
        // Add sub-node: Flush Object Cache
        $flush_oc_url = wp_nonce_url( admin_url( 'admin-post.php?action=uwb_flush_object_cache' ), 'uwb_flush_object_cache_action' );
        $wp_admin_bar->add_node( array(
            'id'     => 'uwb-flush-object-cache',
            'parent' => 'uwb-admin-bar',
            'title'  => 'Clear Object Cache',
            'href'   => $flush_oc_url,
        ) );

        // Add Global Cache Statistics to Admin Bar
        global $wp_object_cache;
        $hits = 0; $misses = 0;
        if ( isset( $wp_object_cache->cache_hits ) ) $hits = intval( $wp_object_cache->cache_hits );
        if ( isset( $wp_object_cache->cache_misses ) ) $misses = intval( $wp_object_cache->cache_misses );
        $total_req = $hits + $misses;
        $hit_ratio = $total_req > 0 ? round( ( $hits / $total_req ) * 100, 1 ) : 0;

        $wp_admin_bar->add_node( array(
            'id'     => 'uwb-oc-stats',
            'parent' => 'uwb-admin-bar',
            'title'  => sprintf( 'Object Cache Stats (Hit Ratio: %s%%)', $hit_ratio ),
            'href'   => admin_url( 'admin.php?page=ultimate-wp-booster' ),
        ) );

        $wp_admin_bar->add_node( array(
            'id'     => 'uwb-oc-hits',
            'parent' => 'uwb-oc-stats',
            'title'  => sprintf( 'Hits: %s', number_format( $hits ) ),
            'href'   => '#',
        ) );

        $wp_admin_bar->add_node( array(
            'id'     => 'uwb-oc-misses',
            'parent' => 'uwb-oc-stats',
            'title'  => sprintf( 'Misses: %s', number_format( $misses ) ),
            'href'   => '#',
        ) );

        $wp_admin_bar->add_node( array(
            'id'     => 'uwb-oc-total',
            'parent' => 'uwb-oc-stats',
            'title'  => sprintf( 'Total Requests: %s', number_format( $total_req ) ),
            'href'   => '#',
        ) );
    }

    // Add sub-node: Flush All & Preload Cache (above settings)
    $flush_all_preload_url = wp_nonce_url( admin_url( 'admin-post.php?action=uwb_flush_all_preload' ), 'uwb_flush_all_preload_action' );
    $wp_admin_bar->add_node( array(
        'id'     => 'uwb-flush-all-preload',
        'parent' => 'uwb-admin-bar',
        'title'  => 'Flush All & Preload Cache',
        'href'   => $flush_all_preload_url,
    ) );

    // Add sub-node: Settings
    $wp_admin_bar->add_node( array(
        'id'     => 'uwb-settings',
        'parent' => 'uwb-admin-bar',
        'title'  => 'Settings',
        'href'   => admin_url( 'admin.php?page=ultimate-wp-booster' ),
    ) );
}

// 5.5. Add Purge Link to Row Actions in Post List
add_filter( 'post_row_actions', 'uwb_add_post_row_actions', 10, 2 );
add_filter( 'page_row_actions', 'uwb_add_post_row_actions', 10, 2 );
function uwb_add_post_row_actions( $actions, $post ) {
    if ( current_user_can( 'manage_options' ) || current_user_can( 'edit_post', $post->ID ) ) {
        $clean_url = get_permalink( $post->ID );
        $purge_url = wp_nonce_url( 
            admin_url( 'admin-post.php?action=uwb_purge_url&url=' . urlencode( $clean_url ) . '&post_id=' . $post->ID ), 
            'uwb_purge_url_action' 
        );
        $actions['uwb_purge'] = '<a href="' . esc_url( $purge_url ) . '" title="' . esc_attr__( 'Purge cache for this post', 'ultimate-wp-booster' ) . '" style="color:#bc00dd;font-weight:600;">' . esc_html__( 'Xóa cache', 'ultimate-wp-booster' ) . '</a>';
    }
    return $actions;
}

// 6. Handle Admin Bar Actions
add_action( 'admin_post_uwb_purge_url', 'uwb_handle_admin_bar_purge_url' );
function uwb_handle_admin_bar_purge_url() {
    $post_id = isset( $_GET['post_id'] ) ? intval( $_GET['post_id'] ) : 0;
    
    $can_purge = false;
    if ( current_user_can( 'manage_options' ) ) {
        $can_purge = true;
    } elseif ( $post_id > 0 && current_user_can( 'edit_post', $post_id ) ) {
        $can_purge = true;
    }

    if ( ! $can_purge ) {
        wp_die( 'Permission denied.' );
    }

    check_admin_referer( 'uwb_purge_url_action' );

    $url = isset( $_GET['url'] ) ? esc_url_raw( urldecode( $_GET['url'] ) ) : '';
    
    if ( $post_id > 0 && function_exists( 'rocket_clean_post' ) ) {
        rocket_clean_post( $post_id );
    } elseif ( ! empty( $url ) ) {
        $uwb_cache = new Uwb_Cache();
        $uwb_cache->purge_url( $url );
    }

    // Redirect back to referrer
    wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url( '/' ) );
    exit;
}

add_action( 'admin_post_uwb_clear_cache_page', 'uwb_handle_admin_bar_clear_cache_page' );
function uwb_handle_admin_bar_clear_cache_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Permission denied.' );
    }

    check_admin_referer( 'uwb_clear_cache_page_action' );

    // Purge all cache
    $uwb_cache = new Uwb_Cache();
    $uwb_cache->purge_all();

    // Redirect back to settings page or referrer
    $referer = wp_get_referer();
    if ( $referer && strpos( $referer, 'admin.php?page=ultimate-wp-booster' ) !== false ) {
        wp_safe_redirect( add_query_arg( 'uwb_msg', 'cache_cleared', $referer ) );
    } else {
        wp_safe_redirect( admin_url( 'admin.php?page=ultimate-wp-booster&uwb_msg=cache_cleared' ) );
    }
    exit;
}

add_action( 'admin_post_uwb_flush_all_preload', 'uwb_handle_admin_bar_flush_all_preload' );
function uwb_handle_admin_bar_flush_all_preload() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Permission denied.' );
    }

    check_admin_referer( 'uwb_flush_all_preload_action' );

    // 1. Purge all page cache (fast – just deletes files)
    $uwb_cache = new Uwb_Cache();
    $uwb_cache->purge_all();

    // 1.1 Clear preloading queue table immediately so UI resets to 0
    global $wpdb;
    $table_name = $wpdb->prefix . 'ultimate_wp_booster_queue';
    $wpdb->query( "TRUNCATE TABLE {$table_name}" );
    update_option( 'uwb_preload_running', 1 );

    // 2. Flush OPCache
    if ( function_exists( 'opcache_reset' ) ) {
        @opcache_reset();
    }

    // 3. Flush Object Cache
    uwb_flush_object_cache_internal();

    // 4. Schedule start_preload() as an async single-event cron so that
    //    sitemap parsing (which makes multiple HTTP requests) does NOT run
    //    inside this admin-bar HTTP request and cause 502/timeout on large sites.
    wp_clear_scheduled_hook( 'uwb_start_preload_async' );
    wp_schedule_single_event( time(), 'uwb_start_preload_async' );

    // Redirect back to settings page or referrer
    $referer = wp_get_referer();
    if ( $referer && strpos( $referer, 'admin.php?page=ultimate-wp-booster' ) !== false ) {
        wp_safe_redirect( add_query_arg( 'uwb_msg', 'preload_started', $referer ) );
    } else {
        wp_safe_redirect( admin_url( 'admin.php?page=ultimate-wp-booster&uwb_msg=preload_started' ) );
    }
    exit;
}

function uwb_flush_object_cache_internal() {
    // Try direct flush using client if class exists
    $oc_type = intval( get_option( 'uwb_redis_enabled', 0 ) );
    if ( $oc_type === 2 ) {
        if ( class_exists( 'Memcached' ) ) {
            $mc_host = get_option( 'uwb_redis_host', '127.0.0.1' );
            $mc_port = intval( get_option( 'uwb_redis_port', 11211 ) );
            if ( $mc_port === 6379 ) {
                $mc_port = 11211;
            }
            $m = new Memcached();
            $m->addServer( $mc_host, $mc_port );
            @$m->flush();
        }
    } else {
        if ( class_exists( 'Redis' ) ) {
            $conn_type = get_option( 'uwb_redis_conn_type', 'tcp' );
            $redis_host = get_option( 'uwb_redis_host', '127.0.0.1' );
            $redis_port = get_option( 'uwb_redis_port', 6379 );
            $redis_socket = get_option( 'uwb_redis_socket', '' );
            $redis_password = get_option( 'uwb_redis_password', '' );
            $redis_db = get_option( 'uwb_redis_db', 0 );

            $redis = new Redis();
            try {
                if ( $conn_type === 'socket' && ! empty( $redis_socket ) ) {
                    $connected = @$redis->connect( $redis_socket );
                } else {
                    $connected = @$redis->connect( $redis_host, $redis_port, 1.0 );
                }

                if ( $connected ) {
                    if ( ! empty( $redis_password ) ) {
                        @$redis->auth( $redis_password );
                    }
                    if ( $redis_db > 0 ) {
                        @$redis->select( $redis_db );
                    }
                    $redis->flushDB();
                }
            } catch ( Exception $e ) {
                // fall through
            }
        }
    }

    wp_cache_flush();
}

add_action( 'admin_post_uwb_flush_object_cache', 'uwb_handle_admin_bar_flush_object_cache' );
function uwb_handle_admin_bar_flush_object_cache() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Permission denied.' );
    }

    check_admin_referer( 'uwb_flush_object_cache_action' );

    uwb_flush_object_cache_internal();

    // Redirect back to referrer
    wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url( '/' ) );
    exit;
}

// 7. Register WP-CLI command
if ( defined( 'WP_CLI' ) && WP_CLI ) {
    class Uwb_CLI_Preload {
        public function run( $args, $assoc_args ) {
            $batch_size = isset( $assoc_args['batch-size'] ) ? intval( $assoc_args['batch-size'] ) : 0;
            if ( class_exists( 'Uwb_Preloader' ) ) {
                $preloader = new Uwb_Preloader();
                $processed = $preloader->run_preload_batch( $batch_size );
                WP_CLI::success( "Preloaded {$processed} URLs successfully!" );
            } else {
                WP_CLI::error( "Preloader class not found." );
            }
        }
    }
    WP_CLI::add_command( 'uwb-preload', 'Uwb_CLI_Preload' );
}

add_action( 'init', 'uwb_handle_external_cron_trigger' );
function uwb_handle_external_cron_trigger() {
    if ( isset( $_GET['uwb_preload_key'] ) ) {
        $saved_key = get_option( 'uwb_preload_secret_key' );
        if ( empty( $saved_key ) ) {
            wp_die( 'Secret key is empty.' );
        }
        if ( hash_equals( $saved_key, $_GET['uwb_preload_key'] ) ) {
            if ( class_exists( 'Uwb_Preloader' ) ) {
                global $wpdb;
                $table_name = $wpdb->prefix . 'ultimate_wp_booster_queue';

                $is_browser = ( isset( $_SERVER['HTTP_ACCEPT'] ) && strpos( $_SERVER['HTTP_ACCEPT'], 'text/html' ) !== false );

                // Check for action=crawl request to start crawling sitemaps in background
                $action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';
                if ( $action === 'crawl' ) {
                    if ( ! get_transient( 'uwb_populating_queue' ) ) {
                        wp_clear_scheduled_hook( 'uwb_start_preload_async' );
                        wp_schedule_single_event( time(), 'uwb_start_preload_async' );
                        update_option( 'uwb_preload_running', 1 );
                        if ( function_exists( 'spawn_cron' ) ) {
                            spawn_cron();
                        }
                        if ( $is_browser ) {
                            echo "<pre style='white-space: pre-wrap; font-family: monospace;'>OK: Sitemap crawl scheduled in background.</pre>";
                        } else {
                            header( 'Content-Type: text/plain; charset=UTF-8' );
                            echo "OK: Sitemap crawl scheduled in background.";
                        }
                        exit;
                    } else {
                        if ( $is_browser ) {
                            echo "<pre style='white-space: pre-wrap; font-family: monospace;'>ERROR: Sitemap crawler is already running.</pre>";
                        } else {
                            header( 'Content-Type: text/plain; charset=UTF-8' );
                            echo "ERROR: Sitemap crawler is already running.";
                        }
                        exit;
                    }
                }

                // If total queue size is 0, auto schedule a crawl so it starts populating
                $total_count = intval( $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" ) );
                if ( $total_count === 0 ) {
                    if ( ! get_transient( 'uwb_populating_queue' ) ) {
                        wp_clear_scheduled_hook( 'uwb_start_preload_async' );
                        wp_schedule_single_event( time(), 'uwb_start_preload_async' );
                        update_option( 'uwb_preload_running', 1 );
                        if ( function_exists( 'spawn_cron' ) ) {
                            spawn_cron();
                        }
                    }
                }

                // Check if there are any pending or retriable failed URLs
                $pending_count = intval( $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE status = 'pending' OR (status = 'failed' AND attempts < 3)" ) );

                if ( $is_browser ) {
                    echo "<pre style='white-space: pre-wrap; font-family: monospace; word-wrap: break-word;'>";
                } else {
                    header( 'Content-Type: text/plain; charset=UTF-8' );
                }

                if ( $pending_count === 0 ) {
                    // Queue is completed! Show all completed preload URLs
                    $completed_urls = $wpdb->get_col( "SELECT url FROM {$table_name} WHERE status = 'completed' ORDER BY priority ASC, id ASC" );
                    if ( empty( $completed_urls ) ) {
                        echo "OK: Preload queue is empty or sitemap is still being scanned. Crawl task was triggered.";
                    } else {
                        echo "OK: Queue completed. Listing completed URLs:\n";
                        foreach ( $completed_urls as $url ) {
                            echo esc_url( $url ) . "\n";
                        }
                    }
                } else {
                    $preloader = new Uwb_Preloader();
                    $result = $preloader->run_preload_batch();
                    $processed = is_array( $result ) ? $result['count'] : 0;
                    $urls = is_array( $result ) ? $result['urls'] : array();

                    echo "OK: Preloaded {$processed} URLs.\n";
                    if ( ! empty( $urls ) ) {
                        foreach ( $urls as $url ) {
                            echo esc_url( $url ) . "\n";
                        }
                    }
                }

                if ( $is_browser ) {
                    echo "</pre>";
                }
            } else {
                if ( $is_browser ) {
                    echo "<pre style='white-space: pre-wrap; font-family: monospace;'>ERROR: Preloader class not found.</pre>";
                } else {
                    echo "ERROR: Preloader class not found.";
                }
            }
            exit;
        } else {
            wp_die( 'Invalid secret key.' );
        }
    }
}

// Handler for Flushing OPcache from Admin Bar
add_action( 'admin_post_uwb_flush_opcache', 'uwb_handle_admin_bar_flush_opcache' );
function uwb_handle_admin_bar_flush_opcache() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Permission denied.' );
    }

    check_admin_referer( 'uwb_flush_opcache_action' );

    if ( function_exists( 'opcache_reset' ) ) {
        @opcache_reset();
    }

    $referer = wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=ultimate-wp-booster' );
    wp_safe_redirect( add_query_arg( 'uwb_opcache_flushed', '1', $referer ) );
    exit;
}
