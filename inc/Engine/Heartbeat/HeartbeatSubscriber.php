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
        if ( function_exists( 'uwb_heartbeat_control' ) ) {
            uwb_heartbeat_control();
        }
    }
}
