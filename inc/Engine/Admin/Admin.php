<?php
namespace Ultimate_WP_Booster\Engine\Admin;

/**
 * Admin Panel Dashboard & Settings
 */

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

use Ultimate_WP_Booster\Engine\Cache\CacheManager;
use Ultimate_WP_Booster\Engine\Preload\Preloader;
use Ultimate_WP_Booster\Engine\Activation\Activation;

class Admin {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_plugin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_init', array( $this, 'admin_init_sync' ) );
        add_action( 'wp_ajax_uwb_test_cdn_connection', array( $this, 'ajax_test_cdn_connection' ) );
        add_action( 'wp_ajax_uwb_test_cf_connection', array( $this, 'ajax_test_cf_connection' ) );
        add_action( 'wp_ajax_uwb_purge_cf_cache', array( $this, 'ajax_purge_cf_cache' ) );
        add_action( 'wp_ajax_uwb_sync_media_to_cdn', array( $this, 'ajax_sync_media_to_cdn' ) );

        $options_to_sync = array(
            'uwb_cache_page_enabled',
            'uwb_cache_lifespan',
            'uwb_excluded_urls',
            'uwb_cache_logged_in',
            'uwb_cache_logged_in_lifespan',
            'uwb_browser_cache_enabled',
            'uwb_browser_cache_lifespan',
            'uwb_ignored_query',
            'uwb_redis_enabled',
            'uwb_redis_conn_type',
            'uwb_redis_host',
            'uwb_redis_port',
            'uwb_redis_socket',
            'uwb_redis_password',
            'uwb_redis_db',
            'uwb_redis_prefix',
            'uwb_redis_timeout',
            'uwb_redis_read_timeout',
            'uwb_redis_retry_interval',
            'uwb_cache_404',
            'uwb_exclude_cookies',
            'uwb_exclude_user_agents',
            'uwb_always_purge_urls',
            'uwb_cache_query_strings',
            'uwb_cache_xml_sitemaps',
            'uwb_cache_xml_sitemaps_lifespan',
            'uwb_cache_php',
            'uwb_cache_php_lifespan',
            'uwb_css_minify',
            'uwb_css_combine',
            'uwb_css_combine_ext_inline',
            'uwb_css_load_async',
            'uwb_css_font_display_opt',
            'uwb_js_minify',
            'uwb_js_combine',
            'uwb_js_combine_ext_inline',
            'uwb_js_load_defer',
            'uwb_html_minify',
            'uwb_html_remove_qs',
            'uwb_html_remove_gfonts',
            'uwb_html_remove_emoji',
            'uwb_html_remove_noscript',
            'uwb_media_lazy_load_images',
            'uwb_media_lazy_load_iframes',
            'uwb_media_image_placeholder',
            'uwb_media_add_missing_sizes',
            'uwb_media_lazy_load_excludes',
            'uwb_media_lazy_load_class_excludes',
            'uwb_tuning_css_excludes',
            'uwb_tuning_js_excludes',
            'uwb_tuning_js_defer_excludes',
            'uwb_tuning_critical_css',
            'uwb_ignore_all_query_strings',
            'uwb_auto_collect_params',
            'uwb_collected_params',
            'uwb_cache_search',
            'uwb_heartbeat_control',
            'uwb_heartbeat_interval',
            'uwb_preconnect_domains',
            'uwb_delay_js',
            'uwb_delay_js_exclusions',
            'uwb_debug_mode',
            'uwb_cdn_distribute_css',
            'uwb_cdn_auto_upload_combined_css',
            'uwb_cdn_auto_upload_minified_css',
            'uwb_cdn_auto_purge_css_cdn',
            'uwb_cdn_distribute_js',
            'uwb_cdn_auto_upload_combined_js',
            'uwb_cdn_auto_upload_minified_js',
            'uwb_cdn_auto_purge_js_cdn',
            'uwb_cdn_distribute_html',
            'uwb_cdn_auto_rewrite_html_urls',
            'uwb_cdn_auto_purge_html_cf',
            'uwb_cdn_distribute_media',
            'uwb_cdn_auto_upload_attachment',
            'uwb_cdn_auto_update_attachment',
            'uwb_cdn_auto_rewrite_attachment_url',
            'uwb_cdn_auto_delete_attachment',
            'uwb_cdn_distribute_font',
            'uwb_cdn_auto_upload_fonts',
            'uwb_cdn_auto_rewrite_font_urls'
        );
        foreach ( $options_to_sync as $opt ) {
            add_action( "update_option_{$opt}", array( CacheManager::class, 'write_config_file' ) );
            add_action( "add_option_{$opt}", array( CacheManager::class, 'write_config_file' ) );
        }

        // Also sync config when WordPress timezone settings change
        add_action( 'update_option_timezone_string', array( CacheManager::class, 'write_config_file' ) );
        add_action( 'add_option_timezone_string', array( CacheManager::class, 'write_config_file' ) );
        add_action( 'update_option_gmt_offset', array( CacheManager::class, 'write_config_file' ) );
        add_action( 'add_option_gmt_offset', array( CacheManager::class, 'write_config_file' ) );

