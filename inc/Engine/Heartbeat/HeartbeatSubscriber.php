<?php
namespace Ultimate_WP_Booster\Engine\Heartbeat;

use Ultimate_WP_Booster\EventManagement\Subscriber_Interface;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class HeartbeatSubscriber implements Subscriber_Interface {

    public static function get_subscribed_events() {
        return array(
            'init' => array( 'heartbeat_control', 10 ),
        );
    }

    public function heartbeat_control() {
        $mode = get_option( 'uwb_heartbeat_control', 'default' );
        if ( $mode === 'default' ) {
            return;
        }

        if ( $mode === 'disable_all' ) {
            add_action( 'init', function() {
                wp_deregister_script( 'heartbeat' );
            }, 1 );
            return;
        }

        if ( $mode === 'disable_frontend' && ! is_admin() ) {
            add_action( 'wp_enqueue_scripts', function() {
                wp_deregister_script( 'heartbeat' );
            }, 1 );
            return;
        }

        if ( $mode === 'reduce' ) {
            $interval = max( 15, intval( get_option( 'uwb_heartbeat_interval', 60 ) ) );
            add_filter( 'heartbeat_settings', function( $settings ) use ( $interval ) {
                $settings['interval'] = $interval;
                return $settings;
            } );
        }
    }
}
