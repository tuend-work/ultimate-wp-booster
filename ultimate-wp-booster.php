<?php
/**
 * Plugin Name: Ultimate WP Booster
 * Plugin URI:  https://github.com/tuend-work/ultimate-wp-booster
 * Description: Ultra-fast Static Cache and Sitemap Preloader. High-compatibility with rocket-nginx.
 * Version:     2.1.0
 * Author:      tuend-work
 * Author URI:  https://github.com/tuend-work
 * License:     GPL2
 * Text Domain: ultimate-wp-booster
 */

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

// Plugin Constants
define( 'UWB_VERSION', '2.1.0' );
define( 'UWB_PLUGIN_FILE', __FILE__ );
define( 'UWB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'UWB_INC_DIR', UWB_PLUGIN_DIR . 'inc/' );

// 0. Unified Autoloader for Ultimate_WP_Booster & Uwb_* legacy fallbacks
spl_autoload_register( function( $class ) {
    $prefix = 'Ultimate_WP_Booster\\';
    $len    = strlen( $prefix );

    if ( strncmp( $prefix, $class, $len ) === 0 ) {
        $relative_class = substr( $class, $len );

        if ( strpos( $relative_class, 'Dependencies\\' ) === 0 ) {
            $dep_relative = substr( $relative_class, strlen( 'Dependencies\\' ) );
            $file = UWB_PLUGIN_DIR . 'includes/Dependencies/' . str_replace( '\\', '/', $dep_relative ) . '.php';
            if ( file_exists( $file ) ) {
                require_once $file;
                return;
            }
        }

        $file = UWB_INC_DIR . str_replace( '\\', '/', $relative_class ) . '.php';
        if ( file_exists( $file ) ) {
            require_once $file;
            return;
        }
    }

    // Legacy Uwb_* class fallback autoloader
    if ( strncmp( 'Uwb_', $class, 4 ) === 0 ) {
        $filename = 'class-' . str_replace( '_', '-', strtolower( $class ) ) . '.php';
        $file = UWB_PLUGIN_DIR . 'includes/' . $filename;
        if ( file_exists( $file ) ) {
            require_once $file;
        }
    }
} );

// 1. Include GitHub Updater
require_once UWB_PLUGIN_DIR . 'github-updater.php';

// 2. Perform Requirements Check before bootstrapping plugin
$uwb_requirements = new Ultimate_WP_Booster\Engine\Requirements\Check( '7.4', '5.6' );
if ( $uwb_requirements->check() ) {
    require_once UWB_INC_DIR . 'main.php';
}
unset( $uwb_requirements );
