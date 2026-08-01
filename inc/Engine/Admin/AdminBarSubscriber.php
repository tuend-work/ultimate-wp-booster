<?php
namespace Ultimate_WP_Booster\Engine\Admin;

use Ultimate_WP_Booster\EventManagement\Subscriber_Interface;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class AdminBarSubscriber implements Subscriber_Interface {

    public static function get_subscribed_events() {
        return array(
            'admin_bar_menu'                   => array( 'add_admin_bar_nodes', 999 ),
            'admin_post_uwb_purge_url'         => 'handle_purge_url',
            'admin_post_uwb_clear_cache_page'  => 'handle_clear_cache_page',
            'admin_post_uwb_flush_all_preload' => 'handle_flush_all_preload',
            'admin_post_uwb_flush_object_cache' => 'handle_flush_object_cache',
            'admin_post_uwb_flush_opcache'      => 'handle_flush_opcache',
        );
    }

    public function add_admin_bar_nodes( $wp_admin_bar ) {
        if ( function_exists( 'uwb_add_admin_bar_nodes' ) ) {
            uwb_add_admin_bar_nodes( $wp_admin_bar );
        }
    }

    public function handle_purge_url() {
        if ( function_exists( 'uwb_handle_admin_bar_purge_url' ) ) {
            uwb_handle_admin_bar_purge_url();
        }
    }

    public function handle_clear_cache_page() {
        if ( function_exists( 'uwb_handle_admin_bar_clear_cache_page' ) ) {
            uwb_handle_admin_bar_clear_cache_page();
        }
    }

    public function handle_flush_all_preload() {
        if ( function_exists( 'uwb_handle_admin_bar_flush_all_preload' ) ) {
            uwb_handle_admin_bar_flush_all_preload();
        }
    }

    public function handle_flush_object_cache() {
        if ( function_exists( 'uwb_handle_admin_bar_flush_object_cache' ) ) {
            uwb_handle_admin_bar_flush_object_cache();
        }
    }

    public function handle_flush_opcache() {
        if ( function_exists( 'uwb_handle_admin_bar_flush_opcache' ) ) {
            uwb_handle_admin_bar_flush_opcache();
        }
    }
}
