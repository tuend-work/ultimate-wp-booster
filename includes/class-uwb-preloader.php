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

        // URL table actions
        add_action( 'wp_ajax_uwb_get_url_table', array( $this, 'ajax_get_url_table' ) );
        add_action( 'wp_ajax_uwb_process_url_now', array( $this, 'ajax_process_url_now' ) );
        add_action( 'wp_ajax_uwb_add_to_exclude', array( $this, 'ajax_add_to_exclude' ) );
        add_action( 'wp_ajax_uwb_add_to_priority', array( $this, 'ajax_add_to_priority' ) );
    }

    /**
     * Parse sitemap and return list of URLs
     */
    public function parse_sitemap( $sitemap_url, $depth = 0, $is_priority_sitemap = false ) {
        $result = array(
            'priority' => array(),
            'normal'   => array(),
        );

        if ( $depth > 5 ) {
            return $result;
        }

        $args = array(
            'timeout'    => 20,
            'sslverify'  => false,
            'user-agent' => 'Ultimate-WP-Booster-Preloader'
        );

        $response = wp_remote_get( $sitemap_url, $args );

        if ( is_wp_error( $response ) ) {
            return $result;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            return $result;
        }

        $xml_content = wp_remote_retrieve_body( $response );
        if ( empty( $xml_content ) ) {
            return $result;
        }

        // Enable internal errors to handle invalid XML gracefully
        libxml_use_internal_errors( true );
        $xml = simplexml_load_string( trim( $xml_content ) );
        if ( ! $xml ) {
            libxml_clear_errors();
            return $result;
        }

        // 1. Check if it's a sitemap index
        if ( isset( $xml->sitemap ) ) {
            foreach ( $xml->sitemap as $sub_sitemap ) {
                if ( isset( $sub_sitemap->loc ) ) {
                    $sub_url = trim( (string) $sub_sitemap->loc );
                    if ( filter_var( $sub_url, FILTER_VALIDATE_URL ) ) {
                        // Check if the sub-sitemap URL indicates a priority type (taxonomy/category/etc.)
                        $filename = strtolower( basename( wp_parse_url( $sub_url, PHP_URL_PATH ) ) );
                        $is_sub_priority = false;
                        if ( preg_match( '/(category|cat|tag|tax|author|archive|brand)/i', $filename ) ) {
                            $is_sub_priority = true;
                        }
                        
                        $sub_result = $this->parse_sitemap( $sub_url, $depth + 1, $is_sub_priority );
                        $result['priority'] = array_merge( $result['priority'], $sub_result['priority'] );
                        $result['normal']   = array_merge( $result['normal'], $sub_result['normal'] );
                    }
                }
            }
        }

        // Collect URLs
        $raw_urls = array();

        // 2. Check if it's a URL sitemap
        if ( isset( $xml->url ) ) {
            foreach ( $xml->url as $url_node ) {
                if ( isset( $url_node->loc ) ) {
                    $loc = trim( (string) $url_node->loc );
                    if ( filter_var( $loc, FILTER_VALIDATE_URL ) ) {
                        $raw_urls[] = $loc;
                    }
                }
            }
        }

        // Standard sitemap parser failsafe for basic xml
        if ( empty( $raw_urls ) ) {
            // RegEx fallback if standard XML parsing failed due to namespaces
            preg_match_all( '/<loc>(https?:\/\/[^<]+)<\/loc>/i', $xml_content, $matches );
            if ( ! empty( $matches[1] ) ) {
                foreach ( $matches[1] as $loc ) {
                    $loc = trim( html_entity_decode( $loc ) );
                    if ( filter_var( $loc, FILTER_VALIDATE_URL ) ) {
                        $raw_urls[] = $loc;
                    }
                }
            }
        }

        libxml_clear_errors();

        // Classify raw URLs
        $tax_slugs = array( 'category', 'post_tag', 'tag', 'author', 'date', 'page', 'type', 'shop' );
        if ( function_exists( 'get_taxonomies' ) ) {
            $taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );
            foreach ( $taxonomies as $tax ) {
                if ( ! empty( $tax->rewrite['slug'] ) ) {
                    $tax_slugs[] = trim( $tax->rewrite['slug'], '/' );
                }
            }
        }
        $tax_slugs = array_unique( $tax_slugs );

        $post_type_slugs = array( 'post', 'page' );
        if ( function_exists( 'get_post_types' ) ) {
            $post_types = get_post_types( array( 'public' => true ), 'objects' );
            foreach ( $post_types as $pt ) {
                if ( ! empty( $pt->rewrite['slug'] ) ) {
                    $post_type_slugs[] = trim( $pt->rewrite['slug'], '/' );
                }
            }
        }
        $post_type_slugs = array_unique( $post_type_slugs );

        $home_url = home_url( '/' );
        $home_url_no_slash = rtrim( $home_url, '/' );

        foreach ( array_unique( $raw_urls ) as $url ) {
            if ( $is_priority_sitemap ) {
                $result['priority'][] = $url;
            } else {
                $is_taxonomy = false;
                if ( $url === $home_url || $url === $home_url_no_slash ) {
                    $is_taxonomy = true;
                } else {
                    $path = wp_parse_url( $url, PHP_URL_PATH );
                    $path_segments = array_filter( explode( '/', trim( $path, '/' ) ) );
                    if ( ! empty( $path_segments ) ) {
                        $first_segment = reset( $path_segments );
                        if ( in_array( $first_segment, $tax_slugs, true ) ) {
                            $is_taxonomy = true;
                        } elseif ( in_array( $first_segment, $post_type_slugs, true ) ) {
                            $is_taxonomy = false;
                        } else {
                            if ( function_exists( 'url_to_postid' ) ) {
                                if ( url_to_postid( $url ) === 0 ) {
                                    $is_taxonomy = true;
                                }
                            }
                        }
                    }
                }

                if ( $is_taxonomy ) {
                    $result['priority'][] = $url;
                } else {
                    $result['normal'][] = $url;
                }
            }
        }

        $result['priority'] = array_unique( $result['priority'] );
        $result['normal']   = array_unique( $result['normal'] );

        return $result;
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
        $batch_size = max( 1, min( 50, intval( $batch_size ) ) );

        // Get pending or failed URLs (retried up to 3 times)
        $query = $wpdb->prepare(
            "SELECT id, url, priority FROM {$this->table_name} 
             WHERE status = 'pending' OR (status = 'failed' AND attempts < 3)
             ORDER BY priority ASC, id ASC 
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
        $processed_urls = array();

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

            $processed_urls[] = array(
                'url'    => $url,
                'status' => $status,
                'time'   => current_time( 'mysql' )
            );

            $processed_count++;

            // Wait a small fraction of a second to prevent overloading CPU
            usleep( 100000 ); // 0.1 seconds
        }

        if ( ! empty( $processed_urls ) ) {
            update_option( 'uwb_preload_last_run_time', current_time( 'mysql' ) );
            update_option( 'uwb_preload_last_run_urls', $processed_urls );
        }

        return $processed_count;
    }

    /**
     * Start the preloading process by parsing sitemap and populating queue
     * @return int|WP_Error Number of URLs added, or WP_Error on failure
     */
    public function start_preload() {
        global $wpdb;

        // Prevent concurrent queue population
        if ( get_transient( 'uwb_populating_queue' ) ) {
            return new WP_Error( 'already_running', 'Queue is already being populated.' );
        }
        set_transient( 'uwb_populating_queue', 1, 60 ); // lock for 60 seconds

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

        // 1. Parse Sitemap first to avoid truncating table if parse fails
        $parsed = $this->parse_sitemap( $sitemap_url );
        $urls = array_values( array_unique( array_merge( $parsed['priority'], $parsed['normal'] ) ) );

        if ( empty( $urls ) ) {
            delete_transient( 'uwb_populating_queue' );
            return new WP_Error( 'no_urls', 'No URLs found in the sitemap: ' . esc_url( $sitemap_url ) );
        }

        // 2. Empty the queue first
        $wpdb->query( "TRUNCATE TABLE {$this->table_name}" );

        // 3. Filter and insert into the database queue in batches
        $now = current_time( 'mysql' );
        $values = array();
        $placeholders = array();

        $non_priority_counter = 1;
        foreach ( $urls as $url ) {
            if ( $this->is_excluded( $url, $excluded_patterns ) ) {
                continue; // Skip excluded URLs
            }

            // Check if it matches priority list
            $is_priority = false;
            foreach ( $priority_urls as $p_url ) {
                if ( ! empty( $p_url ) && strpos( $url, $p_url ) !== false ) {
                    $is_priority = true;
                    break;
                }
            }

            if ( $is_priority ) {
                $priority_value = 0;
            } else {
                $priority_value = $non_priority_counter++;
            }

            $values[] = $url;
            $values[] = $priority_value;
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
                $sql = "INSERT IGNORE INTO {$this->table_name} (url, priority, status, created_at) VALUES " . implode( ',', $chunk_placeholders );
                $wpdb->query( $wpdb->prepare( $sql, $chunk ) );
            }
        }

        // 4. Set status as running and schedule WP Cron if enabled via WP-Cron
        update_option( 'uwb_preload_running', 1 );
        
        $preload_enabled = intval( get_option( 'uwb_preload_enabled', 0 ) );
        if ( $preload_enabled === 1 ) {
            if ( ! wp_next_scheduled( 'uwb_preload_cron_job' ) ) {
                wp_schedule_event( time(), 'every_minute', 'uwb_preload_cron_job' );
            }
        } else {
            wp_clear_scheduled_hook( 'uwb_preload_cron_job' );
        }

        delete_transient( 'uwb_populating_queue' );

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
     * AJAX action: deprecated. Preloading must run through WP-Cron, Linux cron, or WP-CLI.
     */
    public function ajax_trigger_preload_batch() {
        check_ajax_referer( 'uwb_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Invalid request.' ) );
        }

        wp_send_json_success( array(
            'processed' => 0,
            'message'   => 'AJAX preload trigger is disabled. Preloading runs through cron.'
        ) );
    }

    /**
     * AJAX action: Get paginated URL table with filters and sorting
     */
    public function ajax_get_url_table() {
        check_ajax_referer( 'uwb_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ) );
        }

        global $wpdb;

        $status   = isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : '';
        $search   = isset( $_POST['search'] ) ? sanitize_text_field( $_POST['search'] ) : '';
        $orderby  = in_array( $_POST['orderby'] ?? '', array( 'url', 'status', 'priority', 'attempts', 'created_at', 'last_attempt' ), true )
                    ? $_POST['orderby'] : 'priority';
        $order    = strtoupper( $_POST['order'] ?? 'ASC' ) === 'DESC' ? 'DESC' : 'ASC';
        $page     = max( 1, intval( $_POST['page'] ?? 1 ) );
        $per_page = 20;
        $offset   = ( $page - 1 ) * $per_page;

        $where = array( '1=1' );
        $params = array();

        if ( $status && in_array( $status, array( 'pending', 'processing', 'completed', 'failed' ), true ) ) {
            $where[]  = 'status = %s';
            $params[] = $status;
        }
        if ( $search ) {
            $where[]  = 'url LIKE %s';
            $params[] = '%' . $wpdb->esc_like( $search ) . '%';
        }

        $where_sql = implode( ' AND ', $where );

        $total = $params
            ? $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_sql}", $params ) )
            : $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_sql}" );

        $orderby_sql = "{$orderby} {$order}";
        if ( $orderby === 'priority' ) {
            $orderby_sql = "priority {$order}, id ASC";
        }

        $query_sql = "SELECT id, url, status, priority, attempts, created_at, last_attempt FROM {$this->table_name} WHERE {$where_sql} ORDER BY {$orderby_sql} LIMIT %d OFFSET %d";
        $query_params = array_merge( $params, array( $per_page, $offset ) );
        $rows = $wpdb->get_results( $wpdb->prepare( $query_sql, $query_params ) );

        wp_send_json_success( array(
            'rows'       => $rows,
            'total'      => intval( $total ),
            'page'       => $page,
            'per_page'   => $per_page,
            'total_pages'=> ceil( $total / $per_page ),
        ) );
    }

    /**
     * AJAX action: Immediately process a single URL
     */
    public function ajax_process_url_now() {
        check_ajax_referer( 'uwb_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ) );
        }

        global $wpdb;
        $id = intval( $_POST['id'] ?? 0 );
        if ( ! $id ) {
            wp_send_json_error( array( 'message' => 'Invalid ID.' ) );
        }

        $item = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table_name} WHERE id = %d", $id ) );
        if ( ! $item ) {
            wp_send_json_error( array( 'message' => 'URL not found.' ) );
        }

        // Mark as processing
        $wpdb->update( $this->table_name, array( 'status' => 'processing', 'attempts' => $item->attempts + 1, 'last_attempt' => current_time( 'mysql' ) ), array( 'id' => $id ), array( '%s', '%d', '%s' ), array( '%d' ) );

        $response = wp_remote_get( $item->url, array(
            'timeout'   => 20,
            'sslverify' => false,
            'user-agent'=> 'Ultimate-WP-Booster-Preloader',
            'headers'   => array( 'X-Ultimate-WP-Booster-Preload' => '1' ),
        ) );

        $status = ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) ? 'completed' : 'failed';
        $wpdb->update( $this->table_name, array( 'status' => $status ), array( 'id' => $id ), array( '%s' ), array( '%d' ) );

        wp_send_json_success( array( 'status' => $status, 'id' => $id ) );
    }

    /**
     * AJAX action: Add URL to excluded list
     */
    public function ajax_add_to_exclude() {
        check_ajax_referer( 'uwb_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ) );
        }

        global $wpdb;
        $id = intval( $_POST['id'] ?? 0 );
        if ( ! $id ) wp_send_json_error( array( 'message' => 'Invalid ID.' ) );

        $item = $wpdb->get_row( $wpdb->prepare( "SELECT url FROM {$this->table_name} WHERE id = %d", $id ) );
        if ( ! $item ) wp_send_json_error( array( 'message' => 'Not found.' ) );

        $path = wp_parse_url( $item->url, PHP_URL_PATH );
        $path = '/' . trim( $path, '/' );

        $existing = get_option( 'uwb_excluded_urls', '' );
        $lines    = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", '', $existing ) ) ) );

        if ( ! in_array( $path, $lines, true ) ) {
            $lines[]   = $path;
            update_option( 'uwb_excluded_urls', implode( "\n", $lines ) );
            Uwb_Cache::write_config_file();
        }

        wp_send_json_success( array( 'message' => "Added {$path} to excluded URLs.", 'path' => $path ) );
    }

    /**
     * AJAX action: Add URL to priority list
     */
    public function ajax_add_to_priority() {
        check_ajax_referer( 'uwb_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ) );
        }

        global $wpdb;
        $id = intval( $_POST['id'] ?? 0 );
        if ( ! $id ) wp_send_json_error( array( 'message' => 'Invalid ID.' ) );

        $item = $wpdb->get_row( $wpdb->prepare( "SELECT url FROM {$this->table_name} WHERE id = %d", $id ) );
        if ( ! $item ) wp_send_json_error( array( 'message' => 'Not found.' ) );

        $path = wp_parse_url( $item->url, PHP_URL_PATH );
        $path = '/' . trim( $path, '/' );

        $existing = get_option( 'uwb_priority_urls', '' );
        $lines    = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", '', $existing ) ) ) );

        if ( ! in_array( $path, $lines, true ) ) {
            $lines[]   = $path;
            update_option( 'uwb_priority_urls', implode( "\n", $lines ) );
            // Also mark as priority in the queue (set priority to 0)
            $wpdb->update( $this->table_name, array( 'priority' => 0 ), array( 'id' => $id ), array( '%d' ), array( '%d' ) );
        }

        wp_send_json_success( array( 'message' => "Added {$path} to priority URLs.", 'path' => $path ) );
    }
}
