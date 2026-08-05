<?php
namespace Ultimate_WP_Booster\Engine\Preload;

use Ultimate_WP_Booster\EventManagement\Subscriber_Interface;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class PreloadSubscriber implements Subscriber_Interface {

    private $preloader;

    public function __construct() {
        $this->preloader = new Preloader();
    }

    public static function get_subscribed_events() {
        return array(
            'uwb_preload_cron_job'              => 'run_preload_batch',
            'uwb_start_preload_async'           => 'start_preload',
            'shutdown'                          => 'maybe_mark_cached_url_completed',
            'init'                              => array(
                array( 'maybe_output_important_sitemap', 0 ),
            ),
            'wp_footer'                         => 'maybe_output_preload_links_script',
            'wp_update_nav_menu'                => 'invalidate_homepage_links_cache',
            'switch_theme'                      => 'invalidate_homepage_links_cache',
            'update_option_sidebars_widgets'     => 'invalidate_homepage_links_cache',
            'wp_ajax_uwb_start_preload'         => 'ajax_start_preload',
            'wp_ajax_uwb_stop_preload'          => 'ajax_stop_preload',
            'wp_ajax_uwb_clear_preload'         => 'ajax_clear_preload',
            'wp_ajax_uwb_get_preload_status'    => 'ajax_get_preload_status',
            'wp_ajax_uwb_trigger_preload_batch' => 'ajax_trigger_preload_batch',
            'wp_ajax_uwb_get_url_table'         => 'ajax_get_url_table',
            'wp_ajax_uwb_process_url_now'       => 'ajax_process_url_now',
            'wp_ajax_uwb_add_to_exclude'        => 'ajax_add_to_exclude',
            'wp_ajax_uwb_add_to_priority'       => 'ajax_add_to_priority',
        );
    }

    public function run_preload_batch() {
        if ( ! get_option( 'uwb_module_preload_enabled', 1 ) ) {
            return;
        }
        $this->preloader->run_preload_batch();
    }

    public function start_preload() {
        if ( ! get_option( 'uwb_module_preload_enabled', 1 ) ) {
            return;
        }
        $this->preloader->start_preload();
    }

    public function maybe_mark_cached_url_completed() {
        if ( ! get_option( 'uwb_module_preload_enabled', 1 ) ) {
            return;
        }
        $this->preloader->maybe_mark_cached_url_completed();
    }

    public function maybe_output_important_sitemap() {
        if ( ! get_option( 'uwb_module_preload_enabled', 1 ) ) {
            return;
        }
        $this->preloader->maybe_output_important_sitemap();
    }

    public function maybe_output_preload_links_script() {
        if ( ! get_option( 'uwb_module_preload_enabled', 1 ) ) {
            return;
        }
        $this->preloader->maybe_output_preload_links_script();
    }

    public function invalidate_homepage_links_cache() {
        if ( ! get_option( 'uwb_module_preload_enabled', 1 ) ) {
            return;
        }
        Preloader::invalidate_homepage_links_cache();
    }

    public function ajax_start_preload() {
        if ( ! get_option( 'uwb_module_preload_enabled', 1 ) ) {
            wp_send_json_error( array( 'message' => 'Preload module is disabled.' ) );
        }
        $this->preloader->ajax_start_preload();
    }

    public function ajax_stop_preload() {
        if ( ! get_option( 'uwb_module_preload_enabled', 1 ) ) {
            wp_send_json_error( array( 'message' => 'Preload module is disabled.' ) );
        }
        $this->preloader->ajax_stop_preload();
    }

    public function ajax_clear_preload() {
        if ( ! get_option( 'uwb_module_preload_enabled', 1 ) ) {
            wp_send_json_error( array( 'message' => 'Preload module is disabled.' ) );
        }
        $this->preloader->ajax_clear_preload();
    }

    public function ajax_get_preload_status() {
        if ( ! get_option( 'uwb_module_preload_enabled', 1 ) ) {
            wp_send_json_success( array(
                'total'      => 0,
                'pending'    => 0,
                'processing' => 0,
                'completed'  => 0,
                'failed'     => 0,
                'running'    => 0,
                'log'        => 'Preload module is currently disabled. Please enable it in Preload Settings.'
            ) );
        }
        $this->preloader->ajax_get_preload_status();
    }

    public function ajax_trigger_preload_batch() {
        if ( ! get_option( 'uwb_module_preload_enabled', 1 ) ) {
            wp_send_json_error( array( 'message' => 'Preload module is disabled.' ) );
        }
        $this->preloader->ajax_trigger_preload_batch();
    }

    public function ajax_get_url_table() {
        if ( ! get_option( 'uwb_module_preload_enabled', 1 ) ) {
            wp_send_json_error( array( 'message' => 'Preload module is disabled.' ) );
        }
        $this->preloader->ajax_get_url_table();
    }

    public function ajax_process_url_now() {
        if ( ! get_option( 'uwb_module_preload_enabled', 1 ) ) {
            wp_send_json_error( array( 'message' => 'Preload module is disabled.' ) );
        }
        $this->preloader->ajax_process_url_now();
    }

    public function ajax_add_to_exclude() {
        if ( ! get_option( 'uwb_module_preload_enabled', 1 ) ) {
            wp_send_json_error( array( 'message' => 'Preload module is disabled.' ) );
        }
        $this->preloader->ajax_add_to_exclude();
    }

    public function ajax_add_to_priority() {
        if ( ! get_option( 'uwb_module_preload_enabled', 1 ) ) {
            wp_send_json_error( array( 'message' => 'Preload module is disabled.' ) );
        }
        $this->preloader->ajax_add_to_priority();
    }
}
