<?php
/**
 * WP Rocket Compatibility and Migration Layer
 */

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

// 1. Deactivate WP Rocket if active to prevent extreme conflicts
add_action( 'admin_init', 'uwb_deactivate_wp_rocket_if_active' );
function uwb_deactivate_wp_rocket_if_active() {
    if ( ! current_user_can( 'activate_plugins' ) ) {
        return;
    }
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    if ( is_plugin_active( 'wp-rocket/wp-rocket.php' ) ) {
        deactivate_plugins( 'wp-rocket/wp-rocket.php' );
        set_transient( 'uwb_rocket_deactivated_notice', 1, 60 );
    }
}

// Show deactivation notice
add_action( 'admin_notices', 'uwb_wp_rocket_compatibility_notices' );
function uwb_wp_rocket_compatibility_notices() {
    // 1. WP Rocket Deactivated notice
    if ( get_transient( 'uwb_rocket_deactivated_notice' ) ) {
        delete_transient( 'uwb_rocket_deactivated_notice' );
        ?>
        <div class="notice notice-warning is-dismissible" style="border-left-color: #ffb900; padding: 12px 20px; border-radius: 6px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-top: 15px;">
            <p style="margin: 0; font-size: 14px; font-weight: 500; color: #32373c;">
                <span style="font-weight: 600; color: #d54e21; margin-right: 5px;">⚠️ Conflict Prevented:</span> 
                WP Rocket has been automatically deactivated because running two caching plugins simultaneously causes severe errors.
            </p>
        </div>
        <?php
    }

    // 2. Settings imported notice
    if ( isset( $_GET['uwb_rocket_imported'] ) && $_GET['uwb_rocket_imported'] === '1' ) {
        ?>
        <div class="notice notice-success is-dismissible" style="border-left-color: #bc00dd; padding: 12px 20px; border-radius: 6px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-top: 15px;">
            <p style="margin: 0; font-size: 14px; font-weight: 500; color: #32373c;">
                <span style="font-weight: 600; color: #bc00dd; margin-right: 5px;">🚀 Success:</span> 
                Your WP Rocket configuration has been successfully imported into Ultimate WP Booster!
            </p>
        </div>
        <?php
    }

    // 3. Settings Import Prompt
    if ( get_option( 'uwb_show_rocket_import_prompt' ) == 1 ) {
        $import_url = wp_nonce_url( admin_url( 'admin-post.php?action=uwb_import_rocket_settings' ), 'uwb_import_rocket_action' );
        $dismiss_url = wp_nonce_url( admin_url( 'admin-post.php?action=uwb_dismiss_rocket_import' ), 'uwb_dismiss_rocket_action' );
        ?>
        <div class="notice notice-info" style="border-left: 4px solid #bc00dd; background: #fff; padding: 16px 20px; border-radius: 8px; box-shadow: 0 8px 16px rgba(188,0,221,0.08); margin-top: 20px; border-top: 1px solid #f1f1f1; border-right: 1px solid #f1f1f1; border-bottom: 1px solid #f1f1f1; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px;">
            <div style="flex: 1; min-width: 280px;">
                <h4 style="margin: 0 0 5px 0; color: #bc00dd; font-size: 16px; font-weight: 700; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, sans-serif;">Migrate to Ultimate WP Booster</h4>
                <p style="margin: 0; font-size: 14px; color: #50575e; line-height: 1.5;">We detected existing WP Rocket configuration data on your site. Would you like to import your WP Rocket settings (Cache settings, Minification, Exclusions, Lazyload) directly to Ultimate WP Booster?</p>
            </div>
            <div style="display: flex; gap: 10px; align-items: center;">
                <a href="<?php echo esc_url( $import_url ); ?>" class="button button-primary" style="background: linear-gradient(135deg, #bc00dd 0%, #7d00dd 100%); border: none; border-radius: 4px; padding: 6px 16px; height: auto; line-height: 1.5; font-weight: 600; text-shadow: none; box-shadow: 0 2px 4px rgba(188,0,221,0.3); transition: all 0.2s ease;">Import Configuration</a>
                <a href="<?php echo esc_url( $dismiss_url ); ?>" class="button" style="border: 1px solid #ccd0d4; border-radius: 4px; padding: 6px 16px; height: auto; line-height: 1.5; font-weight: 600; background: #fff; color: #444; transition: all 0.2s ease;">No, Go to Settings</a>
            </div>
        </div>
        <?php
    }
}

