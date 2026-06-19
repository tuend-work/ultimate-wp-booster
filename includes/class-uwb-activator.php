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

        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            url varchar(2083) NOT NULL,
            priority tinyint(1) NOT NULL DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'pending',
            attempts int(11) NOT NULL DEFAULT 0,
            last_attempt datetime DEFAULT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY url (url(191)),
            KEY status_priority (status, priority, id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        // 2. Set default options
        if ( get_option( 'uwb_cache_lifespan' ) === false ) {
            update_option( 'uwb_cache_lifespan', 10 ); // 10 hours
        }

        if ( get_option( 'uwb_preload_enabled' ) === false ) {
            update_option( 'uwb_preload_enabled', 0 ); // Disabled by default
        }

        if ( get_option( 'uwb_preload_batch_size' ) === false ) {
            update_option( 'uwb_preload_batch_size', 5 ); // 5 URLs per batch
        }

        if ( get_option( 'uwb_cache_logged_in' ) === false ) {
            update_option( 'uwb_cache_logged_in', 0 ); // Disabled by default
        }

        // 3. Copy advanced-cache.php drop-in
        self::copy_advanced_cache_dropin();

        // 4. Enable WP_CACHE in wp-config.php
        self::toggle_wp_cache( true );
    }

    /**
     * Copy advanced-cache.php to wp-content/advanced-cache.php
     */
    public static function copy_advanced_cache_dropin() {
        $source = dirname( __DIR__ ) . '/advanced-cache.php';
        $destination = WP_CONTENT_DIR . '/advanced-cache.php';

        if ( file_exists( $source ) ) {
            // Check if we need to write/overwrite
            if ( ! file_exists( $destination ) || md5_file( $source ) !== md5_file( $destination ) ) {
                @copy( $source, $destination );
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
     * Copy object-cache.php to wp-content/object-cache.php
     */
    public static function copy_object_cache_dropin() {
        $source = dirname( __DIR__ ) . '/object-cache.php';
        $destination = WP_CONTENT_DIR . '/object-cache.php';

        if ( file_exists( $source ) ) {
            if ( ! file_exists( $destination ) || md5_file( $source ) !== md5_file( $destination ) ) {
                @copy( $source, $destination );
            }
        }
    }

    /**
     * Remove wp-content/object-cache.php
     */
    public static function remove_object_cache_dropin() {
        $destination = WP_CONTENT_DIR . '/object-cache.php';
        if ( file_exists( $destination ) ) {
            $content = @file_get_contents( $destination );
            if ( $content && strpos( $content, 'Ultimate WP Booster Redis Drop-in' ) !== false ) {
                @unlink( $destination );
            }
        }
    }
}
