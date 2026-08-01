<?php
namespace Ultimate_WP_Booster\Engine\Admin;

use Ultimate_WP_Booster\EventManagement\Subscriber_Interface;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class PostRowActionsSubscriber implements Subscriber_Interface {

    public static function get_subscribed_events() {
        return array(
            'post_row_actions' => array( 'add_post_row_actions', 10, 2 ),
            'page_row_actions' => array( 'add_post_row_actions', 10, 2 ),
        );
    }

    public function add_post_row_actions( $actions, $post ) {
        if ( function_exists( 'uwb_add_post_row_actions' ) ) {
            return uwb_add_post_row_actions( $actions, $post );
        }
        return $actions;
    }
}
