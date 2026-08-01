<?php
namespace Ultimate_WP_Booster;

use Ultimate_WP_Booster\EventManagement\Event_Manager;
use Ultimate_WP_Booster\Engine\Admin\AdminBarSubscriber;
use Ultimate_WP_Booster\Engine\Admin\PostRowActionsSubscriber;
use Ultimate_WP_Booster\Engine\Heartbeat\HeartbeatSubscriber;
use Ultimate_WP_Booster\Engine\Optimization\BufferSubscriber;
use Ultimate_WP_Booster\Engine\CLI\PreloadCommand;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class Plugin {

    /**
     * Event Manager instance.
     *
     * @var Event_Manager
     */
    private $event_manager;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->event_manager = new Event_Manager();
    }

    /**
     * Returns the Event Manager instance.
     *
     * @return Event_Manager
     */
    public function get_event_manager() {
        return $this->event_manager;
    }

    /**
     * Bootstraps subscribers and commands.
     *
     * @return void
     */
    public function load() {
        // Register CLI commands
        PreloadCommand::register();

        // Get subscriber instances
        $subscribers = $this->get_subscribers();

        foreach ( $subscribers as $subscriber ) {
            $this->event_manager->add_subscriber( $subscriber );
        }

        // Initialize Core Engines
        $this->init_engines();
    }

    /**
     * Initialize subscribers based on context (admin / frontend / common).
     *
     * @return array
     */
    private function get_subscribers() {
        $subscribers = array(
            new AdminBarSubscriber(),
            new PostRowActionsSubscriber(),
            new HeartbeatSubscriber(),
        );

        if ( is_admin() ) {
            $subscribers[] = new \Ultimate_WP_Booster\Engine\Admin\AdminNoticeSubscriber();
        } else {
            $subscribers[] = new BufferSubscriber();
        }

        return $subscribers;
    }

    /**
     * Initialize legacy core engines (Cache, Preloader, Admin).
     *
     * @return void
     */
    private function init_engines() {
        if ( class_exists( 'Uwb_Cache' ) ) {
            new \Uwb_Cache();
        }

        if ( class_exists( 'Uwb_Preloader' ) ) {
            new \Uwb_Preloader();
        }

        if ( is_admin() ) {
            if ( class_exists( 'Uwb_Admin' ) ) {
                new \Uwb_Admin();
            }

            if ( class_exists( 'Uwb_Github_Updater' ) ) {
                new \Uwb_Github_Updater( UWB_PLUGIN_FILE );
            }
        }
    }
}
