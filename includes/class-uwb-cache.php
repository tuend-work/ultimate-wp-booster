<?php
/**
 * Cache Management Engine (Backward Compatibility Proxy)
 */

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class Uwb_Cache {

    private $cache_manager;

    public function __construct() {
        $this->cache_manager = new \Ultimate_WP_Booster\Engine\Cache\CacheManager();
    }

    public static function get_cache_dir() {
        return \Ultimate_WP_Booster\Engine\Cache\CacheManager::get_cache_dir();
    }

    public static function write_config_file() {
        \Ultimate_WP_Booster\Engine\Cache\CacheManager::write_config_file();
    }

    public static function write_valid_post_ids_json() {
        \Ultimate_WP_Booster\Engine\Cache\CacheManager::write_valid_post_ids_json();
    }

    public function purge_all() {
        $this->cache_manager->purge_all();
    }

    public function purge_url( $url ) {
        $this->cache_manager->purge_url( $url );
    }

    public function purge_post_cache( $post_id, $post = null, $update = null ) {
        $this->cache_manager->purge_post_cache( $post_id, $post, $update );
    }

    public function clean_expired_cache() {
        $this->cache_manager->clean_expired_cache();
    }
}