        // Redis AJAX hooks
        add_action( 'wp_ajax_uwb_test_redis_connection', array( $this, 'ajax_test_redis_connection' ) );
        add_action( 'wp_ajax_uwb_flush_redis_cache', array( $this, 'ajax_flush_redis_cache' ) );
        add_action( 'wp_ajax_uwb_clear_preload_log', array( $this, 'ajax_clear_preload_log' ) );
        add_action( 'admin_init', array( $this, 'handle_import_export' ) );
    }

    public function add_plugin_menu() {
        global $menu, $submenu;

        // Check if Parent Menu "ultimate-wp" already exists
        $parent_exists = false;
        if ( is_array( $menu ) ) {
            foreach ( $menu as $item ) {
                if ( isset( $item[2] ) && $item[2] === 'ultimate-wp' ) {
                    $parent_exists = true;
                    break;
                }
            }
        }

        // Add Parent Menu if not registered yet
        if ( ! $parent_exists ) {
            add_menu_page(
                'Ultimate WP Dashboard',
                'Ultimate WP',
                'manage_options',
                'ultimate-wp',
                'ultimate_wp_render_dashboard',
                'dashicons-superhero',
                2 // Fixed integer position to avoid float key mismatch
            );
        }

        // Add Submenu for Booster settings
        add_submenu_page(
            'ultimate-wp',
            'Ultimate WP Booster Settings',
            'Ultimate Booster',
            'manage_options',
            'ultimate-wp-booster',
            array( $this, 'render_settings_page' )
        );

        // Rename the first default submenu (created automatically by WordPress) from "Ultimate WP" to "Dashboard"
        if ( isset( $submenu['ultimate-wp'] ) && is_array( $submenu['ultimate-wp'] ) ) {
            foreach ( $submenu['ultimate-wp'] as $key => $item ) {
                if ( isset( $item[2] ) && $item[2] === 'ultimate-wp' ) {
                    $submenu['ultimate-wp'][$key][0] = 'Dashboard';
                }
            }
        }
    }

    public function register_settings() {
        register_setting( 'uwb_settings_group', 'uwb_cache_page_enabled', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_cache_lifespan', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_cache_logged_in', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_cache_logged_in_lifespan', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_browser_cache_enabled', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_browser_cache_lifespan', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_browser_cache_html', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_browser_cache_html_lifespan', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_browser_cache_css', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_browser_cache_css_lifespan', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_browser_cache_js', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_browser_cache_js_lifespan', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_browser_cache_image', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_browser_cache_image_lifespan', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_browser_cache_font', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_browser_cache_font_lifespan', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_browser_cache_other', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_browser_cache_other_lifespan', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_excluded_urls', array( $this, 'sanitize_excluded_urls' ) );
        register_setting( 'uwb_settings_group', 'uwb_ignored_query', 'sanitize_textarea_field' );
        register_setting( 'uwb_settings_group', 'uwb_preload_enabled', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_preload_sitemap', array( $this, 'sanitize_sitemap_list' ) );
        register_setting( 'uwb_settings_group', 'uwb_priority_urls', array( $this, 'sanitize_priority_urls' ) );
        register_setting( 'uwb_settings_group', 'uwb_preload_batch_size', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_preload_links', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_cache_404', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_exclude_cookies', 'sanitize_textarea_field' );
        register_setting( 'uwb_settings_group', 'uwb_exclude_user_agents', 'sanitize_textarea_field' );
        register_setting( 'uwb_settings_group', 'uwb_always_purge_urls', array( $this, 'sanitize_always_purge_urls' ) );
        register_setting( 'uwb_settings_group', 'uwb_cache_query_strings', 'sanitize_textarea_field' );
        register_setting( 'uwb_settings_group', 'uwb_cache_xml_sitemaps', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_cache_xml_sitemaps_lifespan', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_cache_php', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_cache_php_lifespan', 'intval' );

        // Redis Object Cache Settings
        register_setting( 'uwb_settings_group', 'uwb_redis_enabled', array( $this, 'sanitize_object_cache_enabled' ) );
        register_setting( 'uwb_settings_group', 'uwb_redis_conn_type', 'sanitize_text_field' );
        register_setting( 'uwb_settings_group', 'uwb_redis_host', 'sanitize_text_field' );
        register_setting( 'uwb_settings_group', 'uwb_redis_port', 'intval' );
        register_setting( 'uwb_redis_socket', 'uwb_redis_socket', 'sanitize_text_field' );
        register_setting( 'uwb_settings_group', 'uwb_redis_password', 'sanitize_text_field' );
        register_setting( 'uwb_settings_group', 'uwb_redis_db', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_redis_prefix', 'sanitize_text_field' );
        register_setting( 'uwb_settings_group', 'uwb_redis_timeout', 'floatval' );
        register_setting( 'uwb_settings_group', 'uwb_redis_read_timeout', 'floatval' );
        register_setting( 'uwb_settings_group', 'uwb_redis_retry_interval', 'sanitize_text_field' );

        // Page Optimization Settings
        register_setting( 'uwb_settings_group', 'uwb_css_minify', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_css_combine', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_css_combine_ext_inline', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_css_load_async', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_css_font_display_opt', 'sanitize_text_field' );

        register_setting( 'uwb_settings_group', 'uwb_js_minify', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_js_combine', array( $this, 'sanitize_js_combine' ) );
        register_setting( 'uwb_settings_group', 'uwb_js_combine_ext_inline', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_js_load_defer', 'intval' );

        register_setting( 'uwb_settings_group', 'uwb_html_minify', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_html_remove_qs', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_html_remove_gfonts', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_html_remove_emoji', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_html_remove_noscript', 'intval' );

        register_setting( 'uwb_settings_group', 'uwb_media_lazy_load_images', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_media_lazy_load_iframes', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_media_image_placeholder', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_media_add_missing_sizes', 'intval' );

        register_setting( 'uwb_settings_group', 'uwb_media_lazy_load_excludes', 'sanitize_textarea_field' );
        register_setting( 'uwb_settings_group', 'uwb_media_lazy_load_class_excludes', 'sanitize_textarea_field' );

        // CDN Cache Settings
        register_setting( 'uwb_settings_group', 'uwb_cdn_enabled', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_cdn_provider', 'sanitize_text_field' );
        register_setting( 'uwb_settings_group', 'uwb_cdn_account_id', 'sanitize_text_field' );
        register_setting( 'uwb_settings_group', 'uwb_cdn_access_key', 'sanitize_text_field' );
        register_setting( 'uwb_settings_group', 'uwb_cdn_secret_key', 'sanitize_text_field' );
        register_setting( 'uwb_settings_group', 'uwb_cdn_bucket', 'sanitize_text_field' );
        register_setting( 'uwb_settings_group', 'uwb_cdn_endpoint', 'esc_url_raw' );
        register_setting( 'uwb_settings_group', 'uwb_cdn_region', 'sanitize_text_field' );
        register_setting( 'uwb_settings_group', 'uwb_cdn_custom_domain', 'esc_url_raw' );
        register_setting( 'uwb_settings_group', 'uwb_cdn_cache_control', 'sanitize_text_field' );
        register_setting( 'uwb_settings_group', 'uwb_cdn_file_types_images', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_cdn_file_types_css', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_cdn_file_types_js', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_cdn_file_types_fonts', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_cdn_file_types_media', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_cdn_auto_upload', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_cdn_auto_upload_combined', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_cdn_auto_purge_minified', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_cdn_auto_delete', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_cdn_delete_local', 'intval' );

        // Cloudflare Zone Cache Purge Settings
        register_setting( 'uwb_settings_group', 'uwb_cf_enabled', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_cf_zone_id', 'sanitize_text_field' );
        register_setting( 'uwb_settings_group', 'uwb_cf_api_token', 'sanitize_text_field' );
        register_setting( 'uwb_settings_group', 'uwb_cf_auto_purge_on_clear', 'intval' );

        // CDN Distribution by Asset Type
        register_setting( 'uwb_settings_group', 'uwb_cdn_distribute_css', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_cdn_auto_upload_combined_css', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_cdn_auto_upload_minified_css', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_cdn_auto_purge_css_cdn', 'intval' );

        register_setting( 'uwb_settings_group', 'uwb_cdn_distribute_js', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_cdn_auto_upload_combined_js', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_cdn_auto_upload_minified_js', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_cdn_auto_purge_js_cdn', 'intval' );

        register_setting( 'uwb_settings_group', 'uwb_cdn_distribute_html', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_cdn_auto_rewrite_html_urls', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_cdn_auto_purge_html_cf', 'intval' );

        register_setting( 'uwb_settings_group', 'uwb_cdn_distribute_media', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_cdn_auto_upload_attachment', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_cdn_auto_update_attachment', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_cdn_auto_rewrite_attachment_url', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_cdn_auto_delete_attachment', 'intval' );

        register_setting( 'uwb_settings_group', 'uwb_cdn_distribute_font', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_cdn_auto_upload_fonts', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_cdn_auto_rewrite_font_urls', 'intval' );

        register_setting( 'uwb_settings_group', 'uwb_tuning_css_excludes', 'sanitize_textarea_field' );
        register_setting( 'uwb_settings_group', 'uwb_tuning_js_excludes', 'sanitize_textarea_field' );
        register_setting( 'uwb_settings_group', 'uwb_tuning_js_defer_excludes', 'sanitize_textarea_field' );

        register_setting( 'uwb_settings_group', 'uwb_tuning_critical_css', 'sanitize_textarea_field' );
        register_setting( 'uwb_settings_group', 'uwb_ignore_all_query_strings', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_auto_collect_params', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_collected_params', 'sanitize_textarea_field' );
        register_setting( 'uwb_settings_group', 'uwb_cache_search', 'intval' );

        // Advanced / Tools Settings
        register_setting( 'uwb_settings_group', 'uwb_heartbeat_control', 'sanitize_text_field' );
        register_setting( 'uwb_settings_group', 'uwb_heartbeat_interval', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_preconnect_domains', 'sanitize_textarea_field' );
        register_setting( 'uwb_settings_group', 'uwb_preload_fonts', 'sanitize_textarea_field' );
        register_setting( 'uwb_settings_group', 'uwb_delay_js', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_delay_js_exclusions', 'sanitize_textarea_field' );
        register_setting( 'uwb_settings_group', 'uwb_debug_mode', 'intval' );
    }

    public function sanitize_object_cache_enabled( $val ) {
        $val = intval( $val );
        if ( $val === 1 && ! ( extension_loaded( 'redis' ) || class_exists( 'Redis' ) ) ) {
            add_settings_error( 'uwb_redis_enabled', 'redis_missing', 'Không thể kích hoạt Redis Object Cache do PHP Redis extension chưa được cài đặt trên máy chủ.', 'error' );
            return 0;
        }
        if ( $val === 2 && ! extension_loaded( 'memcached' ) ) {
            add_settings_error( 'uwb_redis_enabled', 'memcached_missing', 'Không thể kích hoạt Memcached Object Cache do PHP Memcached extension chưa được cài đặt trên máy chủ.', 'error' );
            return 0;
        }
        return $val;
    }

    /**
     * Sanitize uwb_js_combine: force 0 if Delay JS is enabled.
     * JS Combine and Delay JS are incompatible — the combined file executes before
     * the Delay JS loader can re-inject delayed scripts in the correct order.
     */
    public function sanitize_js_combine( $val ) {
        $val = intval( $val );
        if ( $val === 1 && intval( get_option( 'uwb_delay_js', 0 ) ) === 1 ) {
            add_settings_error(
                'uwb_js_combine',
                'js_combine_delay_conflict',
                'JS Combine has been automatically disabled because Delay JavaScript Execution is enabled. Disable Delay JS first to use JS Combine.',
                'warning'
            );
            return 0;
        }
        return $val;
    }

    private function uwb_clean_url_to_uri( $url ) {
        $url = trim( (string) $url );
        if ( empty( $url ) ) {
            return '';
        }

        $home_url = home_url();
        $parsed_home = wp_parse_url( $home_url );
        $home_host = isset( $parsed_home['host'] ) ? $parsed_home['host'] : '';
        $home_path = isset( $parsed_home['path'] ) ? trim( $parsed_home['path'], '/' ) : '';

        // Strip protocol and host
        if ( ! empty( $home_host ) ) {
            $url = preg_replace( '#^(https?:)?//' . preg_quote( $home_host, '#' ) . '#i', '', $url );
        }

        // Strip subdirectory if present
        if ( ! empty( $home_path ) ) {
            $url = preg_replace( '#^/?' . preg_quote( $home_path, '#' ) . '#i', '', $url );
        }

        // Strip any remaining protocol/host (e.g. if they put another domain or custom scheme)
        $url = preg_replace( '#^(https?:)?//[^/]+#i', '', $url );

        // Ensure it starts with /
        $url = '/' . ltrim( $url, '/' );

        return $url;
    }

    public function sanitize_sitemap_list( $value ) {
        $lines = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", '', (string) $value ) ) ) );
        $uris  = array();

        foreach ( $lines as $line ) {
            $uri = $this->uwb_clean_url_to_uri( $line );
            if ( ! empty( $uri ) ) {
                $uris[] = $uri;
            }
        }

        if ( empty( $uris ) ) {
            $uris = $this->get_default_preload_sitemaps();
        }

        return implode( "\n", array_values( array_unique( $uris ) ) );
    }

    public function sanitize_priority_urls( $val ) {
        $val = sanitize_textarea_field( $val );
        $lines = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", '', $val ) ) ) );
        
        $preloader = new Preloader();
        $normalized_uris = array();
        foreach ( $lines as $line ) {
            $uri = $this->uwb_clean_url_to_uri( $line );
            $normalized_uri = $preloader->normalize_url_by_permalink_settings( $uri );
            if ( ! empty( $normalized_uri ) ) {
                $normalized_uris[] = $normalized_uri;
            }
        }
        
        return implode( "\n", array_unique( $normalized_uris ) );
    }

    public function sanitize_excluded_urls( $val ) {
        $val = sanitize_textarea_field( $val );
        $lines = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", '', $val ) ) ) );
        
        $cleaned_uris = array();
        foreach ( $lines as $line ) {
            $cleaned = $this->uwb_clean_url_to_uri( $line );
            if ( ! empty( $cleaned ) ) {
                $cleaned_uris[] = $cleaned;
            }
        }
        
        return implode( "\n", array_unique( $cleaned_uris ) );
    }

    public function sanitize_always_purge_urls( $val ) {
        $val = sanitize_textarea_field( $val );
        $lines = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", '', $val ) ) ) );
        
        $cleaned_uris = array();
        foreach ( $lines as $line ) {
            $cleaned = $this->uwb_clean_url_to_uri( $line );
            if ( ! empty( $cleaned ) ) {
                $cleaned_uris[] = $cleaned;
            }
        }
        
        return implode( "\n", array_unique( $cleaned_uris ) );
    }

    private function get_default_preload_sitemaps() {
        return array(
            '/important-sitemap.xml',
            '/wp-sitemap.xml',
        );
    }

    private function get_preload_sitemap_setting_value() {
        $value = get_option( 'uwb_preload_sitemap', '' );
        if ( empty( $value ) ) {
            $uris = $this->get_default_preload_sitemaps();
        } else {
            $uris = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", '', $value ) ) ) );
        }

        $urls = array();
        foreach ( $uris as $uri ) {
            if ( strpos( $uri, 'http' ) !== 0 ) {
                $urls[] = home_url( '/' . ltrim( $uri, '/' ) );
            } else {
                $urls[] = $uri;
            }
        }

        return implode( "\n", $urls );
    }

    private function get_priority_urls_setting_value() {
        $value = get_option( 'uwb_priority_urls', '' );
        if ( empty( $value ) ) {
            return '';
        }
        $uris = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", '', $value ) ) ) );
        $urls = array();
        foreach ( $uris as $uri ) {
            if ( strpos( $uri, 'http' ) !== 0 ) {
                $urls[] = home_url( '/' . ltrim( $uri, '/' ) );
            } else {
                $urls[] = $uri;
            }
        }
        return implode( "\n", $urls );
    }

    private function get_excluded_urls_setting_value() {
        $value = get_option( 'uwb_excluded_urls', '' );
        if ( empty( $value ) ) {
            return '';
        }
        $uris = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", '', $value ) ) ) );
        $urls = array();
        foreach ( $uris as $uri ) {
            if ( strpos( $uri, 'http' ) !== 0 && strpos( $uri, '/' ) === 0 ) {
                $urls[] = home_url( $uri );
            } else {
                $urls[] = $uri;
            }
        }
        return implode( "\n", $urls );
    }

    private function get_always_purge_urls_setting_value() {
        $value = get_option( 'uwb_always_purge_urls', '' );
        if ( empty( $value ) ) {
            return '';
        }
        $uris = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", '', $value ) ) ) );
        $urls = array();
        foreach ( $uris as $uri ) {
            if ( strpos( $uri, 'http' ) !== 0 && strpos( $uri, '/' ) === 0 ) {
                $urls[] = home_url( $uri );
            } else {
                $urls[] = $uri;
            }
        }
        return implode( "\n", $urls );
    }

    private function migrate_default_important_sitemap() {
        if ( get_option( 'uwb_preload_sitemap_defaults_migrated' ) ) {
            return;
        }

        $important_sitemap = home_url( '/important-sitemap.xml' );
        $value = get_option( 'uwb_preload_sitemap', '' );
        $lines = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", '', (string) $value ) ) ) );

        if ( empty( $lines ) ) {
            $lines = $this->get_default_preload_sitemaps();
        } elseif ( ! in_array( $important_sitemap, $lines, true ) ) {
            array_unshift( $lines, $important_sitemap );
        }

        update_option( 'uwb_preload_sitemap', implode( "\n", array_values( array_unique( $lines ) ) ) );
        update_option( 'uwb_preload_sitemap_defaults_migrated', 1 );
    }

    public function admin_init_sync() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ultimate_wp_booster_queue';
        // Auto-migrate column from tinyint(1) to int(11) in case database has already been created
        $row = $wpdb->get_row( $wpdb->prepare( "SHOW COLUMNS FROM {$table_name} LIKE %s", 'priority' ) );
        if ( $row && strpos( strtolower( $row->Type ), 'tinyint' ) !== false ) {
            $wpdb->query( "ALTER TABLE {$table_name} MODIFY COLUMN priority int(11) NOT NULL DEFAULT 0" );
        }

        // Migrate options from hours to minutes for older versions
        $migrated_hours_to_mins = get_option( 'uwb_hours_to_minutes_migrated_v2' );
        if ( ! $migrated_hours_to_mins ) {
            $cache_lifespan = get_option( 'uwb_cache_lifespan' );
            if ( $cache_lifespan !== false && floatval( $cache_lifespan ) < 48 ) {
                update_option( 'uwb_cache_lifespan', floatval( $cache_lifespan ) * 60 );
            }
            $browser_lifespan = get_option( 'uwb_browser_cache_lifespan' );
            if ( $browser_lifespan !== false && floatval( $browser_lifespan ) < 48 ) {
                update_option( 'uwb_browser_cache_lifespan', floatval( $browser_lifespan ) * 60 );
            }
            $xml_lifespan = get_option( 'uwb_cache_xml_sitemaps_lifespan' );
            if ( $xml_lifespan !== false && floatval( $xml_lifespan ) < 48 ) {
                update_option( 'uwb_cache_xml_sitemaps_lifespan', floatval( $xml_lifespan ) * 60 );
            }
            $php_lifespan = get_option( 'uwb_cache_php_lifespan' );
            if ( $php_lifespan !== false && floatval( $php_lifespan ) < 48 ) {
                update_option( 'uwb_cache_php_lifespan', floatval( $php_lifespan ) * 60 );
            }
            update_option( 'uwb_hours_to_minutes_migrated_v2', 1 );
        }

        Activation::copy_advanced_cache_dropin();
        Activation::copy_object_cache_dropin();
        $this->migrate_default_important_sitemap();
        // Sync config JSON file to keep core options (like timezone) up to date
        CacheManager::write_config_file();

        if ( isset( $_GET['uwb_opcache_flushed'] ) ) {
            add_action( 'admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p><strong>Ultimate WP Booster:</strong> OPcache flushed successfully!</p></div>';
            } );
        }

        if ( isset( $_GET['uwb_msg'] ) ) {
            $msg = sanitize_text_field( $_GET['uwb_msg'] );
            add_action( 'admin_notices', function() use ( $msg ) {
                if ( $msg === 'preload_started' ) {
                    echo '<div class="notice notice-success is-dismissible"><p><strong>Ultimate WP Booster:</strong> Cache cleared successfully! Preload queue populating in the background.</p></div>';
                } elseif ( $msg === 'cache_cleared' ) {
                    echo '<div class="notice notice-success is-dismissible"><p><strong>Ultimate WP Booster:</strong> Page cache cleared successfully!</p></div>';
                }
            } );
        }
    }

    public function handle_import_export() {
        if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // 1. Handle Export
        if ( isset( $_POST['uwb_export_settings'] ) ) {
            check_admin_referer( 'uwb_import_export_action', 'uwb_import_export_nonce' );

            $options_to_export = array(
                'uwb_cache_lifespan',
                'uwb_excluded_urls',
                'uwb_cache_logged_in',
                'uwb_cache_logged_in_lifespan',
                'uwb_browser_cache_enabled',
                'uwb_browser_cache_lifespan',
                'uwb_browser_cache_html',
                'uwb_browser_cache_html_lifespan',
                'uwb_browser_cache_css',
                'uwb_browser_cache_css_lifespan',
                'uwb_browser_cache_js',
                'uwb_browser_cache_js_lifespan',
                'uwb_browser_cache_image',
                'uwb_browser_cache_image_lifespan',
                'uwb_browser_cache_font',
                'uwb_browser_cache_font_lifespan',
                'uwb_browser_cache_other',
                'uwb_browser_cache_other_lifespan',
                'uwb_preload_enabled',
                'uwb_preload_sitemap',
                'uwb_preload_batch_size',
                'uwb_preload_links',
                'uwb_css_minify',
                'uwb_css_combine',
                'uwb_css_combine_ext_inline',
                'uwb_css_load_async',
                'uwb_css_font_display_opt',
                'uwb_js_minify',
                'uwb_js_combine',
                'uwb_js_combine_ext_inline',
                'uwb_js_load_defer',
                'uwb_html_minify',
                'uwb_html_remove_qs',
                'uwb_html_remove_gfonts',
                'uwb_html_remove_emoji',
                'uwb_html_remove_noscript',
                'uwb_media_lazy_load_images',
                'uwb_media_lazy_load_iframes',
                'uwb_media_image_placeholder',
                'uwb_media_add_missing_sizes',
                'uwb_vpi_enabled',
                'uwb_vpi_cron',
                'uwb_media_lazy_load_excludes',
                'uwb_media_lazy_load_class_excludes',
                'uwb_loc_gravatar_cache',
                'uwb_loc_gravatar_cache_cron',
                'uwb_loc_resources',
                'uwb_tuning_css_excludes',
                'uwb_tuning_js_excludes',
                'uwb_tuning_js_defer_excludes',
                'uwb_tuning_critical_css',
                'uwb_ignore_all_query_strings',
                'uwb_auto_collect_params',
                'uwb_collected_params'
            );

            $export_data = array();
            foreach ( $options_to_export as $opt ) {
                $export_data[ $opt ] = get_option( $opt );
            }

            $json = json_encode( $export_data, JSON_PRETTY_PRINT );
            $filename = 'ultimate-wp-booster-settings-' . wp_date( 'Hi-dmY' ) . '.json';
            header( 'Content-disposition: attachment; filename=' . $filename );
            header( 'Content-type: application/json' );
            echo $json;
            exit;
        }

        // 2. Handle Import
        if ( isset( $_POST['uwb_import_settings'] ) ) {
            check_admin_referer( 'uwb_import_export_action', 'uwb_import_export_nonce' );

            if ( ! empty( $_FILES['uwb_import_file']['tmp_name'] ) ) {
                $file = $_FILES['uwb_import_file']['tmp_name'];
                $json_content = file_get_contents( $file );
                $settings = json_decode( $json_content, true );

                if ( is_array( $settings ) ) {
                    $options_to_import = array(
                        'uwb_cache_lifespan',
                        'uwb_excluded_urls',
                        'uwb_cache_logged_in',
                        'uwb_cache_logged_in_lifespan',
                        'uwb_browser_cache_enabled',
                        'uwb_browser_cache_lifespan',
                        'uwb_browser_cache_html',
                        'uwb_browser_cache_html_lifespan',
                        'uwb_browser_cache_css',
                        'uwb_browser_cache_css_lifespan',
                        'uwb_browser_cache_js',
                        'uwb_browser_cache_js_lifespan',
                        'uwb_browser_cache_image',
                        'uwb_browser_cache_image_lifespan',
                        'uwb_browser_cache_font',
                        'uwb_browser_cache_font_lifespan',
                        'uwb_browser_cache_other',
                        'uwb_browser_cache_other_lifespan',
                        'uwb_ignored_query',
                        'uwb_redis_enabled',
                        'uwb_redis_conn_type',
                        'uwb_redis_host',
                        'uwb_redis_port',
                        'uwb_redis_socket',
                        'uwb_redis_password',
                        'uwb_redis_db',
                        'uwb_redis_prefix',
                        'uwb_redis_timeout',
                        'uwb_redis_read_timeout',
                        'uwb_redis_retry_interval',
                        'uwb_cache_404',
                        'uwb_exclude_cookies',
                        'uwb_exclude_user_agents',
                        'uwb_always_purge_urls',
                        'uwb_cache_query_strings',
                        'uwb_cache_xml_sitemaps',
                        'uwb_preload_enabled',
                        'uwb_preload_sitemap',
                        'uwb_priority_urls',
                        'uwb_preload_batch_size',
                        'uwb_preload_links',
                        'uwb_css_minify',
                        'uwb_css_combine',
                        'uwb_css_combine_ext_inline',
                        'uwb_css_load_async',
                        'uwb_css_font_display_opt',
                        'uwb_js_minify',
                        'uwb_js_combine',
                        'uwb_js_combine_ext_inline',
                        'uwb_js_load_defer',
                        'uwb_html_minify',
                        'uwb_html_remove_qs',
                        'uwb_html_remove_gfonts',
                        'uwb_html_remove_emoji',
                        'uwb_html_remove_noscript',
                        'uwb_media_lazy_load_images',
                        'uwb_media_lazy_load_iframes',
                        'uwb_media_image_placeholder',
                        'uwb_media_add_missing_sizes',
                        'uwb_vpi_enabled',
                        'uwb_vpi_cron',
                        'uwb_media_lazy_load_excludes',
                        'uwb_media_lazy_load_class_excludes',
                        'uwb_loc_gravatar_cache',
                        'uwb_loc_gravatar_cache_cron',
                        'uwb_loc_resources',
                        'uwb_tuning_css_excludes',
                        'uwb_tuning_js_excludes',
                        'uwb_tuning_js_defer_excludes',
                        'uwb_tuning_critical_css',
                        'uwb_ignore_all_query_strings',
                        'uwb_auto_collect_params',
                        'uwb_collected_params'
                    );

                    foreach ( $options_to_import as $opt ) {
                        if ( isset( $settings[ $opt ] ) ) {
                            update_option( $opt, $settings[ $opt ] );
                        }
                    }

                    // Force sync config file
                    CacheManager::write_config_file();

                    add_action( 'admin_notices', function() {
                        echo '<div class="notice notice-success is-dismissible"><p><strong>Ultimate WP Booster:</strong> Cấu hình đã được nhập thành công!</p></div>';
                    } );
                } else {
                    add_action( 'admin_notices', function() {
                        echo '<div class="notice notice-error is-dismissible"><p><strong>Ultimate WP Booster:</strong> Tệp JSON không hợp lệ hoặc bị lỗi!</p></div>';
                    } );
                }
            } else {
                add_action( 'admin_notices', function() {
                    echo '<div class="notice notice-error is-dismissible"><p><strong>Ultimate WP Booster:</strong> Vui lòng chọn tệp tin JSON trước khi nhấn Import!</p></div>';
                } );
            }
        }
    }

    private function render_toggle_switch( $option_name, $label_desc, $detailed_desc = '', $disabled = false ) {
        $val = intval( get_option( $option_name, 0 ) );
        if ( $disabled ) {
            $val = 0; // force 0 if disabled
        }
        ?>
        <div class="uwb-opt-row <?php echo $disabled ? 'uwb-opt-disabled' : ''; ?>" style="display: flex; justify-content: space-between; align-items: flex-start; background: #fff; border: 1px solid var(--uwb-border); border-radius: 8px; padding: 20px; margin-bottom: 16px; gap: 20px; flex-wrap: wrap; <?php echo $disabled ? 'opacity: 0.65;' : ''; ?>">
            <div style="flex: 1; min-width: 250px;">
                <strong style="font-size: 14px; color: var(--uwb-text); display: block; margin-bottom: 4px;">
                    <?php echo esc_html( $label_desc ); ?>
                    <?php if ( $disabled ) : ?>
                        <span style="background: #cbd5e1; color: #475569; font-size: 10px; padding: 2px 6px; border-radius: 4px; font-weight: 700; margin-left: 8px; vertical-align: middle;">Updating...</span>
                    <?php endif; ?>
                </strong>
                <?php if ( ! empty( $detailed_desc ) ) : ?>
                    <span class="description" style="font-size: 12.5px; color: var(--uwb-text-muted); line-height: 1.4; display: block;"><?php echo wp_kses_post( $detailed_desc ); ?></span>
                <?php endif; ?>
            </div>
            <div style="flex-shrink: 0;">
                <div class="uwb-toggle-container <?php echo $disabled ? 'disabled' : ''; ?>">
                    <label class="uwb-toggle-btn <?php echo $disabled ? 'disabled' : ''; ?> <?php echo ! $val ? 'active' : ''; ?>">
                        <input type="radio" name="<?php echo esc_attr( $option_name ); ?>" value="0" <?php checked( $val, 0 ); ?> <?php disabled( $disabled, true ); ?> class="uwb-toggle-input"> OFF
                    </label>
                    <label class="uwb-toggle-btn <?php echo $disabled ? 'disabled' : ''; ?> <?php echo $val ? 'active' : ''; ?>">
                        <input type="radio" name="<?php echo esc_attr( $option_name ); ?>" value="1" <?php checked( $val, 1 ); ?> <?php disabled( $disabled, true ); ?> class="uwb-toggle-input"> ON
                    </label>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_textarea_setting( $option_name, $label_desc, $placeholder = '', $detailed_desc = '', $disabled = false ) {
        $val = get_option( $option_name, '' );
        if ( $disabled ) {
            $val = '';
        }
        ?>
        <div class="uwb-opt-row <?php echo $disabled ? 'uwb-opt-disabled' : ''; ?>" style="background: #fff; border: 1px solid var(--uwb-border); border-radius: 8px; padding: 20px; margin-bottom: 16px; <?php echo $disabled ? 'opacity: 0.65;' : ''; ?>">
            <strong style="font-size: 14px; color: var(--uwb-text); display: block; margin-bottom: 8px;">
                <?php echo esc_html( $label_desc ); ?>
                <?php if ( $disabled ) : ?>
                    <span style="background: #cbd5e1; color: #475569; font-size: 10px; padding: 2px 6px; border-radius: 4px; font-weight: 700; margin-left: 8px; vertical-align: middle;">Updating...</span>
                <?php endif; ?>
            </strong>
            <textarea name="<?php echo esc_attr( $option_name ); ?>" id="<?php echo esc_attr( $option_name ); ?>" rows="5" placeholder="<?php echo esc_attr( $placeholder ); ?>" <?php disabled( $disabled, true ); ?> style="width: 100%; border: 1px solid var(--uwb-border); border-radius: 8px; padding: 12px; font-size: 13.5px; <?php echo $disabled ? 'background: #f1f5f9; cursor: not-allowed;' : ''; ?>"><?php echo esc_textarea( $val ); ?></textarea>
            <?php if ( ! empty( $detailed_desc ) ) : ?>
                <span class="description" style="font-size: 12.5px; color: var(--uwb-text-muted); line-height: 1.4; display: block; margin-top: 6px;"><?php echo wp_kses_post( $detailed_desc ); ?></span>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Enqueue styled assets for premium dashboard
     */
    private function render_assets() {
        global $_wp_admin_css_colors;
        $color_scheme = get_user_option( 'admin_color' );
        if ( empty( $color_scheme ) ) {
            $color_scheme = 'fresh';
        }

        $primary_color = '#6366f1';
        $primary_dark = '#4f46e5';
        $header_bg_start = '#1d2327';
        $header_bg_end = '#2c3338';

        if ( ! empty( $_wp_admin_css_colors ) && isset( $_wp_admin_css_colors[ $color_scheme ] ) ) {
            $colors = $_wp_admin_css_colors[ $color_scheme ]->colors;
            if ( isset( $colors[0] ) ) {
                $header_bg_start = $colors[0];
            }
            if ( isset( $colors[1] ) ) {
                $header_bg_end = $colors[1];
            }
            if ( isset( $colors[2] ) ) {
                $primary_color = $colors[2];
            }
            if ( isset( $colors[3] ) ) {
                $primary_dark = $colors[3];
            } else if ( isset( $colors[2] ) ) {
                $primary_dark = $colors[2];
            }
        }
        ?>
        <style>
            :root {
                --uwb-primary: <?php echo esc_attr( $primary_color ); ?>;
                --uwb-primary-dark: <?php echo esc_attr( $primary_dark ); ?>;
                --uwb-success: #10b981;
                --uwb-warning: #f59e0b;
                --uwb-danger: #ef4444;
                --uwb-bg: #f8fafc;
                --uwb-card-bg: #ffffff;
                --uwb-text: #1e293b;
                --uwb-text-muted: #64748b;
                --uwb-border: #e2e8f0;
            }

            .uwb-dashboard-wrap {
                margin: 20px 20px 0 0;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
                color: var(--uwb-text);
            }

            .uwb-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                background: <?php echo esc_attr( $header_bg_start ); ?>;
                padding: 24px 32px;
                border-radius: 16px;
                box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.08), 0 4px 6px -2px rgba(0, 0, 0, 0.04);
                color: #ffffff;
                margin-bottom: 24px;
                border-left: 4px solid var(--uwb-primary);
            }

            .uwb-header-title h1 {
                margin: 0;
                color: #ffffff;
                font-size: 24px;
                font-weight: 800;
                letter-spacing: -0.5px;
                text-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }

            .uwb-header-title p {
                margin: 6px 0 0 0;
                opacity: 0.9;
                font-size: 14px;
            }

            .uwb-header-actions {
                display: flex;
                gap: 12px;
            }

            .uwb-btn-purge {
                background: rgba(255, 255, 255, 0.2);
                border: 1px solid rgba(255, 255, 255, 0.3);
                color: #ffffff;
                padding: 10px 20px;
                font-weight: 600;
                border-radius: 8px;
                text-decoration: none;
                transition: all 0.2s ease;
                display: inline-flex;
                align-items: center;
                gap: 8px;
                font-size: 14px;
            }

            .uwb-btn-purge:hover {
                background: #ffffff;
                color: var(--uwb-primary-dark);
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            }

            .uwb-layout {
                display: grid;
                grid-template-columns: 240px 1fr;
                gap: 24px;
                transition: grid-template-columns 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }

            .uwb-layout.collapsed {
                grid-template-columns: 78px 1fr;
            }

            .uwb-sidebar-nav {
                background: var(--uwb-card-bg);
                border-radius: 12px;
                border: 1px solid var(--uwb-border);
                padding: 16px;
                display: flex;
                flex-direction: column;
                gap: 8px;
                height: fit-content;
                transition: all 0.3s ease;
                overflow: hidden;
            }

            .uwb-layout.collapsed .uwb-sidebar-nav {
                padding: 16px 8px;
            }

            .uwb-sidebar-toggle {
                display: flex;
                align-items: center;
                justify-content: flex-end;
                padding: 8px;
                cursor: pointer;
                color: var(--uwb-text-muted);
                border-bottom: 1px solid var(--uwb-border);
                margin-bottom: 8px;
                transition: all 0.2s ease;
            }

            .uwb-layout.collapsed .uwb-sidebar-toggle {
                justify-content: center;
                border-bottom: none;
                margin-bottom: 0;
            }

            .uwb-nav-item {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 12px 16px;
                border-radius: 8px;
                color: var(--uwb-text-muted);
                text-decoration: none;
                font-weight: 600;
                font-size: 14px;
                transition: all 0.2s ease;
                cursor: pointer;
            }

            .uwb-nav-item span {
                transition: opacity 0.2s ease;
                white-space: nowrap;
            }

            .uwb-layout.collapsed .uwb-nav-item span {
                display: none;
                opacity: 0;
            }

            .uwb-layout.collapsed .uwb-nav-item {
                justify-content: center;
                padding: 12px;
            }

            .uwb-nav-item:hover, .uwb-nav-item.active {
                background: #f1f5f9;
                color: var(--uwb-primary);
            }

            .uwb-nav-item.active {
                background: #e0e7ff;
                color: var(--uwb-primary-dark);
            }

            /* Sub-tabs Styling */
            .uwb-sub-tabs-nav {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
                border-bottom: 2px solid var(--uwb-border);
                margin-bottom: 24px;
                padding-bottom: 12px;
            }

            .uwb-sub-tab-item {
                padding: 10px 18px;
                font-weight: 700;
                font-size: 13.5px;
                color: var(--uwb-text-muted);
                cursor: pointer;
                border-radius: 8px;
                transition: all 0.2s ease;
                background: #f8fafc;
                border: 1px solid var(--uwb-border);
            }

            .uwb-sub-tab-item:hover, .uwb-sub-tab-item.active {
                background: #e0e7ff;
                color: var(--uwb-primary-dark);
                border-color: #c7d2fe;
            }

            .uwb-subtab-content {
                display: none;
            }

            .uwb-subtab-content.active {
                display: block;
            }

            .uwb-content-panel {
                background: var(--uwb-card-bg);
                border-radius: 12px;
                border: 1px solid var(--uwb-border);
                padding: 32px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            }

            .uwb-tab-content {
                display: none;
            }

            .uwb-tab-content.active {
                display: block;
            }

            .uwb-form-group {
                margin-bottom: 24px;
                max-width: 700px;
            }

            .uwb-form-group label {
                display: block;
                font-weight: 600;
                margin-bottom: 8px;
                color: var(--uwb-text);
                font-size: 14px;
            }

            .uwb-form-group input[type="text"],
            .uwb-form-group input[type="number"],
            .uwb-form-group textarea {
                width: 100%;
                border: 1px solid var(--uwb-border);
                border-radius: 8px;
                padding: 12px;
                font-size: 14px;
                color: var(--uwb-text);
                background-color: var(--uwb-bg);
                transition: border-color 0.2s ease, box-shadow 0.2s ease;
            }

            .uwb-form-group input:focus,
            .uwb-form-group textarea:focus {
                border-color: var(--uwb-primary);
                box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
                outline: none;
            }

            .uwb-form-group .description {
                margin-top: 6px;
                color: var(--uwb-text-muted);
                font-size: 12.5px;
                line-height: 1.4;
            }

            /* Preloader Progress CSS */
            .uwb-preload-status-box {
                background: var(--uwb-bg);
                border-radius: 10px;
                padding: 24px;
                border: 1px solid var(--uwb-border);
                margin-bottom: 24px;
            }

            .uwb-stats-grid {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 16px;
                margin-bottom: 24px;
            }

            .uwb-stat-card {
                background: var(--uwb-card-bg);
                border: 1px solid var(--uwb-border);
                border-radius: 8px;
                padding: 16px;
                text-align: center;
                box-shadow: 0 1px 2px rgba(0,0,0,0.02);
            }

            .uwb-stat-card .num {
                font-size: 28px;
                font-weight: 800;
                margin-bottom: 4px;
            }

            .uwb-stat-card .label {
                font-size: 12px;
                font-weight: 600;
                color: var(--uwb-text-muted);
                text-transform: uppercase;
            }

            .uwb-stat-pending .num { color: var(--uwb-text); }
            .uwb-stat-processing .num { color: var(--uwb-warning); }
            .uwb-stat-completed .num { color: var(--uwb-success); }
            .uwb-stat-failed .num { color: var(--uwb-danger); }

            .uwb-progress-bar-wrap {
                background: #e2e8f0;
                border-radius: 100px;
                height: 12px;
                width: 100%;
                overflow: hidden;
                margin-bottom: 12px;
            }

            .uwb-progress-bar-fill {
                background: linear-gradient(90deg, #10b981 0%, #059669 100%);
                height: 100%;
                width: 0%;
                transition: width 0.3s ease;
            }

            .uwb-progress-text {
                display: flex;
                justify-content: space-between;
                font-size: 13px;
                font-weight: 600;
                color: var(--uwb-text);
            }

            .uwb-preload-actions {
                display: flex;
                gap: 12px;
            }

            .uwb-btn-action {
                border-radius: 8px;
                padding: 10px 18px;
                font-size: 13.5px;
                font-weight: 600;
                cursor: pointer;
                border: 1px solid transparent;
                transition: all 0.2s ease;
                display: inline-flex;
                align-items: center;
                gap: 6px;
            }

            .uwb-btn-start { background: var(--uwb-primary); color: #ffffff; }
            .uwb-btn-start:hover { background: var(--uwb-primary-dark); }
            .uwb-btn-stop { background: var(--uwb-warning); color: #ffffff; }
            .uwb-btn-stop:hover { background: #d97706; }
            .uwb-btn-clear { background: #f1f5f9; border-color: #cbd5e1; color: var(--uwb-text); }
            .uwb-btn-clear:hover { background: #e2e8f0; }

            .uwb-nginx-instructions {
                background: #0f172a;
                color: #f8fafc;
                padding: 24px;
                border-radius: 10px;
                font-family: monospace;
                overflow-x: auto;
                font-size: 13px;
                line-height: 1.6;
                border-left: 4px solid var(--uwb-primary);
            }

            #uwb-filter-wc {
                transition: all 0.2s ease;
            }
            #uwb-filter-wc:hover {
                background: #f1f5f9 !important;
                border-color: #cbd5e1 !important;
            }
            #uwb-filter-wc.active {
                background: #e0e7ff !important;
                color: var(--uwb-primary-dark) !important;
                border-color: var(--uwb-primary) !important;
            }
            .uwb-btn-purge:disabled {
                opacity: 0.6;
                cursor: not-allowed;
            }
            .uwb-spinner {
                width: 16px;
                height: 16px;
                border: 2px solid #ffffff;
                border-bottom-color: transparent;
                border-radius: 50%;
                display: inline-block;
                box-sizing: border-box;
                animation: uwb-rotation 1s linear infinite;
            }
            @keyframes uwb-rotation {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }

            @keyframes uwb-rotation {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }

            /* Vertical Cache Tree Layout */
            .uwb-pipeline-container {
                background: #f8fafc;
                border: 1px solid var(--uwb-border);
                border-radius: 12px;
                padding: 24px;
                margin-bottom: 24px;
            }
            .uwb-pipeline-tree {
                display: flex;
                flex-direction: column;
                gap: 0;
                position: relative;
                margin-top: 16px;
            }
            .uwb-pipeline-tree::before {
                content: '';
                position: absolute;
                left: 27px;
                top: 24px;
                bottom: 24px;
                width: 2px;
                background: var(--uwb-border);
                z-index: 1;
            }
            .uwb-tree-node {
                display: flex;
                align-items: center;
                justify-content: space-between;
                background: var(--uwb-card-bg);
                border: 1px solid var(--uwb-border);
                border-radius: 10px;
                padding: 16px 20px;
                margin-bottom: 12px;
                position: relative;
                z-index: 2;
                transition: all 0.2s ease;
                box-shadow: 0 1px 3px rgba(0,0,0,0.02);
                box-sizing: border-box;
            }
            .uwb-tree-node:hover {
                transform: translateX(4px);
                box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            }
            .node-status-left {
                width: 16px;
                height: 16px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin-right: 16px;
                flex-shrink: 0;
            }
            .uwb-tree-node.active .node-status-left {
                background: #d1fae5;
                border: 2px solid #10b981;
            }
            .uwb-tree-node.inactive .node-status-left {
                background: #f1f5f9;
                border: 2px solid #94a3b8;
            }
            .uwb-tree-node.active .node-status-left::after {
                content: '';
                width: 6px;
                height: 6px;
                border-radius: 50%;
                background: #10b981;
            }
            .uwb-tree-node.inactive .node-status-left::after {
                content: '';
                width: 6px;
                height: 6px;
                border-radius: 50%;
                background: #94a3b8;
            }
            .node-info-mid {
                display: flex;
                align-items: center;
                flex: 1;
                margin-right: 20px;
            }
            .node-icon-wrap {
                width: 36px;
                height: 36px;
                border-radius: 8px;
                background: #f1f5f9;
                color: var(--uwb-text-muted);
                display: flex;
                align-items: center;
                justify-content: center;
                margin-right: 16px;
                flex-shrink: 0;
            }
            .uwb-tree-node.active .node-icon-wrap {
                background: #e0e7ff;
                color: var(--uwb-primary);
            }
            .node-text-wrap {
                display: flex;
                flex-direction: column;
                gap: 2px;
            }
            .node-title {
                font-weight: 700;
                font-size: 13.5px;
                color: var(--uwb-text);
            }
            .node-desc {
                font-size: 12px;
                color: var(--uwb-text-muted);
            }
            .node-action-right {
                display: flex;
                align-items: center;
                gap: 8px;
                flex-shrink: 0;
            }
            .uwb-btn-mini {
                padding: 6px 12px;
                font-size: 12px;
                font-weight: 600;
                color: var(--uwb-text);
                background: #ffffff;
                border: 1px solid var(--uwb-border);
                border-radius: 6px;
                cursor: pointer;
                transition: all 0.2s ease;
            }
            .uwb-btn-mini:hover {
                background: #f1f5f9;
                border-color: #cbd5e1;
            }
            .uwb-btn-mini-danger {
                background: #fee2e2;
                border-color: #fca5a5;
                color: #991b1b;
            }
            .uwb-btn-mini-danger:hover {
                background: #fecaca;
                border-color: #f87171;
            }

            /* Toggle Buttons Caching & Optimization switches */
            .uwb-toggle-container {
                display: inline-flex;
                background: #f1f5f9;
                border: 1px solid var(--uwb-border);
                border-radius: 8px;
                padding: 3px;
                gap: 2px;
            }
            .uwb-toggle-btn {
                padding: 8px 20px;
                font-size: 13px;
                font-weight: 700;
                cursor: pointer;
                border-radius: 6px;
                color: var(--uwb-text-muted);
                transition: all 0.2s ease;
                user-select: none;
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }
            .uwb-toggle-btn.active {
                background: var(--uwb-primary);
                color: #ffffff;
                box-shadow: 0 2px 4px rgba(99, 102, 241, 0.2);
            }
            .uwb-toggle-container.disabled {
                background: #e2e8f0;
                cursor: not-allowed;
            }
            .uwb-toggle-btn.disabled {
                cursor: not-allowed;
                opacity: 0.6;
            }
            .uwb-toggle-btn.disabled.active {
                background: #94a3b8;
                color: #ffffff;
                box-shadow: none;
            }
            .uwb-toggle-input {
                display: none !important;
            }
            .uwb-warning-box {
                margin: 10px 0;
                background: #fffbeb;
                border-left: 4px solid #f59e0b;
                padding: 12px;
                border-radius: 4px;
                font-size: 13px;
                color: #b45309;
            }
            .uwb-opt-row a {
                color: var(--uwb-primary);
                text-decoration: none;
                font-weight: 600;
            }
            .uwb-opt-row a:hover {
                text-decoration: underline;
            }
        </style>
        <?php
    }

    public function render_settings_page() {
        $this->render_assets();

        $cdn_active = ! empty( $_SERVER['HTTP_CF_RAY'] ) || ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) || ! empty( $_SERVER['HTTP_X_CDN_FORWARD'] );
        $cdn_details = $cdn_active ? 'Cloudflare / CDN Proxy Detected' : 'No active CDN proxy header detected.';
        
        $server_software = ! empty( $_SERVER['SERVER_SOFTWARE'] ) ? $_SERVER['SERVER_SOFTWARE'] : '';
        $detected_server = 'other';
        $webserver_active = false;
        $webserver_details = 'Webserver cache not detected.';
        if ( stripos( $server_software, 'litespeed' ) !== false ) {
            $detected_server = 'litespeed';
            $webserver_active = true;
            $webserver_details = 'LiteSpeed Web Server Cache Supported';
        } elseif ( stripos($server_software, 'nginx' ) !== false ) {
            $detected_server = 'nginx';
            $webserver_active = true;
            $webserver_details = 'Nginx Cache / FastCGI Cache Supported';
        } elseif ( stripos( $server_software, 'apache' ) !== false ) {
            $detected_server = 'apache';
            $webserver_active = true;
            $webserver_details = 'Apache Web Server / mod_expires Supported';
        } else {
            $webserver_details = esc_html($server_software);
        }
        
        $purge_url = wp_nonce_url( admin_url( 'admin.php?page=ultimate-wp-booster&action=uwb_purge_cache' ), 'uwb_purge_cache_action' );
        $update_nonce = wp_create_nonce( 'uwb_github_update_nonce' );
        ?>
        <div class="uwb-dashboard-wrap">
            <div class="uwb-header">
                <div class="uwb-header-title">
                    <h1>Ultimate WordPress Booster v<?php echo esc_html( UWB_VERSION ); ?></h1>
                    <p>Optimize website loading speed with ultra-fast Static Page Caching.</p>
                </div>
                <div class="uwb-header-actions" style="display: flex; align-items: center; gap: 12px;">
                    <span id="uwb-github-update-status" style="font-size: 13px; font-weight: 600; color: rgba(255, 255, 255, 0.9);"></span>
                    <button type="button" id="uwb-github-update-btn" class="uwb-btn-purge" style="cursor: pointer; border: 1px solid rgba(255, 255, 255, 0.3); outline: none;">
                        <svg class="uwb-git-icon" viewBox="0 0 16 16" width="16" height="16" aria-hidden="true" style="color: inherit;"><path fill="currentColor" d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z"></path></svg>
                        <span class="uwb-btn-text">Update Plugin</span>
                        <span class="uwb-spinner" style="display: none; margin-left: 6px;"></span>
                    </button>
                </div>
            </div>

            <div class="uwb-layout">
                <div class="uwb-sidebar-nav">
                    <div class="uwb-sidebar-toggle" id="uwb-toggle-sidebar" title="Thu gọn / Mở rộng">
                        <svg class="toggle-icon-collapse" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
                        <svg class="toggle-icon-expand" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="display:none;"><polyline points="9 18 15 12 9 6"/></svg>
                    </div>
                    <div class="uwb-nav-item active" data-tab="url_status" title="Dashboard">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>
                        <span>Dashboard</span>
                    </div>
                    <div class="uwb-nav-item" data-tab="cache_settings" title="Cache Settings">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                        <span>Cache Settings</span>
                    </div>
                    <div class="uwb-nav-item" data-tab="preload_settings" title="Preload Settings">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                        <span>Preload Settings</span>
                    </div>
                    <div class="uwb-nav-item" data-tab="page_optimizes" title="Page Optimizes">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/><line x1="20" y1="20" x2="4" y2="4"/></svg>
                        <span>Page Optimizes</span>
                    </div>
                    <div class="uwb-nav-item" data-tab="import_export" title="Import / Export">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                        <span>Import / Export</span>
                    </div>
                    <div class="uwb-nav-item" data-tab="advanced_tools" title="Advanced / Tools">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                        <span>Advanced</span>
                    </div>
                </div>

                <div class="uwb-content-panel">
                    <?php settings_errors(); ?>
                    <form method="post" action="options.php" novalidate>
                        <?php settings_fields( 'uwb_settings_group' ); ?>

                        <!-- TAB 1: Cache Settings -->
                        <div id="tab-cache_settings" class="uwb-tab-content">
                            <h2 style="margin-top:0;">Cache Configuration</h2>
                            <p style="color:var(--uwb-text-muted); margin-bottom: 24px;">Configure cache lifespan, bypass conditions, and exclusions for static files.</p>

                            <!-- Horizontal Sub-tabs Nav -->
                            <div class="uwb-sub-tabs-nav">
                                <div class="uwb-sub-tab-item active" data-subtab="browser_cache">Browser Cache</div>
                                <div class="uwb-sub-tab-item" data-subtab="page_cache">Cache Page</div>
                                <div class="uwb-sub-tab-item" data-subtab="cdn_cache">CDN Cache</div>
                                <div class="uwb-sub-tab-item" data-subtab="webserver_cache">Webserver Cache</div>
                                <div class="uwb-sub-tab-item" data-subtab="object_cache">Object Cache</div>
                                <div class="uwb-sub-tab-item" data-subtab="opcache">OPCache</div>
                            </div>

                            <!-- SUB-TAB 0: Browser Cache -->
                            <div id="subtab-browser_cache" class="uwb-subtab-content active">
                                <div style="background:#f8fafc; border:1px solid var(--uwb-border); border-radius:12px; padding:24px; margin-bottom:24px;">
                                    <h3 style="margin-top:0; margin-bottom:20px; font-size:15px; display:flex; align-items:center; gap:8px;">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                                        Browser Cache Settings
                                    </h3>
                                    
                                    <div class="uwb-form-group" style="margin-bottom: 24px;">
                                        <label for="uwb_browser_cache_enabled">Enable Browser Caching</label>
                                        <select name="uwb_browser_cache_enabled" id="uwb_browser_cache_enabled" style="width:100%; border:1px solid var(--uwb-border); border-radius:8px; padding:12px;">
                                            <option value="0" <?php selected( get_option( 'uwb_browser_cache_enabled', 1 ), 0 ); ?>>Disabled</option>
                                            <option value="1" <?php selected( get_option( 'uwb_browser_cache_enabled', 1 ), 1 ); ?>>Enabled</option>
                                        </select>
                                        <p class="description">Allow visitors' browsers to store static files locally to speed up subsequent loads.</p>
                                    </div>

                                    <div id="uwb-browser-cache-detailed-settings" style="<?php echo get_option( 'uwb_browser_cache_enabled', 1 ) ? '' : 'display:none;'; ?>">
                                        <h4 style="margin-top: 24px; margin-bottom: 16px; font-size: 14px; font-weight: 700; color: var(--uwb-text);">Configure Lifespan by File Type</h4>
                                        <p class="description" style="margin-bottom: 20px;">Lifespan values are configured in minutes. Defaults are 365 days (525600 minutes).</p>
                                        
                                        <!-- Grid of Caching categories -->
                                        <div style="display: flex; flex-direction: column; gap: 16px;">
                                            <?php
                                            $categories = array(
                                                'html'  => 'HTML / XML Pages',
                                                'css'   => 'CSS Stylesheets',
                                                'js'    => 'JavaScript Files',
                                                'image' => 'Images (JPG, PNG, GIF, WebP, SVG, ICO)',
                                                'font'  => 'Fonts (TTF, OTF, WOFF, WOFF2, EOT)',
                                                'other' => 'Other Static Assets (PDF, Audio, Video, Zip)',
                                            );

                                            foreach ( $categories as $key => $label ) :
                                                $opt_enabled  = intval( get_option( "uwb_browser_cache_{$key}", 1 ) );
                                                $opt_lifespan = intval( get_option( "uwb_browser_cache_{$key}_lifespan", 525600 ) ); // 365 days
                                                ?>
                                                <div style="display: flex; align-items: center; justify-content: space-between; background: #fff; border: 1px solid var(--uwb-border); border-radius: 8px; padding: 16px; gap: 20px; flex-wrap: wrap;">
                                                    <div style="flex: 1; min-width: 200px;">
                                                        <strong style="font-size: 13.5px; color: var(--uwb-text); display: block;"><?php echo esc_html( $label ); ?></strong>
                                                    </div>
                                                    
                                                    <div style="display: flex; align-items: center; gap: 16px; flex-wrap: wrap;">
                                                        <!-- Enable/Disable Category Switch -->
                                                        <select name="uwb_browser_cache_<?php echo $key; ?>" class="uwb-bc-cat-toggle" style="width: 75px; border: 1px solid var(--uwb-border); border-radius: 6px; padding: 8px; font-size: 13px;">
                                                            <option value="0" <?php selected( $opt_enabled, 0 ); ?>>Bypass</option>
                                                            <option value="1" <?php selected( $opt_enabled, 1 ); ?>>Cache</option>
                                                        </select>
                                                        
                                                        <!-- Lifespan Input -->
                                                        <div class="uwb-bc-lifespan-wrap" style="display: flex; align-items: center; gap: 8px; <?php echo $opt_enabled ? '' : 'display:none;'; ?>">
                                                            <input type="number" min="1" name="uwb_browser_cache_<?php echo $key; ?>_lifespan" value="<?php echo esc_attr( $opt_lifespan ); ?>" style="width: 130px; padding: 8px; border: 1px solid var(--uwb-border); border-radius: 6px; font-size: 13px;" />
                                                            <span style="font-size: 12.5px; color: var(--uwb-text-muted);">Minutes</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- SUB-TAB 1: Cache Page -->
                            <div id="subtab-page_cache" class="uwb-subtab-content">
                                <!-- Group 1: Page Cache Settings -->
                                <div style="background:#f8fafc; border:1px solid var(--uwb-border); border-radius:12px; padding:24px; margin-bottom:24px;">
                                    <h3 style="margin-top:0; margin-bottom:20px; font-size:15px; display:flex; align-items:center; gap:8px;">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                                        Page Cache Settings
                                    </h3>
                                    
                                    <div class="uwb-form-group" style="margin-bottom: 24px;">
                                        <label for="uwb_cache_page_enabled">Enable HTML Page Caching</label>
                                        <select name="uwb_cache_page_enabled" id="uwb_cache_page_enabled" style="width:100%; border:1px solid var(--uwb-border); border-radius:8px; padding:12px;">
                                            <option value="1" <?php selected( get_option( 'uwb_cache_page_enabled', 1 ), 1 ); ?>>Enabled</option>
                                            <option value="0" <?php selected( get_option( 'uwb_cache_page_enabled', 1 ), 0 ); ?>>Disabled</option>
                                        </select>
                                        <p class="description">If disabled, the plugin will not store or serve static HTML cache files for your pages.</p>
                                    </div>

                                    <div id="uwb-page-cache-detailed-settings" style="<?php echo get_option( 'uwb_cache_page_enabled', 1 ) ? '' : 'display:none;'; ?>">
                                        <div class="uwb-form-group">
                                            <label for="uwb_cache_lifespan">Cache Lifespan (Minutes)</label>
                                            <input type="number" name="uwb_cache_lifespan" id="uwb_cache_lifespan" value="<?php echo esc_attr( get_option( 'uwb_cache_lifespan', 0 ) ); ?>" />
                                            <p class="description">
                                                The amount of time static cache files are kept before being cleared and regenerated. Enter <code>0</code> for unlimited lifespan.<br>
                                                <strong>Quick conversion (click to copy):</strong> <code class="uwb-copy-val" style="cursor:pointer; background:#e2e8f0; padding:2px 6px; border-radius:4px;" title="Click to copy">60</code> (1h) | <code class="uwb-copy-val" style="cursor:pointer; background:#e2e8f0; padding:2px 6px; border-radius:4px;" title="Click to copy">360</code> (6h) | <code class="uwb-copy-val" style="cursor:pointer; background:#e2e8f0; padding:2px 6px; border-radius:4px;" title="Click to copy">720</code> (12h) | <code class="uwb-copy-val" style="cursor:pointer; background:#e2e8f0; padding:2px 6px; border-radius:4px;" title="Click to copy">1440</code> (24h) | <code class="uwb-copy-val" style="cursor:pointer; background:#e2e8f0; padding:2px 6px; border-radius:4px;" title="Click to copy">4320</code> (3d) | <code class="uwb-copy-val" style="cursor:pointer; background:#e2e8f0; padding:2px 6px; border-radius:4px;" title="Click to copy">10080</code> (7d)
                                            </p>
                                        </div>

                                        <div class="uwb-form-group" style="max-width: 700px; margin-bottom: 20px;">
                                            <label style="font-weight: 600; margin-bottom: 8px; color: var(--uwb-text); font-size: 14px;">Cache for Logged-in Users</label>
                                            <div style="display: flex; align-items: stretch; border: 1px solid var(--uwb-border); border-radius: 8px; overflow: hidden; background: #fff;">
                                                <select name="uwb_cache_logged_in" id="uwb_cache_logged_in" style="flex: 1; border: none; border-radius: 0; padding: 12px; font-size: 14px; background: transparent; color: var(--uwb-text); outline: none; box-shadow: none; height: auto;">
                                                    <option value="0" <?php selected( get_option( 'uwb_cache_logged_in', 0 ), 0 ); ?>>No (Recommended)</option>
                                                    <option value="1" <?php selected( get_option( 'uwb_cache_logged_in', 0 ), 1 ); ?>>Yes</option>
                                                </select>
                                                <div id="uwb-logged-in-divider" style="width: 1px; background: var(--uwb-border); <?php echo get_option( 'uwb_cache_logged_in', 0 ) ? '' : 'display:none;'; ?>"></div>
                                                <input type="number" name="uwb_cache_logged_in_lifespan" id="uwb-logged-in-lifespan-group" value="<?php echo esc_attr( get_option( 'uwb_cache_logged_in_lifespan', 10 ) ); ?>" min="1" placeholder="Lifespan (Minutes)" style="flex: 1; border: none; border-radius: 0; padding: 12px; font-size: 14px; background: transparent; color: var(--uwb-text); outline: none; box-shadow: none; height: auto; <?php echo get_option( 'uwb_cache_logged_in', 0 ) ? '' : 'display:none;'; ?>" />
                                            </div>
                                            <p class="description">
                                                Serve static cached pages to logged-in users. When enabled, enter lifespan in minutes (default is 10).<br>
                                                <strong>Warning:</strong> Personalized content may be cached if not configured carefully.<br>
                                                <strong>Quick conversion (click to copy):</strong> <code class="uwb-copy-val" style="cursor:pointer; background:#e2e8f0; padding:2px 6px; border-radius:4px;" title="Click to copy">5</code> (5m) | <code class="uwb-copy-val" style="cursor:pointer; background:#e2e8f0; padding:2px 6px; border-radius:4px;" title="Click to copy">10</code> (10m) | <code class="uwb-copy-val" style="cursor:pointer; background:#e2e8f0; padding:2px 6px; border-radius:4px;" title="Click to copy">15</code> (15m)
                                            </p>
                                        </div>

                                        <div class="uwb-form-group">
                                            <label for="uwb_cache_404">Cache 404 Pages</label>
                                            <select name="uwb_cache_404" id="uwb_cache_404" style="width:100%; border:1px solid var(--uwb-border); border-radius:8px; padding:12px;">
                                                <option value="0" <?php selected( get_option( 'uwb_cache_404', 0 ), 0 ); ?>>Disabled</option>
                                                <option value="1" <?php selected( get_option( 'uwb_cache_404', 0 ), 1 ); ?>>Enabled</option>
                                            </select>
                                            <p class="description">Enable this to generate static cache files for 404 Not Found error pages.</p>
                                        </div>

                                        <div class="uwb-form-group" style="max-width: 700px; margin-bottom: 20px;">
                                            <label style="font-weight: 600; margin-bottom: 8px; color: var(--uwb-text); font-size: 14px;">Cache XML Sitemaps</label>
                                            <div style="display: flex; align-items: stretch; border: 1px solid var(--uwb-border); border-radius: 8px; overflow: hidden; background: #fff;">
                                                <select name="uwb_cache_xml_sitemaps" id="uwb_cache_xml_sitemaps" style="flex: 1; border: none; border-radius: 0; padding: 12px; font-size: 14px; background: transparent; color: var(--uwb-text); outline: none; box-shadow: none; height: auto;">
                                                    <option value="0" <?php selected( get_option( 'uwb_cache_xml_sitemaps', 0 ), 0 ); ?>>Disabled</option>
                                                    <option value="1" <?php selected( get_option( 'uwb_cache_xml_sitemaps', 0 ), 1 ); ?>>Enabled</option>
                                                </select>
                                                <div id="uwb-xml-sitemaps-divider" style="width: 1px; background: var(--uwb-border); <?php echo get_option( 'uwb_cache_xml_sitemaps', 0 ) ? '' : 'display:none;'; ?>"></div>
                                                <input type="number" name="uwb_cache_xml_sitemaps_lifespan" id="uwb-xml-sitemaps-lifespan-group" value="<?php echo esc_attr( get_option( 'uwb_cache_xml_sitemaps_lifespan', 10 ) ); ?>" min="1" placeholder="Lifespan (Minutes)" style="flex: 1; border: none; border-radius: 0; padding: 12px; font-size: 14px; background: transparent; color: var(--uwb-text); outline: none; box-shadow: none; height: auto; <?php echo get_option( 'uwb_cache_xml_sitemaps', 0 ) ? '' : 'display:none;'; ?>" />
                                            </div>
                                            <p class="description">
                                                Generate static cache files for XML sitemaps (e.g. <code>/sitemap.xml</code>). Served as <code>text/xml</code>.<br>
                                                <strong>Quick conversion (click to copy):</strong> <code class="uwb-copy-val" style="cursor:pointer; background:#e2e8f0; padding:2px 6px; border-radius:4px;" title="Click to copy">60</code> (1h) | <code class="uwb-copy-val" style="cursor:pointer; background:#e2e8f0; padding:2px 6px; border-radius:4px;" title="Click to copy">600</code> (10h) | <code class="uwb-copy-val" style="cursor:pointer; background:#e2e8f0; padding:2px 6px; border-radius:4px;" title="Click to copy">1440</code> (24h) | <code class="uwb-copy-val" style="cursor:pointer; background:#e2e8f0; padding:2px 6px; border-radius:4px;" title="Click to copy">10080</code> (7d)
                                            </p>
                                        </div>

                                        <div class="uwb-form-group" style="max-width: 700px; margin-bottom: 0;">
                                            <label style="font-weight: 600; margin-bottom: 8px; color: var(--uwb-text); font-size: 14px;">Cache PHP Pages</label>
                                            <div style="display: flex; align-items: stretch; border: 1px solid var(--uwb-border); border-radius: 8px; overflow: hidden; background: #fff;">
                                                <select name="uwb_cache_php" id="uwb_cache_php" style="flex: 1; border: none; border-radius: 0; padding: 12px; font-size: 14px; background: transparent; color: var(--uwb-text); outline: none; box-shadow: none; height: auto;">
                                                    <option value="0" <?php selected( get_option( 'uwb_cache_php', 0 ), 0 ); ?>>Disabled</option>
                                                    <option value="1" <?php selected( get_option( 'uwb_cache_php', 0 ), 1 ); ?>>Enabled</option>
                                                </select>
                                                <div id="uwb-php-divider" style="width: 1px; background: var(--uwb-border); <?php echo get_option( 'uwb_cache_php', 0 ) ? '' : 'display:none;'; ?>"></div>
                                                <input type="number" name="uwb_cache_php_lifespan" id="uwb-php-lifespan-group" value="<?php echo esc_attr( get_option( 'uwb_cache_php_lifespan', 10 ) ); ?>" min="1" placeholder="Lifespan (Minutes)" style="flex: 1; border: none; border-radius: 0; padding: 12px; font-size: 14px; background: transparent; color: var(--uwb-text); outline: none; box-shadow: none; height: auto; <?php echo get_option( 'uwb_cache_php', 0 ) ? '' : 'display:none;'; ?>" />
                                            </div>
                                            <p class="description">
                                                Generate static cache files for requests ending with <code>.php</code> extension (except <code>index.php</code>).<br>
                                                <strong>Quick conversion (click to copy):</strong> <code class="uwb-copy-val" style="cursor:pointer; background:#e2e8f0; padding:2px 6px; border-radius:4px;" title="Click to copy">60</code> (1h) | <code class="uwb-copy-val" style="cursor:pointer; background:#e2e8f0; padding:2px 6px; border-radius:4px;" title="Click to copy">600</code> (10h) | <code class="uwb-copy-val" style="cursor:pointer; background:#e2e8f0; padding:2px 6px; border-radius:4px;" title="Click to copy">1440</code> (24h) | <code class="uwb-copy-val" style="cursor:pointer; background:#e2e8f0; padding:2px 6px; border-radius:4px;" title="Click to copy">10080</code> (7d)
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div id="uwb-page-cache-detailed-settings-rules" style="<?php echo get_option( 'uwb_cache_page_enabled', 1 ) ? '' : 'display:none;'; ?>">
                                    <!-- Group 3: Exclusion & Bypass Rules -->
                                    <div style="background:#f8fafc; border:1px solid var(--uwb-border); border-radius:12px; padding:24px; margin-bottom:24px;">
                                        <h3 style="margin-top:0; margin-bottom:20px; font-size:15px; display:flex; align-items:center; gap:8px;">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                            Force & Exclusion Rules
                                        </h3>

                                        <div class="uwb-form-group">
                                            <label for="uwb_excluded_urls">Excluded URLs</label>
                                            <textarea name="uwb_excluded_urls" id="uwb_excluded_urls" rows="6"><?php echo esc_textarea( $this->get_excluded_urls_setting_value() ); ?></textarea>
                                            <p class="description">
                                                URLs or RegEx patterns that should NEVER be cached (one per line).<br>
                                                Examples:<br>
                                                <code>/cart(.*)</code> to exclude the shopping cart pages<br>
                                                <code>/checkout(.*)</code> to exclude checkout pages
                                            </p>
                                        </div>

                                        <div class="uwb-form-group">
                                            <label for="uwb_ignore_all_query_strings">Serve Cache for Strange Query Strings</label>
                                            <select name="uwb_ignore_all_query_strings" id="uwb_ignore_all_query_strings" style="width:100%; border:1px solid var(--uwb-border); border-radius:8px; padding:12px;">
                                                <option value="1" <?php selected( get_option( 'uwb_ignore_all_query_strings', 1 ), 1 ); ?>>Enabled (Ignore unrecognized parameters and serve main cache)</option>
                                                <option value="0" <?php selected( get_option( 'uwb_ignore_all_query_strings', 1 ), 0 ); ?>>Disabled (Bypass cache completely for unrecognized parameters)</option>
                                            </select>
                                            <p class="description">When enabled, strange URL queries like <code>?c=123</code> or <code>?xyz=999</code> will serve the cached main page instead of hitting PHP/database. (Recommended)</p>
                                        </div>

                                        <div class="uwb-form-group" style="margin-top: 16px;">
                                            <label style="display: flex; align-items: center; gap: 8px; font-weight: 600; cursor: pointer;">
                                                <input type="checkbox" name="uwb_auto_collect_params" id="uwb_auto_collect_params" value="1" <?php checked( get_option( 'uwb_auto_collect_params', 0 ), 1 ); ?> />
                                                Auto Collect GET Parameters.
                                            </label>
                                            <p class="description" style="margin-top: 4px;">
                                                Auto Collect GET Parameters when the page is loaded successfully (HTTP Status 200).
                                            </p>
                                        </div>

                                        <div class="uwb-form-group" id="uwb-collected-params-group" style="margin-top: 16px; <?php echo get_option( 'uwb_auto_collect_params', 0 ) ? '' : 'display:none;'; ?>">
                                            <label for="uwb_collected_params">Danh sách Parameter đã thu thập (Discovered GET Parameters)</label>
                                            <textarea name="uwb_collected_params" id="uwb_collected_params" rows="5" placeholder="Các parameter thu thập tự động sẽ hiển thị ở đây (mỗi tham số 1 dòng)..."><?php echo esc_textarea( get_option( 'uwb_collected_params', '' ) ); ?></textarea>
                                            <p class="description">
                                                Toàn bộ tham số URL (GET parameters) đã tìm thấy trên trang web (mỗi tham số 1 dòng). Bạn có thể thêm, sửa hoặc xóa các tham số tại đây.
                                            </p>
                                        </div>

                                        <div class="uwb-form-group">
                                            <label for="uwb_ignored_query">Ignored Query Parameters</label>
                                            <textarea name="uwb_ignored_query" id="uwb_ignored_query" rows="5"><?php 
                                                $ignored_query_val = get_option( 'uwb_ignored_query', "utm_source\nutm_medium\nutm_campaign\nfbclid\ngclid\nage-verified" );
                                                echo esc_textarea( $ignored_query_val ); 
                                            ?></textarea>
                                            <p class="description">
                                                Query parameters to ignore when deciding whether to serve the static cache (one per line).<br>
                                                Marketing parameters like <code>utm_source</code>, <code>fbclid</code>, and <code>gclid</code> are ignored by default to ensure ad campaign clicks still get fast static pages.
                                            </p>
                                        </div>

                                        <div class="uwb-form-group">
                                            <label for="uwb_exclude_cookies">Never Cache Cookies</label>
                                            <textarea name="uwb_exclude_cookies" id="uwb_exclude_cookies" rows="4" placeholder="wordpress_no_cache_&#10;custom_cookie_*"><?php echo esc_textarea( get_option( 'uwb_exclude_cookies', '' ) ); ?></textarea>
                                            <p class="description">
                                                Specify cookie names or patterns that should bypass cache when present in the request (one per line).<br>
                                                Supports wildcards, e.g. <code>woocommerce_items_in_cart_*</code>
                                            </p>
                                        </div>

                                        <div class="uwb-form-group">
                                            <label for="uwb_exclude_user_agents">Never Cache User Agent(s)</label>
                                            <textarea name="uwb_exclude_user_agents" id="uwb_exclude_user_agents" rows="4" placeholder="GTmetrix&#10;PingdomLinkCheck"><?php echo esc_textarea( get_option( 'uwb_exclude_user_agents', '' ) ); ?></textarea>
                                            <p class="description">
                                                Specify user agent substrings that should bypass cache (one per line). Case-insensitive.<br>
                                                Examples: <code>GTmetrix</code>, <code>Pingdom</code>, etc.
                                            </p>
                                        </div>

                                        <div class="uwb-form-group">
                                            <label for="uwb_always_purge_urls">Always Purge URL</label>
                                            <textarea name="uwb_always_purge_urls" id="uwb_always_purge_urls" rows="4" placeholder="/some-page/&#10;https://example.com/another-page/"><?php echo esc_textarea( $this->get_always_purge_urls_setting_value() ); ?></textarea>
                                            <p class="description">
                                                Specify URLs you always want purged from cache whenever you update any post or page (one per line).<br>
                                                Supports absolute URLs or relative paths starting with <code>/</code>.
                                            </p>
                                        </div>

                                        <div class="uwb-form-group" style="margin-bottom:0;">
                                            <label for="uwb_cache_query_strings">Cache Query String</label>
                                            <textarea name="uwb_cache_query_strings" id="uwb_cache_query_strings" rows="4" placeholder="paged&#10;sort"><?php echo esc_textarea( get_option( 'uwb_cache_query_strings', '' ) ); ?></textarea>
                                            <p class="description" style="margin-bottom:0;">
                                                Cache for query strings enables you to force caching for specific GET parameters (one per line).<br>
                                                Example: <code>paged</code> or <code>sort</code>.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- SUB-TAB 2: CDN Cache -->
                            <div id="subtab-cdn_cache" class="uwb-subtab-content">
                                <!-- Section 1: Cloudflare Zone CDN Cache Integration -->
                                <div style="background:#f8fafc; border:1px solid var(--uwb-border); border-radius:12px; padding:24px; margin-bottom:24px;">
                                    <h3 style="margin-top:0; margin-bottom:16px; font-size:15px; display:flex; align-items:center; gap:8px;">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 10h-1.26A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10z"/></svg>
                                        Cloudflare Zone CDN Cache Integration
                                    </h3>
                                    <p style="font-size:13px; color:var(--uwb-text-muted); margin-bottom:20px;">Synchronize plugin cache clearing with Cloudflare Edge CDN cache for your domain.</p>

                                    <?php $this->render_toggle_switch( 'uwb_cf_enabled', 'Enable Cloudflare Zone CDN Cache Purge', 'Automatically send Cache Purge API requests to Cloudflare CDN Edge when clearing plugin cache or single page cache.' ); ?>

                                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px; margin-top:20px;">
                                        <div class="uwb-form-group">
                                            <label for="uwb_cf_zone_id">Cloudflare Zone ID</label>
                                            <input type="text" name="uwb_cf_zone_id" id="uwb_cf_zone_id" value="<?php echo esc_attr( get_option( 'uwb_cf_zone_id', '' ) ); ?>" placeholder="e.g. c2547eb745079dac9320b638f5e225cf" style="width:100%; padding:12px; border:1px solid var(--uwb-border); border-radius:8px; font-size:13.5px;" />
                                            <p class="description">Found on your Cloudflare Dashboard &rarr; Domain Overview page (right sidebar).</p>
                                        </div>
                                        <div class="uwb-form-group">
                                            <label for="uwb_cf_api_token">Cloudflare API Token</label>
                                            <input type="password" name="uwb_cf_api_token" id="uwb_cf_api_token" value="<?php echo esc_attr( get_option( 'uwb_cf_api_token', '' ) ); ?>" placeholder="API Token with Cache Purge permission" style="width:100%; padding:12px; border:1px solid var(--uwb-border); border-radius:8px; font-size:13.5px;" autocomplete="new-password" />
                                            <p class="description">Create token at Cloudflare Profile &rarr; API Tokens with <code>Zone - Cache Purge - Purge</code> permission.</p>
                                        </div>
                                    </div>

                                    <div style="margin-bottom:20px; background:#fff; padding:16px; border:1px solid var(--uwb-border); border-radius:8px;">
                                        <label style="display:flex; align-items:center; gap:8px; font-size:13px; font-weight:600; cursor:pointer;">
                                            <input type="checkbox" name="uwb_cf_auto_purge_on_clear" value="1" <?php checked( get_option( 'uwb_cf_auto_purge_on_clear', 1 ), 1 ); ?> />
                                            Auto-purge Cloudflare CDN Edge Cache when clearing plugin cache
                                        </label>
                                    </div>

                                    <div style="display:flex; gap:12px; align-items:center;">
                                        <button type="button" id="btn-test-cf-connection" class="button button-secondary" style="padding:10px 18px; font-weight:600; height:auto; border-radius:8px; cursor:pointer;">
                                            Test Cloudflare API Connection
                                        </button>
                                        <button type="button" id="btn-purge-cf-cache" class="button button-secondary" style="padding:10px 18px; font-weight:600; height:auto; border-radius:8px; cursor:pointer; color:#dc2626; border-color:#fca5a5;">
                                            Purge Cloudflare Zone Cache Now
                                        </button>
                                    </div>
                                    <div id="uwb-cf-test-result" style="margin-top:12px; display:none;"></div>
                                </div>

                                <!-- Section 2: CDN Offload Media Notice Card -->
                                <div style="background:#f8fafc; border:1px solid var(--uwb-border); border-radius:12px; padding:24px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px;">
                                    <div>
                                        <h4 style="margin:0 0 6px 0; font-size:14.5px; font-weight:700; color:var(--uwb-text);">CDN Media &amp; Asset Offloading (R2 / S3)</h4>
                                        <p style="font-size:13px; color:var(--uwb-text-muted); margin:0;">
                                            Cloudflare R2 and S3 storage connection credentials, file type rules, and auto-sync settings are located under <strong>Page Optimizes &rarr; [6] CDN Offload Media</strong>.
                                        </p>
                                    </div>
                                    <button type="button" class="button button-primary" onclick="jQuery('.uwb-nav-item[data-tab=\'page_optimizes\']').trigger('click'); jQuery('.uwb-sub-tab-item[data-subtab=\'opt_cdn_media\']').trigger('click');" style="background:var(--uwb-primary); border-color:var(--uwb-primary); padding:10px 18px; height:auto; border-radius:8px; font-weight:600; cursor:pointer; white-space:nowrap;">
                                        Go to Page Optimizes &rarr; [6] CDN Offload Media
                                    </button>
                                </div>
                            </div>

                            <!-- SUB-TAB 3: Webserver Cache -->
                            <div id="subtab-webserver_cache" class="uwb-subtab-content">
                                <div style="background:#f8fafc; border:1px solid var(--uwb-border); border-radius:12px; padding:24px; margin-bottom:24px;">
                                    <h3 style="margin-top:0; margin-bottom:20px; font-size:15px; display:flex; align-items:center; gap:8px;">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="8" rx="2" ry="2"/><rect x="2" y="14" width="20" height="8" rx="2" ry="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>
                                        Webserver Cache Status & Information
                                    </h3>
                                    <p style="font-size:13.5px; line-height:1.6; color:var(--uwb-text); margin-bottom:20px;">
                                        Webserver-level caching compiles HTML files dynamically and serves them with zero execution overhead. Static files preloaded by Ultimate WP Booster are fully compatible. Below is the status of supported webserver caching technologies detected on your host:
                                    </p>
                                    
                                    <div class="uwb-webserver-cards" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 24px;">
                                        <!-- CARD 1: NGINX -->
                                        <?php 
                                        $is_nginx = ($detected_server === 'nginx');
                                        $nginx_opacity = $is_nginx ? '1' : '0.45';
                                        $nginx_border = $is_nginx ? 'border: 2px solid #10b981;' : 'border: 1px solid var(--uwb-border);';
                                        $nginx_shadow = $is_nginx ? 'box-shadow: 0 4px 12px rgba(16,185,129,0.15);' : '';
                                        $nginx_bg = $is_nginx ? 'background: #ffffff;' : 'background: #f8fafc;';
                                        ?>
                                        <div class="uwb-webserver-card" style="opacity: <?php echo $nginx_opacity; ?>; <?php echo $nginx_border; ?> <?php echo $nginx_shadow; ?> <?php echo $nginx_bg; ?> border-radius: 12px; padding: 20px; transition: all 0.3s ease;">
                                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                                                <h4 style="margin: 0; font-size: 15px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 8px;">
                                                    <span style="display:inline-block; width: 10px; height: 10px; border-radius: 50%; background: <?php echo $is_nginx ? '#10b981;' : '#94a3b8;'; ?>"></span>
                                                    NGINX FastCGI Cache
                                                </h4>
                                                <span style="font-size: 11px; font-weight: 700; padding: 4px 8px; border-radius: 6px; <?php echo $is_nginx ? 'background: #d1fae5; color: #065f46;' : 'background: #e2e8f0; color: #64748b;'; ?>">
                                                    <?php echo $is_nginx ? 'ACTIVE' : 'INACTIVE'; ?>
                                                </span>
                                            </div>
                                            <p style="font-size: 13px; line-height: 1.5; color: #475569; margin: 0 0 16px 0;">
                                                Uses Nginx daemon level static file delivery or FastCGI microcaching. Extremely high performance.
                                            </p>
                                            <?php if ( $is_nginx ) : ?>
                                                <div style="background: #f8fafc; border-radius: 8px; padding: 12px; font-size: 12px; border: 1px solid #e2e8f0;">
                                                    <div style="margin-bottom: 6px; display: flex; justify-content: space-between;">
                                                        <strong style="color: #64748b;">Software:</strong>
                                                        <span style="color: #334155; font-family: monospace;"><?php echo esc_html( $server_software ); ?></span>
                                                    </div>
                                                    <div style="margin-bottom: 6px; display: flex; justify-content: space-between;">
                                                        <strong style="color: #64748b;">Cache Engine:</strong>
                                                        <span style="color: #334155;">Rocket-Nginx Rules Compatible</span>
                                                    </div>
                                                    <div style="display: flex; justify-content: space-between;">
                                                        <strong style="color: #64748b;">Static Directory:</strong>
                                                        <span style="color: #334155; font-family: monospace;">wp-content/cache</span>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- CARD 2: APACHE -->
                                        <?php 
                                        $is_apache = ($detected_server === 'apache');
                                        $apache_opacity = $is_apache ? '1' : '0.45';
                                        $apache_border = $is_apache ? 'border: 2px solid #10b981;' : 'border: 1px solid var(--uwb-border);';
                                        $apache_shadow = $is_apache ? 'box-shadow: 0 4px 12px rgba(16,185,129,0.15);' : '';
                                        $apache_bg = $is_apache ? 'background: #ffffff;' : 'background: #f8fafc;';
                                        
                                        $htaccess_file = ABSPATH . '.htaccess';
                                        $htaccess_writable = file_exists( $htaccess_file ) && is_writable( $htaccess_file );
                                        $rules_active = false;
                                        if ( file_exists( $htaccess_file ) ) {
                                            $htaccess_content = @file_get_contents( $htaccess_file );
                                            if ( $htaccess_content && strpos( $htaccess_content, 'Ultimate WP Booster Browser Cache' ) !== false ) {
                                                $rules_active = true;
                                            }
                                        }
                                        ?>
                                        <div class="uwb-webserver-card" style="opacity: <?php echo $apache_opacity; ?>; <?php echo $apache_border; ?> <?php echo $apache_shadow; ?> <?php echo $apache_bg; ?> border-radius: 12px; padding: 20px; transition: all 0.3s ease;">
                                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                                                <h4 style="margin: 0; font-size: 15px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 8px;">
                                                    <span style="display:inline-block; width: 10px; height: 10px; border-radius: 50%; background: <?php echo $is_apache ? '#10b981;' : '#94a3b8;'; ?>"></span>
                                                    Apache mod_expires & Cache
                                                </h4>
                                                <span style="font-size: 11px; font-weight: 700; padding: 4px 8px; border-radius: 6px; <?php echo $is_apache ? 'background: #d1fae5; color: #065f46;' : 'background: #e2e8f0; color: #64748b;'; ?>">
                                                    <?php echo $is_apache ? 'ACTIVE' : 'INACTIVE'; ?>
                                                </span>
                                            </div>
                                            <p style="font-size: 13px; line-height: 1.5; color: #475569; margin: 0 0 16px 0;">
                                                Uses .htaccess rewriting rules and Apache caching modules to expire and deliver static assets directly.
                                            </p>
                                            <?php if ( $is_apache ) : ?>
                                                <div style="background: #f8fafc; border-radius: 8px; padding: 12px; font-size: 12px; border: 1px solid #e2e8f0;">
                                                    <div style="margin-bottom: 6px; display: flex; justify-content: space-between;">
                                                        <strong style="color: #64748b;">Software:</strong>
                                                        <span style="color: #334155; font-family: monospace;"><?php echo esc_html( $server_software ); ?></span>
                                                    </div>
                                                    <div style="margin-bottom: 6px; display: flex; justify-content: space-between;">
                                                        <strong style="color: #64748b;">.htaccess Status:</strong>
                                                        <span style="color: <?php echo $htaccess_writable ? '#10b981;' : '#ef4444;'; ?>"><?php echo $htaccess_writable ? 'Writable' : 'Not Writable'; ?></span>
                                                    </div>
                                                    <div style="display: flex; justify-content: space-between;">
                                                        <strong style="color: #64748b;">Expiration Rules:</strong>
                                                        <span style="color: <?php echo $rules_active ? '#10b981;' : '#f59e0b;'; ?>"><?php echo $rules_active ? 'Active (.htaccess)' : 'Inactive'; ?></span>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- CARD 3: LITESPEED -->
                                        <?php 
                                        $is_litespeed = ($detected_server === 'litespeed');
                                        $litespeed_opacity = $is_litespeed ? '1' : '0.45';
                                        $litespeed_border = $is_litespeed ? 'border: 2px solid #10b981;' : 'border: 1px solid var(--uwb-border);';
                                        $litespeed_shadow = $is_litespeed ? 'box-shadow: 0 4px 12px rgba(16,185,129,0.15);' : '';
                                        $litespeed_bg = $is_litespeed ? 'background: #ffffff;' : 'background: #f8fafc;';
                                        ?>
                                        <div class="uwb-webserver-card" style="opacity: <?php echo $litespeed_opacity; ?>; <?php echo $litespeed_border; ?> <?php echo $litespeed_shadow; ?> <?php echo $litespeed_bg; ?> border-radius: 12px; padding: 20px; transition: all 0.3s ease;">
                                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                                                <h4 style="margin: 0; font-size: 15px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 8px;">
                                                    <span style="display:inline-block; width: 10px; height: 10px; border-radius: 50%; background: <?php echo $is_litespeed ? '#10b981;' : '#94a3b8;'; ?>"></span>
                                                    LiteSpeed LSCache
                                                </h4>
                                                <span style="font-size: 11px; font-weight: 700; padding: 4px 8px; border-radius: 6px; <?php echo $is_litespeed ? 'background: #d1fae5; color: #065f46;' : 'background: #e2e8f0; color: #64748b;'; ?>">
                                                    <?php echo $is_litespeed ? 'ACTIVE' : 'INACTIVE'; ?>
                                                </span>
                                            </div>
                                            <p style="font-size: 13px; line-height: 1.5; color: #475569; margin: 0 0 16px 0;">
                                                Leverages LiteSpeed Server built-in high performance page cache module. Configured via .htaccess headers.
                                            </p>
                                            <?php if ( $is_litespeed ) : ?>
                                                <div style="background: #f8fafc; border-radius: 8px; padding: 12px; font-size: 12px; border: 1px solid #e2e8f0;">
                                                    <div style="margin-bottom: 6px; display: flex; justify-content: space-between;">
                                                        <strong style="color: #64748b;">Software:</strong>
                                                        <span style="color: #334155; font-family: monospace;"><?php echo esc_html( $server_software ); ?></span>
                                                    </div>
                                                    <div style="margin-bottom: 6px; display: flex; justify-content: space-between;">
                                                        <strong style="color: #64748b;">LSCache Module:</strong>
                                                        <span style="color: #10b981;">Supported (Litespeed Server)</span>
                                                    </div>
                                                    <div style="display: flex; justify-content: space-between;">
                                                        <strong style="color: #64748b;">Htaccess Write:</strong>
                                                        <span style="color: <?php echo $htaccess_writable ? '#10b981;' : '#ef4444;'; ?>"><?php echo $htaccess_writable ? 'Writable' : 'Not Writable'; ?></span>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div style="padding:12px 16px; background:#e0e7ff; color:var(--uwb-primary-dark); border-radius:8px; font-size:13px; font-weight:600; display:inline-block;">
                                        Detected Server: <?php echo esc_html($webserver_details); ?>
                                    </div>
                                </div>
                            </div>

                            <!-- SUB-TAB 4: Object Cache -->
                            <div id="subtab-object_cache" class="uwb-subtab-content">
                                <?php
                                $oc_active = wp_using_ext_object_cache();
                                $oc_dropin = file_exists( WP_CONTENT_DIR . '/object-cache.php' );
                                $oc_type = intval( get_option( 'uwb_redis_enabled', 0 ) );

                                // Fetch stats if active
                                $stats_error = '';
                                $stats_data = array();

                                if ( $oc_active ) {
                                    $curr_conn_type = get_option('uwb_redis_conn_type', 'tcp');
                                    $curr_host = get_option('uwb_redis_host', '127.0.0.1');
                                    $curr_port = get_option('uwb_redis_port', 6379);
                                    $curr_socket = get_option('uwb_redis_socket', '');
                                    $curr_password = get_option('uwb_redis_password', '');
                                    $curr_db = get_option('uwb_redis_db', 0);

                                    if ( $oc_type === 2 ) {
                                        // Memcached
                                        if ( class_exists( 'Memcached' ) ) {
                                            if ( intval( $curr_port ) === 6379 ) {
                                                $curr_port = 11211;
                                            }
                                            $m = new \Memcached();
                                            $m->addServer( $curr_host, $curr_port );
                                            $m_stats = $m->getStats();

                                            if ( is_array( $m_stats ) && ! empty( $m_stats ) ) {
                                                $stats = reset( $m_stats );
                                                if ( is_array( $stats ) && isset( $stats['pid'] ) && $stats['pid'] > 0 ) {
                                                    $stats_data['type'] = 'memcached';
                                                    $stats_data['version'] = isset( $stats['version'] ) ? $stats['version'] : 'Unknown';
                                                    $stats_data['uptime'] = isset( $stats['uptime'] ) ? intval( $stats['uptime'] ) : 0;
                                                    $stats_data['curr_items'] = isset( $stats['curr_items'] ) ? intval( $stats['curr_items'] ) : 0;

                                                    $bytes = isset( $stats['bytes'] ) ? floatval( $stats['bytes'] ) : 0;
                                                    $limit_maxbytes = isset( $stats['limit_maxbytes'] ) ? floatval( $stats['limit_maxbytes'] ) : 0;
                                                    $stats_data['memory_used'] = size_format( $bytes );
                                                    $stats_data['memory_total'] = size_format( $limit_maxbytes );
                                                    $stats_data['memory_pct'] = $limit_maxbytes > 0 ? round( ( $bytes / $limit_maxbytes ) * 100, 1 ) : 0;

                                                    $hits = isset( $stats['get_hits'] ) ? floatval( $stats['get_hits'] ) : 0;
                                                    $misses = isset( $stats['get_misses'] ) ? floatval( $stats['get_misses'] ) : 0;
                                                    $total_req = $hits + $misses;
                                                    $stats_data['hits'] = $hits;
                                                    $stats_data['misses'] = $misses;
                                                    $stats_data['hit_ratio'] = $total_req > 0 ? round( ( $hits / $total_req ) * 100, 1 ) : 0;
                                                } else {
                                                    $stats_error = 'Could not retrieve stats from Memcached server.';
                                                }
                                            } else {
                                                $stats_error = 'Could not connect to Memcached server to fetch stats.';
                                            }
                                        } else {
                                            $stats_error = 'PHP Memcached extension is not loaded.';
                                        }
                                    } else {
                                        // Redis
                                        if ( class_exists( 'Redis' ) ) {
                                            if ( intval( $curr_port ) === 11211 ) {
                                                $curr_port = 6379;
                                            }
                                            $redis = new \Redis();
                                            try {
                                                if ( $curr_conn_type === 'socket' && ! empty( $curr_socket ) ) {
                                                    $connected = @$redis->connect( $curr_socket );
                                                } else {
                                                    $connected = @$redis->connect( $curr_host, $curr_port, 1.0 );
                                                }

                                                if ( $connected ) {
                                                    if ( ! empty( $curr_password ) ) {
                                                        @$redis->auth( $curr_password );
                                                    }
                                                    $info = @$redis->info();
                                                    if ( is_array( $info ) ) {
                                                        $stats_data['type'] = 'redis';
                                                        $stats_data['version'] = isset( $info['redis_version'] ) ? $info['redis_version'] : 'Unknown';
                                                        $stats_data['uptime'] = isset( $info['uptime_in_seconds'] ) ? intval( $info['uptime_in_seconds'] ) : 0;
                                                        $stats_data['connected_clients'] = isset( $info['connected_clients'] ) ? intval( $info['connected_clients'] ) : 0;

                                                        // Memory usage
                                                        $used_memory = isset( $info['used_memory'] ) ? floatval( $info['used_memory'] ) : 0;
                                                        $total_system_memory = isset( $info['total_system_memory'] ) ? floatval( $info['total_system_memory'] ) : 0;
                                                        $maxmemory = isset( $info['maxmemory'] ) ? floatval( $info['maxmemory'] ) : 0;

                                                        $stats_data['memory_used'] = size_format( $used_memory );
                                                        if ( $maxmemory > 0 ) {
                                                            $stats_data['memory_total'] = size_format( $maxmemory ) . ' (maxmemory)';
                                                            $stats_data['memory_pct'] = round( ( $used_memory / $maxmemory ) * 100, 1 );
                                                        } elseif ( $total_system_memory > 0 ) {
                                                            $stats_data['memory_total'] = size_format( $total_system_memory ) . ' (system)';
                                                            $stats_data['memory_pct'] = round( ( $used_memory / $total_system_memory ) * 100, 1 );
                                                        } else {
                                                            $stats_data['memory_total'] = 'N/A';
                                                            $stats_data['memory_pct'] = 0;
                                                        }

                                                        // Keys space stats
                                                        $db_key = 'db' . $curr_db;
                                                        $keys_count = 0;
                                                        if ( isset( $info[ $db_key ] ) ) {
                                                            preg_match( '/keys=(\d+)/', $info[ $db_key ], $matches );
                                                            if ( isset( $matches[1] ) ) {
                                                                $keys_count = intval( $matches[1] );
                                                            }
                                                        }
                                                        $stats_data['keys'] = $keys_count;

                                                        // Hit Ratio from Redis Keyspace
                                                        $hits = isset( $info['keyspace_hits'] ) ? floatval( $info['keyspace_hits'] ) : 0;
                                                        $misses = isset( $info['keyspace_misses'] ) ? floatval( $info['keyspace_misses'] ) : 0;
                                                        $total_req = $hits + $misses;
                                                        $stats_data['hits'] = $hits;
                                                        $stats_data['misses'] = $misses;
                                                        $stats_data['hit_ratio'] = $total_req > 0 ? round( ( $hits / $total_req ) * 100, 1 ) : 0;
                                                    } else {
                                                        $stats_error = 'Could not fetch INFO from Redis server.';
                                                    }
                                                } else {
                                                    $stats_error = 'Could not connect to Redis server.';
                                                }
                                            } catch ( \Exception $e ) {
                                                $stats_error = 'Redis client error: ' . $e->getMessage();
                                            }
                                        } else {
                                            $stats_error = 'PHP Redis class does not exist.';
                                        }
                                    }
                                }
                                ?>
                                
                                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(300px, 1fr)); gap:20px; margin-bottom:24px; align-items:stretch;">
                                    <!-- Cache Status & Connection Test Block -->
                                    <div style="background:#f8fafc; border:1px solid var(--uwb-border); border-radius:12px; padding:24px; display:flex; flex-direction:column; justify-content:space-between; margin-bottom:0;">
                                        <div>
                                            <h3 style="margin-top:0; font-size:15px; display:flex; align-items:center; gap:8px;">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="8" rx="2" ry="2"/><rect x="2" y="14" width="20" height="8" rx="2" ry="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>
                                                Object Cache Status
                                            </h3>
                                            
                                            <div style="margin-top:16px; display:flex; flex-direction:column; gap:16px;">
                                                <!-- Status Info -->
                                                <div style="display:flex; align-items:center; justify-content:space-between;">
                                                    <span style="font-weight:600; font-size:13.5px; color:var(--uwb-text);">Status:</span>
                                                    <?php
                                                    if ( $oc_active ) {
                                                        if ( $oc_type === 2 ) {
                                                            echo '<div style="display:inline-flex; align-items:center; gap:6px; background:#d1fae5; color:#065f46; border:1px solid #6ee7b7; padding:6px 12px; border-radius:6px; font-weight:700; font-size:12px;"><span style="width:6px;height:6px;background:#10b981;border-radius:50%;display:inline-block;"></span> Active (Memcached)</div>';
                                                        } else {
                                                            echo '<div style="display:inline-flex; align-items:center; gap:6px; background:#d1fae5; color:#065f46; border:1px solid #6ee7b7; padding:6px 12px; border-radius:6px; font-weight:700; font-size:12px;"><span style="width:6px;height:6px;background:#10b981;border-radius:50%;display:inline-block;"></span> Active (Redis)</div>';
                                                        }
                                                    } else {
                                                        echo '<div style="display:inline-flex; align-items:center; gap:6px; background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; padding:6px 12px; border-radius:6px; font-weight:700; font-size:12px;"><span style="width:6px;height:6px;background:#ef4444;border-radius:50%;display:inline-block;"></span> Inactive</div>';
                                                    }
                                                    ?>
                                                </div>

                                                <!-- Drop-in Info -->
                                                <div style="border-top:1px solid var(--uwb-border); padding-top:12px; display:flex; align-items:center; justify-content:space-between; font-size:13px;">
                                                    <span style="font-weight:600; color:var(--uwb-text);">Drop-in File:</span>
                                                    <?php if ( $oc_dropin ) : ?>
                                                        <span style="color:#059669; font-weight:600;">✓ Installed</span>
                                                    <?php else : ?>
                                                        <span style="color:#d97706; font-weight:600;">✗ Not Found</span>
                                                    <?php endif; ?>
                                                </div>

                                                <!-- Connection Info -->
                                                <div style="border-top:1px solid var(--uwb-border); padding-top:12px; display:flex; flex-direction:column; gap:6px; font-size:13px;">
                                                    <?php
                                                    $curr_conn_type = get_option('uwb_redis_conn_type', 'tcp');
                                                    $curr_host = get_option('uwb_redis_host', '127.0.0.1');
                                                    $curr_port = get_option('uwb_redis_port', 6379);
                                                    $curr_socket = get_option('uwb_redis_socket', '');
                                                    $curr_db = get_option('uwb_redis_db', 0);
                                                    
                                                    if ( $oc_type === 0 ) {
                                                        $redis_available = extension_loaded('redis') || class_exists('Redis');
                                                        $mc_available = extension_loaded('memcached');
                                                        ?>
                                                        <div style="display:flex; justify-content:space-between;">
                                                            <span style="font-weight:600; color:var(--uwb-text);">Connection:</span>
                                                            <span style="color:var(--uwb-text-muted);">Disabled</span>
                                                        </div>
                                                        <div style="display:flex; justify-content:space-between;">
                                                            <span style="font-weight:600; color:var(--uwb-text);">Redis Extension:</span>
                                                            <span><?php echo $redis_available ? 'Available ✓' : 'Not Installed ✗'; ?></span>
                                                        </div>
                                                        <div style="display:flex; justify-content:space-between;">
                                                            <span style="font-weight:600; color:var(--uwb-text);">Memcached Extension:</span>
                                                            <span><?php echo $mc_available ? 'Available ✓' : 'Not Installed ✗'; ?></span>
                                                        </div>
                                                        <?php
                                                    } else {
                                                        if ( $oc_type === 2 ) {
                                                            if ( intval( $curr_port ) === 6379 ) {
                                                                $curr_port = 11211;
                                                            }
                                                            $conn_str = esc_html( $curr_host . ':' . $curr_port );
                                                            $ext_available = extension_loaded('memcached');
                                                            $ext_label = 'Memcached';
                                                        } else {
                                                            if ( intval( $curr_port ) === 11211 ) {
                                                                $curr_port = 6379;
                                                            }
                                                            if ( $curr_conn_type === 'socket' ) {
                                                                $conn_str = esc_html( $curr_socket );
                                                            } else {
                                                                $conn_str = esc_html( $curr_host . ':' . $curr_port );
                                                            }
                                                            $ext_available = extension_loaded('redis') || class_exists('Redis');
                                                            $ext_label = 'Redis';
                                                        }
                                                        ?>
                                                        <div style="display:flex; justify-content:space-between;">
                                                            <span style="font-weight:600; color:var(--uwb-text);">Connection:</span>
                                                            <code><?php echo $conn_str; ?><?php if ($oc_type !== 2) { echo ' (DB ' . intval( $curr_db ) . ')'; } ?></code>
                                                        </div>
                                                        <div style="display:flex; justify-content:space-between;">
                                                            <span style="font-weight:600; color:var(--uwb-text);">PHP Extension:</span>
                                                            <span><?php echo $ext_available ? $ext_label . ' Available ✓' : $ext_label . ' Not Installed ✗'; ?></span>
                                                        </div>
                                                        <?php
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                            <div id="redis-test-result" style="display:none; padding:10px 14px; border-radius:8px; font-size:12.5px; font-weight:600; margin-top:12px;"></div>
                                        </div>
                                        <div style="display:flex; gap:12px; margin-top:16px; border-top:1px solid var(--uwb-border); padding-top:12px;">
                                            <button type="button" id="btn-test-redis" class="button" style="border:1px solid var(--uwb-border); padding:8px 16px; border-radius:6px; font-weight:600; font-size:12.5px; background:#fff; cursor:pointer; color:var(--uwb-text); transition:all 0.2s; flex:1;">Test Connection</button>
                                            <button type="button" id="btn-flush-redis" class="button" style="border:1px solid #fca5a5; background:#fee2e2; color:#991b1b; padding:8px 16px; border-radius:6px; font-weight:600; font-size:12.5px; cursor:pointer; transition:all 0.2s; flex:1;">Flush Cache</button>
                                        </div>
                                    </div>

                                    <?php if ( $oc_active && ! empty( $stats_data ) ) : ?>
                                        <!-- Gauge 1: Memory -->
                                        <div style="background:#fff; border:1px solid var(--uwb-border); border-radius:12px; padding:24px; text-align:center; display:flex; flex-direction:column; align-items:center; justify-content:center;">
                                            <strong style="font-size:14px; color:var(--uwb-text); margin-bottom:12px; display:block;">Memory Usage</strong>
                                            <div style="position:relative; width:120px; height:120px; display:flex; align-items:center; justify-content:center;">
                                                <svg width="100%" height="100%" viewBox="0 0 36 36">
                                                    <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="#e2e8f0" stroke-width="3" />
                                                    <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="var(--uwb-primary)" stroke-dasharray="<?php echo $stats_data['memory_pct']; ?>, 100" stroke-width="3" stroke-linecap="round" />
                                                </svg>
                                                <span style="position:absolute; font-size:20px; font-weight:700; color:var(--uwb-text);"><?php echo $stats_data['memory_pct']; ?>%</span>
                                            </div>
                                        </div>

                                        <!-- Gauge 2: Hit Rate -->
                                        <div style="background:#fff; border:1px solid var(--uwb-border); border-radius:12px; padding:24px; text-align:center; display:flex; flex-direction:column; align-items:center; justify-content:center;">
                                            <strong style="font-size:14px; color:var(--uwb-text); margin-bottom:12px; display:block;">Hit Rate (Keyspace)</strong>
                                            <div style="position:relative; width:120px; height:120px; display:flex; align-items:center; justify-content:center;">
                                                <svg width="100%" height="100%" viewBox="0 0 36 36">
                                                    <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="#e2e8f0" stroke-width="3" />
                                                    <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="var(--uwb-primary)" stroke-dasharray="<?php echo round($stats_data['hit_ratio']); ?>, 100" stroke-width="3" stroke-linecap="round" />
                                                </svg>
                                                <span style="position:absolute; font-size:20px; font-weight:700; color:var(--uwb-text);"><?php echo $stats_data['hit_ratio']; ?>%</span>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <?php if ( $oc_active && ! empty( $stats_data ) ) : ?>
                                    <!-- Stats details row: 2 Cards -->
                                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:20px; margin-bottom:24px;">
                                        <!-- Card 1: Server Stats -->
                                        <div style="background:#fff; border:1px solid var(--uwb-border); border-radius:10px; padding:20px;">
                                            <strong style="font-size:14px; color:var(--uwb-text); margin-bottom:12px; display:block; border-bottom:1px solid var(--uwb-border); padding-bottom:8px;">Server Information</strong>
                                            <table style="width:100%; font-size:12.5px; border-collapse:collapse;">
                                                <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:6px 0; color:var(--uwb-text-muted);">Cache Type:</td><td style="padding:6px 0; text-align:right; font-weight:600; color:var(--uwb-text);"><?php echo esc_html(ucfirst($stats_data['type'])); ?></td></tr>
                                                <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:6px 0; color:var(--uwb-text-muted);">Version:</td><td style="padding:6px 0; text-align:right; font-weight:600; color:var(--uwb-text);"><?php echo esc_html($stats_data['version']); ?></td></tr>
                                                <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:6px 0; color:var(--uwb-text-muted);">Uptime:</td><td style="padding:6px 0; text-align:right; font-weight:600; color:var(--uwb-text);"><?php echo human_time_diff(0, $stats_data['uptime']); ?></td></tr>
                                                <?php if ( isset( $stats_data['connected_clients'] ) ) : ?>
                                                    <tr><td style="padding:6px 0; color:var(--uwb-text-muted);">Connected Clients:</td><td style="padding:6px 0; text-align:right; font-weight:600; color:var(--uwb-text);"><?php echo number_format($stats_data['connected_clients']); ?></td></tr>
                                                <?php endif; ?>
                                            </table>
                                        </div>

                                        <!-- Card 2: Cache Usage Stats -->
                                        <div style="background:#fff; border:1px solid var(--uwb-border); border-radius:10px; padding:20px;">
                                            <strong style="font-size:14px; color:var(--uwb-text); margin-bottom:12px; display:block; border-bottom:1px solid var(--uwb-border); padding-bottom:8px;">Cache Statistics</strong>
                                            <table style="width:100%; font-size:12.5px; border-collapse:collapse;">
                                                <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:6px 0; color:var(--uwb-text-muted);">Memory Used:</td><td style="padding:6px 0; text-align:right; font-weight:600; color:var(--uwb-text);"><?php echo esc_html($stats_data['memory_used']); ?></td></tr>
                                                <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:6px 0; color:var(--uwb-text-muted);">Memory Limit:</td><td style="padding:6px 0; text-align:right; font-weight:600; color:var(--uwb-text);"><?php echo esc_html($stats_data['memory_total']); ?></td></tr>
                                                <?php if ( isset( $stats_data['keys'] ) ) : ?>
                                                    <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:6px 0; color:var(--uwb-text-muted);">Total Keys (Current DB):</td><td style="padding:6px 0; text-align:right; font-weight:600; color:var(--uwb-text);"><?php echo number_format($stats_data['keys']); ?></td></tr>
                                                <?php elseif ( isset( $stats_data['curr_items'] ) ) : ?>
                                                    <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:6px 0; color:var(--uwb-text-muted);">Total Items:</td><td style="padding:6px 0; text-align:right; font-weight:600; color:var(--uwb-text);"><?php echo number_format($stats_data['curr_items']); ?></td></tr>
                                                <?php endif; ?>
                                                <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:6px 0; color:var(--uwb-text-muted);">Hits:</td><td style="padding:6px 0; text-align:right; font-weight:600; color:var(--uwb-text);"><?php echo number_format($stats_data['hits']); ?></td></tr>
                                                <tr><td style="padding:6px 0; color:var(--uwb-text-muted);">Misses:</td><td style="padding:6px 0; text-align:right; font-weight:600; color:var(--uwb-text);"><?php echo number_format($stats_data['misses']); ?></td></tr>
                                            </table>
                                        </div>
                                    </div>
                                <?php elseif ( $oc_active && ! empty( $stats_error ) ) : ?>
                                    <div style="background:#fff; border:1px solid var(--uwb-border); border-radius:12px; padding:20px; margin-bottom:24px; border-left: 4px solid var(--uwb-danger);">
                                        <p style="margin:0; font-size:13.5px; font-weight:600; color:var(--uwb-danger);">Không thể lấy thông tin chi tiết Object Cache:</p>
                                        <p style="margin:8px 0 0 0; font-size:13px; color:var(--uwb-text-muted);"><?php echo esc_html($stats_error); ?></p>
                                    </div>
                                <?php endif; ?>

                                <!-- Group 4: Object Cache Settings -->
                                <div style="background:#f8fafc; border:1px solid var(--uwb-border); border-radius:12px; padding:24px; margin-bottom:24px;">
                                    <h3 style="margin-top:0; margin-bottom:20px; font-size:15px; display:flex; align-items:center; gap:8px;">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.1a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>
                                        Object Cache Settings
                                    </h3>

                                    <div class="uwb-form-group">
                                        <label for="uwb_redis_enabled">Enable Object Cache?</label>
                                        <select name="uwb_redis_enabled" id="uwb_redis_enabled" style="width:100%; border:1px solid var(--uwb-border); border-radius:8px; padding:12px;">
                                            <option value="0" <?php selected( get_option( 'uwb_redis_enabled', 0 ), 0 ); ?>>None</option>
                                            <option value="1" <?php selected( get_option( 'uwb_redis_enabled', 0 ), 1 ); ?>>Redis / Valkey</option>
                                            <option value="2" <?php selected( get_option( 'uwb_redis_enabled', 0 ), 2 ); ?>>Memcached</option>
                                        </select>
                                        <p class="description" style="margin-bottom:0;">When enabled, database query results will be stored persistently in the selected cache backend. Our custom drop-in file will be automatically copied to <code>wp-content/object-cache.php</code>.</p>
                                    </div>

                                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                                        <div id="uwb-oc-conn-type-group" class="uwb-form-group">
                                            <label for="uwb_redis_conn_type">Connection Type</label>
                                            <select name="uwb_redis_conn_type" id="uwb_redis_conn_type" style="width:100%; border:1px solid var(--uwb-border); border-radius:8px; padding:12px;">
                                                <option value="tcp" <?php selected( get_option( 'uwb_redis_conn_type', 'tcp' ), 'tcp' ); ?>>TCP/IP (Host/Port)</option>
                                                <option value="socket" <?php selected( get_option( 'uwb_redis_conn_type', 'tcp' ), 'socket' ); ?>>Unix Socket</option>
                                            </select>
                                        </div>

                                        <div id="uwb-oc-db-group" class="uwb-form-group">
                                            <label for="uwb_redis_db">Database Index</label>
                                            <input type="number" min="0" max="15" name="uwb_redis_db" id="uwb_redis_db" value="<?php echo esc_attr( get_option( 'uwb_redis_db', 0 ) ); ?>" />
                                        </div>
                                    </div>

                                    <!-- TCP Settings -->
                                    <div id="redis-tcp-settings" style="display:grid; grid-template-columns: 2fr 1fr; gap:16px;">
                                        <div class="uwb-form-group">
                                            <label for="uwb_redis_host">Redis Host</label>
                                            <input type="text" name="uwb_redis_host" id="uwb_redis_host" value="<?php echo esc_attr( get_option( 'uwb_redis_host', '127.0.0.1' ) ); ?>" />
                                        </div>
                                        <div class="uwb-form-group">
                                            <label for="uwb_redis_port">Redis Port</label>
                                            <input type="number" name="uwb_redis_port" id="uwb_redis_port" value="<?php echo esc_attr( get_option( 'uwb_redis_port', 6379 ) ); ?>" />
                                        </div>
                                    </div>

                                    <!-- Socket Settings -->
                                    <div id="redis-socket-settings" style="display:none;">
                                        <div class="uwb-form-group">
                                            <label for="uwb_redis_socket">Unix Socket Path</label>
                                            <input type="text" name="uwb_redis_socket" id="uwb_redis_socket" placeholder="/var/run/redis/redis.sock" value="<?php echo esc_attr( get_option( 'uwb_redis_socket', '' ) ); ?>" />
                                        </div>
                                    </div>

                                    <!-- Password Setting -->
                                    <div id="uwb-oc-password-group" class="uwb-form-group" style="margin-bottom:20px;">
                                        <label for="uwb_redis_password">Redis Password (Optional)</label>
                                        <input type="password" name="uwb_redis_password" id="uwb_redis_password" placeholder="Leave blank if no password" value="<?php echo esc_attr( get_option( 'uwb_redis_password', '' ) ); ?>" style="width:100%; border:1px solid var(--uwb-border); border-radius:8px; padding:12px; font-size:14px;" autocomplete="new-password" />
                                    </div>

                                    <!-- Key Prefix / Salt Setting -->
                                    <div id="uwb-oc-prefix-group" class="uwb-form-group">
                                        <label for="uwb_redis_prefix">Redis Key Prefix / Salt</label>
                                        <input type="text" name="uwb_redis_prefix" id="uwb_redis_prefix" placeholder="uwb_oc:" value="<?php echo esc_attr( get_option( 'uwb_redis_prefix', 'uwb_oc:' ) ); ?>" />
                                        <p class="description">Prefix to avoid conflicts with other sites sharing the same Redis database. Default is <code>uwb_oc:</code>.</p>
                                    </div>

                                    <!-- Redis Connection Timeouts and retry interval -->
                                    <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px; margin-bottom:20px;">
                                        <div class="uwb-form-group">
                                            <label for="uwb_redis_timeout">Timeout (seconds)</label>
                                            <input type="number" step="0.1" min="0.1" name="uwb_redis_timeout" id="uwb_redis_timeout" value="<?php echo esc_attr( get_option( 'uwb_redis_timeout', 1.0 ) ); ?>" />
                                        </div>
                                        <div class="uwb-form-group">
                                            <label for="uwb_redis_read_timeout">Read Timeout (seconds)</label>
                                            <input type="number" step="0.1" min="0.1" name="uwb_redis_read_timeout" id="uwb_redis_read_timeout" value="<?php echo esc_attr( get_option( 'uwb_redis_read_timeout', 1.0 ) ); ?>" />
                                        </div>
                                        <div class="uwb-form-group">
                                            <label for="uwb_redis_retry_interval">Retry Interval (ms)</label>
                                            <input type="number" name="uwb_redis_retry_interval" id="uwb_redis_retry_interval" placeholder="e.g. 100" value="<?php echo esc_attr( get_option( 'uwb_redis_retry_interval', '' ) ); ?>" />
                                        </div>
                                    </div>

                                    <div class="uwb-form-group" style="margin-top:20px; padding-top:20px; border-top:1px solid var(--uwb-border);">
                                        <button type="button" id="btn-test-redis-settings" class="button button-secondary" style="font-weight:600; padding:10px 20px; height:auto; border-radius:8px; display:inline-flex; align-items:center; gap:8px;">
                                            Test Connection Settings
                                        </button>
                                        <div id="redis-test-result-settings" style="display:none; margin-top:12px; padding:12px; border-radius:8px; font-size:13px; font-weight:600;"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- SUB-TAB 5: OPCache -->
                            <!-- SUB-TAB 5: OPCache -->
                            <div id="subtab-opcache" class="uwb-subtab-content">
                                <div style="background:#f8fafc; border:1px solid var(--uwb-border); border-radius:12px; padding:24px; margin-bottom:24px;">
                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:16px;">
                                        <h3 style="margin:0; font-size:15px; display:flex; align-items:center; gap:8px;">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
                                            OPcache Status & Settings
                                        </h3>
                                        <a href="<?php echo wp_nonce_url( admin_url( 'admin-post.php?action=uwb_flush_opcache' ), 'uwb_flush_opcache_action' ); ?>" class="button button-secondary" style="border:1px solid var(--uwb-btn-danger-border); color:var(--uwb-btn-danger-text); background:#fff; font-weight:600; padding:6px 12px; font-size:12.5px; border-radius:6px; cursor:pointer;">Flush OPcache</a>
                                    </div>
                                    
                                    <?php
                                    $opcache_status = function_exists( 'opcache_get_status' ) ? @opcache_get_status( true ) : false;
                                    $opcache_config = function_exists( 'opcache_get_configuration' ) ? @opcache_get_configuration() : false;
                                    $opcache_enabled = ! empty( $opcache_status['opcache_enabled'] );

                                    if ( ! function_exists( 'uwb_format_bytes' ) ) {
                                        function uwb_format_bytes( $bytes, $precision = 1 ) {
                                            $units = array( 'B', 'KB', 'MB', 'GB' );
                                            $bytes = max( $bytes, 0 );
                                            $pow = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
                                            $pow = min( $pow, count( $units ) - 1 );
                                            $bytes /= pow( 1024, $pow );
                                            return round( $bytes, $precision ) . ' ' . $units[$pow];
                                        }
                                    }

                                    if ( $opcache_enabled ) :
                                        // Memory
                                        $mem_used = isset( $opcache_status['memory_usage']['used_memory'] ) ? $opcache_status['memory_usage']['used_memory'] : 0;
                                        $mem_free = isset( $opcache_status['memory_usage']['free_memory'] ) ? $opcache_status['memory_usage']['free_memory'] : 0;
                                        $mem_wasted = isset( $opcache_status['memory_usage']['wasted_memory'] ) ? $opcache_status['memory_usage']['wasted_memory'] : 0;
                                        $mem_wasted_pct = isset( $opcache_status['memory_usage']['current_wasted_percentage'] ) ? round( $opcache_status['memory_usage']['current_wasted_percentage'], 1 ) : 0;
                                        $mem_total = $mem_used + $mem_free + $mem_wasted;
                                        $mem_used_pct = $mem_total > 0 ? round( ( $mem_used / $mem_total ) * 100 ) : 0;
                                        
                                        // Hit rate
                                        $hits = isset( $opcache_status['opcache_statistics']['hits'] ) ? $opcache_status['opcache_statistics']['hits'] : 0;
                                        $misses = isset( $opcache_status['opcache_statistics']['misses'] ) ? $opcache_status['opcache_statistics']['misses'] : 0;
                                        $hit_rate = isset( $opcache_status['opcache_statistics']['opcache_hit_rate'] ) ? round( $opcache_status['opcache_statistics']['opcache_hit_rate'], 1 ) : ( $hits + $misses > 0 ? round( ( $hits / ($hits + $misses) ) * 100, 1 ) : 0 );
                                        
                                        // Keys
                                        $keys_used = isset( $opcache_status['opcache_statistics']['num_cached_keys'] ) ? $opcache_status['opcache_statistics']['num_cached_keys'] : 0;
                                        $keys_max = isset( $opcache_status['opcache_statistics']['max_cached_keys'] ) ? $opcache_status['opcache_statistics']['max_cached_keys'] : 0;
                                        $keys_pct = $keys_max > 0 ? round( ( $keys_used / $keys_max ) * 100 ) : 0;
                                        
                                        // Interned strings
                                        $is_size = isset( $opcache_status['interned_strings_usage']['buffer_size'] ) ? $opcache_status['interned_strings_usage']['buffer_size'] : 0;
                                        $is_used = isset( $opcache_status['interned_strings_usage']['used_memory'] ) ? $opcache_status['interned_strings_usage']['used_memory'] : 0;
                                        $is_free = isset( $opcache_status['interned_strings_usage']['free_memory'] ) ? $opcache_status['interned_strings_usage']['free_memory'] : 0;
                                        $is_strings = isset( $opcache_status['interned_strings_usage']['number_of_strings'] ) ? $opcache_status['interned_strings_usage']['number_of_strings'] : 0;
                                        
                                        // Statistics
                                        $num_scripts = isset( $opcache_status['opcache_statistics']['num_cached_scripts'] ) ? $opcache_status['opcache_statistics']['num_cached_scripts'] : 0;
                                        $blacklist_misses = isset( $opcache_status['opcache_statistics']['blacklist_misses'] ) ? $opcache_status['opcache_statistics']['blacklist_misses'] : 0;
                                        $start_time = isset( $opcache_status['opcache_statistics']['start_time'] ) ? date( 'Y/m/d h:i:s A', $opcache_status['opcache_statistics']['start_time'] ) : 'Unknown';
                                        $last_restart = isset( $opcache_status['opcache_statistics']['last_restart_time'] ) && $opcache_status['opcache_statistics']['last_restart_time'] > 0 ? date( 'Y/m/d h:i:s A', $opcache_status['opcache_statistics']['last_restart_time'] ) : 'Never';
                                        
                                        // General info
                                        $opcache_version = isset( $opcache_config['version']['version'] ) ? $opcache_config['version']['version'] : 'PHP OPcache';
                                        $php_version = PHP_VERSION;
                                        $host = isset( $_SERVER['SERVER_NAME'] ) ? $_SERVER['SERVER_NAME'] : ( isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : 'localhost' );
                                        $server_software = isset( $_SERVER['SERVER_SOFTWARE'] ) ? $_SERVER['SERVER_SOFTWARE'] : 'Unknown';
                                        ?>
                                        
                                        <!-- Gauges row: 3 Gauges -->
                                        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:20px; margin-bottom:24px;">
                                            <!-- Gauge 1: Memory -->
                                            <div style="background:#fff; border:1px solid var(--uwb-border); border-radius:10px; padding:20px; text-align:center; display:flex; flex-direction:column; align-items:center; justify-content:center;">
                                                <strong style="font-size:14px; color:var(--uwb-text); margin-bottom:12px; display:block;">Memory</strong>
                                                <div style="position:relative; width:120px; height:120px; display:flex; align-items:center; justify-content:center;">
                                                    <svg width="100%" height="100%" viewBox="0 0 36 36">
                                                        <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="#e2e8f0" stroke-width="3" />
                                                        <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="var(--uwb-primary)" stroke-dasharray="<?php echo $mem_used_pct; ?>, 100" stroke-width="3" stroke-linecap="round" />
                                                    </svg>
                                                    <span style="position:absolute; font-size:20px; font-weight:700; color:var(--uwb-text);"><?php echo $mem_used_pct; ?>%</span>
                                                </div>
                                            </div>

                                            <!-- Gauge 2: Hit Rate -->
                                            <div style="background:#fff; border:1px solid var(--uwb-border); border-radius:10px; padding:20px; text-align:center; display:flex; flex-direction:column; align-items:center; justify-content:center;">
                                                <strong style="font-size:14px; color:var(--uwb-text); margin-bottom:12px; display:block;">Hit rate</strong>
                                                <div style="position:relative; width:120px; height:120px; display:flex; align-items:center; justify-content:center;">
                                                    <svg width="100%" height="100%" viewBox="0 0 36 36">
                                                        <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="#e2e8f0" stroke-width="3" />
                                                        <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="var(--uwb-primary)" stroke-dasharray="<?php echo round($hit_rate); ?>, 100" stroke-width="3" stroke-linecap="round" />
                                                    </svg>
                                                    <span style="position:absolute; font-size:20px; font-weight:700; color:var(--uwb-text);"><?php echo $hit_rate; ?>%</span>
                                                </div>
                                            </div>

                                            <!-- Gauge 3: Keys -->
                                            <div style="background:#fff; border:1px solid var(--uwb-border); border-radius:10px; padding:20px; text-align:center; display:flex; flex-direction:column; align-items:center; justify-content:center;">
                                                <strong style="font-size:14px; color:var(--uwb-text); margin-bottom:12px; display:block;">Keys</strong>
                                                <div style="position:relative; width:120px; height:120px; display:flex; align-items:center; justify-content:center;">
                                                    <svg width="100%" height="100%" viewBox="0 0 36 36">
                                                        <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="#e2e8f0" stroke-width="3" />
                                                        <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="var(--uwb-primary)" stroke-dasharray="<?php echo $keys_pct; ?>, 100" stroke-width="3" stroke-linecap="round" />
                                                    </svg>
                                                    <span style="position:absolute; font-size:20px; font-weight:700; color:var(--uwb-text);"><?php echo $keys_pct; ?>%</span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Stats details row: 3 Cards -->
                                        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:20px; margin-bottom:24px;">
                                            <!-- Card 1: Memory usage -->
                                            <div style="background:#fff; border:1px solid var(--uwb-border); border-radius:10px; padding:20px;">
                                                <strong style="font-size:14px; color:var(--uwb-text); margin-bottom:12px; display:block; border-bottom:1px solid var(--uwb-border); padding-bottom:8px;">Memory usage</strong>
                                                <table style="width:100%; font-size:12.5px; border-collapse:collapse;">
                                                    <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:6px 0; color:var(--uwb-text-muted);">total memory:</td><td style="padding:6px 0; text-align:right; font-weight:600; color:var(--uwb-text);"><?php echo uwb_format_bytes($mem_total); ?></td></tr>
                                                    <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:6px 0; color:var(--uwb-text-muted);">used memory:</td><td style="padding:6px 0; text-align:right; font-weight:600; color:var(--uwb-text);"><?php echo uwb_format_bytes($mem_used); ?></td></tr>
                                                    <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:6px 0; color:var(--uwb-text-muted);">free memory:</td><td style="padding:6px 0; text-align:right; font-weight:600; color:var(--uwb-text);"><?php echo uwb_format_bytes($mem_free); ?></td></tr>
                                                    <tr><td style="padding:6px 0; color:var(--uwb-text-muted);">wasted memory:</td><td style="padding:6px 0; text-align:right; font-weight:600; color:var(--uwb-text);"><?php echo uwb_format_bytes($mem_wasted); ?> (<?php echo $mem_wasted_pct; ?>%)</td></tr>
                                                </table>
                                            </div>

                                            <!-- Card 2: OPcache statistics -->
                                            <div style="background:#fff; border:1px solid var(--uwb-border); border-radius:10px; padding:20px;">
                                                <strong style="font-size:14px; color:var(--uwb-text); margin-bottom:12px; display:block; border-bottom:1px solid var(--uwb-border); padding-bottom:8px;">OPcache statistics</strong>
                                                <table style="width:100%; font-size:12.5px; border-collapse:collapse;">
                                                    <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:6px 0; color:var(--uwb-text-muted);">number of cached files:</td><td style="padding:6px 0; text-align:right; font-weight:600; color:var(--uwb-text);"><?php echo number_format($num_scripts); ?></td></tr>
                                                    <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:6px 0; color:var(--uwb-text-muted);">number of hits:</td><td style="padding:6px 0; text-align:right; font-weight:600; color:var(--uwb-text);"><?php echo number_format($hits); ?></td></tr>
                                                    <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:6px 0; color:var(--uwb-text-muted);">number of misses:</td><td style="padding:6px 0; text-align:right; font-weight:600; color:var(--uwb-text);"><?php echo number_format($misses); ?></td></tr>
                                                    <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:6px 0; color:var(--uwb-text-muted);">blacklist misses:</td><td style="padding:6px 0; text-align:right; font-weight:600; color:var(--uwb-text);"><?php echo number_format($blacklist_misses); ?></td></tr>
                                                    <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:6px 0; color:var(--uwb-text-muted);">number of cached keys:</td><td style="padding:6px 0; text-align:right; font-weight:600; color:var(--uwb-text);"><?php echo number_format($keys_used); ?></td></tr>
                                                    <tr><td style="padding:6px 0; color:var(--uwb-text-muted);">max cached keys:</td><td style="padding:6px 0; text-align:right; font-weight:600; color:var(--uwb-text);"><?php echo number_format($keys_max); ?></td></tr>
                                                </table>
                                            </div>

                                            <!-- Card 3: Interned strings usage -->
                                            <div style="background:#fff; border:1px solid var(--uwb-border); border-radius:10px; padding:20px;">
                                                <strong style="font-size:14px; color:var(--uwb-text); margin-bottom:12px; display:block; border-bottom:1px solid var(--uwb-border); padding-bottom:8px;">Interned strings usage</strong>
                                                <table style="width:100%; font-size:12.5px; border-collapse:collapse;">
                                                    <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:6px 0; color:var(--uwb-text-muted);">buffer size:</td><td style="padding:6px 0; text-align:right; font-weight:600; color:var(--uwb-text);"><?php echo uwb_format_bytes($is_size); ?></td></tr>
                                                    <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:6px 0; color:var(--uwb-text-muted);">used memory:</td><td style="padding:6px 0; text-align:right; font-weight:600; color:var(--uwb-text);"><?php echo uwb_format_bytes($is_used); ?></td></tr>
                                                    <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:6px 0; color:var(--uwb-text-muted);">free memory:</td><td style="padding:6px 0; text-align:right; font-weight:600; color:var(--uwb-text);"><?php echo uwb_format_bytes($is_free); ?></td></tr>
                                                    <tr><td style="padding:6px 0; color:var(--uwb-text-muted);">number of strings:</td><td style="padding:6px 0; text-align:right; font-weight:600; color:var(--uwb-text);"><?php echo number_format($is_strings); ?></td></tr>
                                                </table>
                                            </div>
                                        </div>

                                        <!-- General Info & Available functions row: 2 Cards -->
                                        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(300px, 1fr)); gap:20px;">
                                            <!-- Card 1: General info -->
                                            <div style="background:#fff; border:1px solid var(--uwb-border); border-radius:10px; padding:20px;">
                                                <strong style="font-size:14px; color:var(--uwb-text); margin-bottom:12px; display:block; border-bottom:1px solid var(--uwb-border); padding-bottom:8px;">General info</strong>
                                                <table style="width:100%; font-size:12.5px; border-collapse:collapse;">
                                                    <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:6px 0; color:var(--uwb-text-muted);">OPcache version:</td><td style="padding:6px 0; text-align:right; font-weight:600; color:var(--uwb-text);"><?php echo esc_html($opcache_version); ?></td></tr>
                                                    <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:6px 0; color:var(--uwb-text-muted);">PHP version:</td><td style="padding:6px 0; text-align:right; font-weight:600; color:var(--uwb-text);"><?php echo esc_html($php_version); ?></td></tr>
                                                    <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:6px 0; color:var(--uwb-text-muted);">host:</td><td style="padding:6px 0; text-align:right; font-weight:600; color:var(--uwb-text);"><?php echo esc_html($host); ?></td></tr>
                                                    <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:6px 0; color:var(--uwb-text-muted);">server software:</td><td style="padding:6px 0; text-align:right; font-weight:600; color:var(--uwb-text);"><?php echo esc_html($server_software); ?></td></tr>
                                                    <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:6px 0; color:var(--uwb-text-muted);">start time:</td><td style="padding:6px 0; text-align:right; font-weight:600; color:var(--uwb-text);"><?php echo esc_html($start_time); ?></td></tr>
                                                    <tr><td style="padding:6px 0; color:var(--uwb-text-muted);">last reset:</td><td style="padding:6px 0; text-align:right; font-weight:600; color:var(--uwb-text);"><?php echo esc_html($last_restart); ?></td></tr>
                                                </table>
                                            </div>

                                            <!-- Card 2: Available functions -->
                                            <div style="background:#fff; border:1px solid var(--uwb-border); border-radius:10px; padding:20px;">
                                                <strong style="font-size:14px; color:var(--uwb-text); margin-bottom:12px; display:block; border-bottom:1px solid var(--uwb-border); padding-bottom:8px;">Available functions</strong>
                                                <div style="display:flex; flex-direction:column; gap:6px; font-size:12.5px;">
                                                    <?php
                                                    $funcs = array(
                                                        'opcache_reset',
                                                        'opcache_get_status',
                                                        'opcache_compile_file',
                                                        'opcache_invalidate',
                                                        'opcache_get_configuration',
                                                        'opcache_is_script_cached',
                                                    );
                                                    foreach ( $funcs as $f ) :
                                                        $avail = function_exists( $f );
                                                        ?>
                                                        <div style="display:flex; justify-content:space-between; align-items:center; padding:4px 0; border-bottom:1px solid #f1f5f9;">
                                                            <code style="color:var(--uwb-primary); background:none; padding:0;"><?php echo $f; ?></code>
                                                            <span style="font-weight:600; color:<?php echo $avail ? '#10b981' : '#ef4444'; ?>;"><?php echo $avail ? 'available' : 'unavailable'; ?></span>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php else : ?>
                                        <div style="padding:16px; background:#fee2e2; border:1px solid #fca5a5; color:#991b1b; border-radius:8px; font-size:13.5px; font-weight:600;">
                                            OPcache is not active or enabled on this server. Check your PHP configuration (<code>opcache.enable</code> directive).
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- TAB 2: Preload Cache -->
                        <div id="tab-preload_settings" class="uwb-tab-content">
                            <h2 style="margin-top:0;">Preload Cache (Automatic Crawler)</h2>
                            <p style="color:var(--uwb-text-muted); margin-bottom: 24px;">Automatically crawl URLs in your sitemap to pre-generate static cache files before visitors arrive.</p>

                            <div class="uwb-form-group">
                                <label for="uwb_preload_enabled">Enable Automatic Preloading</label>
                                <select name="uwb_preload_enabled" id="uwb_preload_enabled" style="width:100%; border:1px solid var(--uwb-border); border-radius:8px; padding:12px;">
                                    <option value="0" <?php selected( get_option( 'uwb_preload_enabled', 0 ), 0 ); ?>>Disabled</option>
                                    <option value="1" <?php selected( get_option( 'uwb_preload_enabled', 0 ), 1 ); ?>>Enabled (via WP-Cron)</option>
                                    <option value="2" <?php selected( get_option( 'uwb_preload_enabled', 0 ), 2 ); ?>>Enabled (via Custom Linux Cron)</option>
                                </select>
                                <p class="description">When enabled, the crawler runs in the background to fetch URLs in the preloading queue in small batches.</p>

                                <!-- Custom Cron Instructions -->
                                <?php
                                $secret_key = get_option( 'uwb_preload_secret_key', '' );
                                $http_cron_cmd = '* * * * * curl -s "' . esc_url( home_url( '/?uwb_preload_key=' . $secret_key ) ) . '" >/dev/null 2>&1';
                                $wp_path = ABSPATH;
                                $wp_cli_cron_cmd = '* * * * * wp uwb-preload run --path=' . escapeshellarg( $wp_path ) . ' >/dev/null 2>&1';
                                ?>
                                <div id="uwb-custom-cron-info" style="margin-top: 16px; padding: 20px; background: #f8fafc; border: 1px solid var(--uwb-border); border-radius: 12px; display: <?php echo ( get_option( 'uwb_preload_enabled', 0 ) == 2 ) ? 'block' : 'none'; ?>;">
                                    <h4 style="margin: 0 0 10px 0; font-size: 14px; color: var(--uwb-text); display: flex; align-items: center; gap: 6px;">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                        Custom Linux Cron Configuration
                                    </h4>
                                    <p style="font-size: 13px; color: var(--uwb-text-muted); margin: 0 0 16px 0; line-height: 1.4;">
                                        Real Linux cron jobs are more reliable than virtual WP-Cron. Use one of the options below to trigger the preloader every minute via your server's crontab (run <code>crontab -e</code> on your server):
                                    </p>
                                    
                                    <div style="margin-bottom: 16px;">
                                        <span style="font-weight: 700; font-size: 12.5px; display: block; margin-bottom: 6px; color: var(--uwb-text);">Option 1: Using curl (Recommended)</span>
                                        <div style="position: relative;">
                                            <input type="text" readonly value="<?php echo esc_attr( $http_cron_cmd ); ?>" style="width: 100%; font-family: monospace; font-size: 12px; background: #fff; padding: 10px 40px 10px 10px; border: 1px solid var(--uwb-border); border-radius: 6px; color: #1e293b;" onclick="this.select();" />
                                            <button type="button" class="uwb-copy-cron" data-clipboard-text="<?php echo esc_attr( $http_cron_cmd ); ?>" style="position: absolute; right: 6px; top: 50%; transform: translateY(-50%); border: none; background: none; cursor: pointer; padding: 4px; color: var(--uwb-text-muted);" title="Copy to clipboard">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                            </button>
                                        </div>
                                    </div>

                                    <div>
                                        <span style="font-weight: 700; font-size: 12.5px; display: block; margin-bottom: 6px; color: var(--uwb-text);">Option 2: Using WP-CLI</span>
                                        <div style="position: relative;">
                                            <input type="text" readonly value="<?php echo esc_attr( $wp_cli_cron_cmd ); ?>" style="width: 100%; font-family: monospace; font-size: 12px; background: #fff; padding: 10px 40px 10px 10px; border: 1px solid var(--uwb-border); border-radius: 6px; color: #1e293b;" onclick="this.select();" />
                                            <button type="button" class="uwb-copy-cron" data-clipboard-text="<?php echo esc_attr( $wp_cli_cron_cmd ); ?>" style="position: absolute; right: 6px; top: 50%; transform: translateY(-50%); border: none; background: none; cursor: pointer; padding: 4px; color: var(--uwb-text-muted);" title="Copy to clipboard">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
 
                            <div class="uwb-form-group">
                                <label for="uwb_preload_batch_size">Preload Batch Size</label>
                                <input type="number" min="1" max="50" name="uwb_preload_batch_size" id="uwb_preload_batch_size" value="<?php echo esc_attr( get_option( 'uwb_preload_batch_size', 5 ) ); ?>" />
                                <p class="description">The number of URLs to crawl per batch to minimize CPU and server overhead.</p>
                            </div>
                            <div class="uwb-form-group">
                                <label for="uwb_preload_sitemap">Sitemap XML URLs</label>
                                <textarea name="uwb_preload_sitemap" id="uwb_preload_sitemap" rows="5" placeholder="<?php echo esc_attr( home_url( '/important-sitemap.xml' ) . "\n" . home_url( '/wp-sitemap.xml' ) ); ?>"><?php echo esc_textarea( $this->get_preload_sitemap_setting_value() ); ?></textarea>
                            </div>

                            <div class="uwb-form-group">
                                <label for="uwb_priority_urls">Important URLs (Preloaded first)</label>
                                <textarea name="uwb_priority_urls" id="uwb_priority_urls" rows="4"><?php echo esc_textarea( $this->get_priority_urls_setting_value() ); ?></textarea>
                                <p class="description">Important URLs or matching keywords, one per line. Valid URLs and paths are also published at <code><?php echo esc_url( home_url( '/important-sitemap.xml' ) ); ?></code>.</p>
                            </div>
                            <div class="uwb-form-group">
                                <label for="uwb_preload_links">Preload Links</label>
                                <select name="uwb_preload_links" id="uwb_preload_links" style="width:100%; border:1px solid var(--uwb-border); border-radius:8px; padding:12px;">
                                    <option value="0" <?php selected( get_option( 'uwb_preload_links', 0 ), 0 ); ?>>Disabled</option>
                                    <option value="1" <?php selected( get_option( 'uwb_preload_links', 0 ), 1 ); ?>>Enabled</option>
                                </select>
                                <p class="description">Link preloading improves the perceived load time by downloading a page when a user hovers over the link. <a href="https://instant.page" target="_blank" rel="noopener noreferrer">More info</a></p>
                            </div>

                            <!-- Real-time Preloader Debug Logs -->
                            <div class="uwb-form-group" style="margin-top:24px;">
                                <label style="display:flex; justify-content:space-between; align-items:center; width:100%;">
                                    <span>Preloader Crawl Logs</span>
                                    <button type="button" id="uwb-clear-preload-log-btn" class="button button-secondary button-small" style="font-size:11px; height:24px; line-height:22px; padding:0 8px;">Clear Logs</button>
                                </label>
                                <?php
                                $log_file = WP_CONTENT_DIR . '/cache/ultimate-wp-booster/preload-debug.log';
                                $log_content = 'No logs available. Start a preload run to generate logs.';
                                if ( file_exists( $log_file ) ) {
                                    $log_content = esc_html( @file_get_contents( $log_file ) );
                                }
                                ?>
                                <textarea id="uwb-preload-log" readonly rows="8" style="width:100%; font-family:monospace; font-size:11px; background:#fafafa; border:1px solid var(--uwb-border); border-radius:8px; padding:12px; margin-top:8px; line-height:1.4; color:#334155; white-space:pre; overflow-x:auto;"><?php echo $log_content; ?></textarea>
                                <p class="description">Live logs for the crawler's sitemap parsing and batch processing stages. Updates automatically during preloading.</p>
                            </div>

                        </div>

                        <!-- TAB 3: Page Optimizes -->
                        <div id="tab-page_optimizes" class="uwb-tab-content">
                            <h2 style="margin-top:0;">Page Optimization</h2>
                            <p style="color:var(--uwb-text-muted); margin-bottom: 24px;">Optimize web source code by minifying and combining resources, lazy loading media, and tuning performance.</p>

                            <!-- Horizontal Sub-tabs Nav -->
                            <div class="uwb-sub-tabs-nav">
                                <div class="uwb-sub-tab-item active" data-subtab="opt_css">[1] CSS</div>
                                <div class="uwb-sub-tab-item" data-subtab="opt_js">[2] JS</div>
                                <div class="uwb-sub-tab-item" data-subtab="opt_html">[3] HTML</div>
                                <div class="uwb-sub-tab-item" data-subtab="opt_media">[4] Media</div>
                                <div class="uwb-sub-tab-item" data-subtab="opt_font">[5] Font</div>
                                <div class="uwb-sub-tab-item" data-subtab="opt_cdn_media">[6] CDN Offload Media</div>
                            </div>

                            <!-- SUB-TAB 1: CSS Settings & Excludes -->
                            <div id="subtab-opt_css" class="uwb-subtab-content active">
                                <?php
                                $this->render_toggle_switch( 'uwb_css_minify', 'CSS Minify', 'Minify CSS files and inline CSS code.' );
                                $this->render_toggle_switch( 'uwb_css_combine', 'CSS Combine', 'Combine CSS stylesheets into a single cached file to reduce HTTP requests.' );
                                $this->render_toggle_switch( 'uwb_css_combine_ext_inline', 'CSS Combine External and Inline', 'Include external CSS files and inline CSS code in the combined CSS bundle.' );
                                $this->render_textarea_setting( 'uwb_tuning_css_excludes', 'CSS Minify & Combine Excludes', '', 'CSS files or inline keywords to exclude from minification/combination (one per line).' );
                                $this->render_toggle_switch( 'uwb_css_load_async', 'Load CSS Asynchronously', 'Load CSS files asynchronously to eliminate render-blocking CSS and speed up page rendering.' );
                                $this->render_textarea_setting( 'uwb_tuning_critical_css', 'Critical CSS', '', 'Custom Critical CSS to inject into &lt;head&gt;.' );

                                $this->render_cdn_distribution_card(
                                    'Cloudflare R2 / S3 CDN Distribution for CSS',
                                    'uwb_cdn_distribute_css',
                                    'Phân phối CSS qua S3 CDN?',
                                    'Tải các tập tin CSS đã được nén (minify) hoặc gộp (combine) lên S3/R2 CDN để tối ưu hóa tốc độ tải trang.',
                                    array(
                                        'Upload to S3 when:' => array(
                                            'uwb_cdn_auto_upload_combined_css' => 'upload (gộp file CSS)',
                                            'uwb_cdn_auto_upload_minified_css' => 'edit (nén file CSS)',
                                        ),
                                        'Delete From S3 when:' => array(
                                            'uwb_cdn_auto_purge_css_cdn' => 'Delete file (xả cache CSS)',
                                        ),
                                    )
                                );
                                ?>
                            </div>

                            <!-- SUB-TAB 2: JS Settings, Defer, Delay & Excludes -->
                            <div id="subtab-opt_js" class="uwb-subtab-content">
                                <h4 style="margin: 0 0 16px 0; font-size: 14px; font-weight: 700; color: var(--uwb-text); border-bottom: 1px solid var(--uwb-border); padding-bottom: 8px;">Minification &amp; Combination</h4>
                                <?php
                                $delay_js_on = (bool) get_option( 'uwb_delay_js', 0 );
                                $this->render_toggle_switch( 'uwb_js_minify', 'JS Minify', 'Minify JS files and inline JS code.' );
                                $this->render_toggle_switch( 'uwb_js_combine', 'JS Combine', 'Combine JavaScript files into a single cached file to reduce HTTP requests.', $delay_js_on );
                                if ( $delay_js_on ) : ?>
                                <div style="display:flex; align-items:flex-start; gap:10px; background:#fffbeb; border:1px solid #fbbf24; border-radius:8px; padding:12px 16px; margin-bottom:16px; font-size:13px; color:#92400e; margin-top:-8px;">
                                    <svg style="flex-shrink:0; margin-top:1px;" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                    <span><strong>Disabled automatically:</strong> JS Combine is not compatible with Delay JavaScript Execution. Disable Delay JS first to enable JS Combine.</span>
                                </div>
                                <?php endif; ?>
                                <?php
                                $this->render_toggle_switch( 'uwb_js_combine_ext_inline', 'JS Combine External and Inline', 'Include external JS files and inline JS code in the combined JS bundle.', $delay_js_on );
                                $this->render_textarea_setting( 'uwb_tuning_js_excludes', 'JS Minify & Combine Excludes', '', 'JS files or inline keywords to exclude from minification/combination (one per line).' );
                                ?>

                                <h4 style="margin: 24px 0 16px 0; font-size: 14px; font-weight: 700; color: var(--uwb-text); border-bottom: 1px solid var(--uwb-border); padding-bottom: 8px;">Load JS Deferred</h4>
                                <?php
                                $js_quick_fix_notice = '<br><span style="display:block; margin-top:8px; padding:10px 14px; background:#f8fafc; border-left:3px solid #6366f1; border-radius:6px; font-size:12px; color:#334155; line-height:1.5;"><strong>💡 Quick Fix:</strong> If you have problems after activating this option, copy and paste the default exclusions to quickly resolve issues:<br><code style="display:block; margin-top:6px; padding:8px 10px; background:#0f172a; color:#f8fafc; border-radius:6px; font-family:monospace; font-size:11.5px; white-space:pre; overflow-x:auto;">\/jquery(-migrate)?-?([0-9.]+)?(.min|.slim|.slim.min)?.js(\?(.*))?( |\'|"|&gt;)
js-(before|after)
(?:/wp-content/|/wp-includes/)(.*)</code></span>';

                                $this->render_toggle_switch( 'uwb_js_load_defer', 'Load JS Deferred', 'Load JS with defer attribute so scripts download in background without blocking DOM parsing.' . $js_quick_fix_notice );
                                $this->render_textarea_setting( 'uwb_tuning_js_defer_excludes', 'JS Deferred Excludes', '', 'JS files or inline keywords to exclude from deferred loading (one per line).' );
                                ?>

                                <h4 style="margin: 24px 0 16px 0; font-size: 14px; font-weight: 700; color: var(--uwb-text); border-bottom: 1px solid var(--uwb-border); padding-bottom: 8px; display:flex; align-items:center; gap:8px;">
                                    Delay JavaScript Execution
                                    <span style="background:#7c3aed; color:#fff; font-size:10px; padding:2px 8px; border-radius:12px; font-weight:700;">NEW</span>
                                </h4>
                                <?php
                                $this->render_toggle_switch( 'uwb_delay_js', 'Enable Delay JS', 'Delay execution of JavaScript until first user interaction (scroll, click, keypress). Dramatically improves <strong>LCP</strong> and <strong>TBT</strong> scores.' . $js_quick_fix_notice );
                                $this->render_textarea_setting(
                                    'uwb_delay_js_exclusions',
                                    'Delay JS Exclusions',
                                    "jquery.js\njquery.min.js\njquery-migrate.min.js\nga.js\ngtm.js",
                                    'One pattern per line. Scripts matching these patterns will NOT be delayed.',
                                    ! intval( get_option( 'uwb_delay_js', 0 ) )
                                );

                                $this->render_cdn_distribution_card(
                                    'Cloudflare R2 / S3 CDN Distribution for JS',
                                    'uwb_cdn_distribute_js',
                                    'Phân phối JS qua S3 CDN?',
                                    'Tải các tập tin JavaScript đã được nén (minify) hoặc gộp (combine) lên S3/R2 CDN để phục vụ khách truy cập.',
                                    array(
                                        'Upload to S3 when:' => array(
                                            'uwb_cdn_auto_upload_combined_js' => 'upload (gộp file JS)',
                                            'uwb_cdn_auto_upload_minified_js' => 'edit (nén file JS)',
                                        ),
                                        'Delete From S3 when:' => array(
                                            'uwb_cdn_auto_purge_js_cdn' => 'Delete file (xả cache JS)',
                                        ),
                                    )
                                );
                                ?>
                            </div>

                            <!-- SUB-TAB 3: HTML Settings -->
                            <div id="subtab-opt_html" class="uwb-subtab-content">
                                <?php
                                $this->render_toggle_switch( 'uwb_html_minify', 'HTML Minify', 'Minify HTML source code.' );
                                $this->render_toggle_switch( 'uwb_html_remove_qs', 'Remove Query Strings', 'Remove query strings from static resources.' );
                                $this->render_toggle_switch( 'uwb_html_remove_gfonts', 'Remove Google Fonts', 'Remove Google Fonts from all pages.' );
                                $this->render_toggle_switch( 'uwb_html_remove_emoji', 'Remove WordPress Emoji', 'Remove default WordPress Emoji CSS/JS.' );
                                $this->render_toggle_switch( 'uwb_html_remove_noscript', 'Remove Noscript Tags', 'Remove all noscript tags from HTML.' );

                                $this->render_cdn_distribution_card(
                                    'Cloudflare R2 / S3 CDN Distribution in HTML Output',
                                    'uwb_cdn_distribute_html',
                                    'Phân phối tài nguyên tĩnh trong HTML qua S3 CDN?',
                                    'Tự động viết lại (rewrite) toàn bộ URL tài nguyên tĩnh trong mã nguồn HTML trang web để phân phối từ CDN Domain.',
                                    array(
                                        'Upload to S3 when:' => array(
                                            'uwb_cdn_auto_rewrite_html_urls' => 'get_url (viết lại URL tĩnh trong HTML)',
                                        ),
                                        'Delete From S3 when:' => array(
                                            'uwb_cdn_auto_purge_html_cf' => 'Delete file (xả Cloudflare Zone CDN Edge)',
                                        ),
                                    )
                                );
                                ?>
                            </div>

                            <!-- SUB-TAB 4: Media Settings & Excludes -->
                            <div id="subtab-opt_media" class="uwb-subtab-content">
                                <?php
                                $this->render_toggle_switch( 'uwb_media_lazy_load_images', 'Lazy Load Images', 'Delay image loading until visible in viewport.' );
                                $this->render_toggle_switch( 'uwb_media_lazy_load_iframes', 'Lazy Load Iframes / Videos', 'Delay iframe (YouTube/Vimeo) and HTML5 video loading until visible in viewport.' );
                                $this->render_toggle_switch( 'uwb_media_image_placeholder', 'Use Image Placeholders', 'Use responsive placeholders for lazy loaded images.', true );
                                $this->render_toggle_switch( 'uwb_media_add_missing_sizes', 'Add Missing Sizes', 'Automatically add width and height attributes to images.' );
                                $this->render_textarea_setting( 'uwb_media_lazy_load_excludes', 'Lazy Load Image Excludes', "/wp-content/uploads/logo.png\nimage-class-name", 'URLs or class names of images to exclude from lazy loading (one per line).' );
                                $this->render_textarea_setting( 'uwb_media_lazy_load_class_excludes', 'Lazy Load Class Excludes', 'skip-lazy', 'CSS class names of images or containers to exclude from lazy loading (one per line).' );

                                $this->render_cdn_distribution_card(
                                    'Cloudflare R2 / S3 CDN Distribution for Media Library & Files',
                                    'uwb_cdn_distribute_media',
                                    'Phân phối Media Library & Tập tin qua S3 CDN?',
                                    'Đồng bộ và phân phối toàn bộ tập tin hình ảnh, tài liệu và thumbnails trong thư viện Media WordPress qua S3/R2 CDN.',
                                    array(
                                        'Upload to S3 when:' => array(
                                            'uwb_cdn_auto_upload_attachment'     => 'upload',
                                            'uwb_cdn_auto_rewrite_attachment_url' => 'get_url',
                                        ),
                                        'Update file to S3 when:' => array(
                                            'uwb_cdn_auto_update_attachment'     => 'edit',
                                        ),
                                        'Delete From S3 when:' => array(
                                            'uwb_cdn_auto_delete_attachment'     => 'Delete file',
                                        ),
                                        'Delete From Local when:' => array(
                                            'uwb_cdn_delete_local'               => 'Uploaded to S3',
                                        ),
                                    )
                                );
                                ?>
                            </div>

                            <!-- SUB-TAB 5: Font Optimization -->
                            <div id="subtab-opt_font" class="uwb-subtab-content">
                                <?php
                                $this->render_toggle_switch( 'uwb_css_font_display_opt', 'Font Display Swap', 'Automatically injects <code>font-display: swap</code> into all CSS <code>@font-face</code> declarations (including theme icon fonts like <code>fl-icons.woff2</code>) and Google Fonts to ensure text remains visible during font load and pass PageSpeed Insights audit.' );
                                $this->render_textarea_setting(
                                    'uwb_preload_fonts',
                                    'Preload Key Fonts',
                                    "/wp-content/themes/flatsome/assets/css/icons/fl-icons.woff2?v=3.20.7\n/wp-content/themes/your-theme/fonts/font.woff2",
                                    'Inject <code>&lt;link rel="preload" as="font" crossorigin&gt;</code> tags to prioritize critical font loading and eliminate FOUT/FOIT. One font URL per line (.woff2 recommended).'
                                );
                                $this->render_textarea_setting(
                                    'uwb_preconnect_domains',
                                    'Preconnect External Font Domains',
                                    "https://fonts.googleapis.com\nhttps://fonts.gstatic.com",
                                    'Inject <code>&lt;link rel="preconnect"&gt;</code> tags into HTML <code>&lt;head&gt;</code> to preconnect external font domains (one per line).'
                                );
                                $this->render_toggle_switch( 'uwb_html_remove_gfonts', 'Remove Google Fonts', 'Completely remove Google Fonts requests to improve page load speed and privacy compliance.' );

                                $this->render_cdn_distribution_card(
                                    'Cloudflare R2 / S3 CDN Distribution for Web Fonts',
                                    'uwb_cdn_distribute_font',
                                    'Phân phối Web Fonts qua S3 CDN?',
                                    'Tải và phục vụ các font chữ tùy chỉnh (.woff2, .woff, .ttf) của theme từ S3/R2 CDN.',
                                    array(
                                        'Upload to S3 when:' => array(
                                            'uwb_cdn_auto_upload_fonts'     => 'upload',
                                            'uwb_cdn_auto_rewrite_font_urls' => 'get_url',
                                        ),
                                    )
                                );
                                ?>
                            </div>

                            <!-- SUB-TAB 6: CDN Offload Media -->
                            <div id="subtab-opt_cdn_media" class="uwb-subtab-content">
                                <!-- Section 1: Provider Settings -->
                                <div style="background:#f8fafc; border:1px solid var(--uwb-border); border-radius:12px; padding:24px; margin-bottom:24px;">
                                    <h3 style="margin-top:0; margin-bottom:16px; font-size:15px; display:flex; align-items:center; gap:8px;">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 10h-1.26A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10z"/></svg>
                                        Provider Settings &amp; Credentials
                                    </h3>
                                    <p style="font-size:13px; color:var(--uwb-text-muted); margin-bottom:20px;">Configure your Cloudflare R2 or S3-Compatible storage connection settings.</p>

                                    <div class="uwb-form-group" style="margin-bottom:20px;">
                                        <label for="uwb_cdn_provider">CDN Storage Provider</label>
                                        <select name="uwb_cdn_provider" id="uwb_cdn_provider" style="width:100%; border:1px solid var(--uwb-border); border-radius:8px; padding:12px; font-size:14px; background:#fff;">
                                            <option value="cloudflare_r2" <?php selected( get_option( 'uwb_cdn_provider', 'cloudflare_r2' ), 'cloudflare_r2' ); ?>>Cloudflare R2 Storage (Recommended)</option>
                                            <option value="other_s3" <?php selected( get_option( 'uwb_cdn_provider', 'cloudflare_r2' ), 'other_s3' ); ?>>Other S3 Compatible Storage (AWS S3, Wasabi, DigitalOcean Spaces, MinIO, Bunny S3)</option>
                                        </select>
                                        <p class="description uwb-cdn-cf-guide" style="margin-top:8px; font-size:12.5px; <?php echo get_option( 'uwb_cdn_provider', 'cloudflare_r2' ) === 'cloudflare_r2' ? '' : 'display:none;'; ?>">
                                            📖 <strong>Hướng dẫn lấy thông số Cloudflare R2:</strong>
                                            <a href="https://developers.cloudflare.com/r2/api/s3/tokens/" target="_blank" rel="noopener noreferrer" style="color:var(--uwb-primary); font-weight:600; text-decoration:underline;">
                                                Xem hướng dẫn tạo R2 API Tokens (Access Key &amp; Secret Key) &amp; Account ID trên Cloudflare Docs &rarr;
                                            </a>
                                        </p>
                                    </div>

                                    <!-- Cloudflare Account ID (CF R2 only) -->
                                    <div class="uwb-form-group uwb-cdn-cf-field" style="margin-bottom:20px; <?php echo get_option( 'uwb_cdn_provider', 'cloudflare_r2' ) === 'cloudflare_r2' ? '' : 'display:none;'; ?>">
                                        <label for="uwb_cdn_account_id">Cloudflare Account ID</label>
                                        <input type="text" name="uwb_cdn_account_id" id="uwb_cdn_account_id" value="<?php echo esc_attr( get_option( 'uwb_cdn_account_id', '' ) ); ?>" placeholder="e.g. 56a84f3c7e0b9d123456789abcdef012" style="width:100%; padding:12px; border:1px solid var(--uwb-border); border-radius:8px; font-size:13.5px;" />
                                        <p class="description">Your Cloudflare Account ID found in your Cloudflare Dashboard URL or R2 overview.</p>
                                    </div>

                                    <!-- Endpoint URL (Other S3 only) -->
                                    <div class="uwb-form-group uwb-cdn-s3-field" style="margin-bottom:20px; <?php echo get_option( 'uwb_cdn_provider', 'cloudflare_r2' ) === 'other_s3' ? '' : 'display:none;'; ?>">
                                        <label for="uwb_cdn_endpoint">S3 Endpoint URL</label>
                                        <input type="url" name="uwb_cdn_endpoint" id="uwb_cdn_endpoint" value="<?php echo esc_attr( get_option( 'uwb_cdn_endpoint', '' ) ); ?>" placeholder="e.g. https://s3.wasabisys.com or https://ams3.digitaloceanspaces.com" style="width:100%; padding:12px; border:1px solid var(--uwb-border); border-radius:8px; font-size:13.5px;" />
                                        <p class="description">The REST API Endpoint of your S3 compatible provider.</p>
                                    </div>

                                    <!-- Region (Other S3 only) -->
                                    <div class="uwb-form-group uwb-cdn-s3-field" style="margin-bottom:20px; <?php echo get_option( 'uwb_cdn_provider', 'cloudflare_r2' ) === 'other_s3' ? '' : 'display:none;'; ?>">
                                        <label for="uwb_cdn_region">S3 Region</label>
                                        <input type="text" name="uwb_cdn_region" id="uwb_cdn_region" value="<?php echo esc_attr( get_option( 'uwb_cdn_region', 'auto' ) ); ?>" placeholder="e.g. us-east-1, us-west-1, ams3" style="width:100%; padding:12px; border:1px solid var(--uwb-border); border-radius:8px; font-size:13.5px;" />
                                    </div>

                                    <!-- Access Key & Secret Key Grid -->
                                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px;">
                                        <div class="uwb-form-group">
                                            <label for="uwb_cdn_access_key">Access Key ID</label>
                                            <input type="text" name="uwb_cdn_access_key" id="uwb_cdn_access_key" value="<?php echo esc_attr( get_option( 'uwb_cdn_access_key', '' ) ); ?>" placeholder="Access Key ID" style="width:100%; padding:12px; border:1px solid var(--uwb-border); border-radius:8px; font-size:13.5px;" />
                                        </div>
                                        <div class="uwb-form-group">
                                            <label for="uwb_cdn_secret_key">Secret Access Key</label>
                                            <input type="password" name="uwb_cdn_secret_key" id="uwb_cdn_secret_key" value="<?php echo esc_attr( get_option( 'uwb_cdn_secret_key', '' ) ); ?>" placeholder="Secret Access Key" style="width:100%; padding:12px; border:1px solid var(--uwb-border); border-radius:8px; font-size:13.5px;" />
                                        </div>
                                    </div>

                                    <!-- Bucket Name -->
                                    <div class="uwb-form-group" style="margin-bottom:20px;">
                                        <label for="uwb_cdn_bucket">Bucket Name</label>
                                        <input type="text" name="uwb_cdn_bucket" id="uwb_cdn_bucket" value="<?php echo esc_attr( get_option( 'uwb_cdn_bucket', '' ) ); ?>" placeholder="e.g. my-website-assets" style="width:100%; padding:12px; border:1px solid var(--uwb-border); border-radius:8px; font-size:13.5px;" />
                                    </div>

                                    <!-- Custom CDN Domain / CNAME -->
                                    <div class="uwb-form-group" style="margin-bottom:20px;">
                                        <label for="uwb_cdn_custom_domain">CDN Custom Domain / CNAME URL</label>
                                        <input type="url" name="uwb_cdn_custom_domain" id="uwb_cdn_custom_domain" value="<?php echo esc_attr( get_option( 'uwb_cdn_custom_domain', '' ) ); ?>" placeholder="e.g. https://cdn.mysite.com or https://pub-xxx.r2.dev" style="width:100%; padding:12px; border:1px solid var(--uwb-border); border-radius:8px; font-size:13.5px;" />
                                        <p class="description">The public URL domain used to rewrite and serve static assets to website visitors.</p>
                                    </div>

                                    <!-- Test Connection Button -->
                                    <div style="margin-top:16px;">
                                        <button type="button" id="btn-test-cdn-connection" class="button button-secondary" style="padding:10px 18px; font-weight:600; height:auto; border-radius:8px; cursor:pointer;">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle; margin-right:4px;"><polyline points="20 6 9 17 4 12"/></svg>
                                            Test CDN Connection
                                        </button>
                                        <div id="uwb-cdn-test-result" style="margin-top:12px; display:none;"></div>
                                    </div>
                                </div>

                                <!-- Section 2: File Types & Offloading Rules -->
                                <div style="background:#f8fafc; border:1px solid var(--uwb-border); border-radius:12px; padding:24px; margin-bottom:24px;">
                                    <h3 style="margin-top:0; margin-bottom:16px; font-size:15px; display:flex; align-items:center; gap:8px;">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                        File Types &amp; URL Rewriting Rules
                                    </h3>

                                    <?php $this->render_toggle_switch( 'uwb_cdn_enabled', 'Enable CDN Static Asset Offloading & URL Rewriter', 'Automatically rewrite static asset URLs in HTML output to serve from CDN Domain.' ); ?>

                                    <h4 style="margin:20px 0 12px 0; font-size:13.5px; font-weight:700; color:var(--uwb-text);">Select File Types to Serve via CDN</h4>
                                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:12px; margin-bottom:20px; background:#fff; padding:16px; border:1px solid var(--uwb-border); border-radius:8px;">
                                        <label style="display:flex; align-items:center; gap:8px; font-size:13px; font-weight:600; cursor:pointer;">
                                            <input type="checkbox" name="uwb_cdn_file_types_images" value="1" <?php checked( get_option( 'uwb_cdn_file_types_images', 1 ), 1 ); ?> />
                                            Images (.jpg, .png, .webp, .svg, .gif)
                                        </label>
                                        <label style="display:flex; align-items:center; gap:8px; font-size:13px; font-weight:600; cursor:pointer;">
                                            <input type="checkbox" name="uwb_cdn_file_types_css" value="1" <?php checked( get_option( 'uwb_cdn_file_types_css', 1 ), 1 ); ?> />
                                            CSS Stylesheets (.css)
                                        </label>
                                        <label style="display:flex; align-items:center; gap:8px; font-size:13px; font-weight:600; cursor:pointer;">
                                            <input type="checkbox" name="uwb_cdn_file_types_js" value="1" <?php checked( get_option( 'uwb_cdn_file_types_js', 1 ), 1 ); ?> />
                                            JavaScript (.js)
                                        </label>
                                        <label style="display:flex; align-items:center; gap:8px; font-size:13px; font-weight:600; cursor:pointer;">
                                            <input type="checkbox" name="uwb_cdn_file_types_fonts" value="1" <?php checked( get_option( 'uwb_cdn_file_types_fonts', 1 ), 1 ); ?> />
                                            Fonts (.woff2, .woff, .ttf)
                                        </label>
                                        <label style="display:flex; align-items:center; gap:8px; font-size:13px; font-weight:600; cursor:pointer;">
                                            <input type="checkbox" name="uwb_cdn_file_types_media" value="1" <?php checked( get_option( 'uwb_cdn_file_types_media', 0 ), 1 ); ?> />
                                            Media &amp; Docs (.mp4, .pdf, .zip)
                                        </label>
                                    </div>

                                    <div class="uwb-form-group" style="margin-bottom:20px;">
                                        <label for="uwb_cdn_cache_control">Object Cache-Control Header</label>
                                        <input type="text" name="uwb_cdn_cache_control" id="uwb_cdn_cache_control" value="<?php echo esc_attr( get_option( 'uwb_cdn_cache_control', 'public, max-age=31536000, immutable' ) ); ?>" style="width:100%; padding:12px; border:1px solid var(--uwb-border); border-radius:8px; font-size:13.5px;" />
                                        <p class="description">Cache-Control header set on uploaded S3/R2 objects.</p>
                                    </div>
                                </div>

                                <!-- Section 3: Event Handling & Batch Sync Tools -->
                                <div style="background:#f8fafc; border:1px solid var(--uwb-border); border-radius:12px; padding:24px;">
                                    <h3 style="margin-top:0; margin-bottom:16px; font-size:15px; display:flex; align-items:center; gap:8px;">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                        Event Handling &amp; Sync Tools
                                    </h3>

                                    <div style="display:flex; flex-direction:column; gap:12px; margin-bottom:24px; background:#fff; padding:16px; border:1px solid var(--uwb-border); border-radius:8px;">
                                        <label style="display:flex; align-items:center; gap:8px; font-size:13px; font-weight:600; cursor:pointer;">
                                            <input type="checkbox" name="uwb_cdn_auto_upload" value="1" <?php checked( get_option( 'uwb_cdn_auto_upload', 1 ), 1 ); ?> />
                                            Auto-upload new Media Library files to CDN (<code>add_attachment</code> hook)
                                        </label>
                                        <label style="display:flex; align-items:center; gap:8px; font-size:13px; font-weight:600; cursor:pointer;">
                                            <input type="checkbox" name="uwb_cdn_auto_upload_combined" value="1" <?php checked( get_option( 'uwb_cdn_auto_upload_combined', 1 ), 1 ); ?> />
                                            Auto upload to CDN after minify or combine (automatically sync minified/combined CSS &amp; JS files to CDN)
                                        </label>
                                        <label style="display:flex; align-items:center; gap:8px; font-size:13px; font-weight:600; cursor:pointer;">
                                            <input type="checkbox" name="uwb_cdn_auto_purge_minified" value="1" <?php checked( get_option( 'uwb_cdn_auto_purge_minified', 1 ), 1 ); ?> />
                                            Auto-purge combined &amp; minified cache files from CDN when clearing cache (keep CDN cache synchronized)
                                        </label>
                                        <label style="display:flex; align-items:center; gap:8px; font-size:13px; font-weight:600; cursor:pointer;">
                                            <input type="checkbox" name="uwb_cdn_auto_delete" value="1" <?php checked( get_option( 'uwb_cdn_auto_delete', 1 ), 1 ); ?> />
                                            Auto-delete files from CDN when deleted in Media Library (<code>delete_attachment</code> hook)
                                        </label>
                                        <label style="display:flex; align-items:center; gap:8px; font-size:13px; font-weight:600; cursor:pointer;">
                                            <input type="checkbox" name="uwb_cdn_delete_local" value="1" <?php checked( get_option( 'uwb_cdn_delete_local', 0 ), 1 ); ?> />
                                            Delete local server files after offloading to CDN (Offload storage mode)
                                        </label>
                                    </div>

                                    <div style="border-top:1px solid var(--uwb-border); padding-top:20px;">
                                        <h4 style="margin:0 0 8px 0; font-size:14px; font-weight:700; color:var(--uwb-text);">Batch Sync Media Library to CDN</h4>
                                        <p style="font-size:12.5px; color:var(--uwb-text-muted); margin-bottom:16px;">Bulk upload all existing media library files and thumbnails to your configured S3/R2 bucket.</p>

                                        <button type="button" id="btn-sync-media-cdn" class="button button-primary" style="background:var(--uwb-primary); border-color:var(--uwb-primary); padding:10px 20px; height:auto; border-radius:8px; font-weight:600; cursor:pointer;">
                                            Sync Existing Media Library to CDN
                                        </button>

                                        <div id="uwb-sync-cdn-progress-wrap" style="margin-top:16px; display:none;">
                                            <div class="uwb-progress-bar-wrap" style="margin-bottom:8px;">
                                                <div class="uwb-progress-bar-fill" id="uwb-sync-cdn-progress-fill" style="width:0%;"></div>
                                            </div>
                                            <div id="uwb-sync-cdn-status-text" style="font-size:12.5px; font-weight:600; color:var(--uwb-text);">Initializing batch sync...</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- TAB 5: Dashboard -->
                        <div id="tab-url_status" class="uwb-tab-content active">
                            <h2 style="margin-top:0;">Dashboard</h2>
                            <!-- Horizontal Cache Pipeline Widget -->
                            <?php
                            // 1. Opcode Cache
                            $opcode_active = false;
                            $opcode_details = 'OPcache is not active or enabled.';
                            if ( function_exists( 'opcache_get_status' ) ) {
                                $opcache_status = @opcache_get_status( false );
                                if ( ! empty( $opcache_status['opcache_enabled'] ) ) {
                                    $opcode_active = true;
                                    if ( isset( $opcache_status['memory_usage']['used_memory'] ) && isset( $opcache_status['memory_usage']['free_memory'] ) ) {
                                        $used = round( $opcache_status['memory_usage']['used_memory'] / 1024 / 1024, 1 );
                                        $free = round( $opcache_status['memory_usage']['free_memory'] / 1024 / 1024, 1 );
                                        $opcode_details = "OPcache Active ({$used}MB used, {$free}MB free)";
                                    } else {
                                        $opcode_details = 'OPcache Active';
                                    }
                                }
                            }

                            // 2. Object Cache
                            $obj_active = wp_using_ext_object_cache();
                            $obj_details = 'No external persistent cache detected.';
                            if ( $obj_active ) {
                                $oc_type = intval( get_option( 'uwb_redis_enabled', 0 ) );
                                if ( $oc_type === 2 ) {
                                    $obj_details = 'Memcached Object Cache Active';
                                } else {
                                    $obj_details = 'Redis Object Cache Active';
                                }
                            }

                            // 3. Page Cache Full
                            $page_cache_active = defined( 'WP_CACHE' ) && WP_CACHE;
                            $page_cache_details = 'WP_CACHE constant is not enabled.';
                            if ( $page_cache_active ) {
                                $cache_dir = WP_CONTENT_DIR . '/cache/wp-rocket';
                                $file_count = 0;
                                if ( is_dir( $cache_dir ) ) {
                                    $di = new \RecursiveDirectoryIterator( $cache_dir, \RecursiveDirectoryIterator::SKIP_DOTS );
                                    $it = new \RecursiveIteratorIterator( $di );
                                    foreach ( $it as $file ) {
                                        if ( $file->isFile() && ( $file->getExtension() === 'html' || $file->getExtension() === 'html_gzip' ) ) {
                                            $file_count++;
                                        }
                                    }
                                }
                                $page_cache_details = "Page Cache Active ({$file_count} static files)";
                            }

                            // 4. CDN Cache
                            $cdn_active = ! empty( $_SERVER['HTTP_CF_RAY'] ) || ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) || ! empty( $_SERVER['HTTP_X_CDN_FORWARD'] );
                            if ( $cdn_active ) {
                                $cdn_details = 'Cloudflare / CDN Proxy Detected';
                            } else {
                                $cdn_details = 'No active CDN proxy header detected.';
                            }

                            // 5. Browser Cache
                            $browser_active = intval( get_option( 'uwb_browser_cache_enabled', 1 ) ) === 1;
                            if ( $browser_active ) {
                                $browser_lifespan = intval( get_option( 'uwb_browser_cache_lifespan', 10 ) );
                                $browser_details = "Browser cache enabled ({$browser_lifespan} minutes)";
                            } else {
                                $browser_details = 'Local browser caching is disabled.';
                            }

                            // 6. DNS Cache
                            $dns_active = true;
                            $dns_details = 'DNS resolution is cached at OS/Browser/ISP level.';
                            ?>
                            
                            <div class="uwb-pipeline-container">
                                <h3 style="margin-top:0; font-size:15px; display:flex; align-items:center; gap:8px; margin-bottom: 20px;">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
                                    Cấu Hình Chuỗi Cache Xử Lý (Cache Pipeline)
                                </h3>
                                
                                <div class="uwb-pipeline-tree">
                                    <!-- Node 1: DNS Cache -->
                                    <div class="uwb-tree-node active">
                                        <div class="node-status-left"></div>
                                        <div class="node-info-mid">
                                            <div class="node-icon-wrap">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                                            </div>
                                            <div class="node-text-wrap">
                                                <span class="node-title">1. DNS Cache</span>
                                                <span class="node-desc"><?php echo esc_html($dns_details); ?></span>
                                            </div>
                                        </div>
                                        <div class="node-action-right">
                                            <button type="button" onclick="window.location.reload();" class="uwb-btn-mini">Retest</button>
                                        </div>
                                    </div>
                                    
                                    <!-- Node 2: Trình duyệt cache -->
                                    <div class="uwb-tree-node <?php echo $browser_active ? 'active' : 'inactive'; ?>">
                                        <div class="node-status-left"></div>
                                        <div class="node-info-mid">
                                            <div class="node-icon-wrap">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                                            </div>
                                            <div class="node-text-wrap">
                                                <span class="node-title">2. Trình duyệt cache (Browser Cache)</span>
                                                <span class="node-desc"><?php echo esc_html($browser_details); ?></span>
                                            </div>
                                        </div>
                                        <div class="node-action-right">
                                            <button type="button" onclick="jQuery('.uwb-nav-item[data-tab=\'cache_settings\']').trigger('click'); jQuery('.uwb-sub-tab-item[data-subtab=\'browser_cache\']').trigger('click');" class="uwb-btn-mini">Settings</button>
                                        </div>
                                    </div>
                                    
                                    <!-- Node 3: CDN Cache -->
                                    <div class="uwb-tree-node <?php echo $cdn_active ? 'active' : 'inactive'; ?>">
                                        <div class="node-status-left"></div>
                                        <div class="node-info-mid">
                                            <div class="node-icon-wrap">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 10h-1.26A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10z"/></svg>
                                            </div>
                                            <div class="node-text-wrap">
                                                <span class="node-title">3. CDN Cache</span>
                                                <span class="node-desc"><?php echo esc_html($cdn_details); ?></span>
                                            </div>
                                        </div>
                                        <div class="node-action-right" style="display:flex; gap:6px;">
                                            <button type="button" onclick="jQuery('.uwb-nav-item[data-tab=\'page_optimizes\']').trigger('click'); jQuery('.uwb-sub-tab-item[data-subtab=\'opt_cdn_media\']').trigger('click');" class="uwb-btn-mini">Settings</button>
                                            <button type="button" onclick="window.location.reload();" class="uwb-btn-mini">Retest</button>
                                        </div>
                                    </div>
                                    
                                    <!-- Node 4: Webserver Cache -->
                                    <div class="uwb-tree-node <?php echo $webserver_active ? 'active' : 'inactive'; ?>">
                                        <div class="node-status-left"></div>
                                        <div class="node-info-mid">
                                            <div class="node-icon-wrap">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="2" y="2" width="20" height="8" rx="2" ry="2"/><rect x="2" y="14" width="20" height="8" rx="2" ry="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>
                                            </div>
                                            <div class="node-text-wrap">
                                                <span class="node-title">4. Webserver Cache</span>
                                                <span class="node-desc"><?php echo esc_html($webserver_details); ?></span>
                                            </div>
                                        </div>
                                        <div class="node-action-right" style="display:flex; gap:6px;">
                                            <button type="button" onclick="jQuery('.uwb-nav-item[data-tab=\'cache_settings\']').trigger('click'); jQuery('.uwb-sub-tab-item[data-subtab=\'webserver_cache\']').trigger('click');" class="uwb-btn-mini">Settings</button>
                                            <button type="button" onclick="window.location.reload();" class="uwb-btn-mini">Retest</button>
                                        </div>
                                    </div>
 
                                    <!-- Node 5: Page Cache Full -->
                                    <div class="uwb-tree-node <?php echo $page_cache_active ? 'active' : 'inactive'; ?>">
                                        <div class="node-status-left"></div>
                                        <div class="node-info-mid">
                                            <div class="node-icon-wrap">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                            </div>
                                            <div class="node-text-wrap">
                                                <span class="node-title">5. Page Cache Full (Static HTML)</span>
                                                <span class="node-desc"><?php echo esc_html($page_cache_details); ?></span>
                                            </div>
                                        </div>
                                        <div class="node-action-right" style="display:flex; gap:6px;">
                                            <button type="button" onclick="jQuery('.uwb-nav-item[data-tab=\'cache_settings\']').trigger('click'); jQuery('.uwb-sub-tab-item[data-subtab=\'page_cache\']').trigger('click');" class="uwb-btn-mini">Settings</button>
                                            <a href="<?php echo $purge_url; ?>" class="uwb-btn-mini uwb-btn-mini-danger" style="text-decoration:none;">Purge Cache</a>
                                        </div>
                                    </div>
 
                                    <!-- Node 6: Object Cache -->
                                    <div class="uwb-tree-node <?php echo $obj_active ? 'active' : 'inactive'; ?>">
                                        <div class="node-status-left"></div>
                                        <div class="node-info-mid">
                                            <div class="node-icon-wrap">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v6c0 1.66 4 3 9 3s9-1.34 9-3V5"/><path d="M3 11v6c0 1.66 4 3 9 3s9-1.34 9-3v-6"/></svg>
                                            </div>
                                            <div class="node-text-wrap">
                                                <span class="node-title">6. Object Cache (Redis/Memcached)</span>
                                                <span class="node-desc"><?php echo esc_html($obj_details); ?></span>
                                            </div>
                                        </div>
                                        <div class="node-action-right" style="display:flex; gap:6px;">
                                            <button type="button" onclick="jQuery('.uwb-nav-item[data-tab=\'cache_settings\']').trigger('click'); jQuery('.uwb-sub-tab-item[data-subtab=\'object_cache\']').trigger('click');" class="uwb-btn-mini">Settings</button>
                                            <button type="button" id="btn-flush-redis-tree" class="uwb-btn-mini uwb-btn-mini-danger">Flush Cache</button>
                                        </div>
                                    </div>
 
                                    <!-- Node 7: Opcode Cache -->
                                    <div class="uwb-tree-node <?php echo $opcode_active ? 'active' : 'inactive'; ?>">
                                        <div class="node-status-left"></div>
                                        <div class="node-info-mid">
                                            <div class="node-icon-wrap">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
                                            </div>
                                            <div class="node-text-wrap">
                                                <span class="node-title">7. Opcode Cache (PHP OPcache)</span>
                                                <span class="node-desc"><?php echo esc_html($opcode_details); ?></span>
                                            </div>
                                        </div>
                                        <div class="node-action-right" style="display:flex; gap:6px;">
                                            <button type="button" onclick="jQuery('.uwb-nav-item[data-tab=\'cache_settings\']').trigger('click'); jQuery('.uwb-sub-tab-item[data-subtab=\'opcache\']').trigger('click');" class="uwb-btn-mini">Settings</button>
                                            <button type="button" onclick="window.location.reload();" class="uwb-btn-mini">Retest</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div style="display:grid; grid-template-columns: 1fr; gap:20px; margin-bottom:20px;">

                                <!-- Cron Preloader Status Block -->
                                <div style="background:#f8fafc; border:1px solid var(--uwb-border); border-radius:12px; padding:24px; display:flex; flex-direction:column; justify-content:space-between;">
                                    <div>
                                        <h3 style="margin-top:0; font-size:15px; display:flex; align-items:center; gap:8px;">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                            Cron Preloader Status
                                        </h3>
                                        <div style="margin-top:12px; font-size:13px; line-height:1.5;">
                                            <?php
                                            $preload_mode = intval( get_option( 'uwb_preload_enabled', 0 ) );
                                            $last_run = get_option( 'uwb_preload_last_run_time', '' );
                                            $last_urls = get_option( 'uwb_preload_last_run_urls', array() );

                                            // Determine Badge and Next Run info
                                            if ( $preload_mode === 0 ) {
                                                $badge_html = '<div style="display:inline-flex; align-items:center; gap:8px; background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; padding:4px 10px; border-radius:6px; font-weight:700; font-size:11px;"><span style="width:6px;height:6px;background:#ef4444;border-radius:50%;display:inline-block;"></span> Disabled</div>';
                                                $next_run_html = '<span style="color:var(--uwb-text-muted);">None (Preload is disabled)</span>';
                                            } elseif ( $preload_mode === 1 ) {
                                                $badge_html = '<div style="display:inline-flex; align-items:center; gap:8px; background:#d1fae5; color:#065f46; border:1px solid #6ee7b7; padding:4px 10px; border-radius:6px; font-weight:700; font-size:11px;"><span style="width:6px;height:6px;background:#10b981;border-radius:50%;display:inline-block;"></span> Enabled (WP-Cron)</div>';
                                                $next_timestamp = wp_next_scheduled( 'uwb_preload_cron_job' );
                                                if ( $next_timestamp ) {
                                                    $next_run_time = function_exists( 'wp_date' ) ? wp_date( 'Y-m-d H:i:s', $next_timestamp ) : date_i18n( 'Y-m-d H:i:s', $next_timestamp );
                                                    $next_run_html = '<strong>' . esc_html( $next_run_time ) . '</strong>';
                                                } else {
                                                    $next_run_html = '<span style="color:#b45309; font-weight:600;">Not scheduled / Waiting</span>';
                                                }
                                            } else {
                                                $badge_html = '<div style="display:inline-flex; align-items:center; gap:8px; background:#e0e7ff; color:#3730a3; border:1px solid #c7d2fe; padding:4px 10px; border-radius:6px; font-weight:700; font-size:11px;"><span style="width:6px;height:6px;background:#6366f1;border-radius:50%;display:inline-block;"></span> Enabled (Custom Cron)</div>';
                                                $next_run_html = '<span style="color:#4f46e5; font-weight:600;">Managed by server crontab</span>';
                                            }
                                            ?>
                                            <p style="margin:0 0 10px 0;"><strong>Active Mode:</strong> <?php echo $badge_html; ?></p>
                                            <p style="margin:0 0 10px 0;"><strong>Last Run:</strong> <code><?php echo ! empty( $last_run ) ? esc_html( $last_run ) : 'Never'; ?></code></p>
                                            <p style="margin:0 0 10px 0;"><strong>Next Scheduled:</strong> <?php echo $next_run_html; ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            

                            <!-- Preload status and last processed URLs grid -->
                            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap:20px; margin-bottom:24px;">
                                <!-- Left Column: Preloading Queue Status -->
                                <div class="uwb-preload-status-box" style="margin-bottom:0; display:flex; flex-direction:column; justify-content:space-between;">
                                    <h3 style="margin-top:0; color:var(--uwb-text); font-size:15px; display:flex; align-items:center; gap:8px;">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                                        Preloading Queue Status
                                    </h3>
                                    
                                    <div class="uwb-stats-grid" style="margin-top:12px; margin-bottom: 16px;">
                                        <div class="uwb-stat-card uwb-stat-pending" style="padding:12px 8px;">
                                            <div class="num" id="queue-pending" style="font-size:22px;">-</div>
                                            <div class="label" style="font-size:10px;">Pending</div>
                                        </div>
                                        <div class="uwb-stat-card uwb-stat-processing" style="padding:12px 8px;">
                                            <div class="num" id="queue-processing" style="font-size:22px;">-</div>
                                            <div class="label" style="font-size:10px;">Processing</div>
                                        </div>
                                        <div class="uwb-stat-card uwb-stat-completed" style="padding:12px 8px;">
                                            <div class="num" id="queue-completed" style="font-size:22px;">-</div>
                                            <div class="label" style="font-size:10px;">Completed</div>
                                        </div>
                                        <div class="uwb-stat-card uwb-stat-failed" style="padding:12px 8px;">
                                            <div class="num" id="queue-failed" style="font-size:22px;">-</div>
                                            <div class="label" style="font-size:10px;">Failed</div>
                                        </div>
                                    </div>

                                    <div class="uwb-progress-bar-wrap" style="margin-bottom: 8px;">
                                        <div class="uwb-progress-bar-fill" id="preload-progress-fill"></div>
                                    </div>
                                    
                                    <div class="uwb-progress-text" style="margin-bottom: 16px;">
                                        <span id="preload-progress-pct" style="font-weight:600;">Progress: 0%</span>
                                        <span id="preload-progress-nums">0 / 0 URLs</span>
                                    </div>

                                    <div class="uwb-preload-actions" style="margin-top:auto; display:flex; gap:10px;">
                                        <button type="button" id="btn-start-preload" class="uwb-btn-action uwb-btn-start" style="padding: 10px 16px; font-size:12.5px; flex:1; display:inline-flex; align-items:center; justify-content:center; gap:6px;">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                                            Start Preload
                                        </button>
                                        <button type="button" id="btn-stop-preload" class="uwb-btn-action uwb-btn-stop" style="padding: 10px 16px; font-size:12.5px; flex:1; display:none; align-items:center; justify-content:center; gap:6px;">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>
                                            Pause Preload
                                        </button>
                                        <button type="button" id="btn-clear-preload" class="uwb-btn-action uwb-btn-clear" style="padding: 10px 16px; font-size:12.5px;">
                                            Clear Queue
                                        </button>
                                    </div>
                                </div>

                                <!-- Right Column: Last Processed URLs -->
                                <div style="background:#f8fafc; border:1px solid var(--uwb-border); border-radius:12px; padding:24px; display:flex; flex-direction:column; justify-content:space-between;">
                                    <div>
                                        <h3 style="margin-top:0; font-size:15px; display:flex; align-items:center; gap:8px;">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                            Last Processed URLs
                                        </h3>
                                        
                                        <?php
                                        $last_urls = get_option( 'uwb_preload_last_run_urls', array() );
                                        if ( ! empty( $last_urls ) && is_array( $last_urls ) ) :
                                        ?>
                                            <div style="overflow-y:auto; max-height:165px; border:1px solid var(--uwb-border); border-radius:8px; background:#fff; margin-top:12px;">
                                                <table style="width:100%; border-collapse:collapse; font-size:11.5px; text-align:left;">
                                                    <thead>
                                                        <tr style="background:#f1f5f9; border-bottom:1px solid var(--uwb-border); position:sticky; top:0; z-index:10;">
                                                            <th style="padding:8px 10px; font-weight:700; color:var(--uwb-text);">URL Path</th>
                                                            <th style="padding:8px 10px; font-weight:700; color:var(--uwb-text); text-align:center; width:70px;">Status</th>
                                                            <th style="padding:8px 10px; font-weight:700; color:var(--uwb-text); text-align:center; width:120px;">Time</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ( array_slice( $last_urls, 0, 10 ) as $url_info ) : 
                                                            $status_badge = '';
                                                            if ( $url_info['status'] === 'completed' ) {
                                                                $status_badge = '<span style="color:#059669; font-weight:800; font-size:10px; text-transform:uppercase;">✓ Success</span>';
                                                            } else {
                                                                $status_badge = '<span style="color:#dc2626; font-weight:800; font-size:10px; text-transform:uppercase;">✗ Failed</span>';
                                                            }
                                                            $url_path = wp_parse_url( $url_info['url'], PHP_URL_PATH );
                                                            $url_query = wp_parse_url( $url_info['url'], PHP_URL_QUERY );
                                                            $display_path = '/' . trim( $url_path, '/' ) . ( $url_query ? '?' . $url_query : '' );
                                                            if ( strlen( $display_path ) > 35 ) {
                                                                $display_path = substr( $display_path, 0, 32 ) . '...';
                                                            }
                                                            $time_display = isset( $url_info['time'] ) ? $url_info['time'] : '';
                                                        ?>
                                                            <tr style="border-bottom:1px solid #f1f5f9;">
                                                                <td style="padding:8px 10px; font-family:monospace; white-space:nowrap; max-width:180px; overflow:hidden; text-overflow:ellipsis;" title="<?php echo esc_attr( $url_info['url'] ); ?>">
                                                                    <a href="<?php echo esc_url( $url_info['url'] ); ?>" target="_blank" style="text-decoration:none; color:var(--uwb-primary);"><?php echo esc_html( $display_path ); ?></a>
                                                                </td>
                                                                <td style="padding:8px 10px; text-align:center;"><?php echo $status_badge; ?></td>
                                                                <td style="padding:8px 10px; text-align:center; color:var(--uwb-text-muted); font-size:10.5px;"><?php echo esc_html( $time_display ); ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php else : ?>
                                            <div style="background:#fff; border:1px solid var(--uwb-border); border-radius:8px; padding:24px; text-align:center; color:var(--uwb-text-muted); font-style:italic; margin-top:12px;">
                                                No URLs processed yet.
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Toolbar -->
                            <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap; margin-bottom:16px;">
                                <input type="text" id="uwb-url-search" placeholder="Search URL..." style="border:1px solid var(--uwb-border); border-radius:8px; padding:9px 12px; font-size:13px; flex:1; min-width:180px; max-width:320px;" />
                                <div style="display:flex; gap:6px; flex-wrap:wrap;">
                                    <button type="button" class="uwb-filter-btn active" data-status="" style="border:1px solid var(--uwb-border); background:#f1f5f9; border-radius:6px; padding:7px 14px; font-size:12.5px; font-weight:600; cursor:pointer;">All</button>
                                    <button type="button" class="uwb-filter-btn" data-status="pending" style="border:1px solid #fcd34d; background:#fef9c3; color:#92400e; border-radius:6px; padding:7px 14px; font-size:12.5px; font-weight:600; cursor:pointer;">Pending</button>
                                    <button type="button" class="uwb-filter-btn" data-status="processing" style="border:1px solid #93c5fd; background:#dbeafe; color:#1e40af; border-radius:6px; padding:7px 14px; font-size:12.5px; font-weight:600; cursor:pointer;">Processing</button>
                                    <button type="button" class="uwb-filter-btn" data-status="completed" style="border:1px solid #6ee7b7; background:#d1fae5; color:#065f46; border-radius:6px; padding:7px 14px; font-size:12.5px; font-weight:600; cursor:pointer;">Completed</button>
                                    <button type="button" class="uwb-filter-btn" data-status="failed" style="border:1px solid #fca5a5; background:#fee2e2; color:#991b1b; border-radius:6px; padding:7px 14px; font-size:12.5px; font-weight:600; cursor:pointer;">Failed</button>
                                </div>
                                <button type="button" id="uwb-filter-wc" style="border:1px solid #cbd5e1; background:#fff; color:var(--uwb-text); border-radius:6px; padding:7px 14px; font-size:12.5px; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:6px;">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                                    WooCommerce
                                </button>
                                <button type="button" id="uwb-url-refresh" style="margin-left:auto; border:1px solid var(--uwb-border); background:#fff; border-radius:6px; padding:7px 14px; font-size:12.5px; font-weight:600; cursor:pointer;">⟳ Refresh</button>
                            </div>

                            <!-- Table -->
                             <div style="overflow-x:auto; border:1px solid var(--uwb-border); border-radius:10px; width:100%;">
                                 <table id="uwb-url-table" style="width:100%; border-collapse:collapse; font-size:13px; table-layout:fixed; min-width:700px;">
                                     <thead>
                                         <tr style="background:#f8fafc; border-bottom:1px solid var(--uwb-border);">
                                             <th class="uwb-sortable" data-col="priority" style="padding:12px 14px; text-align:center; font-weight:700; cursor:pointer; user-select:none; white-space:nowrap; width:60px;">No. <span class="uwb-sort-icon">↑</span></th>
                                             <th class="uwb-sortable" data-col="url" style="padding:12px 14px; text-align:left; font-weight:700; cursor:pointer; user-select:none;">URL <span class="uwb-sort-icon">↕</span></th>
                                             <th class="uwb-sortable" data-col="status" style="padding:12px 14px; text-align:center; font-weight:700; cursor:pointer; user-select:none; white-space:nowrap; width:90px;">Status <span class="uwb-sort-icon">↕</span></th>
                                             <th class="uwb-sortable" data-col="last_attempt" style="padding:12px 14px; text-align:center; font-weight:700; cursor:pointer; user-select:none; white-space:nowrap; width:140px;">Last Attempt <span class="uwb-sort-icon">↕</span></th>
                                             <th style="padding:12px 14px; text-align:center; font-weight:700; width:250px;">Actions</th>
                                         </tr>
                                     </thead>
                                     <tbody id="uwb-url-tbody">
                                         <tr><td colspan="5" style="text-align:center; padding:32px; color:var(--uwb-text-muted);">Loading...</td></tr>
                                     </tbody>
                                 </table>
                             </div>

                            <!-- Pagination -->
                            <div id="uwb-url-pagination" style="display:flex; justify-content:space-between; align-items:center; margin-top:14px; font-size:13px; color:var(--uwb-text-muted);"></div>

                            <!-- Toast notification -->
                            <div id="uwb-url-toast" style="display:none; position:fixed; bottom:24px; right:24px; background:#1e293b; color:#fff; padding:12px 20px; border-radius:10px; font-size:13px; font-weight:600; z-index:9999; box-shadow:0 4px 20px rgba(0,0,0,0.2);"></div>
                        </div>

                        <!-- TAB 5: Advanced Tools -->
                        <div id="tab-advanced_tools" class="uwb-tab-content">
                            <h2 style="margin-top:0;">Advanced &amp; Tools</h2>
                            <p style="color:var(--uwb-text-muted); margin-bottom: 24px;">Developer settings for debugging and troubleshooting.</p>

                            <!-- Debug Mode -->
                            <div style="background:#fff8e1; border:1px solid #fcd34d; border-radius:12px; padding:24px; margin-bottom:24px;">
                                <h3 style="margin-top:0; margin-bottom:20px; font-size:15px; display:flex; align-items:center; gap:8px; color:#92400e;">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                    Developer: Debug Mode
                                </h3>
                                <p class="description" style="margin-bottom:16px; color:#92400e;"><strong>Warning:</strong> When enabled, the optimizer appends a debug log as an HTML comment to every cached page. <strong>Disable on production.</strong></p>

                                <?php $this->render_toggle_switch( 'uwb_debug_mode', 'Enable Optimizer Debug Log', 'Appends debug info as HTML comments. Use only for troubleshooting.' ); ?>
                            </div>
                        </div>

                        <!-- Form Submit (Floating Panel) -->
                        <div style="margin-top: 32px; padding-top: 24px; border-top: 1px solid var(--uwb-border); display: none; gap: 12px;" id="uwb-submit-row">
                            <input type="submit" name="submit" id="submit" class="button button-primary" style="background:var(--uwb-primary); border-color:var(--uwb-primary); padding:8px 20px; height:auto; font-weight:600; border-radius:6px; box-shadow: 0 4px 6px rgba(99, 102, 241, 0.2);" value="Save Changes" />
                        </div>
                    </form>

                    <!-- TAB 4: Import & Export Settings -->
                    <div id="tab-import_export" class="uwb-tab-content">
                        <h2 style="margin-top:0;">Import &amp; Export Settings</h2>
                        <p style="color:var(--uwb-text-muted); margin-bottom: 24px;">Export current settings to a JSON file or import settings from a previously saved JSON file.</p>
                        
                        <form method="post" action="" enctype="multipart/form-data">
                            <?php wp_nonce_field( 'uwb_import_export_action', 'uwb_import_export_nonce' ); ?>
                            
                            <div style="background:#f8fafc; border:1px solid var(--uwb-border); border-radius:12px; padding:24px; margin-bottom:24px;">
                                <h3 style="margin-top:0; margin-bottom:12px; font-size:15px; display:flex; align-items:center; gap:8px;">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                    Export Settings
                                </h3>
                                <p class="description" style="margin-bottom:16px;">Download all your current plugin settings as a <code>.json</code> file.</p>
                                <button type="submit" name="uwb_export_settings" class="button button-primary" style="background:var(--uwb-primary); border-color:var(--uwb-primary); padding:10px 20px; height:auto; border-radius:8px; font-weight:600; cursor:pointer;">Export Settings (JSON)</button>
                            </div>

                            <div style="background:#f8fafc; border:1px solid var(--uwb-border); border-radius:12px; padding:24px;">
                                <h3 style="margin-top:0; margin-bottom:12px; font-size:15px; display:flex; align-items:center; gap:8px;">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                    Import Settings
                                </h3>
                                <p class="description" style="margin-bottom:16px;">Choose a valid plugin settings <code>.json</code> file to import.</p>
                                
                                <input type="file" name="uwb_import_file" id="uwb_import_file" accept=".json" style="margin-bottom:16px; display:block;" />
                                <button type="submit" name="uwb_import_settings" class="button" style="padding:10px 20px; height:auto; border-radius:8px; font-weight:600; border:1px solid var(--uwb-border); background:#fff; cursor:pointer; color:var(--uwb-text);">Import Settings (JSON)</button>
                            </div>
                        </form>
                    </div>

                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#uwb_auto_collect_params').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#uwb-collected-params-group').slideDown();
                } else {
                    $('#uwb-collected-params-group').slideUp();
                }
            });

            // Show/hide heartbeat interval based on control mode
            $('#uwb_heartbeat_control').on('change', function() {
                if ($(this).val() === 'reduce') {
                    $('#uwb-heartbeat-interval-row').show();
                } else {
                    $('#uwb-heartbeat-interval-row').hide();
                }
            });

            // Show/hide Delay JS exclusions textarea based on toggle
            $('input[name="uwb_delay_js"]').on('change', function() {
                var enabled = $('input[name="uwb_delay_js"]:checked').val() === '1';
                $('.uwb-opt-disabled').toggle(!enabled);
            });

            // CDN Provider switch toggle (Cloudflare R2 vs Other S3)
            $('#uwb_cdn_provider').on('change', function() {
                var val = $(this).val();
                if (val === 'cloudflare_r2') {
                    $('.uwb-cdn-cf-field, .uwb-cdn-cf-guide').slideDown();
                    $('.uwb-cdn-s3-field').slideUp();
                } else {
                    $('.uwb-cdn-cf-field, .uwb-cdn-cf-guide').slideUp();
                    $('.uwb-cdn-s3-field').slideDown();
                }
            });

            // Test CDN Connection
            $('#btn-test-cdn-connection').on('click', function() {
                var $btn = $(this);
                var $res = $('#uwb-cdn-test-result');
                var nonce = '<?php echo esc_js( wp_create_nonce( "uwb_admin_nonce" ) ); ?>';

                $btn.prop('disabled', true).text('Testing Connection...');
                $res.hide().html('');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'uwb_test_cdn_connection',
                        nonce: nonce,
                        provider: $('#uwb_cdn_provider').val(),
                        account_id: $('#uwb_cdn_account_id').val(),
                        access_key: $('#uwb_cdn_access_key').val(),
                        secret_key: $('#uwb_cdn_secret_key').val(),
                        bucket: $('#uwb_cdn_bucket').val(),
                        endpoint: $('#uwb_cdn_endpoint').val(),
                        region: $('#uwb_cdn_region').val(),
                        custom_domain: $('#uwb_cdn_custom_domain').val()
                    },
                    success: function(resp) {
                        $btn.prop('disabled', false).html('<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle; margin-right:4px;"><polyline points="20 6 9 17 4 12"/></svg> Test CDN Connection');
                        if (resp.success) {
                            var html = '<div>✅ ' + resp.data.message + '</div>';
                            if (resp.data.file_url) {
                                html += '<div style="margin-top:8px; font-weight:normal; word-break:break-all;">🔗 Direct File URL: <a href="' + resp.data.file_url + '" target="_blank" rel="noopener noreferrer" style="color:#047857; text-decoration:underline; font-weight:700;">' + resp.data.file_url + ' &rarr;</a></div>';
                            }
                            $res.css({'padding':'12px 16px', 'background':'#d1fae5', 'color':'#065f46', 'border':'1px solid #6ee7b7', 'border-radius':'8px', 'font-size':'13px', 'font-weight':'600'}).html(html).slideDown();
                        } else {
                            $res.css({'padding':'12px 16px', 'background':'#fee2e2', 'color':'#991b1b', 'border':'1px solid #fca5a5', 'border-radius':'8px', 'font-size':'13px', 'font-weight':'600'}).html('❌ Error: ' + resp.data).slideDown();
                        }
                    },
                    error: function() {
                        $btn.prop('disabled', false).text('Test CDN Connection');
                        $res.css({'padding':'12px 16px', 'background':'#fee2e2', 'color':'#991b1b', 'border':'1px solid #fca5a5', 'border-radius':'8px', 'font-size':'13px', 'font-weight':'600'}).html('❌ Server request failed.').slideDown();
                    }
                });
            });

            // Sync Media Library to CDN Batch Handler
            $('#btn-sync-media-cdn').on('click', function() {
                var $btn = $(this);
                var $progressWrap = $('#uwb-sync-cdn-progress-wrap');
                var $progressFill = $('#uwb-sync-cdn-progress-fill');
                var $statusText = $('#uwb-sync-cdn-status-text');
                var nonce = '<?php echo esc_js( wp_create_nonce( "uwb_admin_nonce" ) ); ?>';

                if (!confirm('Start batch syncing all Media Library files to CDN S3/R2 storage?')) {
                    return;
                }

                $btn.prop('disabled', true).text('Syncing Media...');
                $progressWrap.slideDown();
                $progressFill.css('width', '5%');
                $statusText.text('Starting media sync batch 1...');

                function processBatch(paged) {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'uwb_sync_media_to_cdn',
                            nonce: nonce,
                            paged: paged
                        },
                        success: function(resp) {
                            if (resp.success) {
                                var d = resp.data;
                                if (d.total > 0) {
                                    var pct = Math.min(100, Math.round(((d.paged - 1) * 20 / d.total) * 100));
                                    $progressFill.css('width', pct + '%');
                                } else {
                                    $progressFill.css('width', '100%');
                                }
                                $statusText.text(d.message);

                                if (!d.completed) {
                                    processBatch(d.paged);
                                } else {
                                    $progressFill.css('width', '100%');
                                    $btn.prop('disabled', false).text('Sync Existing Media Library to CDN');
                                    alert('🎉 ' + d.message);
                                }
                            } else {
                                $btn.prop('disabled', false).text('Sync Existing Media Library to CDN');
                                alert('❌ Error: ' + resp.data);
                            }
                        },
                        error: function() {
                            $btn.prop('disabled', false).text('Sync Existing Media Library to CDN');
                            alert('❌ Batch sync failed due to server error.');
                        }
                    });
                }

                processBatch(1);
            });
        });
        </script>


        <script>
        jQuery(document).ready(function($) {
            // Tab Switcher Logic
            $('.uwb-nav-item').on('click', function() {
                var tabId = $(this).data('tab');
                
                $('.uwb-nav-item').removeClass('active');
                $(this).addClass('active');
                
                $('.uwb-tab-content').removeClass('active');
                $('#tab-' + tabId).addClass('active');

                localStorage.setItem('uwb_active_tab', tabId);

                // Hide submit row on non-settings tabs (these tabs have their own forms)
                if (['url_status', 'import_export'].indexOf(tabId) !== -1) {
                    $('#uwb-submit-row').hide();
                } else {
                    $('#uwb-submit-row').show();
                }

                // Load URL table on first visit
                if (tabId === 'url_status' && !uwbUrlTableLoaded) {
                    uwbUrlTableLoaded = true;
                    loadUrlTable();
                }
            });

            // Sub-tabs Switcher Logic
            $('.uwb-sub-tab-item').on('click', function() {
                var subtabId = $(this).data('subtab');
                var parentTab = $(this).closest('.uwb-tab-content');
                
                parentTab.find('.uwb-sub-tab-item').removeClass('active');
                $(this).addClass('active');
                
                parentTab.find('.uwb-subtab-content').removeClass('active');
                $('#subtab-' + subtabId).addClass('active');

                localStorage.setItem('uwb_active_subtab', subtabId);
            });

            // Toggle Switches interactive handler
            $(document).on('change', '.uwb-toggle-input', function() {
                var $container = $(this).closest('.uwb-toggle-container');
                $container.find('.uwb-toggle-btn').removeClass('active');
                $(this).closest('.uwb-toggle-btn').addClass('active');
            });

            // Sidebar Collapse/Expand Toggle
            $('#uwb-toggle-sidebar').on('click', function() {
                $('.uwb-layout').toggleClass('collapsed');
                if ($('.uwb-layout').hasClass('collapsed')) {
                    $('.toggle-icon-collapse').hide();
                    $('.toggle-icon-expand').show();
                    localStorage.setItem('uwb_sidebar_collapsed', '1');
                } else {
                    $('.toggle-icon-collapse').show();
                    $('.toggle-icon-expand').hide();
                    localStorage.setItem('uwb_sidebar_collapsed', '0');
                }
            });
            
            // Restore sidebar state
            if (localStorage.getItem('uwb_sidebar_collapsed') === '1') {
                $('.uwb-layout').addClass('collapsed');
                $('.toggle-icon-collapse').hide();
                $('.toggle-icon-expand').show();
            }

            // Preloader Live Tracker
            var checkInterval;
            var nonce = '<?php echo esc_js( wp_create_nonce( "uwb_admin_nonce" ) ); ?>';
            var uwbUrlTableLoaded = false;
            var uwbUrlPage = 1;
            var uwbUrlStatus = '';
            var uwbUrlSearch = '';
            var uwbUrlWc = 0;
            var uwbUrlOrderby = 'priority';
            var uwbUrlOrder = 'ASC';

            function updatePreloadStatus() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'uwb_get_preload_status',
                        nonce: nonce
                    },
                    success: function(res) {
                        if (res.success) {
                            var data = res.data;
                            $('#queue-pending').text(data.pending);
                            $('#queue-processing').text(data.processing);
                            $('#queue-completed').text(data.completed);
                            $('#queue-failed').text(data.failed);

                            var total = data.total;
                            var processed = data.completed + data.failed + data.processing;
                            
                            $('#preload-progress-nums').text(processed + ' / ' + total + ' URLs');
                            
                            var pct = 0;
                            if (total > 0) {
                                pct = Math.round((processed / total) * 100);
                            }
                            
                            $('#preload-progress-pct').text('Progress: ' + pct + '%');
                            $('#preload-progress-fill').css('width', pct + '%');

                            if (data.running === 1) {
                                $('#btn-start-preload').hide();
                                $('#btn-stop-preload').show();
                            } else {
                                if (total > 0 && processed >= total && data.pending === 0 && data.processing === 0) {
                                    // Done! Stop polling.
                                    if (checkInterval) {
                                        clearInterval(checkInterval);
                                        checkInterval = null;
                                    }
                                }
                                $('#btn-start-preload').show();
                                $('#btn-stop-preload').hide();
                            }

                            if (data.log !== undefined) {
                                var $logTextarea = $('#uwb-preload-log');
                                if ($logTextarea.length) {
                                    $logTextarea.val(data.log);
                                    // Auto scroll to bottom
                                    $logTextarea.scrollTop($logTextarea[0].scrollHeight);
                                }
                            }
                        }
                    }
                });
            }

            // Start Preload Click
            $('#btn-start-preload').on('click', function(e) {
                e.preventDefault();
                var btn = $(this);
                btn.prop('disabled', true).text('Parsing Sitemap...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'uwb_start_preload',
                        nonce: nonce
                    },
                    success: function(res) {
                        btn.prop('disabled', false).html('<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg> Start Preloading');
                        if (res.success) {
                            updatePreloadStatus();
                            if (!checkInterval) {
                                checkInterval = setInterval(updatePreloadStatus, 15000);
                            }
                        } else {
                            alert(res.data.message);
                        }
                    },
                    error: function() {
                        btn.prop('disabled', false).html('<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg> Start Preloading');
                        alert('Server connection error.');
                    }
                });
            });

            // Stop Preload Click
            $('#btn-stop-preload').on('click', function(e) {
                e.preventDefault();
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'uwb_stop_preload',
                        nonce: nonce
                    },
                    success: function(res) {
                        updatePreloadStatus();
                        if (checkInterval) {
                            clearInterval(checkInterval);
                            checkInterval = null;
                        }
                    }
                });
            });

            // Clear Preload Click
            $('#btn-clear-preload').on('click', function(e) {
                e.preventDefault();
                if (confirm('Are you sure you want to clear the preloading queue?')) {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'uwb_clear_preload',
                            nonce: nonce
                        },
                        success: function(res) {
                            updatePreloadStatus();
                            if (checkInterval) {
                                clearInterval(checkInterval);
                                checkInterval = null;
                            }
                        }
                    });
                }
            });

            // Clear Preloader Logs
            $(document).on('click', '#uwb-clear-preload-log-btn', function() {
                if (confirm('Are you sure you want to clear the logs?')) {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'uwb_clear_preload_log',
                            nonce: nonce
                        },
                        success: function(res) {
                            if (res.success) {
                                $('#uwb-preload-log').val('No logs available. Start a preload run to generate logs.');
                            }
                        }
                    });
                }
            });

            // Init status on load
            updatePreloadStatus();
            // Poll status only for display; cron or WP-CLI performs the actual preload work.
            checkInterval = setInterval(updatePreloadStatus, 15000);

            // Toggle Redis configuration fields
            function toggleRedisFields() {
                var connType = $('#uwb_redis_conn_type').val();
                if (connType === 'socket') {
                    $('#redis-tcp-settings').hide();
                    $('#redis-socket-settings').show();
                } else {
                    $('#redis-tcp-settings').show();
                    $('#redis-socket-settings').hide();
                }
            }
            $('#uwb_redis_conn_type').on('change', toggleRedisFields);
            toggleRedisFields();

            // Toggle Object Cache fields depending on type (None, Redis, Memcached)
            function toggleObjectCacheFields() {
                var ocType = $('#uwb_redis_enabled').val();
                if (ocType === '0') {
                    $('#uwb-oc-conn-type-group').hide();
                    $('#redis-tcp-settings').hide();
                    $('#redis-socket-settings').hide();
                    $('#uwb-oc-db-group').hide();
                    $('#uwb-oc-password-group').hide();
                    $('#uwb-oc-settings-test-group').hide();
                } else if (ocType === '2') {
                    // Memcached: show Host/Port, hide others
                    $('#uwb-oc-conn-type-group').hide();
                    if ($('#uwb_redis_port').val() === '6379') {
                        $('#uwb_redis_port').val('11211');
                    }
                    $('label[for="uwb_redis_host"]').text('Memcached Host');
                    $('label[for="uwb_redis_port"]').text('Memcached Port');
                    $('#redis-tcp-settings').show();
                    $('#redis-socket-settings').hide();
                    $('#uwb-oc-db-group').hide();
                    $('#uwb-oc-password-group').hide();
                    $('#uwb-oc-settings-test-group').show();
                } else {
                    // Redis
                    $('#uwb-oc-conn-type-group').show();
                    $('#uwb-oc-db-group').show();
                    $('#uwb-oc-password-group').show();
                    $('label[for="uwb_redis_host"]').text('Redis Host');
                    $('label[for="uwb_redis_port"]').text('Redis Port');
                    if ($('#uwb_redis_port').val() === '11211') {
                        $('#uwb_redis_port').val('6379');
                    }
                    toggleRedisFields();
                    $('#uwb-oc-settings-test-group').show();
                }
            }
            $('#uwb_redis_enabled').on('change', toggleObjectCacheFields);
            toggleObjectCacheFields();

            // Toggle Custom Cron instructions
            function toggleCronFields() {
                var preloadEnabled = $('#uwb_preload_enabled').val();
                if (preloadEnabled === '2') {
                    $('#uwb-custom-cron-info').slideDown(250);
                } else {
                    $('#uwb-custom-cron-info').slideUp(250);
                }
            }
            $('#uwb_preload_enabled').on('change', toggleCronFields);
            toggleCronFields();

            // Toggle Browser Cache fields
            function toggleBrowserCacheFields() {
                var browserCacheEnabled = $('#uwb_browser_cache_enabled').val();
                if (browserCacheEnabled === '1') {
                    $('#uwb-browser-cache-detailed-settings').show();
                } else {
                    $('#uwb-browser-cache-detailed-settings').hide();
                }
            }
            $('#uwb_browser_cache_enabled').on('change', toggleBrowserCacheFields);
            toggleBrowserCacheFields();

            // Toggle Page Cache fields
            function togglePageCacheFields() {
                var pageCacheEnabled = $('#uwb_cache_page_enabled').val();
                if (pageCacheEnabled === '1') {
                    $('#uwb-page-cache-detailed-settings').show();
                    $('#uwb-page-cache-detailed-settings-rules').show();
                } else {
                    $('#uwb-page-cache-detailed-settings').hide();
                    $('#uwb-page-cache-detailed-settings-rules').hide();
                }
            }
            $('#uwb_cache_page_enabled').on('change', togglePageCacheFields);
            togglePageCacheFields();

            // Toggle individual category lifespan fields
            $('.uwb-bc-cat-toggle').on('change', function() {
                var wrap = $(this).closest('div').find('.uwb-bc-lifespan-wrap');
                if ($(this).val() === '1') {
                    wrap.css('display', 'flex');
                } else {
                    wrap.hide();
                }
            });

            // Toggle Logged-in Cache Lifespan fields
            function toggleLoggedInFields() {
                var cacheLoggedIn = $('#uwb_cache_logged_in').val();
                if (cacheLoggedIn === '1') {
                    $('#uwb-logged-in-lifespan-group').show();
                    $('#uwb-logged-in-divider').show();
                } else {
                    $('#uwb-logged-in-lifespan-group').hide();
                    $('#uwb-logged-in-divider').hide();
                }
            }
            $('#uwb_cache_logged_in').on('change', toggleLoggedInFields);
            toggleLoggedInFields();

            // Toggle XML Sitemap Cache fields
            function toggleXMLFields() {
                var cacheXML = $('#uwb_cache_xml_sitemaps').val();
                if (cacheXML === '1') {
                    $('#uwb-xml-sitemaps-lifespan-group').show();
                    $('#uwb-xml-sitemaps-divider').show();
                } else {
                    $('#uwb-xml-sitemaps-lifespan-group').hide();
                    $('#uwb-xml-sitemaps-divider').hide();
                }
            }
            $('#uwb_cache_xml_sitemaps').on('change', toggleXMLFields);
            toggleXMLFields();

            // Toggle PHP Cache fields
            function togglePHPFields() {
                var cachePHP = $('#uwb_cache_php').val();
                if (cachePHP === '1') {
                    $('#uwb-php-lifespan-group').show();
                    $('#uwb-php-divider').show();
                } else {
                    $('#uwb-php-lifespan-group').hide();
                    $('#uwb-php-divider').hide();
                }
            }
            $('#uwb_cache_php').on('change', togglePHPFields);
            togglePHPFields();

            // Click to copy and auto-fill lifespan conversion helper values
            $('.uwb-copy-val').on('click', function(e) {
                e.preventDefault();
                var $code = $(this);
                var val = $code.text().trim();
                
                // Copy to clipboard
                var $temp = $("<input>");
                $("body").append($temp);
                $temp.val(val).select();
                document.execCommand("copy");
                $temp.remove();

                // Auto-fill the corresponding input
                var $group = $code.closest('.uwb-form-group');
                var $input = $group.find('input[type="number"]');
                if ($input.length) {
                    $input.val(val).trigger('change');
                }

                // Show toast/notification
                var $toast = $('#uwb-url-toast');
                if ($toast.length) {
                    $toast.text('Copied and applied: ' + val + ' minutes').fadeIn(200).delay(1500).fadeOut(200);
                }
            });

            // Copy Cron Job to clipboard
            $('.uwb-copy-cron').on('click', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var text = $btn.data('clipboard-text');
                
                // Copy text using a temporary input element
                var $temp = $("<input>");
                $("body").append($temp);
                $temp.val(text).select();
                document.execCommand("copy");
                $temp.remove();
                
                // Show copied feedback
                var originalHtml = $btn.html();
                $btn.html('<span style="color:var(--uwb-success); font-weight:bold; font-size:11px;">✓ Copied</span>');
                setTimeout(function() {
                    $btn.html(originalHtml);
                }, 1500);
            });

            // GitHub manual update click handler in Header
            $('#uwb-github-update-btn').on('click', function(e) {
                e.preventDefault();
                var btn = $(this);
                var status = $('#uwb-github-update-status');
                var spinner = btn.find('.uwb-spinner');
                var btnText = btn.find('.uwb-btn-text');
                
                btn.prop('disabled', true);
                btnText.text('Updating...');
                spinner.show();
                status.text('Downloading latest version from GitHub...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    timeout: 120000,
                    data: {
                        action: 'uwb_github_manual_update',
                        nonce: '<?php echo esc_js( $update_nonce ); ?>'
                    },
                    success: function(res) {
                        spinner.hide();
                        btn.prop('disabled', false);
                        btnText.text('Update Plugin');
                        if (res.success) {
                            status.css('color', '#a7f3d0').text('✓ Plugin updated to latest version. Reloading...');
                            alert('Plugin updated successfully! The page will now reload.');
                            location.reload();
                        } else {
                            status.css('color', '#fca5a5').text('✗ Error: ' + (res.data.message || 'Unknown error.'));
                            alert('Update failed: ' + (res.data.message || 'Unknown error.'));
                        }
                    },
                    error: function(xhr, textStatus, errorThrown) {
                        spinner.hide();
                        btn.prop('disabled', false);
                        btnText.text('Update Plugin');
                        var detail = errorThrown || textStatus;
                        if (xhr.responseText) {
                            var preview = xhr.responseText.replace(/<[^>]+>/g, '').trim().substring(0, 300);
                            detail = '(HTTP ' + xhr.status + ') ' + preview;
                        }
                        status.css('color', '#fca5a5').text('✗ Server error.');
                        alert('Server error: ' + detail);
                    }
                });
            });

            // Test Redis Connection
            $('#btn-test-redis').on('click', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var $result = $('#redis-test-result');
                
                $btn.prop('disabled', true).text('Testing...');
                $result.hide().removeClass('notice-success notice-error').css({'background': '', 'color': '', 'border': ''});
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'uwb_test_redis_connection',
                        nonce: nonce,
                        is_stored: 1
                    },
                    success: function(res) {
                        $btn.prop('disabled', false).text('Test Connection');
                        $result.show();
                        if (res.success) {
                            $result.css({
                                'background': '#d1fae5',
                                'color': '#065f46',
                                'border': '1px solid #6ee7b7'
                            }).text(res.data.message);
                        } else {
                            $result.css({
                                'background': '#fee2e2',
                                'color': '#991b1b',
                                'border': '1px solid #fca5a5'
                            }).text(res.data.message);
                        }
                    },
                    error: function() {
                        $btn.prop('disabled', false).text('Test Connection');
                        $result.show().css({
                            'background': '#fee2e2',
                            'color': '#991b1b',
                            'border': '1px solid #fca5a5'
                        }).text('Server error occurred during the test.');
                    }
                });
            });

            // Test Redis Connection (Settings Page)
            $('#btn-test-redis-settings').on('click', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var $result = $('#redis-test-result-settings');
                
                $btn.prop('disabled', true).text('Testing...');
                $result.hide().removeClass('notice-success notice-error').css({'background': '', 'color': '', 'border': ''});
                
                var ocType = $('#uwb_redis_enabled').val();
                var connType = $('#uwb_redis_conn_type').val();
                var host = $('#uwb_redis_host').val();
                var port = $('#uwb_redis_port').val();
                var socket = $('#uwb_redis_socket').val();
                var password = $('#uwb_redis_password').val();
                var db = $('#uwb_redis_db').val();

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'uwb_test_redis_connection',
                        nonce: nonce,
                        type: ocType,
                        conn_type: connType,
                        host: host,
                        port: port,
                        socket: socket,
                        password: password,
                        db: db
                    },
                    success: function(res) {
                        $btn.prop('disabled', false).text('Test Connection');
                        $result.show();
                        if (res.success) {
                            $result.css({
                                'background': '#d1fae5',
                                'color': '#065f46',
                                'border': '1px solid #6ee7b7'
                            }).text(res.data.message);
                        } else {
                            $result.css({
                                'background': '#fee2e2',
                                'color': '#991b1b',
                                'border': '1px solid #fca5a5'
                            }).text(res.data.message);
                        }
                    },
                    error: function() {
                        $btn.prop('disabled', false).text('Test Connection');
                        $result.show().css({
                            'background': '#fee2e2',
                            'color': '#991b1b',
                            'border': '1px solid #fca5a5'
                        }).text('Server error occurred during the test.');
                    }
                });
            });

            // Test Cloudflare API Connection
            $('#btn-test-cf-connection').on('click', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var $result = $('#uwb-cf-test-result');

                var zoneId = $('#uwb_cf_zone_id').val();
                var token = $('#uwb_cf_api_token').val();

                $btn.prop('disabled', true).text('Testing API...');
                $result.hide();

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'uwb_test_cf_connection',
                        nonce: nonce,
                        zone_id: zoneId,
                        api_token: token
                    },
                    success: function(res) {
                        $btn.prop('disabled', false).text('Test Cloudflare API Connection');
                        $result.show();
                        if (res.success) {
                            $result.css({
                                'background': '#d1fae5',
                                'color': '#065f46',
                                'border': '1px solid #6ee7b7',
                                'padding': '12px',
                                'border-radius': '8px',
                                'font-size': '13px',
                                'font-weight': '600'
                            }).html('✓ ' + res.data.message);
                        } else {
                            $result.css({
                                'background': '#fee2e2',
                                'color': '#991b1b',
                                'border': '1px solid #fca5a5',
                                'padding': '12px',
                                'border-radius': '8px',
                                'font-size': '13px',
                                'font-weight': '600'
                            }).html('✕ Error: ' + (res.data.message || 'Connection failed'));
                        }
                    },
                    error: function() {
                        $btn.prop('disabled', false).text('Test Cloudflare API Connection');
                        $result.show().css({
                            'background': '#fee2e2',
                            'color': '#991b1b',
                            'border': '1px solid #fca5a5',
                            'padding': '12px',
                            'border-radius': '8px',
                            'font-size': '13px',
                            'font-weight': '600'
                        }).html('✕ Server error testing Cloudflare connection.');
                    }
                });
            });

            // Purge Cloudflare Zone Cache Now
            $('#btn-purge-cf-cache').on('click', function(e) {
                e.preventDefault();
                if (!confirm('Purge all Cloudflare CDN Edge Cache for this zone now?')) {
                    return;
                }
                var $btn = $(this);
                var $result = $('#uwb-cf-test-result');

                var zoneId = $('#uwb_cf_zone_id').val();
                var token = $('#uwb_cf_api_token').val();

                $btn.prop('disabled', true).text('Purging CDN...');
                $result.hide();

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'uwb_purge_cf_cache',
                        nonce: nonce,
                        zone_id: zoneId,
                        api_token: token
                    },
                    success: function(res) {
                        $btn.prop('disabled', false).text('Purge Cloudflare Zone Cache Now');
                        $result.show();
                        if (res.success) {
                            $result.css({
                                'background': '#d1fae5',
                                'color': '#065f46',
                                'border': '1px solid #6ee7b7',
                                'padding': '12px',
                                'border-radius': '8px',
                                'font-size': '13px',
                                'font-weight': '600'
                            }).html('✓ ' + res.data.message);
                        } else {
                            $result.css({
                                'background': '#fee2e2',
                                'color': '#991b1b',
                                'border': '1px solid #fca5a5',
                                'padding': '12px',
                                'border-radius': '8px',
                                'font-size': '13px',
                                'font-weight': '600'
                            }).html('✕ Error: ' + (res.data.message || 'Purge failed'));
                        }
                    },
                    error: function() {
                        $btn.prop('disabled', false).text('Purge Cloudflare Zone Cache Now');
                        $result.show().css({
                            'background': '#fee2e2',
                            'color': '#991b1b',
                            'border': '1px solid #fca5a5',
                            'padding': '12px',
                            'border-radius': '8px',
                            'font-size': '13px',
                            'font-weight': '600'
                        }).html('✕ Server error purging Cloudflare cache.');
                    }
                });
            });

            // Flush Redis Cache
            $('#btn-flush-redis, #btn-flush-redis-tree').on('click', function(e) {
                e.preventDefault();
                if (!confirm('Are you sure you want to flush the persistent object cache?')) {
                    return;
                }
                var $btn = $(this);
                $btn.prop('disabled', true).text('Flushing...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'uwb_flush_redis_cache',
                        nonce: nonce
                    },
                    success: function(res) {
                        $btn.prop('disabled', false).text('Flush Cache');
                        if (res.success) {
                            showToast(res.data.message);
                            // Reload page after a delay to update stats
                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        } else {
                            showToast(res.data.message, true);
                        }
                    },
                    error: function() {
                        $btn.prop('disabled', false).text('Flush Cache');
                        showToast('Server error flushing cache.', true);
                    }
                });
            });

            /* =====================================================
               URL STATUS TABLE
            ===================================================== */

            var uwbSearchTimer = null;

            function showToast(msg, isError) {
                var $t = $('#uwb-url-toast');
                $t.text(msg).css('background', isError ? '#dc2626' : '#1e293b').fadeIn(200);
                setTimeout(function() { $t.fadeOut(400); }, 3000);
            }

            function statusBadge(s) {
                var colors = {
                    pending:    'background:#fef9c3; color:#92400e; border:1px solid #fcd34d;',
                    processing: 'background:#dbeafe; color:#1e40af; border:1px solid #93c5fd;',
                    completed:  'background:#d1fae5; color:#065f46; border:1px solid #6ee7b7;',
                    failed:     'background:#fee2e2; color:#991b1b; border:1px solid #fca5a5;'
                };
                var style = colors[s] || '';
                return '<span style="' + style + ' padding:3px 10px; border-radius:20px; font-size:11.5px; font-weight:700; white-space:nowrap;">' + s + '</span>';
            }

            function loadUrlTable() {
                $('#uwb-url-tbody').html('<tr><td colspan="6" style="text-align:center; padding:32px; color:var(--uwb-text-muted);">Loading...</td></tr>');
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action:  'uwb_get_url_table',
                        nonce:   nonce,
                        status:  uwbUrlStatus,
                        search:  uwbUrlSearch,
                        orderby: uwbUrlOrderby,
                        order:   uwbUrlOrder,
                        page:    uwbUrlPage,
                        is_woocommerce: uwbUrlWc
                    },
                    success: function(res) {
                        if (!res.success) { showToast('Failed to load table.', true); return; }
                        renderUrlTable(res.data);
                    },
                    error: function() { showToast('Server error loading table.', true); }
                });
            }

            function renderUrlTable(data) {
                var rows = data.rows;
                var $tbody = $('#uwb-url-tbody');
                if (!rows || rows.length === 0) {
                    $tbody.html('<tr><td colspan="5" style="text-align:center; padding:32px; color:var(--uwb-text-muted);">No URLs found.</td></tr>');
                } else {
                    var html = '';
                    $.each(rows, function(i, r) {
                        var rowBg = (i % 2 === 0) ? '#ffffff' : '#f8fafc';
                        var priorityLabel = '<span style="font-weight:600; color:var(--uwb-text);">' + r.priority + '</span>';
                        var lastAttempt = r.last_attempt ? r.last_attempt : '—';
                        var uri = r.url.replace(/^https?:\/\/[^\/]+/i, '');
                        if (uri === '') { uri = '/'; }
                        
                        html += '<tr style="background:' + rowBg + '; border-bottom:1px solid #f1f5f9; transition:background 0.15s;" onmouseover="this.style.background=\'#eef2ff\'" onmouseout="this.style.background=\'' + rowBg + '\'">';
                        html += '<td style="padding:10px 14px; text-align:center;">' + priorityLabel + '</td>';
                        html += '<td style="padding:10px 14px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="' + $('<div>').text(r.url).html() + '"><a href="' + $('<div>').text(r.url).html() + '" target="_blank" style="color:var(--uwb-primary); text-decoration:none; font-size:12.5px;">' + $('<div>').text(uri).html() + '</a></td>';
                        html += '<td style="padding:10px 14px; text-align:center;">' + statusBadge(r.status) + '</td>';
                        html += '<td style="padding:10px 14px; text-align:center; color:var(--uwb-text-muted); font-size:12px;">' + lastAttempt + '</td>';
                        html += '<td style="padding:10px 14px; text-align:center; white-space:nowrap;">';
                        html += '<button class="uwb-act-process" data-id="' + r.id + '" style="background:#6366f1; color:#fff; border:none; border-radius:5px; padding:5px 10px; font-size:11.5px; font-weight:600; cursor:pointer; margin:2px;" title="Process this URL now">▶ Now</button>';
                        html += '<button class="uwb-act-exclude" data-id="' + r.id + '" style="background:#f1f5f9; border:1px solid #cbd5e1; color:#475569; border-radius:5px; padding:5px 10px; font-size:11.5px; font-weight:600; cursor:pointer; margin:2px;" title="Add to Exclude list">✕ Exclude</button>';
                        
                        var isImportant = (r.priority == 0);
                        var btnText = isImportant ? '★ Important' : '☆ Important';
                        var btnStyle = isImportant ? 'background:#fef9c3; border:1px solid #fcd34d; color:#92400e;' : 'background:#f1f5f9; border:1px solid #cbd5e1; color:#475569;';
                        var btnTitle = isImportant ? 'Remove from Important URLs' : 'Add to Important URLs';
                        html += '<button class="uwb-act-priority" data-id="' + r.id + '" style="' + btnStyle + ' border-radius:5px; padding:5px 10px; font-size:11.5px; font-weight:600; cursor:pointer; margin:2px;" title="' + btnTitle + '">' + btnText + '</button>';
                        html += '</td></tr>';
                    });
                    $tbody.html(html);
                }

                // Pagination
                var totalPages = data.total_pages;
                var currentPage = data.page;
                var from = (currentPage - 1) * data.per_page + 1;
                var to = Math.min(currentPage * data.per_page, data.total);
                var paginHtml = '<span>Showing ' + from + '–' + to + ' of ' + data.total + ' URLs</span>';
                
                $('#uwb-url-pagination').data('total-pages', totalPages);
                
                paginHtml += '<div style="display:flex; gap:6px; align-items:center;">';
                
                var firstDisabled = (currentPage > 1) ? '' : ' disabled style="opacity:0.5; cursor:not-allowed;"';
                var prevDisabled = (currentPage > 1) ? '' : ' disabled style="opacity:0.5; cursor:not-allowed;"';
                paginHtml += '<button id="uwb-page-first"' + firstDisabled + ' style="border:1px solid var(--uwb-border); background:#fff; border-radius:6px; padding:5px 12px; cursor:pointer; font-weight:600;">First</button>';
                paginHtml += '<button id="uwb-page-prev"' + prevDisabled + ' style="border:1px solid var(--uwb-border); background:#fff; border-radius:6px; padding:5px 12px; cursor:pointer; font-weight:600;">Prev</button>';

                var range = [];
                if (totalPages <= 7) {
                    for (var i = 1; i <= totalPages; i++) {
                        range.push(i);
                    }
                } else {
                    range.push(1);
                    range.push(2);

                    var start = Math.max(3, currentPage - 1);
                    var end = Math.min(totalPages - 2, currentPage + 1);

                    for (var i = start; i <= end; i++) {
                        range.push(i);
                    }

                    range.push(totalPages - 1);
                    range.push(totalPages);
                }

                range = range.filter(function(item, pos, self) {
                    return self.indexOf(item) == pos;
                }).sort(function(a, b) { return a - b; });

                var lastNum = 0;
                $.each(range, function(idx, p) {
                    if (lastNum > 0) {
                        if (p - lastNum > 1) {
                            paginHtml += '<span style="padding:5px 8px; color:var(--uwb-text-muted);">...</span>';
                        }
                    }
                    var active = (p === currentPage) ? 'background:var(--uwb-primary);color:#fff;' : 'background:#fff;';
                    paginHtml += '<button class="uwb-page-btn" data-page="' + p + '" style="border:1px solid var(--uwb-border);' + active + 'border-radius:6px; padding:5px 12px; cursor:pointer; font-weight:600;">' + p + '</button>';
                    lastNum = p;
                });

                var nextDisabled = (currentPage < totalPages) ? '' : ' disabled style="opacity:0.5; cursor:not-allowed;"';
                var lastDisabled = (currentPage < totalPages) ? '' : ' disabled style="opacity:0.5; cursor:not-allowed;"';
                paginHtml += '<button id="uwb-page-next"' + nextDisabled + ' style="border:1px solid var(--uwb-border); background:#fff; border-radius:6px; padding:5px 12px; cursor:pointer; font-weight:600;">Next</button>';
                paginHtml += '<button id="uwb-page-last"' + lastDisabled + ' style="border:1px solid var(--uwb-border); background:#fff; border-radius:6px; padding:5px 12px; cursor:pointer; font-weight:600;">Last</button>';

                paginHtml += '</div>';
                $('#uwb-url-pagination').html(paginHtml);
            }

            // Sort headers
            $(document).on('click', '.uwb-sortable', function(e) {
                e.preventDefault();
                var col = $(this).data('col');
                if (uwbUrlOrderby === col) {
                    uwbUrlOrder = (uwbUrlOrder === 'ASC') ? 'DESC' : 'ASC';
                } else {
                    uwbUrlOrderby = col;
                    uwbUrlOrder = 'ASC';
                }
                $('.uwb-sort-icon').text('↕');
                $(this).find('.uwb-sort-icon').text(uwbUrlOrder === 'ASC' ? '↑' : '↓');
                uwbUrlPage = 1;
                loadUrlTable();
            });

            // Filter buttons
            $(document).on('click', '.uwb-filter-btn', function(e) {
                e.preventDefault();
                $('.uwb-filter-btn').css('outline', '').removeClass('active');
                $(this).css('outline', '2px solid var(--uwb-primary)').addClass('active');
                uwbUrlStatus = $(this).data('status');
                uwbUrlPage = 1;
                loadUrlTable();
            });

            // Filter WooCommerce
            $('#uwb-filter-wc').on('click', function(e) {
                e.preventDefault();
                $(this).toggleClass('active');
                uwbUrlWc = $(this).hasClass('active') ? 1 : 0;
                uwbUrlPage = 1;
                loadUrlTable();
            });

            // Search
            $('#uwb-url-search').on('input', function() {
                clearTimeout(uwbSearchTimer);
                var val = $(this).val();
                uwbSearchTimer = setTimeout(function() {
                    uwbUrlSearch = val;
                    uwbUrlPage = 1;
                    loadUrlTable();
                }, 400);
            });

            // Refresh button
            $('#uwb-url-refresh').on('click', function(e) { e.preventDefault(); loadUrlTable(); });

            // Pagination
            $(document).on('click', '#uwb-page-first', function(e) {
                e.preventDefault();
                if ($(this).prop('disabled')) return;
                if (uwbUrlPage > 1) { uwbUrlPage = 1; loadUrlTable(); }
            });
            $(document).on('click', '#uwb-page-prev', function(e) {
                e.preventDefault();
                if ($(this).prop('disabled')) return;
                if (uwbUrlPage > 1) { uwbUrlPage--; loadUrlTable(); }
            });
            $(document).on('click', '#uwb-page-next', function(e) {
                e.preventDefault();
                if ($(this).prop('disabled')) return;
                var totalPages = parseInt($('#uwb-url-pagination').data('total-pages') || 1);
                if (uwbUrlPage < totalPages) { uwbUrlPage++; loadUrlTable(); }
            });
            $(document).on('click', '#uwb-page-last', function(e) {
                e.preventDefault();
                if ($(this).prop('disabled')) return;
                var totalPages = parseInt($('#uwb-url-pagination').data('total-pages') || 1);
                if (uwbUrlPage < totalPages) { uwbUrlPage = totalPages; loadUrlTable(); }
            });
            $(document).on('click', '.uwb-page-btn', function(e) {
                e.preventDefault();
                uwbUrlPage = parseInt($(this).data('page'));
                loadUrlTable();
            });

            // Row action: Process Now
            $(document).on('click', '.uwb-act-process', function(e) {
                e.preventDefault();
                var id = $(this).data('id');
                var $btn = $(this).prop('disabled', true).text('...');
                $.ajax({
                    url: ajaxurl, type: 'POST',
                    data: { action: 'uwb_process_url_now', nonce: nonce, id: id },
                    success: function(res) {
                        $btn.prop('disabled', false).text('▶ Now');
                        if (res.success) {
                            showToast('Done! Status: ' + res.data.status);
                            loadUrlTable();
                        } else {
                            showToast(res.data.message, true);
                        }
                    },
                    error: function() { $btn.prop('disabled', false).text('▶ Now'); showToast('Error.', true); }
                });
            });

            // Row action: Add to Exclude
            $(document).on('click', '.uwb-act-exclude', function(e) {
                e.preventDefault();
                var id = $(this).data('id');
                var $btn = $(this).prop('disabled', true).text('...');
                $.ajax({
                    url: ajaxurl, type: 'POST',
                    data: { action: 'uwb_add_to_exclude', nonce: nonce, id: id },
                    success: function(res) {
                        $btn.prop('disabled', false).text('✕ Exclude');
                        if (res.success) { showToast(res.data.message); }
                        else { showToast(res.data.message, true); }
                    },
                    error: function() { $btn.prop('disabled', false).text('✕ Exclude'); showToast('Error.', true); }
                });
            });

            // Row action: Add to Important / Remove from Important
            $(document).on('click', '.uwb-act-priority', function(e) {
                e.preventDefault();
                var id = $(this).data('id');
                var $btn = $(this).prop('disabled', true).text('...');
                $.ajax({
                    url: ajaxurl, type: 'POST',
                    data: { action: 'uwb_add_to_priority', nonce: nonce, id: id },
                    success: function(res) {
                        if (res.success) { 
                            showToast(res.data.message); 
                            if (res.data.urls !== undefined) {
                                $('#uwb_priority_urls').val(res.data.urls);
                            }
                            loadUrlTable(); 
                        }
                        else { $btn.prop('disabled', false).text('Important'); showToast(res.data.message, true); }
                    },
                    error: function() { $btn.prop('disabled', false).text('Important'); showToast('Error.', true); }
                });
            });

            // Restore active tab and subtab state
            var savedTab = localStorage.getItem('uwb_active_tab');
            if (savedTab) {
                $('.uwb-nav-item').removeClass('active');
                var $targetTabBtn = $('.uwb-nav-item[data-tab="' + savedTab + '"]');
                $targetTabBtn.addClass('active');
                
                $('.uwb-tab-content').removeClass('active');
                $('#tab-' + savedTab).addClass('active');
                
                if (['url_status', 'import_export'].indexOf(savedTab) !== -1) {
                    $('#uwb-submit-row').hide();
                } else {
                    $('#uwb-submit-row').show();
                }
                
                if (savedTab === 'url_status') {
                    uwbUrlTableLoaded = true;
                    loadUrlTable();
                }
            } else {
                // Default: Load URL table on load since it is the default tab (Dashboard)
                uwbUrlTableLoaded = true;
                loadUrlTable();
            }
            
            var savedSubtab = localStorage.getItem('uwb_active_subtab');
            if (savedSubtab) {
                var $targetSubtabBtn = $('.uwb-sub-tab-item[data-subtab="' + savedSubtab + '"]');
                if ($targetSubtabBtn.length) {
                    var $parentTab = $targetSubtabBtn.closest('.uwb-tab-content');
                    $parentTab.find('.uwb-sub-tab-item').removeClass('active');
                    $targetSubtabBtn.addClass('active');
                    
                    $parentTab.find('.uwb-subtab-content').removeClass('active');
                    $('#subtab-' + savedSubtab).addClass('active');
                }
            }

            // Toggle event action checkboxes on CDN distribution switch change
            $(document).on('change', 'input[name="uwb_cdn_distribute_css"], input[name="uwb_cdn_distribute_js"], input[name="uwb_cdn_distribute_html"], input[name="uwb_cdn_distribute_media"], input[name="uwb_cdn_distribute_font"]', function() {
                var $card = $(this).closest('div');
                var $wrap = $card.find('.uwb-cdn-events-wrap');
                if ($(this).is(':checked')) {
                    $wrap.slideDown(200);
                } else {
                    $wrap.slideUp(200);
                }
            });
        });
        </script>
        <?php

    }

    public function ajax_clear_preload_log() {
        check_ajax_referer( 'uwb_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ) );
        }
        $log_file = WP_CONTENT_DIR . '/cache/ultimate-wp-booster/preload-debug.log';
        @unlink( $log_file );
        wp_send_json_success( array( 'message' => 'Log cleared.' ) );
    }

    public function ajax_test_redis_connection() {
        check_ajax_referer( 'uwb_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ) );
        }

        $is_stored = isset( $_POST['is_stored'] ) ? intval( $_POST['is_stored'] ) : 0;

        if ( $is_stored ) {
            $type = intval( get_option( 'uwb_redis_enabled', 0 ) );
            $conn_type = get_option( 'uwb_redis_conn_type', 'tcp' );
            $host = get_option( 'uwb_redis_host', '127.0.0.1' );
            $port = intval( get_option( 'uwb_redis_port', $type === 2 ? 11211 : 6379 ) );
            $socket = get_option( 'uwb_redis_socket', '' );
            $password = get_option( 'uwb_redis_password', '' );
        } else {
            $type = isset( $_POST['type'] ) ? intval( $_POST['type'] ) : 1;
            $host = isset( $_POST['host'] ) ? sanitize_text_field( $_POST['host'] ) : '127.0.0.1';
            $port = isset( $_POST['port'] ) ? intval( $_POST['port'] ) : ( $type === 2 ? 11211 : 6379 );
            $socket = isset( $_POST['socket'] ) ? sanitize_text_field( $_POST['socket'] ) : '';
            $conn_type = isset( $_POST['conn_type'] ) ? sanitize_text_field( $_POST['conn_type'] ) : 'tcp';
            $password = isset( $_POST['password'] ) ? sanitize_text_field( $_POST['password'] ) : '';
        }

        if ( $type === 2 ) {
            // Memcached Test
            if ( ! extension_loaded( 'memcached' ) ) {
                $fp = @fsockopen( $host, $port, $errno, $errstr, 1.0 );
                if ( $fp ) {
                    fclose( $fp );
                    wp_send_json_success( array(
                        'message' => sprintf( 'Memcached extension is not installed, but port %d is open on %s.', $port, $host )
                    ) );
                } else {
                    wp_send_json_error( array(
                        'message' => 'Memcached PHP extension is not installed, and could not connect to port ' . $port . ' on ' . $host
                    ) );
                }
                exit;
            }

            $m = new \Memcached();
            $m->addServer( $host, $port );
            $statuses = $m->getVersion();
            
            if ( is_array( $statuses ) && ! empty( $statuses ) ) {
                $versions = array_values( $statuses );
                $version = isset( $versions[0] ) ? $versions[0] : 'Unknown';
                if ( $version !== '255.255.255' && $version !== '0.0.0' && ! empty( $version ) ) {
                    wp_send_json_success( array(
                        'message' => sprintf( 'Memcached connection successful! Host: %s:%d | Version: %s', $host, $port, $version )
                    ) );
                }
            }
            
            wp_send_json_error( array(
                'message' => sprintf( 'Could not connect to Memcached server at %s:%d.', $host, $port )
            ) );
            exit;
        }

        // Redis Test
        if ( ! class_exists( 'Redis' ) ) {
            $fp = @fsockopen( $host, $port, $errno, $errstr, 1.0 );
            if ( $fp ) {
                fclose( $fp );
                wp_send_json_success( array(
                    'message' => sprintf( 'Redis extension is not installed, but port %d is open on %s.', $port, $host )
                ) );
            } else {
                wp_send_json_error( array(
                    'message' => 'Redis PHP extension is not installed, and could not connect to port ' . $port . ' on ' . $host
                ) );
            }
            exit;
        }

        $redis = new \Redis();
        try {
            if ( $conn_type === 'socket' && ! empty( $socket ) ) {
                $connected = @$redis->connect( $socket );
            } else {
                $connected = @$redis->connect( $host, $port, 1.0 );
            }
            
            if ( ! $connected ) {
                wp_send_json_error( array(
                    'message' => sprintf( 'Could not connect to Redis server at %s:%d.', $host, $port )
                ) );
            }

            if ( ! empty( $password ) ) {
                $authenticated = @$redis->auth( $password );
                if ( ! $authenticated ) {
                    wp_send_json_error( array(
                        'message' => 'Authentication failed. Please verify password.'
                    ) );
                }
            }

            $ping = $redis->ping();
            $ping_str = is_bool( $ping ) ? ( $ping ? 'PONG' : 'FAIL' ) : (string) $ping;

            wp_send_json_success( array(
                'message' => sprintf( 'Connection successful! Host: %s:%d | Ping: %s', $host, $port, $ping_str )
            ) );

        } catch ( \Exception $e ) {
            wp_send_json_error( array(
                'message' => 'Redis error: ' . $e->getMessage()
            ) );
        }
        exit;
    }

    public function ajax_flush_redis_cache() {
        check_ajax_referer( 'uwb_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ) );
        }

        $flushed = wp_cache_flush();

        if ( $flushed ) {
            wp_send_json_success( array( 'message' => 'Object Cache flushed successfully!' ) );
        } else {
            $oc_type = intval( get_option( 'uwb_redis_enabled', 0 ) );
            if ( $oc_type === 2 ) {
                if ( class_exists( 'Memcached' ) ) {
                    $mc_host = get_option( 'uwb_redis_host', '127.0.0.1' );
                    $mc_port = intval( get_option( 'uwb_redis_port', 11211 ) );
                    if ( $mc_port === 6379 ) {
                        $mc_port = 11211;
                    }
                    $m = new \Memcached();
                    $m->addServer( $mc_host, $mc_port );
                    if ( $m->flush() ) {
                        wp_send_json_success( array( 'message' => 'Memcached flushed successfully via direct connection!' ) );
                        exit;
                    }
                }
            } else {
                if ( class_exists( 'Redis' ) ) {
                    $redis_host = get_option( 'uwb_redis_host', '127.0.0.1' );
                    $redis_port = get_option( 'uwb_redis_port', 6379 );
                    $redis_password = get_option( 'uwb_redis_password', '' );

                    $redis = new \Redis();
                    try {
                        if ( @$redis->connect( $redis_host, $redis_port, 1.0 ) ) {
                            if ( ! empty( $redis_password ) ) {
                                @$redis->auth( $redis_password );
                            }
                            $redis->flushDB();
                            wp_send_json_success( array( 'message' => 'Redis DB flushed successfully via direct connection!' ) );
                            exit;
                        }
                    } catch ( \Exception $e ) {
                        // fall through
                    }
                }
            }
            wp_send_json_error( array( 'message' => 'Failed to flush object cache. Make sure Object Cache is active and configured.' ) );
        }
        exit;
    }

    public function ajax_test_cdn_connection() {
        check_ajax_referer( 'uwb_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied' );
        }

        $config = array(
            'provider'   => isset( $_POST['provider'] ) ? sanitize_text_field( $_POST['provider'] ) : 'cloudflare_r2',
            'access_key' => isset( $_POST['access_key'] ) ? sanitize_text_field( $_POST['access_key'] ) : '',
            'secret_key' => isset( $_POST['secret_key'] ) ? sanitize_text_field( $_POST['secret_key'] ) : '',
            'bucket'     => isset( $_POST['bucket'] ) ? sanitize_text_field( $_POST['bucket'] ) : '',
            'account_id' => isset( $_POST['account_id'] ) ? sanitize_text_field( $_POST['account_id'] ) : '',
            'endpoint'   => isset( $_POST['endpoint'] ) ? esc_url_raw( $_POST['endpoint'] ) : '',
            'region'     => isset( $_POST['region'] ) ? sanitize_text_field( $_POST['region'] ) : 'auto',
        );

        $custom_domain = isset( $_POST['custom_domain'] ) ? esc_url_raw( $_POST['custom_domain'] ) : '';

        $client = new \Ultimate_WP_Booster\Engine\CDN\S3Client( $config );
        $res = $client->test_connection( $custom_domain );

        if ( is_wp_error( $res ) ) {
            wp_send_json_error( $res->get_error_message() );
        }

        $file_url = ! empty( $res['file_url'] ) ? $res['file_url'] : '';

        wp_send_json_success( array(
            'message'  => 'Successfully uploaded test file (uwb-test-connection.txt) to S3/R2 storage!',
            'file_url' => $file_url,
        ) );
    }

    public function ajax_test_cf_connection() {
        check_ajax_referer( 'uwb_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied' );
        }

        $zone_id = isset( $_POST['zone_id'] ) ? sanitize_text_field( $_POST['zone_id'] ) : get_option( 'uwb_cf_zone_id', '' );
        $token   = isset( $_POST['api_token'] ) ? sanitize_text_field( $_POST['api_token'] ) : get_option( 'uwb_cf_api_token', '' );

        $res = \Ultimate_WP_Booster\Engine\CDN\CloudflareAPI::test_connection( $zone_id, $token );
        if ( is_wp_error( $res ) ) {
            wp_send_json_error( $res->get_error_message() );
        }

        $zone_name = ! empty( $res['name'] ) ? " (Zone: {$res['name']})" : '';
        wp_send_json_success( array(
            'message' => "Cloudflare API Connection Successful! {$zone_name}",
        ) );
    }

    public function ajax_purge_cf_cache() {
        check_ajax_referer( 'uwb_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied' );
        }

        $zone_id = isset( $_POST['zone_id'] ) ? sanitize_text_field( $_POST['zone_id'] ) : get_option( 'uwb_cf_zone_id', '' );
        $token   = isset( $_POST['api_token'] ) ? sanitize_text_field( $_POST['api_token'] ) : get_option( 'uwb_cf_api_token', '' );

        $res = \Ultimate_WP_Booster\Engine\CDN\CloudflareAPI::purge_everything( $zone_id, $token );
        if ( is_wp_error( $res ) ) {
            wp_send_json_error( $res->get_error_message() );
        }

        wp_send_json_success( array(
            'message' => 'Successfully sent Cache Purge request! All Cloudflare CDN Edge Cache has been purged.',
        ) );
    }

    public function ajax_sync_media_to_cdn() {
        check_ajax_referer( 'uwb_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied' );
        }

        $paged = isset( $_POST['paged'] ) ? max( 1, intval( $_POST['paged'] ) ) : 1;
        $posts_per_page = 20;

        $args = array(
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => $posts_per_page,
            'paged'          => $paged,
            'fields'         => 'ids',
        );

        $query = new \WP_Query( $args );
        $total_attachments = $query->found_posts;
        $attachment_ids = $query->posts;

        if ( empty( $attachment_ids ) ) {
            wp_send_json_success( array(
                'completed' => true,
                'paged'     => $paged,
                'processed' => 0,
                'total'     => $total_attachments,
                'message'   => 'Media sync complete!',
            ) );
        }

        $s3_client = \Ultimate_WP_Booster\Engine\CDN\CDNManager::get_s3_client();
        if ( ! $s3_client->is_configured() ) {
            wp_send_json_error( 'CDN credentials are missing or incomplete. Save settings first.' );
        }

        $cache_control = get_option( 'uwb_cdn_cache_control', 'public, max-age=31536000, immutable' );
        $uploads = wp_upload_dir();
        $base_dir = rtrim( str_replace( '\\', '/', $uploads['basedir'] ), '/' );
        $count = 0;

        foreach ( $attachment_ids as $id ) {
            $file = get_attached_file( $id );
            if ( $file && file_exists( $file ) ) {
                $file_norm = str_replace( '\\', '/', $file );
                if ( strpos( $file_norm, $base_dir ) === 0 ) {
                    $rel = ltrim( substr( $file_norm, strlen( $base_dir ) ), '/' );
                    $s3_key = 'wp-content/uploads/' . $rel;
                    $res = $s3_client->put_object( $file, $s3_key, '', $cache_control );
                    if ( $res ) {
                        \Ultimate_WP_Booster\Engine\CDN\CDNManager::mark_attachment_offloaded( $id, $s3_key );
                    }
                    $count++;

                    $meta = wp_get_attachment_metadata( $id );
                    if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
                        $dir = dirname( $file );
                        $rel_dir = dirname( $rel );
                        $rel_dir = ( $rel_dir === '.' ) ? '' : $rel_dir . '/';
                        foreach ( $meta['sizes'] as $info ) {
                            if ( ! empty( $info['file'] ) ) {
                                $thumb_file = $dir . '/' . $info['file'];
                                if ( file_exists( $thumb_file ) ) {
                                    $thumb_key = 'wp-content/uploads/' . $rel_dir . $info['file'];
                                    $s3_client->put_object( $thumb_file, $thumb_key, '', $cache_control );
                                }
                            }
                        }
                    }
                }
            }
        }

        $next_paged = $paged + 1;
        $is_done = ( $paged * $posts_per_page ) >= $total_attachments;

        wp_send_json_success( array(
            'completed' => $is_done,
            'paged'     => $next_paged,
            'processed' => $count,
            'total'     => $total_attachments,
            'message'   => $is_done ? 'All media library items successfully synced to CDN!' : "Synced batch {$paged} ({$count} files)...",
        ) );
    }

    private function render_cdn_distribution_card( $title, $toggle_key, $toggle_label, $toggle_desc, $events = array() ) {
        $s3_configured = \Ultimate_WP_Booster\Engine\CDN\CDNManager::get_s3_client()->is_configured();
        ?>
        <div style="background:#f8fafc; border:1px solid var(--uwb-border); border-radius:12px; padding:24px; margin-top:24px;">
            <h4 style="margin-top:0; margin-bottom:16px; font-size:14px; font-weight:700; color:var(--uwb-text); display:flex; align-items:center; gap:8px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 10h-1.26A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10z"/></svg>
                <?php echo esc_html( $title ); ?>
            </h4>

            <?php if ( ! $s3_configured ) : ?>
                <div style="background:#fffbeb; border:1px solid #fbbf24; border-radius:10px; padding:16px 20px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
                    <div style="display:flex; align-items:center; gap:12px; color:#92400e; font-size:13px; font-weight:500;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2.5" style="flex-shrink:0;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        <span><strong>CDN Storage chưa được cấu hình:</strong> Vui lòng cấu hình tài khoản Cloudflare R2 / S3 Storage trước tại <strong>[6] CDN Offload Media</strong> để bật tính năng này.</span>
                    </div>
                    <button type="button" class="button button-secondary button-small" onclick="jQuery('.uwb-nav-item[data-tab=\'page_optimizes\']').trigger('click'); jQuery('.uwb-sub-tab-item[data-subtab=\'opt_cdn_media\']').trigger('click');" style="font-weight:600; border-radius:6px; cursor:pointer;">
                        Cấu hình CDN Offload Media &rarr;
                    </button>
                </div>
            <?php else : ?>
                <?php $this->render_toggle_switch( $toggle_key, $toggle_label, $toggle_desc ); ?>

                <?php if ( ! empty( $events ) && is_array( $events ) ) : 
                    $is_active = (bool) get_option( $toggle_key, 1 );
                ?>
                    <div class="uwb-cdn-events-wrap" style="margin-top:16px; background:#fff; border:1px solid var(--uwb-border); border-radius:10px; padding:16px; <?php echo $is_active ? '' : 'display:none;'; ?>">
                        <h5 style="margin:0 0 14px 0; font-size:12px; font-weight:700; color:var(--uwb-text); text-transform:uppercase; letter-spacing:0.5px;">Sự kiện tự động đồng bộ S3 CDN (Event Actions)</h5>
                        <div style="display:flex; flex-direction:column; gap:10px;">
                            <?php foreach ( $events as $group_label => $group_data ) : ?>
                                <?php if ( is_array( $group_data ) ) : ?>
                                    <div style="display:flex; align-items:center; gap:16px; flex-wrap:wrap; padding:10px 14px; background:#f8fafc; border:1px solid var(--uwb-border); border-radius:8px;">
                                        <div style="min-width:180px; font-weight:700; font-size:13px; color:var(--uwb-text); flex-shrink:0;">
                                            <?php echo esc_html( $group_label ); ?>
                                        </div>
                                        <div style="display:flex; align-items:center; gap:18px; flex-wrap:wrap; flex:1;">
                                            <?php foreach ( $group_data as $event_key => $event_label ) : 
                                                $default_val = 1;
                                                if ( strpos( $event_key, 'attachment' ) !== false || $event_key === 'uwb_cdn_delete_local' ) {
                                                    $default_val = 0;
                                                }
                                            ?>
                                                <label style="display:inline-flex; align-items:center; gap:6px; font-size:13px; font-weight:600; cursor:pointer; color:#334155;">
                                                    <input type="hidden" name="<?php echo esc_attr( $event_key ); ?>" value="0" />
                                                    <input type="checkbox" name="<?php echo esc_attr( $event_key ); ?>" value="1" <?php checked( get_option( $event_key, $default_val ), 1 ); ?> />
                                                    <?php echo esc_html( $event_label ); ?>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php else : ?>
                                    <label style="display:flex; align-items:center; gap:8px; font-size:13px; font-weight:600; cursor:pointer; color:#334155;">
                                        <input type="hidden" name="<?php echo esc_attr( $group_label ); ?>" value="0" />
                                        <input type="checkbox" name="<?php echo esc_attr( $group_label ); ?>" value="1" <?php checked( get_option( $group_label, 1 ), 1 ); ?> />
                                        <?php echo esc_html( $group_data ); ?>
                                    </label>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }
}

