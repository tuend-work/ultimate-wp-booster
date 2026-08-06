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
        add_action( 'wp_ajax_uwb_clear_critical_css_cache', array( $this, 'ajax_clear_critical_css_cache' ) );
        add_action( 'wp_ajax_uwb_save_viewport_data', array( 'Ultimate_WP_Booster\Engine\Optimization\ViewportScreen', 'ajax_save_viewport_data' ) );
        add_action( 'wp_ajax_nopriv_uwb_save_viewport_data', array( 'Ultimate_WP_Booster\Engine\Optimization\ViewportScreen', 'ajax_save_viewport_data' ) );
        add_action( 'wp_ajax_uwb_get_database_stats', array( $this, 'ajax_get_database_stats' ) );
        add_action( 'wp_ajax_uwb_optimize_database', array( $this, 'ajax_optimize_database' ) );
        add_action( 'wp_ajax_uwb_get_redis_memory_info', array( $this, 'ajax_get_redis_memory_info' ) );
        // Invalidate cached file count transient when cache is purged
        add_action( 'uwb_after_purge_all', static function() {
            delete_transient( 'uwb_dashboard_cache_file_count' );
        } );

        $options_to_sync = array(
            // Module enable flags
            'uwb_module_cache_enabled',
            'uwb_module_preload_enabled',
            'uwb_module_optimizer_enabled',
            'uwb_module_cdn_enabled',
            'uwb_module_media_opt_enabled',
            'uwb_module_general_enabled',
            'uwb_module_object_cache_enabled',
            'uwb_module_html_enabled',
            'uwb_module_css_enabled',
            'uwb_module_js_enabled',
            'uwb_module_font_enabled',
            'uwb_module_database_enabled',
            // Cache settings
            'uwb_cache_page_enabled',
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
            'uwb_html_lazy_load_elements_enabled',
            'uwb_html_lazy_load_elements',
            'uwb_html_lazy_load_elements_excludes',
            'uwb_media_lazy_load_images',
            'uwb_media_optimize_viewport_images',
            'uwb_media_lazy_load_iframes',
            'uwb_media_image_placeholder',
            'uwb_media_add_missing_sizes',
            'uwb_media_lazy_load_excludes',
            'uwb_media_lazy_load_class_excludes',
            'uwb_tuning_css_excludes',
            'uwb_tuning_js_excludes',
            'uwb_tuning_js_defer_excludes',
            'uwb_tuning_critical_css',
            'uwb_auto_critical_css',
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
            'uwb_cdn_enabled',
            'uwb_cdn_provider',
            'uwb_cdn_account_id',
            'uwb_cdn_access_key',
            'uwb_cdn_secret_key',
            'uwb_cdn_bucket',
            'uwb_cdn_endpoint',
            'uwb_cdn_region',
            'uwb_cdn_custom_domain',
            'uwb_cdn_media_custom_domain',
            'uwb_cdn_cache_control',
            'uwb_cdn_file_types_images',
            'uwb_cdn_file_types_css',
            'uwb_cdn_file_types_js',
            'uwb_cdn_file_types_fonts',
            'uwb_cdn_file_types_media',
            'uwb_cdn_auto_upload',
            'uwb_cdn_auto_upload_combined',
            'uwb_cdn_auto_purge_minified',
            'uwb_cdn_auto_delete',
            'uwb_cdn_delete_local',
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
            'uwb_cdn_auto_rewrite_font_urls',
            'uwb_cf_enabled',
            'uwb_cf_zone_id',
            'uwb_cf_api_token',
            'uwb_cf_auto_purge_on_clear',
            'uwb_general_disable_emojis',
            'uwb_general_disable_dashicons',
            'uwb_general_disable_embeds',
            'uwb_general_disable_xmlrpc',
            'uwb_general_remove_jquery_migrate',
            'uwb_general_hide_wp_version',
            'uwb_general_remove_wlwmanifest',
            'uwb_general_remove_rsd',
            'uwb_general_remove_shortlink',
            'uwb_general_disable_rss_feeds',
            'uwb_general_remove_rss_feed_links',
            'uwb_general_disable_self_pingbacks',
            'uwb_general_disable_rest_api',
            'uwb_general_remove_rest_api_links',
            'uwb_general_disable_google_maps',
            'uwb_general_disable_password_strength_meter',
            'uwb_general_disable_comments',
            'uwb_general_remove_comment_urls',
            'uwb_general_add_blank_favicon',
            'uwb_general_remove_global_styles',
            'uwb_general_disable_heartbeat',
            'uwb_general_heartbeat_frequency',
            'uwb_general_limit_post_revisions',
            'uwb_general_autosave_interval',
        );
        foreach ( $options_to_sync as $opt ) {
            add_action( "update_option_{$opt}", array( $this, 'write_config_file_and_purge' ) );
            add_action( "add_option_{$opt}", array( $this, 'write_config_file_and_purge' ) );
        }

        // Also sync config when WordPress timezone settings change
        add_action( 'update_option_timezone_string', array( $this, 'write_config_file_and_purge' ) );
        add_action( 'add_option_timezone_string', array( $this, 'write_config_file_and_purge' ) );
        add_action( 'update_option_gmt_offset', array( $this, 'write_config_file_and_purge' ) );
        add_action( 'add_option_gmt_offset', array( $this, 'write_config_file_and_purge' ) );

        // Auto-update .htaccess when Preload Engine mode changes (LiteSpeed Native Crawler support)
        add_action( 'update_option_uwb_preload_enabled', array( '\Ultimate_WP_Booster\Engine\Activation\Activation', 'update_litespeed_htaccess' ) );
        add_action( 'add_option_uwb_preload_enabled', array( '\Ultimate_WP_Booster\Engine\Activation\Activation', 'update_litespeed_htaccess' ) );

        // Redis AJAX hooks
        add_action( 'wp_ajax_uwb_test_redis_connection', array( $this, 'ajax_test_redis_connection' ) );
        add_action( 'wp_ajax_uwb_flush_redis_cache', array( $this, 'ajax_flush_redis_cache' ) );
        add_action( 'wp_ajax_uwb_clear_preload_log', array( $this, 'ajax_clear_preload_log' ) );
        add_action( 'wp_ajax_uwb_batch_optimize_images', array( $this, 'ajax_batch_optimize_images' ) );
        add_action( 'wp_ajax_uwb_optimize_single_attachment', array( $this, 'ajax_optimize_single_attachment' ) );
        add_action( 'wp_ajax_uwb_upload_single_attachment', array( $this, 'ajax_upload_single_attachment' ) );
        add_action( 'wp_ajax_uwb_download_single_attachment', array( $this, 'ajax_download_single_attachment' ) );
        add_action( 'wp_ajax_uwb_restore_single_attachment', array( $this, 'ajax_restore_single_attachment' ) );
        add_action( 'wp_ajax_uwb_clear_cdn_cache', array( $this, 'ajax_clear_cdn_cache' ) );
        add_action( 'admin_init', array( $this, 'handle_import_export' ) );
    }

    public function write_config_file_and_purge() {
        CacheManager::write_config_file( true );
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
        register_setting( 'uwb_settings_group', 'uwb_optimize_logged_in', 'intval' );
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
        register_setting( 'uwb_settings_group', 'uwb_preload_user_agent', 'sanitize_text_field' );
        register_setting( 'uwb_settings_group', 'uwb_preload_custom_cookies', 'sanitize_textarea_field' );
        register_setting( 'uwb_settings_group', 'uwb_preload_custom_headers', 'sanitize_textarea_field' );
        register_setting( 'uwb_settings_group', 'uwb_important_sitemap_enabled', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_imp_homepage_links', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_imp_taxonomies_enabled', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_imp_taxonomy_mode', 'sanitize_text_field' );
        register_setting( 'uwb_settings_group', 'uwb_imp_taxonomy_terms', array( $this, 'sanitize_taxonomy_terms_array' ) );
        register_setting( 'uwb_settings_group', 'uwb_preload_usleep', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_preload_run_duration', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_preload_run_interval', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_preload_crawl_interval', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_preload_threads', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_preload_request_timeout', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_preload_server_load_limit', 'floatval' );
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
        register_setting( 'uwb_settings_group', 'uwb_html_lazy_load_elements_enabled', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_html_lazy_load_elements', 'sanitize_textarea_field' );
        register_setting( 'uwb_settings_group', 'uwb_html_lazy_load_elements_excludes', 'sanitize_textarea_field' );

        register_setting( 'uwb_settings_group', 'uwb_media_lazy_load_images', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_media_optimize_viewport_images', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_media_lazy_load_iframes', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_media_image_placeholder', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_media_add_missing_sizes', 'intval' );

        register_setting( 'uwb_settings_group', 'uwb_media_lazy_load_excludes', 'sanitize_textarea_field' );
        register_setting( 'uwb_settings_group', 'uwb_media_lazy_load_class_excludes', 'sanitize_textarea_field' );

        // Image Optimization Settings
        register_setting( 'uwb_settings_group', 'uwb_media_opt_enabled', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_media_opt_backup_bak', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_media_opt_quality', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_media_opt_format', 'sanitize_text_field' );
        register_setting( 'uwb_settings_group', 'uwb_media_opt_mode', 'sanitize_text_field' );
        register_setting( 'uwb_settings_group', 'uwb_media_opt_mode_sidecar', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_media_opt_mode_overwrite', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_media_opt_mode_replace_ext', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_img_opt_event_upload', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_img_opt_event_edit', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_img_opt_event_get_url', 'intval' );

        // Module Enable/Disable Flags
        register_setting( 'uwb_settings_group', 'uwb_module_cache_enabled', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_module_preload_enabled', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_module_optimizer_enabled', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_module_cdn_enabled', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_module_media_opt_enabled', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_module_general_enabled', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_module_object_cache_enabled', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_module_html_enabled', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_module_css_enabled', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_module_js_enabled', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_module_font_enabled', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_module_database_enabled', 'intval' );

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
        register_setting( 'uwb_settings_group', 'uwb_cdn_media_custom_domain', 'esc_url_raw' );
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
        register_setting( 'uwb_settings_group', 'uwb_auto_critical_css', 'intval' );
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

        // General Tab Settings
        register_setting( 'uwb_settings_group', 'uwb_general_disable_emojis', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_general_disable_dashicons', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_general_disable_embeds', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_general_disable_xmlrpc', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_general_remove_jquery_migrate', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_general_hide_wp_version', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_general_remove_wlwmanifest', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_general_remove_rsd', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_general_remove_shortlink', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_general_disable_rss_feeds', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_general_remove_rss_feed_links', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_general_disable_self_pingbacks', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_general_disable_rest_api', 'sanitize_text_field' );
        register_setting( 'uwb_settings_group', 'uwb_general_remove_rest_api_links', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_general_disable_google_maps', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_general_disable_password_strength_meter', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_general_disable_comments', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_general_remove_comment_urls', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_general_add_blank_favicon', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_general_remove_global_styles', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_general_disable_heartbeat', 'sanitize_text_field' );
        register_setting( 'uwb_settings_group', 'uwb_general_heartbeat_frequency', 'sanitize_text_field' );
        register_setting( 'uwb_settings_group', 'uwb_general_limit_post_revisions', 'sanitize_text_field' );
        register_setting( 'uwb_settings_group', 'uwb_general_autosave_interval', 'sanitize_text_field' );
    }

    public function sanitize_taxonomy_terms_array( $input ) {
        if ( ! is_array( $input ) ) {
            return array();
        }
        return array_map( 'sanitize_text_field', $input );
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

    public function render_taxonomy_terms_checklist() {
        $selected_terms = get_option( 'uwb_imp_taxonomy_terms', array() );
        if ( ! is_array( $selected_terms ) ) {
            $selected_terms = array();
        }

        $taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );
        if ( empty( $taxonomies ) ) {
            echo '<p style="color:var(--uwb-text-muted);">No public taxonomies found.</p>';
            return;
        }

        echo '<div style="display:flex; flex-direction:column; gap:16px; margin-top:12px;">';
        foreach ( $taxonomies as $tax_slug => $tax ) {
            $terms = get_terms( array(
                'taxonomy'   => $tax_slug,
                'hide_empty' => false,
                'number'     => 100,
            ) );
            if ( empty( $terms ) || is_wp_error( $terms ) ) continue;

            echo '<div style="background:#fff; border:1px solid var(--uwb-border); border-radius:8px; padding:16px;">';
            echo '<strong style="font-size:13px; color:var(--uwb-primary); display:block; margin-bottom:10px;">🏷️ ' . esc_html( $tax->label ) . ' (' . esc_html( $tax_slug ) . ')</strong>';
            echo '<div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap:10px;">';
            foreach ( $terms as $term ) {
                $term_key = $tax_slug . ':' . $term->term_id;
                $checked = in_array( $term_key, $selected_terms, true ) ? 'checked' : '';
                echo '<label style="display:flex; align-items:center; gap:8px; font-size:12.5px; cursor:pointer; color:var(--uwb-text);">';
                echo '<input type="checkbox" name="uwb_imp_taxonomy_terms[]" value="' . esc_attr( $term_key ) . '" ' . $checked . ' />';
                echo esc_html( $term->name );
                echo '</label>';
            }
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
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

        // Auto-migrate column — only run once (flag stored in options)
        if ( ! get_option( 'uwb_db_priority_migrated_v1' ) ) {
            $row = $wpdb->get_row( $wpdb->prepare( "SHOW COLUMNS FROM {$table_name} LIKE %s", 'priority' ) );
            if ( $row && strpos( strtolower( $row->Type ), 'tinyint' ) !== false ) {
                $wpdb->query( "ALTER TABLE {$table_name} MODIFY COLUMN priority int(11) NOT NULL DEFAULT 0" );
            }
            update_option( 'uwb_db_priority_migrated_v1', 1, false );
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
                } elseif ( $msg === 'cdn_cache_cleared' ) {
                    echo '<div class="notice notice-success is-dismissible"><p><strong>Ultimate WP Booster:</strong> CDN Cache cleared successfully!</p></div>';
                }
            } );
        }
    }

    public function ajax_clear_cdn_cache() {
        check_ajax_referer( 'uwb_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied' ) );
        }

        \Ultimate_WP_Booster\Engine\CDN\CDNManager::clear_cdn_cache();

        wp_send_json_success( array( 'message' => '☁️ Đã xóa CDN Cache thành công!' ) );
    }

    public function ajax_get_redis_memory_info() {
        check_ajax_referer( 'uwb_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied' ) );
        }

        if ( ! class_exists( 'Redis' ) ) {
            wp_send_json_error( array( 'message' => 'Redis extension not available' ) );
        }

        try {
            $host   = get_option( 'uwb_redis_host', '127.0.0.1' );
            $port   = intval( get_option( 'uwb_redis_port', 6379 ) );
            $pw     = get_option( 'uwb_redis_password', '' );
            $db     = intval( get_option( 'uwb_redis_db', 0 ) );

            $r = new \Redis();
            if ( ! @$r->connect( $host, $port, 1 ) ) {
                wp_send_json_error( array( 'message' => 'Cannot connect to Redis' ) );
            }
            if ( $pw ) @$r->auth( $pw );
            if ( $db ) @$r->select( $db );

            $info = $r->info( 'memory' );
            $used_mb = isset( $info['used_memory'] ) ? round( $info['used_memory'] / 1048576, 1 ) : 0;
            $max_mb  = ( isset( $info['maxmemory'] ) && $info['maxmemory'] > 0 )
                ? round( $info['maxmemory'] / 1048576, 0 ) . 'MB'
                : 'unlimited';

            wp_send_json_success( array(
                'used' => $used_mb,
                'max'  => $max_mb,
                'text' => "{$used_mb}MB / {$max_mb}",
            ) );
        } catch ( \Exception $e ) {
            wp_send_json_error( array( 'message' => $e->getMessage() ) );
        }
    }

    public function ajax_clear_critical_css_cache() {
        check_ajax_referer( 'uwb_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied' ) );
        }

        if ( class_exists( 'Ultimate_WP_Booster\Engine\Optimization\CSS\CriticalCSS' ) ) {
            \Ultimate_WP_Booster\Engine\Optimization\CSS\CriticalCSS::purge_cache();
        }

        wp_send_json_success( array( 'message' => '⚡ Đã xóa Critical CSS Cache thành công!' ) );
    }

    public function ajax_get_database_stats() {
        check_ajax_referer( 'uwb_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied' ) );
        }

        if ( ! class_exists( 'Ultimate_WP_Booster\Engine\Optimization\DatabaseOptimizer' ) ) {
            require_once dirname( __DIR__ ) . '/Optimization/DatabaseOptimizer.php';
        }

        $stats = \Ultimate_WP_Booster\Engine\Optimization\DatabaseOptimizer::get_stats();
        wp_send_json_success( $stats );
    }

    public function ajax_optimize_database() {
        check_ajax_referer( 'uwb_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied' ) );
        }

        if ( ! class_exists( 'Ultimate_WP_Booster\Engine\Optimization\DatabaseOptimizer' ) ) {
            require_once dirname( __DIR__ ) . '/Optimization/DatabaseOptimizer.php';
        }

        $options = isset( $_POST['options'] ) ? $_POST['options'] : array();
        $results = \Ultimate_WP_Booster\Engine\Optimization\DatabaseOptimizer::optimize( $options );

        wp_send_json_success( array(
            'message' => '🎉 Database optimized successfully!',
            'details' => $results
        ) );
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

    /**
     * Render module enable/disable banner với iOS-style toggle.
     * Toggle lập tức ẩn/hiện nội dung tab — vẫn lưu giá trị khi Save Settings.
     *
     * @param string $option_key  Ví dụ: 'uwb_module_cdn_enabled'
     * @param string $module_name Ví dụ: '☁️ CDN & S3 Storage'
     * @param string $description Mô tả ngắn về module
     */
    private function render_module_banner( $option_key, $module_name, $description = '' ) {
        $is_enabled = (int) get_option( $option_key, 1 );
        $content_id = 'uwb-mcontent-' . esc_attr( str_replace( array( 'uwb_module_', '_enabled' ), '', $option_key ) );
        ?>
        <div class="uwb-module-banner-inline"
             data-content-id="<?php echo esc_attr( $content_id ); ?>"
             style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px; margin-bottom:24px;
                 border:1px solid var(--uwb-border); border-radius:12px; padding:18px 24px; background:#ffffff; box-shadow:0 1px 3px rgba(0,0,0,0.02);">
            <div style="flex:1; min-width:200px;">
                <h2 style="margin:0; font-size:18px; font-weight:700; color:var(--uwb-text);"><?php echo esc_html( $module_name ); ?></h2>
                <?php if ( ! empty( $description ) ) : ?>
                    <p style="color:var(--uwb-text-muted); margin:4px 0 0 0; font-size:13px; line-height:1.4;"><?php echo esc_html( $description ); ?></p>
                <?php endif; ?>
            </div>
            <!-- Toggle Switch (Segmented OFF/ON) -->
            <div style="flex-shrink:0;">
                <div class="uwb-toggle-container" style="background:#f1f5f9; border:1px solid var(--uwb-border); border-radius:30px; display:inline-flex; padding:2px;">
                    <label class="uwb-toggle-btn <?php echo ! $is_enabled ? 'active' : ''; ?>" style="border-radius:20px; padding:6px 18px; font-weight:700; font-size:11px; cursor:pointer;">
                        <input type="radio" name="<?php echo esc_attr( $option_key ); ?>" value="0" <?php checked( $is_enabled, 0 ); ?> class="uwb-module-toggle-cb" style="display:none !important;"> OFF
                    </label>
                    <label class="uwb-toggle-btn <?php echo $is_enabled ? 'active' : ''; ?>" style="border-radius:20px; padding:6px 18px; font-weight:700; font-size:11px; cursor:pointer;">
                        <input type="radio" name="<?php echo esc_attr( $option_key ); ?>" value="1" <?php checked( $is_enabled, 1 ); ?> class="uwb-module-toggle-cb" style="display:none !important;"> ON
                    </label>
                </div>
            </div>
        </div>
        <!-- Content wrapper: shown/hidden by JS toggle, inputs keep values -->
        <div id="<?php echo esc_attr( $content_id ); ?>"
             class="uwb-module-content-wrap"
             style="<?php echo ! $is_enabled ? 'display:none;' : ''; ?>">
        <?php
        // NOTE: This div is closed in render_module_banner_end()
        // Called at the END of each tab/subtab content block
    }

    /**
     * Đóng div wrapper mở bởi render_module_banner().
     * Phải gọi ở cuối mỗi tab/subtab content có module banner.
     */
    private function render_module_banner_end() {
        echo '</div><!-- /.uwb-module-content-wrap -->';
    }

    /**
     * Render header submodule với toggle ON/OFF nằm cùng hàng (ngang hàng) với sub title.
     * Không có phần giải thích rườm rà phía dưới.
     *
     * @param string $option_key  Ví dụ: 'uwb_browser_cache_enabled'
     * @param string $sub_title   Ví dụ: 'Browser Cache Settings'
     * @param string $icon_svg    (Optional) Mã SVG icon cho tiêu đề
     */
    private function render_submodule_header( $option_key, $sub_title, $icon_svg = '' ) {
        $is_enabled = (int) get_option( $option_key, 1 );
        $content_id = 'uwb-mcontent-' . esc_attr( str_replace( array( 'uwb_module_', '_enabled' ), '', $option_key ) );
        ?>
        <div class="uwb-submodule-header"
             data-content-id="<?php echo esc_attr( $content_id ); ?>"
             style="display:flex; align-items:center; justify-content:space-between; margin-top:0; margin-bottom:20px; gap:16px; flex-wrap:wrap;">
            <h3 style="margin:0; font-size:15px; font-weight:700; color:var(--uwb-text); display:flex; align-items:center; gap:8px;">
                <?php if ( ! empty( $icon_svg ) ) { echo $icon_svg; } ?>
                <span><?php echo esc_html( $sub_title ); ?></span>
            </h3>
            <div style="flex-shrink:0;">
                <div class="uwb-toggle-container" style="background:#f1f5f9; border:1px solid var(--uwb-border); border-radius:30px; display:inline-flex; padding:2px;">
                    <label class="uwb-toggle-btn <?php echo ! $is_enabled ? 'active' : ''; ?>" style="border-radius:20px; padding:6px 18px; font-weight:700; font-size:11px; cursor:pointer;">
                        <input type="radio" name="<?php echo esc_attr( $option_key ); ?>" value="0" <?php checked( $is_enabled, 0 ); ?> class="uwb-module-toggle-cb" style="display:none !important;"> OFF
                    </label>
                    <label class="uwb-toggle-btn <?php echo $is_enabled ? 'active' : ''; ?>" style="border-radius:20px; padding:6px 18px; font-weight:700; font-size:11px; cursor:pointer;">
                        <input type="radio" name="<?php echo esc_attr( $option_key ); ?>" value="1" <?php checked( $is_enabled, 1 ); ?> class="uwb-module-toggle-cb" style="display:none !important;"> ON
                    </label>
                </div>
            </div>
        </div>
        <div id="<?php echo esc_attr( $content_id ); ?>"
             class="uwb-module-content-wrap"
             style="<?php echo ! $is_enabled ? 'display:none;' : ''; ?>">
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

    private function render_select_setting( $option_name, $label_desc, $options, $detailed_desc = '' ) {
        $val = get_option( $option_name, 'default' );
        ?>
        <div class="uwb-opt-row" style="display: flex; justify-content: space-between; align-items: flex-start; background: #fff; border: 1px solid var(--uwb-border); border-radius: 8px; padding: 20px; margin-bottom: 16px; gap: 20px; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 250px;">
                <strong style="font-size: 14px; color: var(--uwb-text); display: block; margin-bottom: 4px;">
                    <?php echo esc_html( $label_desc ); ?>
                </strong>
                <?php if ( ! empty( $detailed_desc ) ) : ?>
                    <span class="description" style="font-size: 12.5px; color: var(--uwb-text-muted); line-height: 1.4; display: block;"><?php echo wp_kses_post( $detailed_desc ); ?></span>
                <?php endif; ?>
            </div>
            <div style="flex-shrink: 0;">
                <select name="<?php echo esc_attr( $option_name ); ?>" style="min-width: 200px; border: 1px solid var(--uwb-border); border-radius: 8px; padding: 8px; font-size: 13px;">
                    <?php foreach ( $options as $k => $v ) : ?>
                        <option value="<?php echo esc_attr( $k ); ?>" <?php selected( $val, $k ); ?>><?php echo esc_html( $v ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
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
        include __DIR__ . '/Views/assets.php';
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
        include __DIR__ . '/Views/page_shell_open.php';
        include __DIR__ . '/Views/tab_dashboard.php';

        // TAB 1: Cache Settings
        echo '<div id="tab-cache_settings" class="uwb-tab-content">';
        include __DIR__ . '/Views/tab_cache_nav.php';
        include __DIR__ . '/Views/subtab_browser_cache.php';
        include __DIR__ . '/Views/subtab_page_cache.php';
        include __DIR__ . '/Views/subtab_cdn_cache.php';
        include __DIR__ . '/Views/subtab_webserver_cache.php';
        include __DIR__ . '/Views/subtab_object_cache.php';
        include __DIR__ . '/Views/subtab_opcache.php';
        $this->render_module_banner_end();
        echo '</div>';

        // TAB 2: Preload Module
        echo '<div id="tab-preload_settings" class="uwb-tab-content">';
        include __DIR__ . '/Views/tab_preload_nav.php';
        include __DIR__ . '/Views/subtab_preload_status.php';
        include __DIR__ . '/Views/subtab_preload_settings.php';
        include __DIR__ . '/Views/subtab_preload_sitemap.php';
        include __DIR__ . '/Views/subtab_preload_simulation.php';
        $this->render_module_banner_end();
        echo '</div>';

        // TAB 3: Page Optimizes
        echo '<div id="tab-page_optimizes" class="uwb-tab-content">';
        include __DIR__ . '/Views/tab_page_optimizes_nav.php';
        include __DIR__ . '/Views/subtab_opt_general.php';
        include __DIR__ . '/Views/subtab_opt_html.php';
        include __DIR__ . '/Views/subtab_opt_css.php';
        include __DIR__ . '/Views/subtab_opt_js.php';
        include __DIR__ . '/Views/subtab_opt_media.php';
        include __DIR__ . '/Views/subtab_opt_font.php';
        include __DIR__ . '/Views/subtab_opt_cdn_media.php';
        include __DIR__ . '/Views/subtab_opt_database.php';
        $this->render_module_banner_end();
        echo '</div>';

        // TAB 4: Advanced Tools
        echo '<div id="tab-advanced_tools" class="uwb-tab-content">';
        include __DIR__ . '/Views/tab_advanced_nav.php';
        include __DIR__ . '/Views/subtab_advanced_debug.php';
        include __DIR__ . '/Views/subtab_plugin_load_manager.php';
        echo '</div>';

        include __DIR__ . '/Views/page_shell_close.php';
        include __DIR__ . '/Views/scripts.php';
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
