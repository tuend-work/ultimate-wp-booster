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
            'uwb_preload_cron_job'      => 'run_preload_batch',
            'uwb_start_preload_async'   => 'start_preload',
            'shutdown'                  => 'maybe_mark_cached_url_completed',
            'init'                      => array(
                array( 'maybe_output_important_sitemap', 0 ),
            ),
            'wp_update_nav_menu'            => 'invalidate_homepage_links_cache',
            'switch_theme'                  => 'invalidate_homepage_links_cache',
            'update_option_sidebars_widgets' => 'invalidate_homepage_links_cache',
        );
    }

    public function run_preload_batch() {
        $this->preloader->run_preload_batch();
    }

    public function start_preload() {
        $this->preloader->start_preload();
    }

    public function maybe_mark_cached_url_completed() {
        $this->preloader->maybe_mark_cached_url_completed();
    }

    public function maybe_output_important_sitemap() {
        $this->preloader->maybe_output_important_sitemap();
    }

    public function invalidate_homepage_links_cache() {
        Preloader::invalidate_homepage_links_cache();
    }
}