/**
 * Global function to render the unified Ultimate WP Ecosystem Dashboard page.
 * Wrapped in function_exists to prevent conflicts if multiple ecosystem plugins define it.
 */
if ( ! function_exists( 'ultimate_wp_render_dashboard' ) ) {
    function ultimate_wp_render_dashboard() {
        global $_wp_admin_css_colors;
        $color_scheme = get_user_option( 'admin_color' );
        if ( empty( $color_scheme ) ) {
            $color_scheme = 'fresh';
        }

        $primary_color = '#6366f1';
        $primary_dark = '#4f46e5';
        $header_bg_start = '#1d2327';
        $header_bg_end = '#2c3338';

        if ( ! empty( $_wp_admin_css_colors ) && isset( $_wp_admin_css_colors[ $color_scheme ] ) ) {
            $colors = $_wp_admin_css_colors[ $color_scheme ]->colors;
            if ( isset( $colors[0] ) ) {
                $header_bg_start = $colors[0];
            }
            if ( isset( $colors[1] ) ) {
                $header_bg_end = $colors[1];
            }
            if ( isset( $colors[2] ) ) {
                $primary_color = $colors[2];
            }
            if ( isset( $colors[3] ) ) {
                $primary_dark = $colors[3];
            } else if ( isset( $colors[2] ) ) {
                $primary_dark = $colors[2];
            }
        }

        include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

        $ecosystem_plugins = array(
            'ultimate-wp-booster' => array(
                'name'         => 'Ultimate WP Booster',
                'description'  => 'Tối ưu hóa tốc độ tải trang toàn diện, dọn dẹp và tối ưu hóa cơ sở dữ liệu, nén ảnh, gộp và nén CSS/JS, tích hợp Redis Cache.',
                'path'         => 'ultimate-wp-booster/ultimate-wp-booster.php',
                'settings_url' => admin_url( 'admin.php?page=ultimate-wp-booster' ),
            ),
            'ultimate-wp-flatsome' => array(
                'name'         => 'Ultimate WP Flatsome',
                'description'  => 'Mở rộng khả năng thiết kế của Flatsome. Cho phép sử dụng UX Builder kéo thả layout trực tiếp cho taxonomy và single page của custom post types.',
                'path'         => 'ultimate-wp-flatsome/ultimate-wp-flatsome.php',
                'settings_url' => admin_url( 'admin.php?page=ultimate-wp-flatsome' ),
            ),
            'ultimate-wp-smtp-queue' => array(
                'name'         => 'Ultimate WP SMTP Queue',
                'description'  => 'Cấu hình gửi email qua giao thức SMTP chuyên nghiệp kết hợp hệ thống hàng đợi gửi ngầm chạy nền (Queue) hiệu năng cao, giảm tải máy chủ.',
                'path'         => 'ultimate-wp-smtp-queue/ultimate-wp-smtp-queue.php',
                'settings_url' => admin_url( 'options-general.php?page=ultimate-wp-smtp-queue' ),
            ),
        );
        ?>
        <style>
            :root {
                --uwp-primary: <?php echo esc_attr( $primary_color ); ?>;
                --uwp-primary-dark: <?php echo esc_attr( $primary_dark ); ?>;
                --uwp-success: #10b981;
                --uwp-warning: #f59e0b;
                --uwp-danger: #ef4444;
                --uwp-bg: #f8fafc;
                --uwp-card-bg: #ffffff;
                --uwp-text: #1e293b;
                --uwp-text-muted: #64748b;
                --uwp-border: #e2e8f0;
            }

            .uwp-dashboard-wrap {
                margin: 20px 20px 0 0;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
                color: var(--uwp-text);
            }

            /* Header Section */
            .uwp-header {
                background: linear-gradient(135deg, <?php echo esc_attr( $header_bg_start ); ?>, <?php echo esc_attr( $header_bg_end ); ?>);
                color: #ffffff;
                padding: 40px;
                border-radius: 12px;
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
                margin-bottom: 30px;
                position: relative;
                overflow: hidden;
            }

            .uwp-header::after {
                content: '';
                position: absolute;
                top: -50%;
                right: -20%;
                width: 300px;
                height: 300px;
                background: radial-gradient(circle, rgba(99, 102, 241, 0.15) 0%, transparent 70%);
                border-radius: 50%;
            }

            .uwp-header h1 {
                margin: 0 0 10px 0;
                font-size: 2.2rem;
                font-weight: 700;
                letter-spacing: -0.5px;
                color: #ffffff;
                display: flex;
                align-items: center;
                gap: 12px;
            }

            .uwp-header h1 span {
                background: linear-gradient(to right, #a5b4fc, #818cf8);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
            }

            .uwp-header p {
                margin: 0;
                font-size: 1.1rem;
                color: #e2e8f0;
                max-width: 600px;
                line-height: 1.6;
            }

            /* Grid Layout */
            .uwp-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 24px;
                margin-bottom: 40px;
            }

            /* Card Style */
            .uwp-card {
                background: var(--uwp-card-bg);
                border: 1px solid var(--uwp-border);
                border-radius: 12px;
                padding: 30px;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                display: flex;
                flex-direction: column;
                justify-content: space-between;
                position: relative;
            }

            .uwp-card:hover {
                transform: translateY(-4px);
                box-shadow: 0 12px 20px rgba(0, 0, 0, 0.05);
                border-color: var(--uwp-primary);
            }

            .uwp-card-title {
                font-size: 1.4rem;
                font-weight: 600;
                margin: 0 0 15px 0;
                color: var(--uwp-text);
                display: flex;
                align-items: center;
                justify-content: space-between;
            }

            .uwp-card-desc {
                font-size: 0.95rem;
                color: var(--uwp-text-muted);
                line-height: 1.6;
                margin-bottom: 25px;
                flex-grow: 1;
            }

            /* Badges */
            .uwp-status {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                font-size: 0.8rem;
                font-weight: 600;
                padding: 4px 12px;
                border-radius: 9999px;
            }

            .uwp-status-active {
                background-color: #d1fae5;
                color: #065f46;
            }

            .uwp-status-inactive {
                background-color: #fef3c7;
                color: #92400e;
            }

            .uwp-status-notinstalled {
                background-color: #f1f5f9;
                color: #475569;
            }

            .uwp-status-dot {
                width: 6px;
                height: 6px;
                border-radius: 50%;
                background-color: currentColor;
            }

            /* Buttons */
            .uwp-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                font-weight: 500;
                font-size: 0.9rem;
                padding: 10px 20px;
                border-radius: 8px;
                text-decoration: none;
                transition: all 0.2s ease;
                cursor: pointer;
                border: none;
                width: 100%;
                text-align: center;
            }

            .uwp-btn-primary {
                background-color: var(--uwp-primary);
                color: #ffffff;
            }

            .uwp-btn-primary:hover {
                background-color: var(--uwp-primary-dark);
                color: #ffffff;
            }

            .uwp-btn-secondary {
                background-color: #f1f5f9;
                color: #0f172a;
            }

            .uwp-btn-secondary:hover {
                background-color: #e2e8f0;
                color: #0f172a;
            }

            .uwp-btn-disabled {
                background-color: #f8fafc;
                color: #94a3b8;
                cursor: not-allowed;
                border: 1px dashed var(--uwp-border);
            }

            /* Info Box */
            .uwp-info-box {
                background-color: #f8fafc;
                border: 1px solid var(--uwp-border);
                border-radius: 12px;
                padding: 30px;
                margin-top: 40px;
            }

            .uwp-info-box h3 {
                margin-top: 0;
                font-size: 1.2rem;
                font-weight: 600;
            }

            .uwp-info-box p {
                color: var(--uwp-text-muted);
                line-height: 1.6;
                margin-bottom: 0;
            }
        </style>

        <div class="uwp-dashboard-wrap">
            <!-- Header -->
            <div class="uwp-header">
                <h1><span>Ultimate WP</span> Ecosystem</h1>
                <p>Hệ sinh thái các plugin tối ưu hóa và mở rộng tính năng chuyên nghiệp dành cho WordPress và theme Flatsome của bạn.</p>
            </div>

            <!-- Grid Plugins -->
            <div class="uwp-grid">
                <?php
                foreach ( $ecosystem_plugins as $slug => $data ) {
                    $is_installed = file_exists( WP_PLUGIN_DIR . '/' . $data['path'] );
                    $is_active = $is_installed && is_plugin_active( $data['path'] );

                    if ( $slug === 'ultimate-wp-flatsome' ) {
                        $settings_url = admin_url( 'admin.php?page=ultimate-wp-flatsome' );
                    } else if ( $slug === 'ultimate-wp-booster' && $is_active ) {
                        // Check if booster is updated to submenus
                        $settings_url = admin_url( 'admin.php?page=ultimate-wp-booster' );
                    } else {
                        $settings_url = $data['settings_url'];
                    }
                    ?>
                    <div class="uwp-card">
                        <div>
                            <div class="uwp-card-title">
                                <?php echo esc_html( $data['name'] ); ?>
                                <?php if ( $is_active ) : ?>
                                    <span class="uwp-status uwp-status-active">
                                        <span class="uwp-status-dot"></span> Đang hoạt động
                                    </span>
                                <?php elseif ( $is_installed ) : ?>
                                    <span class="uwp-status uwp-status-inactive">
                                        <span class="uwp-status-dot"></span> Chưa kích hoạt
                                    </span>
                                <?php else : ?>
                                    <span class="uwp-status uwp-status-notinstalled">
                                        Chưa cài đặt
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="uwp-card-desc">
                                <?php echo esc_html( $data['description'] ); ?>
                            </div>
                        </div>

                        <div class="uwp-card-actions">
                            <?php if ( $is_active ) : ?>
                                <a href="<?php echo esc_url( $settings_url ); ?>" class="uwp-btn uwp-btn-primary">
                                    <span class="dashicons dashicons-admin-settings" style="font-size:17px; line-height:22px; margin-right:4px;"></span> Cấu hình ngay
                                </a>
                            <?php elseif ( $is_installed ) : ?>
                                <?php
                                $activate_url = wp_nonce_url( admin_url( 'plugins.php?action=activate&plugin=' . $data['path'] ), 'activate-plugin_' . $data['path'] );
                                ?>
                                <a href="<?php echo esc_url( $activate_url ); ?>" class="uwp-btn uwp-btn-secondary" style="background-color: #fef3c7; color: #d97706;">
                                    <span class="dashicons dashicons-admin-plugins" style="font-size:17px; line-height:22px; margin-right:4px;"></span> Kích hoạt Plugin
                                </a>
                            <?php else : ?>
                                <button class="uwp-btn uwp-btn-disabled" disabled>
                                    Chưa cài đặt Plugin
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php
                }
                ?>
            </div>

            <!-- Ecosystem Info -->
            <div class="uwp-info-box">
                <h3>Về hệ sinh thái Ultimate WP Plugins</h3>
                <p>Hệ sinh thái Ultimate WP được xây dựng với mục tiêu mang lại hiệu năng cao nhất, giao diện trực quan thân thiện và khả năng tương thích tuyệt vời cho các website chạy mã nguồn WordPress và Flatsome. Toàn bộ các plugin đều được tối ưu hóa sâu ở mức mã nguồn để đảm bảo tốc độ tải trang nhanh nhất.</p>
            </div>
        </div>
        <?php
    }
}