// 2. Admin actions for settings import / dismiss
add_action( 'admin_post_uwb_import_rocket_settings', 'uwb_handle_import_rocket_settings' );
function uwb_handle_import_rocket_settings() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Permission denied.' );
    }
    check_admin_referer( 'uwb_import_rocket_action' );
    
    // Perform import
    uwb_import_wp_rocket_settings();
    
    delete_option( 'uwb_show_rocket_import_prompt' );
    
    wp_safe_redirect( admin_url( 'admin.php?page=ultimate-wp-booster&uwb_rocket_imported=1' ) );
    exit;
}

add_action( 'admin_post_uwb_dismiss_rocket_import', 'uwb_handle_dismiss_rocket_import' );
function uwb_handle_dismiss_rocket_import() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Permission denied.' );
    }
    check_admin_referer( 'uwb_dismiss_rocket_action' );
    
    delete_option( 'uwb_show_rocket_import_prompt' );
    
    wp_safe_redirect( admin_url( 'admin.php?page=ultimate-wp-booster' ) );
    exit;
}

/**
 * Import and map WP Rocket database settings to Ultimate WP Booster options
 */
function uwb_import_wp_rocket_settings() {
    $rocket_settings = get_option( 'wp_rocket_settings' );
    if ( ! is_array( $rocket_settings ) ) {
        return;
    }

    // Mapping: cache_logged_user => uwb_cache_logged_in
    if ( isset( $rocket_settings['cache_logged_user'] ) ) {
        update_option( 'uwb_cache_logged_in', intval( $rocket_settings['cache_logged_user'] ) );
    }

    // Mapping: purge_cron_interval => uwb_cache_lifespan (WP Rocket stores in hours/seconds, convert to minutes)
    if ( isset( $rocket_settings['purge_cron_interval'] ) ) {
        $interval_seconds = intval( $rocket_settings['purge_cron_interval'] );
        update_option( 'uwb_cache_lifespan', round( $interval_seconds / 60 ) );
    }

    // Mapping: cache_reject_uri => uwb_excluded_urls
    if ( isset( $rocket_settings['cache_reject_uri'] ) && is_array( $rocket_settings['cache_reject_uri'] ) ) {
        update_option( 'uwb_excluded_urls', implode( "\n", array_map( 'trim', $rocket_settings['cache_reject_uri'] ) ) );
    }

    // Mapping: cache_reject_cookies => uwb_exclude_cookies
    if ( isset( $rocket_settings['cache_reject_cookies'] ) && is_array( $rocket_settings['cache_reject_cookies'] ) ) {
        update_option( 'uwb_exclude_cookies', implode( "\n", array_map( 'trim', $rocket_settings['cache_reject_cookies'] ) ) );
    }

    // Mapping: cache_reject_ua => uwb_exclude_user_agents
    if ( isset( $rocket_settings['cache_reject_ua'] ) && is_array( $rocket_settings['cache_reject_ua'] ) ) {
        update_option( 'uwb_exclude_user_agents', implode( "\n", array_map( 'trim', $rocket_settings['cache_reject_ua'] ) ) );
    }

    // Mapping: cache_query_strings => uwb_cache_query_strings
    if ( isset( $rocket_settings['cache_query_strings'] ) && is_array( $rocket_settings['cache_query_strings'] ) ) {
        update_option( 'uwb_cache_query_strings', implode( "\n", array_map( 'trim', $rocket_settings['cache_query_strings'] ) ) );
    }

    // Mapping: minify_css => uwb_css_minify
    if ( isset( $rocket_settings['minify_css'] ) ) {
        update_option( 'uwb_css_minify', intval( $rocket_settings['minify_css'] ) );
    }

    // Mapping: minify_js => uwb_js_minify
    if ( isset( $rocket_settings['minify_js'] ) ) {
        update_option( 'uwb_js_minify', intval( $rocket_settings['minify_js'] ) );
    }

    // Mapping: lazyload => uwb_media_lazy_load_images
    if ( isset( $rocket_settings['lazyload'] ) ) {
        update_option( 'uwb_media_lazy_load_images', intval( $rocket_settings['lazyload'] ) );
    }

    // Mapping: lazyload_iframes => uwb_media_lazy_load_iframes
    if ( isset( $rocket_settings['lazyload_iframes'] ) ) {
        update_option( 'uwb_media_lazy_load_iframes', intval( $rocket_settings['lazyload_iframes'] ) );
    }

    // Mapping: defer_js => uwb_js_load_defer
    if ( isset( $rocket_settings['defer_js'] ) ) {
        update_option( 'uwb_js_load_defer', intval( $rocket_settings['defer_js'] ) );
    }

    // Regenerate config files
    \Ultimate_WP_Booster\Engine\Cache\CacheManager::write_config_file();
}

