<?php
namespace Ultimate_WP_Booster\Engine\Activation;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class Activation {

    public static function activate_plugin() {
        self::activate();
    }

    public static function activate() {
        global $wpdb;

        // 1. Create the database table for preloading queue
        $table_name = $wpdb->prefix . 'ultimate_wp_booster_queue';
        $charset_collate = $wpdb->get_charset_collate();

        if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) === $table_name ) {
            $index_exists = $wpdb->get_results( "SHOW INDEX FROM $table_name WHERE Key_name = 'url'" );
            if ( ! empty( $index_exists ) ) {
                $is_unique = (int) $index_exists[0]->Non_unique === 0;
                if ( ! $is_unique ) {
                    $wpdb->query( "ALTER TABLE $table_name DROP INDEX url" );
                }
            }
        }

        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            url varchar(2083) NOT NULL,
            priority int(11) NOT NULL DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'pending',
            attempts int(11) NOT NULL DEFAULT 0,
            last_attempt datetime DEFAULT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY url (url(191)),
            KEY status_priority (status, priority, id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        $wpdb->query( "ALTER TABLE $table_name MODIFY COLUMN priority int(11) NOT NULL DEFAULT 0;" );

        // 2. Set default options
        if ( get_option( 'uwb_cache_lifespan' ) === false ) {
            update_option( 'uwb_cache_lifespan', 0 );
        }

        if ( get_option( 'uwb_preload_enabled' ) === false ) {
            update_option( 'uwb_preload_enabled', 0 );
        }

        // Module enable flags — mặc định TẮT khi cài lần đầu.
        // Dùng get_option() === false để không ghi đè khi re-activate / update plugin.
        $module_defaults = array(
            'uwb_module_cache_enabled'        => 0,  // Page Cache & Browser Cache
            'uwb_module_preload_enabled'      => 0,  // Sitemap Preloader
            'uwb_module_optimizer_enabled'    => 0,  // JS/CSS/HTML Optimizer
            'uwb_module_cdn_enabled'          => 0,  // CDN & S3 Storage
            'uwb_module_media_opt_enabled'    => 0,  // Image Optimizer
            'uwb_module_general_enabled'      => 0,  // General WP Tweaks
            'uwb_module_object_cache_enabled' => 0,  // Object Cache Subtab
            'uwb_module_html_enabled'         => 0,  // HTML Optimizer Subtab
            'uwb_module_css_enabled'          => 0,  // CSS Optimizer Subtab
            'uwb_module_js_enabled'           => 0,  // JS Optimizer Subtab
            'uwb_module_font_enabled'         => 0,  // Font Optimizer Subtab
            'uwb_module_database_enabled'     => 0,  // Database Optimizer Subtab
        );
        foreach ( $module_defaults as $key => $default_val ) {
            if ( get_option( $key ) === false ) {
                update_option( $key, $default_val, false );
            }
        }

        if ( get_option( 'uwb_preload_batch_size' ) === false ) {
            update_option( 'uwb_preload_batch_size', 5 );
        }

        if ( get_option( 'uwb_preload_sitemap' ) === false ) {
            update_option( 'uwb_preload_sitemap', "/important-sitemap.xml\n/wp-sitemap.xml" );
        }

        if ( get_option( 'uwb_cache_logged_in' ) === false ) {
            update_option( 'uwb_cache_logged_in', 0 );
        }

        if ( get_option( 'uwb_browser_cache_enabled' ) === false ) {
            update_option( 'uwb_browser_cache_enabled', 1 );
        }

        if ( get_option( 'uwb_browser_cache_lifespan' ) === false ) {
            update_option( 'uwb_browser_cache_lifespan', 10 );
        }

        if ( get_option( 'uwb_ignored_query' ) === false ) {
            update_option( 'uwb_ignored_query', "utm_source\nutm_medium\nutm_campaign\nfbclid\ngclid\nage-verified" );
        }

        if ( get_option( 'uwb_cache_xml_sitemaps_lifespan' ) === false ) {
            update_option( 'uwb_cache_xml_sitemaps_lifespan', 10 );
        }

        if ( get_option( 'uwb_cache_php' ) === false ) {
            update_option( 'uwb_cache_php', 0 );
        }

        if ( get_option( 'uwb_cache_php_lifespan' ) === false ) {
            update_option( 'uwb_cache_php_lifespan', 10 );
        }

        if ( get_option( 'uwb_delay_js_exclusions' ) === false ) {
            update_option( 'uwb_delay_js_exclusions', "jquery.js\njquery.min.js\njquery-migrate\nwp-i18n\nwp-hooks\nwp-polyfill\nnoscript\nuwb-lazy" );
        }

        if ( get_option( 'uwb_tuning_js_excludes' ) === false ) {
            update_option( 'uwb_tuning_js_excludes', "jquery.js\njquery.min.js\njquery-migrate\nwp-i18n\nwp-hooks\nwp-polyfill" );
        }

        if ( get_option( 'uwb_tuning_js_defer_excludes' ) === false ) {
            update_option( 'uwb_tuning_js_defer_excludes', "jquery.js\njquery.min.js\njquery-migrate\nwp-i18n\nwp-hooks\nwp-polyfill" );
        }

        // 3. Copy drop-ins and enable WP_CACHE
        self::copy_advanced_cache_dropin();
        self::toggle_wp_cache( true );
        self::copy_object_cache_dropin();

        // 4. Schedule cleanup cron
        if ( ! wp_next_scheduled( 'uwb_clean_expired_cache' ) ) {
            wp_schedule_event( time(), 'hourly', 'uwb_clean_expired_cache' );
        }

        // 5. Write valid post IDs whitelist JSON
        \Ultimate_WP_Booster\Engine\Cache\CacheManager::write_valid_post_ids_json();

        // 6. Check for WP Rocket settings import
        if ( get_option( 'wp_rocket_settings' ) !== false ) {
            return; //temp disable
            update_option( 'uwb_show_rocket_import_prompt', 1 );
        }
    }

    public static function copy_advanced_cache_dropin() {
        $source = UWB_PLUGIN_DIR . 'templates/advanced-cache.php';
        $destination = WP_CONTENT_DIR . '/advanced-cache.php';

        if ( file_exists( $source ) ) {
            if ( ! file_exists( $destination ) || md5_file( $source ) !== md5_file( $destination ) ) {
                @copy( $source, $destination );
                if ( function_exists( 'opcache_invalidate' ) ) {
                    @opcache_invalidate( $destination, true );
                }
            }
        }

        self::update_litespeed_htaccess();
    }

    public static function update_litespeed_htaccess() {
        if ( ! \Ultimate_WP_Booster\Engine\Cache\LiteSpeedEngine::is_litespeed_server() ) {
            return;
        }

        $htaccess_path = ABSPATH . '.htaccess';
        
        // Ensure WordPress admin functions are loaded
        if ( ! function_exists( 'insert_with_markers' ) ) {
            require_once ABSPATH . 'wp-admin/includes/misc.php';
        }

        $cache_logged_in  = (int) get_option( 'uwb_cache_logged_in', 0 );
        $preload_enabled = (int) get_option( 'uwb_preload_enabled', 0 );

        if ( function_exists( 'insert_with_markers' ) ) {
            $rules = array(
                '<IfModule LiteSpeed>',
                '    CacheLookup on',
            );

            if ( $preload_enabled === 3 ) {
                $usleep = (int) get_option( 'uwb_preload_usleep', 500 );
                $load_limit = (float) get_option( 'uwb_preload_server_load_limit', 1.0 );
                $threads = (int) get_option( 'uwb_preload_threads', 3 );
                $rules[] = '    # Enable LiteSpeed Server Native Crawler Engine & Directives';
                $rules[] = '    CacheEngine on crawler';
                $rules[] = '    SetEnv CRAWLER_USLEEP ' . $usleep;
                $rules[] = '    SetEnv CRAWLER_LOAD_LIMIT ' . $load_limit;
                $rules[] = '    SetEnv CRAWLER_THREADS ' . $threads;
            }

            $rules[] = '    RewriteEngine On';

            if ( $cache_logged_in !== 2 ) {
                $rules[] = '    # Bypass LiteSpeed cache for logged-in users, commenters & WooCommerce sessions';
                $rules[] = '    RewriteCond %{HTTP_COOKIE} (uwb_logged_in|wordpress_logged_in_|comment_author_|woocommerce_items_in_cart|wp_woocommerce_session_) [NC]';
                $rules[] = '    RewriteRule .* - [E=Cache-Control:no-cache]';
            } else {
                $rules[] = '    # Bypass LiteSpeed cache for commenters & WooCommerce sessions (Per-user Vary lookup enabled for uwb_logged_in)';
                $rules[] = '    RewriteCond %{HTTP_COOKIE} (comment_author_|woocommerce_items_in_cart|wp_woocommerce_session_) [NC]';
                $rules[] = '    RewriteRule .* - [E=Cache-Control:no-cache]';
            }

            $rules[] = '    # Bypass LiteSpeed cache for POST requests, admin, page builders & API endpoints';
            $rules[] = '    RewriteCond %{REQUEST_METHOD} ^POST$ [OR]';
            $rules[] = '    RewriteCond %{QUERY_STRING} (app=uxbuilder|uxbuilder|uxb_iframe|elementor-preview|et_fb|vc_editable|ct_builder|bricks|fl_builder) [NC,OR]';
            $rules[] = '    RewriteCond %{REQUEST_URI} ^/(wp-admin|wp-json|xmlrpc\.php|uxbuilder) [NC]';
            $rules[] = '    RewriteRule .* - [E=Cache-Control:no-cache]';
            $rules[] = '</IfModule>';

            insert_with_markers( $htaccess_path, 'Ultimate WP Booster LiteSpeed', $rules );

            // Ensure the rules are at the very top of .htaccess to execute before WordPress rules
            $new_content = @file_get_contents( $htaccess_path );
            if ( $new_content ) {
                $marker_start = '# BEGIN Ultimate WP Booster LiteSpeed';
                $marker_end   = '# END Ultimate WP Booster LiteSpeed';
                $start_pos    = strpos( $new_content, $marker_start );
                $end_pos      = strpos( $new_content, $marker_end );
                
                if ( $start_pos !== false && $end_pos !== false && $end_pos > $start_pos ) {
                    $block_len = ($end_pos + strlen( $marker_end )) - $start_pos;
                    $block = substr( $new_content, $start_pos, $block_len );
                    
                    // Remove the block from its current position
                    $cleaned = str_replace( $block, '', $new_content );
                    $cleaned = trim( $cleaned );
                    
                    // Prepend it to the top
                    $final_content = $block . "\n\n" . $cleaned;
                    @file_put_contents( $htaccess_path, $final_content );
                }
            }
        }
        
        \Ultimate_WP_Booster\Engine\Cache\LiteSpeedEngine::touch_htaccess();
    }

    public static function toggle_wp_cache( $enable ) {
        $config_file = ABSPATH . 'wp-config.php';
        if ( ! file_exists( $config_file ) || ! is_writable( $config_file ) ) {
            return;
        }

        $config_content = file_get_contents( $config_file );
        $has_wp_cache = preg_match( '/define\(\s*\'WP_CACHE\'\s*,\s*(true|false)\s*\)/i', $config_content );

        if ( $enable ) {
            if ( $has_wp_cache ) {
                $config_content = preg_replace( '/define\(\s*\'WP_CACHE\'\s*,\s*false\s*\)/i', "define( 'WP_CACHE', true )", $config_content );
            } else {
                $config_content = preg_replace( '/^<\?php/i', "<?php\ndefine( 'WP_CACHE', true ); // Added by Ultimate WP Booster", $config_content, 1 );
            }
        } else {
            if ( $has_wp_cache ) {
                $config_content = preg_replace( '/define\(\s*\'WP_CACHE\'\s*,\s*true\s*\)/i', "define( 'WP_CACHE', false )", $config_content );
            }
        }

        @file_put_contents( $config_file, $config_content );
    }

    public static function copy_object_cache_dropin() {
        $type = intval( get_option( 'uwb_redis_enabled', 0 ) );
        $destination = WP_CONTENT_DIR . '/object-cache.php';

        if ( $type === 1 ) {
            $source = UWB_PLUGIN_DIR . 'templates/object-cache.php';
            if ( file_exists( $source ) ) {
                if ( ! file_exists( $destination ) || md5_file( $source ) !== md5_file( $destination ) ) {
                    @copy( $source, $destination );
                    if ( function_exists( 'opcache_invalidate' ) ) {
                        @opcache_invalidate( $destination, true );
                    }
                }
            }
        } elseif ( $type === 2 ) {
            $source = UWB_PLUGIN_DIR . 'templates/object-cache-memcached.php';
            if ( file_exists( $source ) ) {
                if ( ! file_exists( $destination ) || md5_file( $source ) !== md5_file( $destination ) ) {
                    @copy( $source, $destination );
                    if ( function_exists( 'opcache_invalidate' ) ) {
                        @opcache_invalidate( $destination, true );
                    }
                }
            }
        } else {
            self::remove_object_cache_dropin();
        }
    }

    public static function remove_object_cache_dropin() {
        $destination = WP_CONTENT_DIR . '/object-cache.php';
        if ( file_exists( $destination ) ) {
            $content = @file_get_contents( $destination );
            if ( $content && strpos( $content, 'Ultimate WP Booster Object Cache Drop-in' ) !== false ) {
                @unlink( $destination );
            }
        }
    }
}
