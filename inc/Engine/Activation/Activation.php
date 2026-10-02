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

        // 6. Write directory protection files (.htaccess and web.config)
        self::write_htaccess_protection();

        // 7. Update LiteSpeed .htaccess if applicable
        self::update_litespeed_htaccess();

        // 8. Check for WP Rocket settings import
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
    }

    public static function update_litespeed_htaccess() {
        if ( ! \Ultimate_WP_Booster\Engine\Cache\LiteSpeedEngine::is_litespeed_server() ) {
            return;
        }

        // Get true home path for .htaccess
        if ( ! function_exists( 'get_home_path' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        $home_path     = function_exists( 'get_home_path' ) ? get_home_path() : ABSPATH;
        $htaccess_path = $home_path . '.htaccess';

        // If Cache module is disabled, ensure our rules are cleanly removed
        $module_cache_enabled = (int) get_option( 'uwb_module_cache_enabled', 1 );
        if ( ! $module_cache_enabled ) {
            self::remove_litespeed_htaccess();
            return;
        }

        // Directory or file writable check
        if ( ! file_exists( $htaccess_path ) && ! is_writable( dirname( $htaccess_path ) ) ) {
            return;
        }
        if ( file_exists( $htaccess_path ) && ! is_writable( $htaccess_path ) ) {
            return;
        }

        // Open with 'c+' (read/write, does NOT truncate file on open)
        $fp = @fopen( $htaccess_path, 'c+' );
        if ( ! $fp ) {
            return;
        }

        // Acquire exclusive lock before reading or modifying to eliminate race conditions
        if ( ! flock( $fp, LOCK_EX ) ) {
            fclose( $fp );
            return;
        }

        $current_content = '';
        while ( ! feof( $fp ) ) {
            $current_content .= fread( $fp, 8192 );
        }

        // Strictly modify ONLY the Ultimate WP Booster LiteSpeed block:
        // Case 1: If our block already exists in .htaccess, replace ONLY that block in-place.
        // Case 2: If our block does not exist, prepend it to the top without touching any existing content below.
        $has_existing_block = ( strpos( $current_content, $marker_start ) !== false && strpos( $current_content, $marker_end ) !== false );

        if ( $has_existing_block ) {
            $pattern       = '/# BEGIN Ultimate WP Booster LiteSpeed\b.*?# END Ultimate WP Booster LiteSpeed/s';
            $final_content = preg_replace( $pattern, $new_block, $current_content );
        } else {
            $trimmed_current = ltrim( $current_content );
            $final_content   = ! empty( $trimmed_current ) ? $new_block . "\n\n" . $trimmed_current : $new_block . "\n";
        }

        // Compare normalized versions to avoid redundant disk writes and server restarts
        $norm_current = trim( str_replace( "\r\n", "\n", $current_content ) );
        $norm_final   = trim( str_replace( "\r\n", "\n", (string) $final_content ) );

        if ( $norm_current === $norm_final ) {
            flock( $fp, LOCK_UN );
            fclose( $fp );
            return;
        }

        ftruncate( $fp, 0 );
        rewind( $fp );
        fwrite( $fp, (string) $final_content );
        fflush( $fp );
        flock( $fp, LOCK_UN );
        fclose( $fp );

        \Ultimate_WP_Booster\Engine\Cache\LiteSpeedEngine::touch_htaccess();
    }

    public static function remove_litespeed_htaccess() {
        if ( ! function_exists( 'get_home_path' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        $home_path     = function_exists( 'get_home_path' ) ? get_home_path() : ABSPATH;
        $htaccess_path = $home_path . '.htaccess';

        if ( ! file_exists( $htaccess_path ) || ! is_writable( $htaccess_path ) ) {
            return;
        }

        $fp = @fopen( $htaccess_path, 'c+' );
        if ( ! $fp ) {
            return;
        }

        if ( ! flock( $fp, LOCK_EX ) ) {
            fclose( $fp );
            return;
        }

        $current_content = '';
        while ( ! feof( $fp ) ) {
            $current_content .= fread( $fp, 8192 );
        }

        if ( strpos( $current_content, '# BEGIN Ultimate WP Booster LiteSpeed' ) === false ) {
            flock( $fp, LOCK_UN );
            fclose( $fp );
            return;
        }

        // Strictly remove ONLY our block, leaving all other blocks (WordPress, Wordfence, etc.) completely intact
        $pattern         = '/# BEGIN Ultimate WP Booster LiteSpeed\b.*?# END Ultimate WP Booster LiteSpeed\s*/s';
        $cleaned_content = preg_replace( $pattern, '', $current_content );

        ftruncate( $fp, 0 );
        rewind( $fp );
        fwrite( $fp, (string) $cleaned_content );
        fflush( $fp );
        flock( $fp, LOCK_UN );
        fclose( $fp );

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

    public static function write_htaccess_protection() {
        $cache_dir = WP_CONTENT_DIR . '/cache';
        if ( ! file_exists( $cache_dir ) ) {
            @mkdir( $cache_dir, 0755, true );
        }

        // 1. .htaccess protection for Apache/LiteSpeed
        $htaccess_file = $cache_dir . '/.htaccess';
        if ( ! file_exists( $htaccess_file ) ) {
            $content = "# BEGIN Ultimate WP Booster Protection\n" .
                       "<FilesMatch \"\\.(php|json|log)$\">\n" .
                       "    <IfModule mod_authz_core.c>\n" .
                       "        Require all denied\n" .
                       "    </IfModule>\n" .
                       "    <IfModule !mod_authz_core.c>\n" .
                       "        Order deny,allow\n" .
                       "        Deny from all\n" .
                       "    </IfModule>\n" .
                       "</FilesMatch>\n" .
                       "# END Ultimate WP Booster Protection\n";
            @file_put_contents( $htaccess_file, $content );
        }

        // 2. web.config protection for IIS
        $web_config_file = $cache_dir . '/web.config';
        if ( ! file_exists( $web_config_file ) ) {
            $content = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n" .
                       "<configuration>\n" .
                       "  <system.webServer>\n" .
                       "    <security>\n" .
                       "      <requestFiltering>\n" .
                       "        <fileExtensions>\n" .
                       "          <add fileExtension=\".php\" allowed=\"false\" />\n" .
                       "          <add fileExtension=\".json\" allowed=\"false\" />\n" .
                       "          <add fileExtension=\".log\" allowed=\"false\" />\n" .
                       "        </fileExtensions>\n" .
                       "      </requestFiltering>\n" .
                       "    </security>\n" .
                       "  </system.webServer>\n" .
                       "</configuration>\n";
            @file_put_contents( $web_config_file, $content );
        }
    }
}
