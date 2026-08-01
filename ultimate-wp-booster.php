<?php
/**
 * Plugin Name: Ultimate WP Booster
 * Plugin URI:  https://github.com/tuend-work/ultimate-wp-booster
 * Description: Ultra-fast Static Cache and Sitemap Preloader. High-compatibility with rocket-nginx.
 * Version:     1.22.1
 * Author:      tuend-work
 * Author URI:  https://github.com/tuend-work
 * License:     GPL2
 * Text Domain: ultimate-wp-booster
 */

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

// Plugin Constants
define( 'UWB_VERSION', '1.22.1' );
define( 'UWB_PLUGIN_FILE', __FILE__ );
define( 'UWB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'UWB_INC_DIR', UWB_PLUGIN_DIR . 'inc/' );

// 0. Unified PSR-4 Autoloader for Ultimate_WP_Booster namespace
spl_autoload_register( function( $class ) {
    $prefix = 'Ultimate_WP_Booster\\';
    $len    = strlen( $prefix );

    if ( strncmp( $prefix, $class, $len ) !== 0 ) {
        return;
    }

    $relative_class = substr( $class, $len );

    // Check Dependencies namespace subfolder
    if ( strpos( $relative_class, 'Dependencies\\' ) === 0 ) {
        $dep_relative = substr( $relative_class, strlen( 'Dependencies\\' ) );
        $file = UWB_PLUGIN_DIR . 'includes/Dependencies/' . str_replace( '\\', '/', $dep_relative ) . '.php';
        if ( file_exists( $file ) ) {
            require_once $file;
            return;
        }
    }

    // Default mapping to inc/ directory
    $file = UWB_INC_DIR . str_replace( '\\', '/', $relative_class ) . '.php';
    if ( file_exists( $file ) ) {
        require_once $file;
    }
} );

// 1. Include core legacy engine files
require_once UWB_PLUGIN_DIR . 'github-updater.php';
require_once UWB_PLUGIN_DIR . 'includes/class-uwb-activator.php';
require_once UWB_PLUGIN_DIR . 'includes/class-uwb-deactivator.php';
require_once UWB_PLUGIN_DIR . 'includes/class-uwb-cache.php';
require_once UWB_PLUGIN_DIR . 'includes/class-uwb-preloader.php';
require_once UWB_PLUGIN_DIR . 'includes/class-uwb-admin.php';

// 2. Perform Requirements Check before bootstrapping plugin
$uwb_requirements = new Ultimate_WP_Booster\Engine\Requirements\Check( '7.4', '5.6' );
if ( $uwb_requirements->check() ) {
    require_once UWB_INC_DIR . 'main.php';
}
unset( $uwb_requirements );
