<?php
/**
 * Ultimate WP Booster — Uninstall
 *
 * Fired when the plugin is deleted via the WordPress admin.
 * Cleans up all plugin options, database tables, drop-in files, and cache.
 *
 * @package Ultimate_WP_Booster
 */

// If uninstall.php is not called by WordPress, abort.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// =========================================================================
// 1. Remove all plugin options from wp_options
// =========================================================================
global $wpdb;

$option_prefixes = array( 'uwb_', 'ultimate_wp_booster_' );

foreach ( $option_prefixes as $prefix ) {
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            $prefix . '%'
        )
    );
}

// =========================================================================
// 2. Remove custom database tables
// =========================================================================
$tables = array(
    $wpdb->prefix . 'ultimate_wp_booster_queue',
    $wpdb->prefix . 'uwb_s3_attachments',
);

foreach ( $tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

// =========================================================================
// 3. Remove drop-in files
// =========================================================================
$drop_ins = array(
    WP_CONTENT_DIR . '/advanced-cache.php',
    WP_CONTENT_DIR . '/object-cache.php',
);

foreach ( $drop_ins as $drop_in ) {
    if ( file_exists( $drop_in ) ) {
        // Only remove if it belongs to this plugin
        $content = @file_get_contents( $drop_in );
        if ( $content && strpos( $content, 'Ultimate WP Booster' ) !== false ) {
            @unlink( $drop_in );
        }
    }
}

// Remove MU plugin (Runtime Optimizer)
$mu_plugin = WP_CONTENT_DIR . '/mu-plugins/uro-runtime.php';
if ( file_exists( $mu_plugin ) ) {
    @unlink( $mu_plugin );
}

// =========================================================================
// 4. Remove cache directories and config files
// =========================================================================
$cache_paths = array(
    WP_CONTENT_DIR . '/cache/wp-rocket',
    WP_CONTENT_DIR . '/cache/ultimate-wp-booster',
);

foreach ( $cache_paths as $path ) {
    if ( is_dir( $path ) ) {
        uwb_recursive_delete( $path );
    }
}

// Remove config file
$config_file = WP_CONTENT_DIR . '/cache/ultimate-wp-booster-config.php';
if ( file_exists( $config_file ) ) {
    @unlink( $config_file );
}

// Remove post IDs JSON
$post_ids_file = WP_CONTENT_DIR . '/cache/uwb-valid-post-ids.json';
if ( file_exists( $post_ids_file ) ) {
    @unlink( $post_ids_file );
}

// Remove log file
$log_file = WP_CONTENT_DIR . '/cache/uwb-cache-purge.log';
if ( file_exists( $log_file ) ) {
    @unlink( $log_file );
}

// Remove compiled runtime file
$compiled_file = WP_CONTENT_DIR . '/cache/ultimate-wp-booster/uro-compiled.php';
if ( file_exists( $compiled_file ) ) {
    @unlink( $compiled_file );
}

// =========================================================================
// 5. Clean up post meta
// =========================================================================
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_uwb_%'" );

// =========================================================================
// 6. Clean up scheduled cron events
// =========================================================================
$cron_hooks = array(
    'uwb_preload_cron_job',
    'uwb_start_preload_async',
    'uwb_uro_recompile_event',
    'uwb_scheduled_db_cleanup',
    'uwb_clean_expired_cache',
);

foreach ( $cron_hooks as $hook ) {
    wp_clear_scheduled_hook( $hook );
}

// =========================================================================
// 7. Remove transients
// =========================================================================
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
        '_transient_uwb_%',
        '_transient_timeout_uwb_%'
    )
);

// =========================================================================
// Helper: recursively delete a directory
// =========================================================================
function uwb_recursive_delete( $dir ) {
    if ( ! is_dir( $dir ) ) {
        return;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ( $items as $item ) {
        if ( $item->isDir() ) {
            @rmdir( $item->getPathname() );
        } else {
            @unlink( $item->getPathname() );
        }
    }
    @rmdir( $dir );
}
