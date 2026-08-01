<?php
namespace Ultimate_WP_Booster\EventManagement;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class Event_Manager {

    /**
     * Array of registered subscribers.
     *
     * @var array
     */
    private $subscribers = array();

    /**
     * Add a subscriber to register its hooks.
     *
     * @param Subscriber_Interface $subscriber Subscriber instance.
     * @return void
     */
    public function add_subscriber( Subscriber_Interface $subscriber ) {
        $class_name = get_class( $subscriber );
        if ( isset( $this->subscribers[ $class_name ] ) ) {
            return;
        }

        $this->subscribers[ $class_name ] = $subscriber;

        foreach ( $subscriber::get_subscribed_events() as $event_name => $params ) {
            $this->register_subscriber_callback( $subscriber, $event_name, $params );
        }
    }

    /**
     * Register a single subscriber callback with add_action or add_filter.
     *
     * @param Subscriber_Interface $subscriber  Subscriber instance.
     * @param string               $event_name  Hook name.
     * @param string|array         $params      Callback specification.
     * @return void
     */
    private function register_subscriber_callback( $subscriber, $event_name, $params ) {
        if ( is_string( $params ) ) {
            add_action( $event_name, array( $subscriber, $params ) );
            return;
        }

        if ( is_array( $params ) ) {
            if ( isset( $params[0] ) && is_string( $params[0] ) ) {
                $method_name   = $params[0];
                $priority      = isset( $params[1] ) ? intval( $params[1] ) : 10;
                $accepted_args = isset( $params[2] ) ? intval( $params[2] ) : 1;
                add_action( $event_name, array( $subscriber, $method_name ), $priority, $accepted_args );
                return;
            }

            // Multiple callbacks for the same hook
            foreach ( $params as $single_params ) {
                $this->register_subscriber_callback( $subscriber, $event_name, $single_params );
            }
        }
    }
}
