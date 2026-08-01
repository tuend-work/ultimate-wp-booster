<?php
/**
 * Fired during plugin activation
 */

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class Uwb_Activator {

    public static function activate() {
        global $wpdb;

        // 1. Create the database table for preloading queue
        $table_name = $wpdb->prefix . 'ultimate_wp_booster_queue';
        $charset_collate = $wpdb->get_charset_collate();

        // Drop old non-unique index if it exists, to allow UNIQUE KEY index creation
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

        // Ensure column type is updated to int(11) in case dbDelta skips type modification
        $wpdb->query( "ALTER TABLE $table_name MODIFY COLUMN priority int(11) NOT NULL DEFAULT 0;" );

        // 2. Set default options
        if ( get_option( 'uwb_cache_lifespan' ) === false ) {
            update_option( 'uwb_cache_lifespan', 0 ); // 0 minutes (unlimited)
        }

        if ( get_option( 'uwb_preload_enabled' ) === false ) {
            update_option( 'uwb_preload_enabled', 0 ); // Disabled by default
        }

        if ( get_option( 'uwb_preload_batch_size' ) === false ) {
            update_option( 'uwb_preload_batch_size', 5 ); // 5 URLs per batch
        }

        if ( get_option( 'uwb_preload_sitemap' ) === false ) {
            update_option( 'uwb_preload_sitemap', "/important-sitemap.xml\n/wp-sitemap.xml" );
        }

        if ( get_option( 'uwb_cache_logged_in' ) === false ) {
            update_option( 'uwb_cache_logged_in', 0 ); // Disabled by default
        }

        if ( get_option( 'uwb_browser_cache_enabled' ) === false ) {
            update_option( 'uwb_browser_cache_enabled', 1 ); // Enabled by default
        }

        if ( get_option( 'uwb_browser_cache_lifespan' ) === false ) {
            update_option( 'uwb_browser_cache_lifespan', 10 ); // 10 minutes
        }

        if ( get_option( 'uwb_ignored_query' ) === false ) {
            update_option( 'uwb_ignored_query', "utm_source\nutm_medium\nutm_campaign\nfbclid\ngclid\nage-verified" );
        }

        if ( get_option( 'uwb_cache_xml_sitemaps_lifespan' ) === false ) {
            update_option( 'uwb_cache_xml_sitemaps_lifespan', 10 ); // 10 minutes by default
        }

        if ( get_option( 'uwb_cache_php' ) === false ) {
            update_option( 'uwb_cache_php', 0 ); // Disabled by default
        }

        if ( get_option( 'uwb_cache_php_lifespan' ) === false ) {
            update_option( 'uwb_cache_php_lifespan', 10 ); // 10 minutes by default
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

        // 3. Copy advanced-cache.php drop-in
        self::copy_advanced_cache_dropin();

        // 4. Enable WP_CACHE in wp-config.php
        self::toggle_wp_cache( true );

        // 5. Copy object-cache.php drop-in if configured
        self::copy_object_cache_dropin();

        // 6. Schedule expired cache cleanup cron
        if ( ! wp_next_scheduled( 'uwb_clean_expired_cache' ) ) {
            wp_schedule_event( time(), 'hourly', 'uwb_clean_expired_cache' );
        }

        // 7. Write valid post IDs JSON
        require_once dirname( __FILE__ ) . '/class-uwb-cache.php';
        Uwb_Cache::write_valid_post_ids_json();

        // 8. Check for WP Rocket settings to prompt for import
        if ( get_option( 'wp_rocket_settings' ) !== false ) {
            update_option( 'uwb_show_rocket_import_prompt', 1 );
        }
    }

    /**
     * Copy advanced-cache.php to wp-content/advanced-cache.php
     */
    public static function copy_advanced_cache_dropin() {
        $source = dirname( __DIR__ ) . '/templates/advanced-cache.php';
        $destination = WP_CONTENT_DIR . '/advanced-cache.php';

        if ( file_exists( $source ) ) {
            // Check if we need to write/overwrite
            if ( ! file_exists( $destination ) || md5_file( $source ) !== md5_file( $destination ) ) {
                @copy( $source, $destination );
                if ( function_exists( 'opcache_invalidate' ) ) {
                    @opcache_invalidate( $destination, true );
                }
            }
        }
    }

    /**
     * Set define('WP_CACHE', true); in wp-config.php
     */
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
                // Insert after <?php
                $config_content = preg_replace( '/^<\?php/i', "<?php\ndefine( 'WP_CACHE', true ); // Added by Ultimate WP Booster", $config_content, 1 );
            }
        } else {
            if ( $has_wp_cache ) {
                $config_content = preg_replace( '/define\(\s*\'WP_CACHE\'\s*,\s*true\s*\)/i', "define( 'WP_CACHE', false )", $config_content );
            }
        }

        @file_put_contents( $config_file, $config_content );
    }

    /**
     * Copy the appropriate object-cache drop-in to wp-content/object-cache.php
     */
    public static function copy_object_cache_dropin() {
        $type = intval( get_option( 'uwb_redis_enabled', 0 ) );
        $destination = WP_CONTENT_DIR . '/object-cache.php';

        if ( $type === 1 ) {
            $source = dirname( __DIR__ ) . '/templates/object-cache.php';
            if ( file_exists( $source ) ) {
                if ( ! file_exists( $destination ) || md5_file( $source ) !== md5_file( $destination ) ) {
                    @copy( $source, $destination );
                    if ( function_exists( 'opcache_invalidate' ) ) {
                        @opcache_invalidate( $destination, true );
                    }
                }
            }
        } elseif ( $type === 2 ) {
            $source = dirname( __DIR__ ) . '/templates/object-cache-memcached.php';
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

    /**
     * Remove wp-content/object-cache.php
     */
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
