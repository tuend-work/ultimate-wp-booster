<?php
/**
 * Preloader Engine
 */

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class Uwb_Preloader {

    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'ultimate_wp_booster_queue';

        // Background preloading runner hook
        add_action( 'uwb_preload_cron_job', array( $this, 'run_preload_batch' ) );

        // AJAX handlers
        add_action( 'wp_ajax_uwb_start_preload', array( $this, 'ajax_start_preload' ) );
        add_action( 'wp_ajax_uwb_stop_preload', array( $this, 'ajax_stop_preload' ) );
        add_action( 'wp_ajax_uwb_clear_preload', array( $this, 'ajax_clear_preload' ) );
        add_action( 'wp_ajax_uwb_get_preload_status', array( $this, 'ajax_get_preload_status' ) );
        add_action( 'wp_ajax_uwb_trigger_preload_batch', array( $this, 'ajax_trigger_preload_batch' ) );
    }

    /**
     * Parse sitemap and return list of URLs
     */
    public function parse_sitemap( $sitemap_url, $depth = 0 ) {
        if ( $depth > 5 ) {
            return array(); // Prevent infinite loops or too deep recursion
        }

        $args = array(
            'timeout'    => 20,
            'sslverify'  => false,
            'user-agent' => 'Ultimate-WP-Booster-Preloader'
        );

        $response = wp_remote_get( $sitemap_url, $args );

        if ( is_wp_error( $response ) ) {
            return array();
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            return array();
        }

        $xml_content = wp_remote_retrieve_body( $response );
        if ( empty( $xml_content ) ) {
            return array();
        }

        // Enable internal errors to handle invalid XML gracefully
        libxml_use_internal_errors( true );
        $xml = simplexml_load_string( trim( $xml_content ) );
        if ( ! $xml ) {
            libxml_clear_errors();
            return array();
        }

        $urls = array();

        // 1. Check if it's a sitemap index
        if ( isset( $xml->sitemap ) ) {
            foreach ( $xml->sitemap as $sub_sitemap ) {
                if ( isset( $sub_sitemap->loc ) ) {
                    $sub_url = trim( (string) $sub_sitemap->loc );
                    if ( filter_var( $sub_url, FILTER_VALIDATE_URL ) ) {
                        $sub_urls = $this->parse_sitemap( $sub_url, $depth + 1 );
                        $urls = array_merge( $urls, $sub_urls );
                    }
                }
            }
        }

        // 2. Check if it's a URL sitemap
        if ( isset( $xml->url ) ) {
            foreach ( $xml->url as $url_node ) {
                if ( isset( $url_node->loc ) ) {
                    $loc = trim( (string) $url_node->loc );
                    if ( filter_var( $loc, FILTER_VALIDATE_URL ) ) {
                        $urls[] = $loc;
                    }
                }
            }
        }

        // Standard sitemap parser failsafe for basic xml
        if ( empty( $urls ) ) {
            // RegEx fallback if standard XML parsing failed due to namespaces
            preg_match_all( '/<loc>(https?:\/\/[^<]+)<\/loc>/i', $xml_content, $matches );
            if ( ! empty( $matches[1] ) ) {
                foreach ( $matches[1] as $loc ) {
                    $loc = trim( html_entity_decode( $loc ) );
                    if ( filter_var( $loc, FILTER_VALIDATE_URL ) ) {
                        $urls[] = $loc;
                    }
                }
            }
        }

        libxml_clear_errors();
        return array_unique( $urls );
    }

    /**
     * Check if a URL should be excluded based on exclusion list
     */
    private function is_excluded( $url, $excluded_patterns ) {
        $path = wp_parse_url( $url, PHP_URL_PATH );
        $normalized_path = '/' . trim( $path, '/' );
        if ( $normalized_path === '//' ) {
            $normalized_path = '/';
        }

        foreach ( $excluded_patterns as $pattern ) {
            $pattern = trim( $pattern );
            if ( empty( $pattern ) ) {
                continue;
            }

            // Build regular expression for wildcard matching
            $regex = str_replace( '\*', '.*', preg_quote( $pattern, '#' ) );
            if ( preg_match( '#^' . $regex . '$#i', $normalized_path ) || preg_match( '#^' . $regex . '$#i', $path ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Run a batch of preloads
     */
    public function run_preload_batch( $batch_size = 0 ) {
        global $wpdb;

        if ( $batch_size <= 0 ) {
            $batch_size = intval( get_option( 'uwb_preload_batch_size', 5 ) );
        }

        // Get pending or failed URLs (retried up to 3 times)
        $query = $wpdb->prepare(
            "SELECT id, url, priority FROM {$this->table_name} 
             WHERE status = 'pending' OR (status = 'failed' AND attempts < 3)
             ORDER BY priority DESC, id ASC 
             LIMIT %d",
            $batch_size
        );

        $queue_items = $wpdb->get_results( $query );
        if ( empty( $queue_items ) ) {
            // No items left to preload, unschedule cron
            wp_clear_scheduled_hook( 'uwb_preload_cron_job' );
            update_option( 'uwb_preload_running', 0 );
            return 0;
        }

        // Mark them as processing first to avoid race conditions
        $ids = wp_list_pluck( $queue_items, 'id' );
        $ids_string = implode( ',', array_map( 'intval', $ids ) );
        $wpdb->query( "UPDATE {$this->table_name} SET status = 'processing' WHERE id IN ($ids_string)" );

        $processed_count = 0;

        foreach ( $queue_items as $item ) {
            $url = $item->url;
            $id = $item->id;

            // Increment attempts
            $wpdb->query( $wpdb->prepare( "UPDATE {$this->table_name} SET attempts = attempts + 1, last_attempt = %s WHERE id = %d", current_time( 'mysql' ), $id ) );

            // Perform the remote GET request to trigger cache generation
            $args = array(
                'timeout'    => 15,
                'sslverify'  => false,
                'user-agent' => 'Ultimate-WP-Booster-Preloader',
                'headers'    => array(
                    'X-Ultimate-WP-Booster-Preload' => '1'
                )
            );

            $response = wp_remote_get( $url, $args );

            if ( is_wp_error( $response ) ) {
                $status = 'failed';
            } else {
                $code = wp_remote_retrieve_response_code( $response );
                // Treat 200 OK as successful preload
                $status = ( $code === 200 ) ? 'completed' : 'failed';
            }

            // Update queue item status
            $wpdb->update(
                $this->table_name,
                array( 'status' => $status ),
                array( 'id' => $id ),
                array( '%s' ),
                array( '%d' )
            );

            $processed_count++;

            // Wait a small fraction of a second to prevent overloading CPU
            usleep( 100000 ); // 0.1 seconds
        }

        return $processed_count;
    }

    /**
     * Start the preloading process by parsing sitemap and populating queue
     * @return int|WP_Error Number of URLs added, or WP_Error on failure
     */
    public function start_preload() {
        global $wpdb;

        // Retrieve settings
        $sitemap_url = get_option( 'uwb_preload_sitemap', '' );
        if ( empty( $sitemap_url ) ) {
            $sitemap_url = home_url( '/wp-sitemap.xml' );
        }

        // Fetch URL exclusions
        $exclusions_raw = get_option( 'uwb_excluded_urls', '' );
        $excluded_patterns = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", "", $exclusions_raw ) ) ) );

        // Fetch Priority URLs
        $priority_raw = get_option( 'uwb_priority_urls', '' );
        $priority_urls = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", "", $priority_raw ) ) ) );

        // 1. Empty the queue first
        $wpdb->query( "TRUNCATE TABLE {$this->table_name}" );

        // 2. Parse Sitemap
        $urls = $this->parse_sitemap( $sitemap_url );

        if ( empty( $urls ) ) {
            return new WP_Error( 'no_urls', 'No URLs found in the sitemap: ' . esc_url( $sitemap_url ) );
        }

        // 3. Filter and insert into the database queue in batches
        $now = current_time( 'mysql' );
        $values = array();
        $placeholders = array();

        foreach ( $urls as $url ) {
            if ( $this->is_excluded( $url, $excluded_patterns ) ) {
                continue; // Skip excluded URLs
            }

            // Check if it matches priority list
            $is_priority = 0;
            foreach ( $priority_urls as $p_url ) {
                if ( ! empty( $p_url ) && strpos( $url, $p_url ) !== false ) {
                    $is_priority = 1;
                    break;
                }
            }

            $values[] = $url;
            $values[] = $is_priority;
            $values[] = 'pending';
            $values[] = $now;

            $placeholders[] = "(%s, %d, %s, %s)";
        }

        if ( ! empty( $values ) ) {
            // Insert in chunks of 500 rows to prevent query payload limit
            $chunks = array_chunk( $values, 2000 ); // 2000 items = 500 rows (4 values per row)
            
            foreach ( $chunks as $chunk ) {
                $rows_count = count( $chunk ) / 4;
                $chunk_placeholders = array_slice( $placeholders, 0, $rows_count );
                $sql = "INSERT INTO {$this->table_name} (url, priority, status, created_at) VALUES " . implode( ',', $chunk_placeholders );
                $wpdb->query( $wpdb->prepare( $sql, $chunk ) );
            }
        }

        // 4. Set status as running and schedule WP Cron
        update_option( 'uwb_preload_running', 1 );
        
        if ( ! wp_next_scheduled( 'uwb_preload_cron_job' ) ) {
            wp_schedule_event( time(), 'every_minute', 'uwb_preload_cron_job' );
        }

        return count( $urls );
    }

    /**
     * AJAX action: populate queue and start preloading
     */
    public function ajax_start_preload() {
        check_ajax_referer( 'uwb_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'You do not have permission to perform this action.' ) );
        }

        $result = $this->start_preload();

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( array(
            'message' => 'Sitemap parsed successfully. Added ' . $result . ' links to the preloading queue!'
        ) );
    }

    /**
     * AJAX action: Stop preloading
     */
    public function ajax_stop_preload() {
        check_ajax_referer( 'uwb_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Invalid request.' ) );
        }

        wp_clear_scheduled_hook( 'uwb_preload_cron_job' );
        update_option( 'uwb_preload_running', 0 );

        wp_send_json_success( array( 'message' => 'Preloading process paused!' ) );
    }

    /**
     * AJAX action: Clear queue
     */
    public function ajax_clear_preload() {
        check_ajax_referer( 'uwb_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Invalid request.' ) );
        }

        global $wpdb;
        $wpdb->query( "TRUNCATE TABLE {$this->table_name}" );
        wp_clear_scheduled_hook( 'uwb_preload_cron_job' );
        update_option( 'uwb_preload_running', 0 );

        wp_send_json_success( array( 'message' => 'Preloading queue cleared!' ) );
    }

    /**
     * AJAX action: Fetch live progress counts
     */
    public function ajax_get_preload_status() {
        check_ajax_referer( 'uwb_admin_nonce', 'nonce' );

        global $wpdb;

        $counts = $wpdb->get_results(
            "SELECT status, COUNT(*) as cnt FROM {$this->table_name} GROUP BY status",
            OBJECT_K
        );

        $total = $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name}" );

        $pending = isset( $counts['pending'] ) ? intval( $counts['pending']->cnt ) : 0;
        $processing = isset( $counts['processing'] ) ? intval( $counts['processing']->cnt ) : 0;
        $completed = isset( $counts['completed'] ) ? intval( $counts['completed']->cnt ) : 0;
        $failed = isset( $counts['failed'] ) ? intval( $counts['failed']->cnt ) : 0;

        $running = intval( get_option( 'uwb_preload_running', 0 ) );

        wp_send_json_success( array(
            'total'      => intval( $total ),
            'pending'    => $pending,
            'processing' => $processing,
            'completed'  => $completed,
            'failed'     => $failed,
            'running'    => $running
        ) );
    }

    /**
     * AJAX action: Manual batch trigger (for real-time fast preload in front-end)
     */
    public function ajax_trigger_preload_batch() {
        check_ajax_referer( 'uwb_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Invalid request.' ) );
        }

        $processed = $this->run_preload_batch( 5 ); // Fast execution of 5 links

        wp_send_json_success( array(
            'processed' => $processed
        ) );
    }
}
