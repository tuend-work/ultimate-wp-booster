<?php
/**
 * Plugin Name: Ultimate WordPress Booster
 * Plugin URI:  https://github.com/tuend-work/ultimate-wp-booster
 * Description: Ultra-fast Static Cache and Sitemap Preloader. High-compatibility with rocket-nginx.
 * Version:     1.1.5
 * Author:      tuend-work
 * Author URI:  https://github.com/tuend-work
 * License:     GPL2
 * Text Domain: ultimate-wp-booster
 */

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

define( 'UWB_VERSION', '1.1.5' );
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
