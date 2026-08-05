<?php
namespace Ultimate_WP_Booster;

use Ultimate_WP_Booster\EventManagement\Event_Manager;
use Ultimate_WP_Booster\Engine\Admin\AdminBarSubscriber;
use Ultimate_WP_Booster\Engine\Admin\PostRowActionsSubscriber;
use Ultimate_WP_Booster\Engine\Heartbeat\HeartbeatSubscriber;
use Ultimate_WP_Booster\Engine\Optimization\BufferSubscriber;
use Ultimate_WP_Booster\Engine\CLI\PreloadCommand;
use Ultimate_WP_Booster\Engine\RuntimeOptimizer\Runtime\RuntimeManager;
use Ultimate_WP_Booster\Engine\RuntimeOptimizer\Analyzer\Analyzer;

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

        // Cache module — bao gồm Page Cache, Browser Cache purge hooks
        if ( get_option( 'uwb_module_cache_enabled', 1 ) ) {
            $subscribers[] = new \Ultimate_WP_Booster\Engine\Cache\CacheSubscriber();
        }

        // Preload module — Sitemap crawler (always loaded to prevent AJAX 400 errors)
        $subscribers[] = new \Ultimate_WP_Booster\Engine\Preload\PreloadSubscriber();

        // CDN module — S3 Storage, media offload, CDN URL rewriting
        if ( get_option( 'uwb_module_cdn_enabled', 1 ) ) {
            $subscribers[] = new \Ultimate_WP_Booster\Engine\CDN\CDNSubscriber();
        }

        // Optimizer module — JS/CSS/HTML/Media optimization
        if ( get_option( 'uwb_module_optimizer_enabled', 1 ) ) {
            $subscribers[] = new \Ultimate_WP_Booster\Engine\Optimization\GeneralOptimizationSubscriber();
        }

        if ( is_admin() ) {
            $subscribers[] = new \Ultimate_WP_Booster\Engine\Admin\AdminNoticeSubscriber();
        } else {
            // BufferSubscriber chỉ load khi ít nhất 1 optimization module bật
            if ( get_option( 'uwb_module_optimizer_enabled', 1 ) || get_option( 'uwb_module_cdn_enabled', 1 ) ) {
                $subscribers[] = new BufferSubscriber();
            }
        }

        return $subscribers;
    }

    /**
     * Initialize core admin & updater engines.
     *
     * @return void
     */
    private function init_engines() {
        // RuntimeOptimizer: register compile triggers and AJAX handlers (always)
        $runtime_manager = new RuntimeManager();
        $runtime_manager->register_hooks();

        // Hook: deferred recompile via WP Cron
        add_action( 'uwb_uro_recompile_event', static function () {
            ( new RuntimeManager() )->recompile();
        } );

        // Analyzer: collect page data (when enabled)
        $analyzer = new Analyzer();
        $analyzer->init();

        if ( is_admin() ) {
            new \Ultimate_WP_Booster\Engine\Admin\Admin();

            if ( class_exists( 'Uwb_Github_Updater' ) ) {
                new \Uwb_Github_Updater( UWB_PLUGIN_FILE );
            }
        }
    }
}
