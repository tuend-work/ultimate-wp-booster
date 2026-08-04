<?php
use Ultimate_WP_Booster\Plugin;
use Ultimate_WP_Booster\Engine\Activation\Activation;
use Ultimate_WP_Booster\Engine\Deactivation\Deactivation;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

// 1. Include compatibility layers (dynamically load all files in compatibility directory)
foreach ( glob( UWB_PLUGIN_DIR . 'compatibility/*.php' ) as $compat_file ) {
    require_once $compat_file;
}

// 2. Automated Version Upgrade Check (Sync drop-ins on version bump)
add_action( 'init', 'uwb_check_upgrade' );
if ( ! function_exists( 'uwb_check_upgrade' ) ) {
    function uwb_check_upgrade() {
        $db_version  = get_option( 'uwb_version' );
        $dropin_file = WP_CONTENT_DIR . '/advanced-cache.php';
        if ( $db_version !== UWB_VERSION || ! file_exists( $dropin_file ) ) {
            Activation::copy_advanced_cache_dropin();
            Activation::copy_object_cache_dropin();
            Activation::toggle_wp_cache( true );
            \Ultimate_WP_Booster\Engine\Cache\CacheManager::write_config_file();

            // Sync URO MU plugin if installed
            $mu_manager = new \Ultimate_WP_Booster\Engine\RuntimeOptimizer\Runtime\RuntimeManager();
            $status = $mu_manager->mu_plugin_status();
            if ( $status['installed'] ) {
                $mu_manager->install_mu_plugin();
                $mu_manager->recompile();
            }

            update_option( 'uwb_version', UWB_VERSION );
        }
    }
}

// 3. Register activation & deactivation hooks
register_activation_hook( UWB_PLUGIN_FILE, array( Activation::class, 'activate_plugin' ) );
register_deactivation_hook( UWB_PLUGIN_FILE, array( Deactivation::class, 'deactivate_plugin' ) );

// 4. Register custom cron schedules
add_filter( 'cron_schedules', 'uwb_add_cron_schedules' );
if ( ! function_exists( 'uwb_add_cron_schedules' ) ) {
    function uwb_add_cron_schedules( $schedules ) {
        if ( ! isset( $schedules['every_minute'] ) ) {
            $schedules['every_minute'] = array(
                'interval' => 60,
                'display'  => 'Every minute (1 min)',
            );
        }
        return $schedules;
    }
}

// 5. Generate secret key if not exists (runs after pluggable functions are loaded)
add_action( 'init', 'uwb_init_preload_secret_key', 9 );
if ( ! function_exists( 'uwb_init_preload_secret_key' ) ) {
    function uwb_init_preload_secret_key() {
        if ( ! get_option( 'uwb_preload_secret_key' ) ) {
            update_option( 'uwb_preload_secret_key', wp_generate_password( 24, false, false ) );
        }
    }
}

/**
 * Initialize Ultimate WP Booster plugin execution.
 */
function uwb_init() {
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    do_action( 'uwb_before_load' );

    $plugin = new Plugin();
    $plugin->load();

    $GLOBALS['uwb_plugin'] = $plugin;

    do_action( 'uwb_loaded' );
}
add_action( 'plugins_loaded', 'uwb_init' );
