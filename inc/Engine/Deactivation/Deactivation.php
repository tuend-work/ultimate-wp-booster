<?php
namespace Ultimate_WP_Booster\Engine\Deactivation;

use Ultimate_WP_Booster\Engine\Activation\Activation;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class Deactivation {

    public static function deactivate_plugin() {
        self::deactivate();
    }

    public static function deactivate() {
        $destination = WP_CONTENT_DIR . '/advanced-cache.php';
        if ( file_exists( $destination ) ) {
            $content = @file_get_contents( $destination );
            if ( $content && strpos( $content, 'Ultimate WordPress Booster' ) !== false ) {
                @unlink( $destination );
            }
        }

        Activation::toggle_wp_cache( false );

        wp_clear_scheduled_hook( 'uwb_preload_cron_job' );
        wp_clear_scheduled_hook( 'uwb_clean_expired_cache' );
    }
}
