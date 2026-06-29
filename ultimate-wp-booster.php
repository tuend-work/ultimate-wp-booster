<?php
/**
 * Plugin Name: Ultimate WP Booster
 * Plugin URI:  https://github.com/tuend-work/ultimate-wp-booster
 * Description: Ultra-fast Static Cache and Sitemap Preloader. High-compatibility with rocket-nginx.
 * Version:     1.4.79
 * Author:      tuend-work
 * Author URI:  https://github.com/tuend-work
 * License:     GPL2
 * Text Domain: ultimate-wp-booster
 */

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

define( 'UWB_VERSION', '1.4.79' );
define( 'UWB_PLUGIN_FILE', __FILE__ );
define( 'UWB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

// 1. Include core files
require_once UWB_PLUGIN_DIR . 'github-updater.php';
require_once UWB_PLUGIN_DIR . 'includes/class-uwb-activator.php';
require_once UWB_PLUGIN_DIR . 'includes/class-uwb-deactivator.php';
require_once UWB_PLUGIN_DIR . 'includes/class-uwb-cache.php';
require_once UWB_PLUGIN_DIR . 'includes/class-uwb-preloader.php';
require_once UWB_PLUGIN_DIR . 'includes/class-uwb-admin.php';

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

// 5. Admin Bar Menu Customization
add_action( 'admin_bar_menu', 'uwb_add_admin_bar_nodes', 999 );
function uwb_add_admin_bar_nodes( $wp_admin_bar ) {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    // Add main node
    $wp_admin_bar->add_node( array(
        'id'    => 'uwb-admin-bar',
        'title' => 'WP Booster',
        'href'  => admin_url( 'options-general.php?page=ultimate-wp-booster' ),
    ) );

    // Add sub-node: Purge This URL (only on frontend)
    if ( ! is_admin() ) {
        $current_url = ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        $clean_url = strtok( $current_url, '?' );
        $purge_url = wp_nonce_url( admin_url( 'admin-post.php?action=uwb_purge_url&url=' . urlencode( $clean_url ) ), 'uwb_purge_url_action' );
        
        $wp_admin_bar->add_node( array(
            'id'     => 'uwb-purge-url',
            'parent' => 'uwb-admin-bar',
            'title'  => 'Purge This URL',
            'href'   => $purge_url,
        ) );
    }

    // Add sub-node: Clear & Preload Cache
    $clear_preload_url = wp_nonce_url( admin_url( 'admin-post.php?action=uwb_clear_preload' ), 'uwb_clear_preload_action' );
    $wp_admin_bar->add_node( array(
        'id'     => 'uwb-clear-preload',
        'parent' => 'uwb-admin-bar',
        'title'  => 'Clear & Preload Cache',
        'href'   => $clear_preload_url,
    ) );

    if ( wp_using_ext_object_cache() ) {
        // Add sub-node: Flush Object Cache
        $flush_oc_url = wp_nonce_url( admin_url( 'admin-post.php?action=uwb_flush_object_cache' ), 'uwb_flush_object_cache_action' );
        $wp_admin_bar->add_node( array(
            'id'     => 'uwb-flush-object-cache',
            'parent' => 'uwb-admin-bar',
            'title'  => 'Flush Object Cache',
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
            'href'   => admin_url( 'options-general.php?page=ultimate-wp-booster' ),
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

    // Add sub-node: Settings
    $wp_admin_bar->add_node( array(
        'id'     => 'uwb-settings',
        'parent' => 'uwb-admin-bar',
        'title'  => 'Settings',
        'href'   => admin_url( 'options-general.php?page=ultimate-wp-booster' ),
    ) );
}

// 6. Handle Admin Bar Actions
add_action( 'admin_post_uwb_purge_url', 'uwb_handle_admin_bar_purge_url' );
function uwb_handle_admin_bar_purge_url() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Permission denied.' );
    }

    check_admin_referer( 'uwb_purge_url_action' );

    $url = isset( $_GET['url'] ) ? esc_url_raw( urldecode( $_GET['url'] ) ) : '';
    
    if ( ! empty( $url ) ) {
        $uwb_cache = new Uwb_Cache();
        $uwb_cache->purge_url( $url );
    }

    // Redirect back to referrer
    wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url( '/' ) );
    exit;
}

add_action( 'admin_post_uwb_clear_preload', 'uwb_handle_admin_bar_clear_preload' );
function uwb_handle_admin_bar_clear_preload() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Permission denied.' );
    }

    check_admin_referer( 'uwb_clear_preload_action' );

    // Purge all cache
    $uwb_cache = new Uwb_Cache();
    $uwb_cache->purge_all();

    // Start preloader process
    $uwb_preloader = new Uwb_Preloader();
    $uwb_preloader->start_preload();

    // Redirect back to settings page or referrer
    $referer = wp_get_referer();
    if ( $referer && strpos( $referer, 'options-general.php?page=ultimate-wp-booster' ) !== false ) {
        wp_safe_redirect( add_query_arg( 'uwb_msg', 'preload_started', $referer ) );
    } else {
        wp_safe_redirect( admin_url( 'options-general.php?page=ultimate-wp-booster' ) );
    }
    exit;
}

add_action( 'admin_post_uwb_flush_object_cache', 'uwb_handle_admin_bar_flush_object_cache' );
function uwb_handle_admin_bar_flush_object_cache() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Permission denied.' );
    }

    check_admin_referer( 'uwb_flush_object_cache_action' );

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

                // Check if there are any pending or retriable failed URLs
                $pending_count = intval( $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE status = 'pending' OR (status = 'failed' AND attempts < 3)" ) );

                if ( $pending_count === 0 ) {
                    // Queue is completed! Show all completed preload URLs
                    $completed_urls = $wpdb->get_col( "SELECT url FROM {$table_name} WHERE status = 'completed' ORDER BY priority ASC, id ASC" );
                    header( 'Content-Type: text/plain; charset=UTF-8' );
                    if ( empty( $completed_urls ) ) {
                        echo "OK: Preload queue is empty or no URLs have been completed yet.";
                    } else {
                        foreach ( $completed_urls as $url ) {
                            echo esc_url( $url ) . "\n";
                        }
                    }
                } else {
                    $preloader = new Uwb_Preloader();
                    $result = $preloader->run_preload_batch();
                    $processed = is_array( $result ) ? $result['count'] : 0;
                    $urls = is_array( $result ) ? $result['urls'] : array();

                    header( 'Content-Type: text/plain; charset=UTF-8' );
                    echo "OK: Preloaded {$processed} URLs.\n";
                    if ( ! empty( $urls ) ) {
                        foreach ( $urls as $url ) {
                            echo esc_url( $url ) . "\n";
                        }
                    }
                }
            } else {
                echo "ERROR: Preloader class not found.";
            }
            exit;
        } else {
            wp_die( 'Invalid secret key.' );
        }
    }
}
