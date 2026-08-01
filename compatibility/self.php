<?php
/**
 * Ultimate WP Booster Self-Compatibility Layer
 * Handles internal cron trigger, preloader bypass, and HTML debug indicators.
 */

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

/**
 * Ultimate WP Booster Self Query Bypass Parameters
 * Returns array of query parameters that should force cache bypass.
 */
function uwb_self_get_bypass_query_params() {
    return array(
        'uwb_preload_key',
    );
}

/**
 * Ultimate WP Booster Self Default Excluded URLs
 * Returns standard URL paths to exclude from static caching.
 */
function uwb_self_get_excluded_urls() {
    return array();
}

/**
 * Helper to fetch the current caching status and bypass reason.
 * Use this in themes or plugins to check if the page is cached or bypassed.
 *
 * @return array Status array containing 'status', 'reason', and 'message'.
 */
function uwb_get_cache_status_info() {
    $reason = isset( $GLOBALS['uwb_bypass_reason'] ) ? $GLOBALS['uwb_bypass_reason'] : '';
    if ( ! empty( $reason ) ) {
        return array(
            'status'  => 'bypassed',
            'reason'  => $reason,
            'message' => "Bypass Reason: {$reason}"
        );
    }
    if ( defined( 'UWB_BUFFER_STARTED' ) ) {
        return array(
            'status'  => 'caching',
            'reason'  => '',
            'message' => 'Status: Cache Valid / Serviced'
        );
    }
    return array(
        'status'  => 'unknown',
        'reason'  => '',
        'message' => 'Status: Unknown'
    );
}

/**
 * Debug: Inject cache bypass reason as HTML comment into <head> (WP_DEBUG only)
 * For early bypasses (cookie, query string, URL) – wp_head fires normally since WordPress still loads.
 * For shutdown-time bypasses – reason is embedded in the <!-- Dynamic Page | Bypass: ... --> comment.
 */
add_action( 'wp_head', 'uwb_debug_inject_bypass_reason', 1 );
function uwb_debug_inject_bypass_reason() {
    $reason = isset( $GLOBALS['uwb_bypass_reason'] ) ? $GLOBALS['uwb_bypass_reason'] : '';
    if ( empty( $reason ) ) return;
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo "\n<!-- 🚫 UWB Cache Bypass Reason: " . esc_html( $reason ) . " -->\n";
}

/**
 * Handle external cron trigger for preloader queue execution
 */
add_action( 'init', 'uwb_handle_external_cron_trigger' );
function uwb_handle_external_cron_trigger() {
    if ( isset( $_GET['uwb_preload_key'] ) ) {
        $saved_key = get_option( 'uwb_preload_secret_key' );
        if ( empty( $saved_key ) ) {
            wp_die( 'Secret key is empty.' );
        }
        if ( hash_equals( $saved_key, $_GET['uwb_preload_key'] ) ) {
            $GLOBALS['uwb_do_not_cache'] = true;
            if ( class_exists( 'Uwb_Preloader' ) ) {
                global $wpdb;
                $table_name = $wpdb->prefix . 'ultimate_wp_booster_queue';

                $is_browser = ( isset( $_SERVER['HTTP_ACCEPT'] ) && strpos( $_SERVER['HTTP_ACCEPT'], 'text/html' ) !== false );

                // Check for action=crawl request to start crawling sitemaps in background
                $action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';
                if ( $action === 'crawl' ) {
                    if ( ! get_transient( 'uwb_populating_queue' ) ) {
                        wp_clear_scheduled_hook( 'uwb_start_preload_async' );
                        wp_schedule_single_event( time(), 'uwb_start_preload_async' );
                        update_option( 'uwb_preload_running', 1 );
                        if ( function_exists( 'spawn_cron' ) ) {
                            spawn_cron();
                        }
                        if ( $is_browser ) {
                            echo "<pre style='white-space: pre-wrap; font-family: monospace;'>OK: Sitemap crawl scheduled in background.</pre>";
                        } else {
                            header( 'Content-Type: text/plain; charset=UTF-8' );
                            echo "OK: Sitemap crawl scheduled in background.";
                        }
                        exit;
                    } else {
                        if ( $is_browser ) {
                            echo "<pre style='white-space: pre-wrap; font-family: monospace;'>ERROR: Sitemap crawler is already running.</pre>";
                        } else {
                            header( 'Content-Type: text/plain; charset=UTF-8' );
                            echo "ERROR: Sitemap crawler is already running.";
                        }
                        exit;
                    }
                }

                // If total queue size is 0, auto schedule a crawl so it starts populating
                $total_count = intval( $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" ) );
                if ( $total_count === 0 ) {
                    if ( ! get_transient( 'uwb_populating_queue' ) ) {
                        wp_clear_scheduled_hook( 'uwb_start_preload_async' );
                        wp_schedule_single_event( time(), 'uwb_start_preload_async' );
                        update_option( 'uwb_preload_running', 1 );
                        if ( function_exists( 'spawn_cron' ) ) {
                            spawn_cron();
                        }
                    }
                }

                // Check if there are any pending or retriable failed URLs
                $pending_count = intval( $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE status = 'pending' OR (status = 'failed' AND attempts < 3)" ) );

                if ( $is_browser ) {
                    echo "<pre style='white-space: pre-wrap; font-family: monospace; word-wrap: break-word;'>";
                } else {
                    header( 'Content-Type: text/plain; charset=UTF-8' );
                }

                if ( $pending_count === 0 ) {
                    // Queue is completed! Show all completed preload URLs
                    $completed_urls = $wpdb->get_col( "SELECT url FROM {$table_name} WHERE status = 'completed' ORDER BY priority ASC, id ASC" );
                    if ( empty( $completed_urls ) ) {
                        echo "OK: Preload queue is empty or sitemap is still being scanned. Crawl task was triggered.";
                    } else {
                        echo "OK: Queue completed. Listing completed URLs:\n";
                        foreach ( $completed_urls as $url ) {
                            echo esc_url( $url ) . "\n";
                        }
                    }
                } else {
                    $preloader = new Uwb_Preloader();
                    $result = $preloader->run_preload_batch();
                    $processed = is_array( $result ) ? $result['count'] : 0;
                    $urls = is_array( $result ) ? $result['urls'] : array();

                    echo "OK: Preloaded {$processed} URLs.\n";
                    if ( ! empty( $urls ) ) {
                        foreach ( $urls as $url ) {
                            echo esc_url( $url ) . "\n";
                        }
                    }
                }

                if ( $is_browser ) {
                    echo "</pre>";
                }
            } else {
                if ( $is_browser ) {
                    echo "<pre style='white-space: pre-wrap; font-family: monospace;'>ERROR: Preloader class not found.</pre>";
                } else {
                    echo "ERROR: Preloader class not found.";
                }
            }
            exit;
        } else {
            wp_die( 'Invalid secret key.' );
        }
    }
}
