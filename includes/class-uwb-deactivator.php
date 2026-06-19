<?php
/**
 * Fired during plugin deactivation
 */

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class Uwb_Deactivator {

    public static function deactivate() {
        // 1. Remove advanced-cache.php drop-in
        $destination = WP_CONTENT_DIR . '/advanced-cache.php';
        if ( file_exists( $destination ) ) {
            // Only delete if it's ours (check comment or md5, or just delete it if we want to clean up)
            $content = @file_get_contents( $destination );
            if ( $content && strpos( $content, 'Ultimate WordPress Booster' ) !== false ) {
                @unlink( $destination );
            }
        }

        // 2. Disable WP_CACHE in wp-config.php
        require_once plugin_dir_path( __FILE__ ) . 'class-uwb-activator.php';
        Uwb_Activator::toggle_wp_cache( false );

        // Remove object-cache.php drop-in if it's ours
        Uwb_Activator::remove_object_cache_dropin();

        // 3. Clear scheduled cron jobs
        wp_clear_scheduled_hook( 'uwb_preload_cron_job' );

        // Note: We don't delete the database table here to preserve queue state if the user reactivates,
        // and we don't delete the cache folder to prevent slow loading immediately after reactivation.
        // Cache is cleaned dynamically or via the plugin settings dashboard.
    }
}
