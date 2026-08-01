<?php
namespace Ultimate_WP_Booster\EventManagement;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

interface Subscriber_Interface {
    /**
     * Returns an array of events this subscriber wants to listen to.
     *
     * Format:
     * [
     *     'hook_name' => 'method_name',
     *     'another_hook' => ['method_name', priority, accepted_args]
     * ]
     *
     * @return array
     */
    public static function get_subscribed_events();
}