// 3. WP Rocket Compatibility Wrapper Functions
// Prevents PHP errors and maintains integration compatibility with themes, plugins, and custom scripts.

if ( ! function_exists( 'rocket_clean_domain' ) ) {
    function rocket_clean_domain() {
        $uwb_cache = new \Ultimate_WP_Booster\Engine\Cache\CacheManager();
        $uwb_cache->purge_all();
    }
}

if ( ! function_exists( 'rocket_clean_post' ) ) {
    function rocket_clean_post( $post_id ) {
        $uwb_cache = new \Ultimate_WP_Booster\Engine\Cache\CacheManager();
        $uwb_cache->purge_post_cache( $post_id );
    }
}

if ( ! function_exists( 'rocket_clean_home' ) ) {
    function rocket_clean_home() {
        $uwb_cache = new \Ultimate_WP_Booster\Engine\Cache\CacheManager();
        $uwb_cache->purge_url( home_url( '/' ) );
    }
}

if ( ! function_exists( 'rocket_clean_minify' ) ) {
    function rocket_clean_minify() {
        $minify_dir = WP_CONTENT_DIR . '/cache/ultimate-wp-booster/minify';
        if ( file_exists( $minify_dir ) && is_dir( $minify_dir ) ) {
            $delete_files = function( $dir, $self ) use ( &$delete_files ) {
                $files = array_diff( scandir( $dir ), array( '.', '..' ) );
                foreach ( $files as $file ) {
                    ( is_dir( "$dir/$file" ) ) ? $delete_files( "$dir/$file", true ) : @unlink( "$dir/$file" );
                }
                return $self ? @rmdir( $dir ) : true;
            };
            $delete_files( $minify_dir, false );
        }
    }
}

if ( ! function_exists( 'get_rocket_option' ) ) {
    function get_rocket_option( $option, $default = false ) {
        $mapping = array(
            'minify_css'          => 'uwb_css_minify',
            'minify_js'           => 'uwb_js_minify',
            'lazyload'            => 'uwb_media_lazy_load_images',
            'lazyload_iframes'    => 'uwb_media_lazy_load_iframes',
            'defer_js'            => 'uwb_js_load_defer',
            'cache_logged_user'   => 'uwb_cache_logged_in',
            'purge_cron_interval' => 'uwb_cache_lifespan',
        );

        if ( isset( $mapping[ $option ] ) ) {
            $val = get_option( $mapping[ $option ], $default );
            if ( $mapping[ $option ] === 'uwb_cache_lifespan' ) {
                return intval( $val ) * 60; // Convert minutes back to seconds for WP Rocket
            }
            return $val;
        }

        return $default;
    }
}
