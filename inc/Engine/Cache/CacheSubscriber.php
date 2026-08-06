<?php
namespace Ultimate_WP_Booster\Engine\Cache;

use Ultimate_WP_Booster\EventManagement\Subscriber_Interface;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class CacheSubscriber implements Subscriber_Interface {

    private $cache_manager;

    public function __construct() {
        $this->cache_manager = new CacheManager();
    }

    public static function get_subscribed_events() {
        return array(
            'save_post'                     => array(
                array( 'purge_post_cache', 10, 3 ),
                array( 'write_valid_post_ids_json', 20 ),
            ),
            'delete_post'                   => array( 'write_valid_post_ids_json', 20 ),
            'wp_update_nav_menu'            => 'purge_all',
            'switch_theme'                  => 'purge_all',
            'update_option_sidebars_widgets' => 'purge_all',
            'uwb_clean_expired_cache'       => 'clean_expired_cache',
            'init'                          => 'schedule_cleanup_cron',
            'wp_headers'                    => 'send_litespeed_headers',
        );
    }

    public function send_litespeed_headers( $headers ) {
        if ( LiteSpeedEngine::is_litespeed_server() ) {
            $lifespan           = intval( get_option( 'uwb_cache_lifespan', 36000 ) );
            $cache_page_enabled = intval( get_option( 'uwb_cache_page_enabled', 1 ) );
            $is_no_cache        = ( $cache_page_enabled === 0 );

            LiteSpeedEngine::send_cache_control_headers( $lifespan, $is_no_cache );
            if ( ! $is_no_cache ) {
                LiteSpeedEngine::send_tag_headers();
            }
        }
        return $headers;
    }

    public function purge_post_cache( $post_id, $post = null, $update = null ) {
        $this->cache_manager->purge_post_cache( $post_id, $post, $update );
    }

    public function purge_all() {
        $this->cache_manager->purge_all();
    }

    public function clean_expired_cache() {
        $this->cache_manager->clean_expired_cache();
    }

    public function write_valid_post_ids_json() {
        CacheManager::write_valid_post_ids_json();
    }

    public function schedule_cleanup_cron() {
        if ( ! wp_next_scheduled( 'uwb_clean_expired_cache' ) ) {
            wp_schedule_event( time(), 'hourly', 'uwb_clean_expired_cache' );
        }
    }
}
