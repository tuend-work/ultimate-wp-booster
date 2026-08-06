<?php
namespace Ultimate_WP_Booster\Engine\Optimization;

use Ultimate_WP_Booster\EventManagement\Subscriber_Interface;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class BufferSubscriber implements Subscriber_Interface {

    public static function get_subscribed_events() {
        return array(
            'template_redirect' => array( 'start_buffering', 2 ),
        );
    }

    public function start_buffering() {
        if ( is_admin() || is_feed() || is_trackback() || is_robots() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
            return;
        }

        if ( \Ultimate_WP_Booster\Engine\Cache\LiteSpeedEngine::is_page_builder_request() ) {
            return; // Do not optimize/buffer for UX Builder & visual page builders
        }

        if ( defined( 'UWB_BUFFER_STARTED' ) ) {
            return; // Handled by early drop-in advanced-cache.php
        }

        $cache_logged_in    = intval( get_option( 'uwb_cache_logged_in', 0 ) );
        $optimize_logged_in = intval( get_option( 'uwb_optimize_logged_in', 0 ) );
        if ( is_user_logged_in() && $cache_logged_in === 0 && $optimize_logged_in === 0 ) {
            return; // Do not buffer or optimize for logged-in users when both cache and optimize are set to None
        }

        if ( function_exists( 'uwb_start_output_buffering' ) ) {
            uwb_start_output_buffering();
        }
    }
}
