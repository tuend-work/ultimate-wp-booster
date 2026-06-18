<?php
/**
 * Plugin Name: Ultimate WordPress Booster
 * Plugin URI:  https://github.com/tuend-work/ultimate-wp-booster
 * Description: Ultra-fast Static Cache and Sitemap Preloader. High-compatibility with rocket-nginx.
 * Version:     1.0.0
 * Author:      tuend-work
 * Author URI:  https://github.com/tuend-work
 * License:     GPL2
 * Text Domain: ultimate-wp-booster
 */

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

define( 'UWB_VERSION', '1.0.0' );
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
            'display'  => 'Mỗi phút (1 min)'
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
