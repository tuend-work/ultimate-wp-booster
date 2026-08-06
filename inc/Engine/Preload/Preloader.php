<?php
namespace Ultimate_WP_Booster\Engine\Preload;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class Preloader {

    private $table_name;
    private $inserted_hashes = array();
    private $total_inserted = 0;
    private $excluded_patterns = array();
    private $priority_urls = array();

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'ultimate_wp_booster_queue';
    }

    public function maybe_mark_cached_url_completed() {
        if ( wp_doing_ajax() || wp_doing_cron() || is_admin() || php_sapi_name() === 'cli' ) {
            return;
        }

        if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || $_SERVER['REQUEST_METHOD'] !== 'GET' ) {
            return;
        }

        $ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '';
        if ( strpos( $ua, 'Ultimate-WP-Booster-Preloader' ) !== false ) {
            return;
        }

        global $wpdb;
        $has_queue = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name}" );
        if ( $has_queue === 0 ) {
            return;
        }

        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
        $uri_path    = rawurldecode( explode( '?', $request_uri )[0] );
        $path        = '/' . trim( $uri_path, '/' );
        if ( $path !== '/' ) {
            $path = rtrim( $path, '/' ) . '/';
        }

        $host = isset( $_SERVER['HTTP_HOST'] ) ? strtolower( explode( ':', $_SERVER['HTTP_HOST'] )[0] ) : '';
        if ( $host ) {
            $is_https = (
                ( isset( $_SERVER['HTTPS'] ) && ( $_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] == 1 ) ) ||
                ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https' ) ||
                ( isset( $_SERVER['SERVER_PORT'] ) && $_SERVER['SERVER_PORT'] == 443 )
            );
            $normalized_uri = trim( $uri_path, '/' );
            $cache_base     = WP_CONTENT_DIR . '/cache/wp-rocket/' . $host;
            $cache_dir      = $normalized_uri !== '' ? $cache_base . '/' . $normalized_uri : $cache_base;
            $cache_file     = $cache_dir . '/' . ( $is_https ? 'index-https.html' : 'index.html' );

            if ( ! file_exists( $cache_file ) ) {
                return;
            }
        }

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$this->table_name}
                 SET status = 'completed'
                 WHERE (url = %s OR url = %s)
                   AND status IN ('pending', 'processing', 'failed')",
                $path,
                rtrim( $path, '/' )
            )
        );
    }

    public function maybe_output_important_sitemap() {
        $request_path = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) : '';
        $sitemap_path = wp_parse_url( home_url( '/important-sitemap.xml' ), PHP_URL_PATH );
        if ( $request_path !== $sitemap_path ) {
            return;
        }

        $enabled = intval( get_option( 'uwb_important_sitemap_enabled', 1 ) );
        if ( ! $enabled ) {
            status_header( 404 );
            nocache_headers();
            echo 'Important sitemap disabled.';
            exit;
        }

        $manual_urls = $this->get_manual_priority_urls();
        $homepage_urls = intval( get_option( 'uwb_imp_homepage_links', 1 ) ) ? $this->scrape_homepage_links() : array();
        $taxonomy_urls = intval( get_option( 'uwb_imp_taxonomies_enabled', 1 ) ) ? $this->collect_public_taxonomy_urls() : array();

        $all_urls = array_values( array_unique( array_merge( $manual_urls, $homepage_urls, $taxonomy_urls ) ) );

        status_header( 200 );
        header( 'Content-Type: application/xml; charset=UTF-8' );
        nocache_headers();

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ( $all_urls as $url ) {
            echo "\t<url>\n";
            echo "\t\t<loc>" . esc_url( $url ) . "</loc>\n";
            echo "\t</url>\n";
        }
        echo '</urlset>';
        exit;
    }

    private function get_manual_priority_urls() {
        $priority_raw = get_option( 'uwb_priority_urls', '' );
        $lines = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", '', $priority_raw ) ) ) );
        $urls = array();

        foreach ( $lines as $line ) {
            $url = $this->normalize_user_url( $line );
            if ( $url && filter_var( $url, FILTER_VALIDATE_URL ) ) {
                $urls[] = $url;
            }
        }

        return array_values( array_unique( $urls ) );
    }

    public function scrape_homepage_links() {
        $cache_key = 'uwb_homepage_links_v1';
        $cached    = get_transient( $cache_key );
        if ( is_array( $cached ) ) {
            return $cached;
        }

        $home_url  = home_url( '/' );
        $parsed    = wp_parse_url( $home_url );
        $home_host = isset( $parsed['host'] ) ? strtolower( $parsed['host'] ) : '';
        $scheme    = isset( $parsed['scheme'] ) ? $parsed['scheme'] : 'https';
        $base_path = isset( $parsed['path'] ) ? rtrim( $parsed['path'], '/' ) : '';

        $response = wp_remote_get( $home_url, array(
            'timeout'    => 20,
            'sslverify'  => false,
            'user-agent' => 'Ultimate-WP-Booster-Sitemap-Scraper',
            'headers'    => array( 'Accept' => 'text/html' ),
        ) );

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            set_transient( $cache_key, array(), 5 * MINUTE_IN_SECONDS );
            return array();
        }

        $html = wp_remote_retrieve_body( $response );
        if ( empty( $html ) ) {
            set_transient( $cache_key, array(), 5 * MINUTE_IN_SECONDS );
            return array();
        }

        preg_match_all( '/<a\s[^>]*href=["\']([^"\'#\s][^"\'>]*)["\'][^>]*>/i', $html, $matches );
        $hrefs = isset( $matches[1] ) ? $matches[1] : array();

        $skip_extensions = '/\.(jpg|jpeg|png|gif|svg|webp|ico|pdf|zip|mp4|mp3|ogg|wav|xml|json|css|js|woff|woff2|ttf|eot)$/i';
        $skip_prefixes   = array( 'mailto:', 'tel:', 'javascript:', '#', 'data:' );
        $skip_paths      = array(
            '/wp-admin', '/wp-login', '/wp-json', '/wp-cron', '?add-to-cart',
            '/cart', '/checkout', '/my-account', '/feed', '/trackback',
        );

        $exclusions_raw    = get_option( 'uwb_excluded_urls', '' );
        $excluded_patterns = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", '', $exclusions_raw ) ) ) );

        $urls = array();

        foreach ( $hrefs as $href ) {
            $href = html_entity_decode( trim( $href ) );
            $lower_href = strtolower( $href );
            $skip = false;
            foreach ( $skip_prefixes as $prefix ) {
                if ( strpos( $lower_href, $prefix ) === 0 ) {
                    $skip = true;
                    break;
                }
            }
            if ( $skip ) continue;

            if ( preg_match( '#^https?://#i', $href ) ) {
                $abs_url = $href;
            } elseif ( strpos( $href, '//' ) === 0 ) {
                $abs_url = $scheme . ':' . $href;
            } elseif ( strpos( $href, '/' ) === 0 ) {
                $abs_url = $scheme . '://' . $home_host . $href;
            } else {
                $abs_url = $scheme . '://' . $home_host . $base_path . '/' . ltrim( $href, '/' );
            }

            if ( ! filter_var( $abs_url, FILTER_VALIDATE_URL ) ) continue;

            $link_host = strtolower( (string) wp_parse_url( $abs_url, PHP_URL_HOST ) );
            if ( $link_host !== $home_host ) continue;

            $clean_url = strtok( $abs_url, '?' );
            $clean_url = strtok( $clean_url, '#' );

            if ( preg_match( $skip_extensions, (string) wp_parse_url( $clean_url, PHP_URL_PATH ) ) ) continue;

            $url_path = (string) wp_parse_url( $clean_url, PHP_URL_PATH );
            $skip = false;
            foreach ( $skip_paths as $sp ) {
                if ( strpos( $url_path, $sp ) !== false || strpos( $clean_url, $sp ) !== false ) {
                    $skip = true;
                    break;
                }
            }
            if ( $skip ) continue;

            if ( $this->is_excluded( $url_path, $excluded_patterns ) ) continue;

            $urls[] = $clean_url;
        }

        $urls = array_values( array_unique( $urls ) );
        set_transient( $cache_key, $urls, HOUR_IN_SECONDS );

        return $urls;
    }

    public static function invalidate_homepage_links_cache() {
        delete_transient( 'uwb_homepage_links_v1' );
    }

    private function normalize_user_url( $value ) {
        $value = trim( (string) $value );
        if ( $value === '' ) return '';
        if ( preg_match( '#^https?://#i', $value ) ) return $value;
        if ( strpos( $value, '/' ) === 0 ) return home_url( $value );

        if ( preg_match( '#^(www\.|[a-z0-9.-]+\.[a-z]{2,}/)#i', $value ) ) {
            $scheme = wp_parse_url( home_url( '/' ), PHP_URL_SCHEME );
            if ( empty( $scheme ) ) $scheme = is_ssl() ? 'https' : 'http';
            return $scheme . '://' . $value;
        }

        if ( strpos( $value, '.' ) === false ) return '';
        return home_url( '/' . ltrim( $value, '/' ) );
    }

    public function normalize_url_by_permalink_settings( $url_or_path ) {
        $url_or_path = trim( (string) $url_or_path );
        if ( empty( $url_or_path ) ) {
            return '';
        }

        if ( preg_match( '/[\*\?\(\)\|\[\]]/', $url_or_path ) ) {
            return $url_or_path;
        }

        $parsed = wp_parse_url( $url_or_path );
        if ( ! $parsed ) {
            return $url_or_path;
        }

        $path = isset( $parsed['path'] ) ? $parsed['path'] : '';
        if ( empty( $path ) ) {
            if ( isset( $parsed['host'] ) ) {
                $path = '/';
            } else {
                return $url_or_path;
            }
        }

        $filename = basename( $path );
        if ( strpos( $filename, '.' ) === false && $path !== '/' ) {
            if ( function_exists( 'user_trailingslashit' ) ) {
                $path = user_trailingslashit( $path );
            } else {
                global $wp_rewrite;
                if ( isset( $wp_rewrite->use_trailing_slashes ) && ! $wp_rewrite->use_trailing_slashes ) {
                    $path = rtrim( $path, '/' );
                } else {
                    $path = rtrim( $path, '/' ) . '/';
                }
            }
        }

        $scheme   = isset( $parsed['scheme'] ) ? $parsed['scheme'] : '';
        $host     = isset( $parsed['host'] ) ? $parsed['host'] : '';
        $port     = isset( $parsed['port'] ) ? ':' . $parsed['port'] : '';
        $query    = isset( $parsed['query'] ) ? '?' . $parsed['query'] : '';
        $fragment = isset( $parsed['fragment'] ) ? '#' . $parsed['fragment'] : '';

        if ( $scheme && $host ) {
            return $scheme . '://' . $host . $port . $path . $query . $fragment;
        }

        if ( strpos( $path, '/' ) !== 0 && ! empty( $path ) ) {
            $path = '/' . $path;
        }

        return $path . $query . $fragment;
    }

    public function get_crawl_request_args() {
        $is_litespeed = \Ultimate_WP_Booster\Engine\Cache\LiteSpeedEngine::is_litespeed_server();
        $custom_ua = trim( (string) get_option( 'uwb_preload_user_agent', '' ) );

        if ( ! empty( $custom_ua ) ) {
            $ua = $custom_ua;
        } else {
            $ua = $is_litespeed ? 'lscache_runner' : 'Ultimate-WP-Booster-Preloader';
        }

        $headers = array(
            'X-Ultimate-WP-Booster-Preload' => '1'
        );

        $custom_headers_raw = get_option( 'uwb_preload_custom_headers', '' );
        if ( ! empty( $custom_headers_raw ) ) {
            $lines = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", '', $custom_headers_raw ) ) ) );
            foreach ( $lines as $line ) {
                if ( strpos( $line, ':' ) !== false ) {
                    list( $h_key, $h_val ) = explode( ':', $line, 2 );
                    $headers[ trim( $h_key ) ] = trim( $h_val );
                }
            }
        }

        $cookies = array();
        $custom_cookies_raw = get_option( 'uwb_preload_custom_cookies', '' );
        if ( ! empty( $custom_cookies_raw ) ) {
            $lines = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", '', $custom_cookies_raw ) ) ) );
            foreach ( $lines as $line ) {
                if ( strpos( $line, '=' ) !== false ) {
                    list( $c_key, $c_val ) = explode( '=', $line, 2 );
                    $cookies[] = new \WP_Http_Cookie( array(
                        'name'  => trim( $c_key ),
                        'value' => trim( $c_val ),
                    ) );
                }
            }
        }

        $timeout = intval( get_option( 'uwb_preload_request_timeout', 30 ) );
        if ( $timeout < 5 ) $timeout = 5;

        $args = array(
            'timeout'    => $timeout,
            'sslverify'  => false,
            'user-agent' => $ua,
            'headers'    => $headers,
        );

        if ( ! empty( $cookies ) ) {
            $args['cookies'] = $cookies;
        }

        return $args;
    }

    private function collect_public_taxonomy_urls() {
        $urls = array();
        $home_url = home_url( '/' );
        $urls[] = $home_url;

        $mode = get_option( 'uwb_imp_taxonomy_mode', 'all' );
        $selected_terms = get_option( 'uwb_imp_taxonomy_terms', array() );
        if ( ! is_array( $selected_terms ) ) {
            $selected_terms = array();
        }

        if ( function_exists( 'get_taxonomies' ) && function_exists( 'get_terms' ) ) {
            $taxonomies = get_taxonomies( array( 'public' => true ), 'names' );
            if ( ! empty( $taxonomies ) && is_array( $taxonomies ) ) {
                foreach ( $taxonomies as $taxonomy ) {
                    $terms = get_terms( array(
                        'taxonomy'   => $taxonomy,
                        'hide_empty' => false,
                    ) );

                    if ( ! is_wp_error( $terms ) && ! empty( $terms ) && is_array( $terms ) ) {
                        foreach ( $terms as $term ) {
                            if ( $mode === 'specific' ) {
                                $term_key = $taxonomy . ':' . $term->term_id;
                                if ( ! in_array( $term_key, $selected_terms, true ) ) {
                                    continue;
                                }
                            }
                            $link = get_term_link( $term );
                            if ( ! is_wp_error( $link ) && filter_var( $link, FILTER_VALIDATE_URL ) ) {
                                $urls[] = $link;
                            }
                        }
                    }
                }
            }
        }

        return array_values( array_unique( $urls ) );
    }

    public function start_preload() {
        global $wpdb;

        if ( get_transient( 'uwb_populating_queue' ) ) {
            return new \WP_Error( 'already_running', 'Queue is already being populated.' );
        }
        set_transient( 'uwb_populating_queue', 1, 120 );

        $wpdb->query( "TRUNCATE TABLE {$this->table_name}" );

        $sitemap_urls = $this->get_preload_sitemap_urls();
        $exclusions_raw = get_option( 'uwb_excluded_urls', '' );
        $this->excluded_patterns = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", "", $exclusions_raw ) ) ) );

        $priority_raw = get_option( 'uwb_priority_urls', '' );
        $this->priority_urls = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", "", $priority_raw ) ) ) );

        $this->total_inserted = 0;
        $this->inserted_hashes = array();

        $manual_priority_urls = $this->get_manual_priority_urls();
        $this->insert_urls_to_queue( $manual_priority_urls, true );

        foreach ( $sitemap_urls as $sitemap_url ) {
            $this->parse_sitemap( $sitemap_url, 0, $this->is_important_sitemap_url( $sitemap_url ) );
        }

        if ( $this->total_inserted === 0 ) {
            delete_transient( 'uwb_populating_queue' );
            return new \WP_Error( 'no_urls', 'No URLs found in sitemaps, important URLs, or taxonomy terms.' );
        }

        update_option( 'uwb_preload_running', 1 );
        
        $preload_enabled = intval( get_option( 'uwb_preload_enabled', 0 ) );
        if ( $preload_enabled === 1 ) {
            if ( ! wp_next_scheduled( 'uwb_preload_cron_job' ) ) {
                wp_schedule_event( time(), 'every_minute', 'uwb_preload_cron_job' );
            }
        }

        delete_transient( 'uwb_populating_queue' );
        return $this->total_inserted;
    }

    private function get_preload_sitemap_urls() {
        $raw = get_option( 'uwb_preload_sitemap', '' );
        if ( empty( $raw ) ) {
            $raw = home_url( '/important-sitemap.xml' ) . "\n" . home_url( '/wp-sitemap.xml' );
        }

        $lines = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", '', $raw ) ) ) );
        $urls = array();

        foreach ( $lines as $line ) {
            $url = $this->normalize_user_url( $line );
            if ( $url && filter_var( $url, FILTER_VALIDATE_URL ) ) {
                $urls[] = $url;
            }
        }

        return array_values( array_unique( $urls ) );
    }

    private function is_important_sitemap_url( $url ) {
        $path = wp_parse_url( $url, PHP_URL_PATH );
        return strtolower( basename( (string) $path ) ) === 'important-sitemap.xml';
    }

    private function is_xml_url( $url ) {
        $path = wp_parse_url( $url, PHP_URL_PATH );
        if ( empty( $path ) ) return false;
        return strtolower( substr( $path, -4 ) ) === '.xml';
    }

    public function parse_sitemap( $sitemap_url, $depth = 0, $is_priority_sitemap = false ) {
        if ( $depth > 5 ) return;

        $args = array(
            'timeout'    => 20,
            'sslverify'  => false,
            'user-agent' => 'Ultimate-WP-Booster-Preloader'
        );

        $response = wp_remote_get( $sitemap_url, $args );
        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return;
        }

        $xml_content = wp_remote_retrieve_body( $response );
        if ( empty( $xml_content ) ) return;

        libxml_use_internal_errors( true );
        $xml = simplexml_load_string( trim( $xml_content ) );

        $has_sitemap_index = $xml && isset( $xml->sitemap );
        if ( $has_sitemap_index ) {
            foreach ( $xml->sitemap as $sub_sitemap ) {
                if ( isset( $sub_sitemap->loc ) ) {
                    $sub_url = trim( (string) $sub_sitemap->loc );
                    if ( filter_var( $sub_url, FILTER_VALIDATE_URL ) ) {
                        $filename = strtolower( basename( wp_parse_url( $sub_url, PHP_URL_PATH ) ) );
                        $is_sub_priority = preg_match( '/(category|cat|tag|tax|author|archive|brand)/i', $filename ) === 1;
                        $this->parse_sitemap( $sub_url, $depth + 1, $is_sub_priority );
                    }
                }
            }
        }

        if ( ! $has_sitemap_index ) {
            preg_match_all( '/<loc>\s*(https?:\/\/[^<]+\.xml(?:\?[^<]*)?)\s*<\/loc>/i', $xml_content, $sitemap_matches );
            if ( ! empty( $sitemap_matches[1] ) ) {
                foreach ( $sitemap_matches[1] as $sub_url ) {
                    $sub_url = trim( html_entity_decode( $sub_url ) );
                    if ( filter_var( $sub_url, FILTER_VALIDATE_URL ) ) {
                        $filename = strtolower( basename( wp_parse_url( $sub_url, PHP_URL_PATH ) ) );
                        $is_sub_priority = preg_match( '/(category|cat|tag|tax|author|archive|brand|important)/i', $filename ) === 1;
                        $this->parse_sitemap( $sub_url, $depth + 1, $is_sub_priority );
                    }
                }
                $has_sitemap_index = true;
            }
        }

        $raw_urls = array();
        if ( $xml && isset( $xml->url ) ) {
            foreach ( $xml->url as $url_node ) {
                if ( isset( $url_node->loc ) ) {
                    $loc = trim( (string) $url_node->loc );
                    if ( filter_var( $loc, FILTER_VALIDATE_URL ) ) {
                        $raw_urls[] = $loc;
                    }
                }
            }
        }

        if ( empty( $raw_urls ) && ! $has_sitemap_index ) {
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

        $urls_to_insert = array();
        foreach ( array_unique( $raw_urls ) as $url ) {
            if ( ! $this->is_xml_url( $url ) ) {
                $urls_to_insert[] = $url;
            }
        }

        if ( ! empty( $urls_to_insert ) ) {
            $this->insert_urls_to_queue( $urls_to_insert, $is_priority_sitemap );
        }
    }

    private function insert_urls_to_queue( $urls, $is_priority = false ) {
        global $wpdb;
        if ( empty( $urls ) ) return;

        $created_at = current_time( 'mysql' );
        $values = array();
        $placeholders = array();

        foreach ( $urls as $url ) {
            $parsed = wp_parse_url( $url );
            $path = isset( $parsed['path'] ) ? $parsed['path'] : '/';
            $query = isset( $parsed['query'] ) ? '?' . $parsed['query'] : '';
            $relative_url = $path . $query;

            if ( $this->is_excluded( $relative_url, $this->excluded_patterns ) ) {
                continue;
            }

            $url_hash = md5( strtolower( rtrim( $relative_url, '/' ) ) );
            if ( isset( $this->inserted_hashes[ $url_hash ] ) ) {
                continue;
            }
            $this->inserted_hashes[ $url_hash ] = true;

            $priority = 5;
            if ( $is_priority ) {
                $priority = 1;
            } elseif ( $relative_url === '/' ) {
                $priority = 1;
            } else {
                foreach ( $this->priority_urls as $pur ) {
                    if ( ! empty( $pur ) && ( stripos( $relative_url, $pur ) !== false || stripos( $url, $pur ) !== false ) ) {
                        $priority = 1;
                        break;
                    }
                }
            }

            $values[] = $relative_url;
            $values[] = $priority;
            $values[] = 'pending';
            $values[] = 0;
            $values[] = $created_at;

            $placeholders[] = "(%s, %d, %s, %d, %s)";
            $this->total_inserted++;
        }

        if ( ! empty( $placeholders ) ) {
            $query = "INSERT IGNORE INTO {$this->table_name} (url, priority, status, attempts, created_at) VALUES " . implode( ', ', $placeholders );
            $wpdb->query( $wpdb->prepare( $query, $values ) );
        }
    }

    private function is_excluded( $url, $excluded_patterns ) {
        $path = wp_parse_url( $url, PHP_URL_PATH );
        $normalized_path = '/' . trim( $path, '/' );
        if ( $normalized_path === '//' ) {
            $normalized_path = '/';
        }

        foreach ( $excluded_patterns as $pattern ) {
            $pattern = trim( $pattern );
            if ( empty( $pattern ) ) continue;

            $regex = str_replace( '\*', '.*', preg_quote( $pattern, '#' ) );
            if ( preg_match( '#^' . $regex . '$#i', $normalized_path ) || preg_match( '#^' . $regex . '$#i', $path ) ) {
                return true;
            }
        }

        return false;
    }

    public function run_preload_batch( $batch_size = 0 ) {
        global $wpdb;

        if ( $batch_size <= 0 ) {
            $batch_size = intval( get_option( 'uwb_preload_batch_size', 5 ) );
        }
        $batch_size = max( 1, min( 50, intval( $batch_size ) ) );

        $query = $wpdb->prepare(
            "SELECT id, url, priority FROM {$this->table_name} 
             WHERE status = 'pending' OR (status = 'failed' AND attempts < 3)
             ORDER BY priority ASC, id ASC 
             LIMIT %d",
            $batch_size
        );

        $queue_items = $wpdb->get_results( $query );
        if ( empty( $queue_items ) ) {
            wp_clear_scheduled_hook( 'uwb_preload_cron_job' );
            update_option( 'uwb_preload_running', 0 );
            return array( 'count' => 0, 'urls' => array() );
        }

        $ids = wp_list_pluck( $queue_items, 'id' );
        $ids_string = implode( ',', array_map( 'intval', $ids ) );
        $wpdb->query( "UPDATE {$this->table_name} SET status = 'processing' WHERE id IN ($ids_string)" );

        $processed_count = 0;
        $processed_urls = array();

        $threads = max( 1, min( 16, intval( get_option( 'uwb_preload_threads', 3 ) ) ) );

        // If threads > 1 and curl_multi is available, run multi-threaded concurrent requests
        if ( $threads > 1 && function_exists( 'curl_multi_init' ) ) {
            $chunks = array_chunk( $queue_items, $threads );
            foreach ( $chunks as $chunk ) {
                // Server load check
                if ( function_exists( 'sys_getloadavg' ) ) {
                    $load = sys_getloadavg();
                    $limit = floatval( get_option( 'uwb_preload_server_load_limit', 1.0 ) );
                    if ( is_array( $load ) && isset( $load[0] ) && $load[0] > $limit ) {
                        $this->log( 'Server load too high (' . $load[0] . ' > ' . $limit . '). Pausing preloader batch.' );
                        break;
                    }
                }

                $chunk_processed = $this->process_concurrent_batch( $chunk );
                if ( is_array( $chunk_processed ) ) {
                    $processed_urls = array_merge( $processed_urls, $chunk_processed );
                    $processed_count += count( $chunk_processed );
                }

                $usleep_val = intval( get_option( 'uwb_preload_usleep', 500 ) );
                if ( $usleep_val > 0 ) {
                    usleep( min( $usleep_val, 30000000 ) );
                }
            }
        } else {
            // Single-threaded sequential crawling
            foreach ( $queue_items as $item ) {
                $url = $item->url;
                if ( strpos( $url, 'http' ) !== 0 ) {
                    $url = home_url( '/' . ltrim( $url, '/' ) );
                }
                $id = $item->id;

                $wpdb->query( $wpdb->prepare( "UPDATE {$this->table_name} SET attempts = attempts + 1, last_attempt = %s WHERE id = %d", current_time( 'mysql' ), $id ) );

                $args = $this->get_crawl_request_args();

                $response = wp_remote_get( $url, $args );

                if ( is_wp_error( $response ) ) {
                    $status = 'failed';
                } else {
                    $code = wp_remote_retrieve_response_code( $response );
                    $status = ( $code === 200 ) ? 'completed' : 'failed';
                }

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

                // Server load check
                if ( function_exists( 'sys_getloadavg' ) ) {
                    $load = sys_getloadavg();
                    $limit = floatval( get_option( 'uwb_preload_server_load_limit', 1.0 ) );
                    if ( is_array( $load ) && isset( $load[0] ) && $load[0] > $limit ) {
                        $this->log( 'Server load too high (' . $load[0] . ' > ' . $limit . '). Pausing preloader batch.' );
                        break;
                    }
                }

                $usleep_val = intval( get_option( 'uwb_preload_usleep', 500 ) );
                if ( $usleep_val > 0 ) {
                    usleep( min( $usleep_val, 30000000 ) );
                }
            }
        }

        if ( ! empty( $processed_urls ) ) {
            update_option( 'uwb_preload_last_run_time', current_time( 'mysql' ) );
            update_option( 'uwb_preload_last_run_urls', $processed_urls );
        }

        return array(
            'count' => $processed_count,
            'urls'  => wp_list_pluck( $processed_urls, 'url' )
        );
    }

    private function process_concurrent_batch( $items ) {
        $mh = curl_multi_init();
        $curl_handles = array();
        $processed_urls = array();

        $base_args = $this->get_crawl_request_args();
        global $wpdb;

        foreach ( $items as $item ) {
            $url = $item->url;
            if ( strpos( $url, 'http' ) !== 0 ) {
                $url = home_url( '/' . ltrim( $url, '/' ) );
            }
            $id = $item->id;
            $wpdb->query( $wpdb->prepare( "UPDATE {$this->table_name} SET attempts = attempts + 1, last_attempt = %s WHERE id = %d", current_time( 'mysql' ), $id ) );

            $ch = curl_init();
            curl_setopt( $ch, CURLOPT_URL, $url );
            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
            curl_setopt( $ch, CURLOPT_TIMEOUT, $base_args['timeout'] );
            curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
            curl_setopt( $ch, CURLOPT_USERAGENT, $base_args['user-agent'] );

            $formatted_headers = array();
            if ( isset( $base_args['headers'] ) && is_array( $base_args['headers'] ) ) {
                foreach ( $base_args['headers'] as $hk => $hv ) {
                    $formatted_headers[] = $hk . ': ' . $hv;
                }
            }
            curl_setopt( $ch, CURLOPT_HTTPHEADER, $formatted_headers );

            if ( isset( $base_args['cookies'] ) && is_array( $base_args['cookies'] ) ) {
                $cookie_strs = array();
                foreach ( $base_args['cookies'] as $cookie ) {
                    if ( $cookie instanceof \WP_Http_Cookie ) {
                        $cookie_strs[] = $cookie->name . '=' . $cookie->value;
                    }
                }
                if ( ! empty( $cookie_strs ) ) {
                    curl_setopt( $ch, CURLOPT_COOKIE, implode( '; ', $cookie_strs ) );
                }
            }

            curl_multi_add_handle( $mh, $ch );
            $curl_handles[ (int) $ch ] = array(
                'handle' => $ch,
                'item'   => $item,
                'url'    => $url,
            );
        }

        $active = null;
        do {
            $mrc = curl_multi_exec( $mh, $active );
        } while ( $mrc === CURLM_CALL_MULTI_PER_SELECT || $active );

        while ( $active && $mrc === CURLM_OK ) {
            if ( curl_multi_select( $mh ) === -1 ) {
                usleep( 100 );
            }
            do {
                $mrc = curl_multi_exec( $mh, $active );
            } while ( $mrc === CURLM_CALL_MULTI_PER_SELECT );
        }

        foreach ( $curl_handles as $info ) {
            $ch = $info['handle'];
            $id = $info['item']->id;
            $url = $info['url'];

            $http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
            $status = ( $http_code === 200 ) ? 'completed' : 'failed';

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

            curl_multi_remove_handle( $mh, $ch );
            curl_close( $ch );
        }

        curl_multi_close( $mh );

        return $processed_urls;
    }

    // --- AJAX HANDLERS ---

    public function ajax_start_preload() {
        check_ajax_referer( 'uwb_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ) );
        }

        $log_file = WP_CONTENT_DIR . '/cache/ultimate-wp-booster/preload-debug.log';
        @unlink( $log_file );

        if ( get_transient( 'uwb_populating_queue' ) ) {
            wp_send_json_error( array( 'message' => 'Queue is already being populated.' ) );
        }

        wp_clear_scheduled_hook( 'uwb_start_preload_async' );
        wp_schedule_single_event( time(), 'uwb_start_preload_async' );
        update_option( 'uwb_preload_running', 1 );

        if ( function_exists( 'spawn_cron' ) ) {
            spawn_cron();
        }

        wp_send_json_success( array(
            'message' => 'Sitemap parsing scheduled. URLs will be added to the preloading queue shortly!'
        ) );
    }

    public function ajax_stop_preload() {
        check_ajax_referer( 'uwb_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ) );
        }

        wp_clear_scheduled_hook( 'uwb_preload_cron_job' );
        wp_clear_scheduled_hook( 'uwb_start_preload_async' );
        delete_transient( 'uwb_populating_queue' );
        update_option( 'uwb_preload_running', 0 );

        wp_send_json_success( array( 'message' => 'Preloader stopped.' ) );
    }

    public function ajax_clear_preload() {
        check_ajax_referer( 'uwb_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ) );
        }

        global $wpdb;
        $wpdb->query( "TRUNCATE TABLE {$this->table_name}" );
        wp_clear_scheduled_hook( 'uwb_preload_cron_job' );
        wp_clear_scheduled_hook( 'uwb_start_preload_async' );
        delete_transient( 'uwb_populating_queue' );
        update_option( 'uwb_preload_running', 0 );

        wp_send_json_success( array( 'message' => 'Preload queue cleared.' ) );
    }

    public function ajax_get_preload_status() {
        check_ajax_referer( 'uwb_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ) );
        }

        global $wpdb;
        $total      = $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name}" );
        $pending    = $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'pending'" );
        $processing = $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'processing'" );
        $completed  = $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'completed'" );
        $failed     = $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'failed'" );

        $running = intval( get_option( 'uwb_preload_running', 0 ) );

        $log_file = WP_CONTENT_DIR . '/cache/ultimate-wp-booster/preload-debug.log';
        $log_content = 'No logs available. Start a preload run to generate logs.';
        if ( file_exists( $log_file ) ) {
            $fsize = filesize( $log_file );
            $handle = @fopen( $log_file, "r" );
            if ( $handle ) {
                if ( $fsize > 15000 ) {
                    @fseek( $handle, -15000, SEEK_END );
                }
                $log_content = @fread( $handle, 15000 );
                @fclose( $handle );
            }
        }

        wp_send_json_success( array(
            'total'      => intval( $total ),
            'pending'    => intval( $pending ),
            'processing' => intval( $processing ),
            'completed'  => intval( $completed ),
            'failed'     => intval( $failed ),
            'running'    => $running,
            'log'        => $log_content
        ) );
    }

    public function ajax_trigger_preload_batch() {
        check_ajax_referer( 'uwb_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ) );
        }

        $result = $this->run_preload_batch();
        wp_send_json_success( $result );
    }

    public function ajax_get_url_table() {
        check_ajax_referer( 'uwb_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ) );
        }

        global $wpdb;

        $page = isset( $_POST['page'] ) ? max( 1, intval( $_POST['page'] ) ) : 1;
        $limit = isset( $_POST['limit'] ) ? max( 1, intval( $_POST['limit'] ) ) : 20;
        $offset = ( $page - 1 ) * $limit;
        $search = isset( $_POST['search'] ) ? trim( sanitize_text_field( $_POST['search'] ) ) : '';

        $where = '';
        if ( ! empty( $search ) ) {
            $like = '%' . $wpdb->esc_like( $search ) . '%';
            $where = $wpdb->prepare( "WHERE url LIKE %s", $like );
        }

        $total = $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name} {$where}" );
        $items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$this->table_name} {$where} ORDER BY priority ASC, id ASC LIMIT %d OFFSET %d", $limit, $offset ) );

        $priority_raw = get_option( 'uwb_priority_urls', '' );
        $priority_lines = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", '', $priority_raw ) ) ) );

        $rows = array();
        foreach ( $items as $item ) {
            $is_prio = false;
            $path = wp_parse_url( $item->url, PHP_URL_PATH );
            if ( empty( $path ) ) $path = '/';
            $path = $this->normalize_url_by_permalink_settings( $path );

            foreach ( $priority_lines as $pl ) {
                $norm_pl = $this->normalize_url_by_permalink_settings( $pl );
                if ( $path === $norm_pl || $item->url === $pl ) {
                    $is_prio = true;
                    break;
                }
            }

            $rows[] = array(
                'id'          => $item->id,
                'url'         => $item->url,
                'priority'    => $item->priority,
                'status'      => $item->status,
                'attempts'    => $item->attempts,
                'is_priority' => $is_prio,
            );
        }

        wp_send_json_success( array(
            'rows'        => $rows,
            'total'       => intval( $total ),
            'page'        => $page,
            'total_pages' => ceil( $total / $limit ),
        ) );
    }

    public function ajax_process_url_now() {
        check_ajax_referer( 'uwb_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ) );
        }

        global $wpdb;
        $id = intval( $_POST['id'] ?? 0 );
        if ( ! $id ) wp_send_json_error( array( 'message' => 'Invalid ID.' ) );

        $item = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table_name} WHERE id = %d", $id ) );
        if ( ! $item ) wp_send_json_error( array( 'message' => 'Not found.' ) );

        $url = $item->url;
        if ( strpos( $url, 'http' ) !== 0 ) {
            $url = home_url( '/' . ltrim( $url, '/' ) );
        }

        $args = $this->get_crawl_request_args();

        $response = wp_remote_get( $url, $args );
        $status = 'failed';
        if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
            $status = 'completed';
        }

        $wpdb->update( $this->table_name, array( 'status' => $status ), array( 'id' => $id ) );

        wp_send_json_success( array( 'status' => $status, 'url' => $url ) );
    }

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
        if ( empty( $path ) ) $path = '/';

        $existing = get_option( 'uwb_excluded_urls', '' );
        $lines = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", '', $existing ) ) ) );

        if ( ! in_array( $path, $lines, true ) ) {
            $lines[] = $path;
            update_option( 'uwb_excluded_urls', implode( "\n", array_unique( $lines ) ) );
            \Ultimate_WP_Booster\Engine\Cache\CacheManager::write_config_file();
        }

        wp_send_json_success( array( 'message' => "Added {$path} to excluded URLs.", 'path' => $path ) );
    }

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
        if ( empty( $path ) ) $path = '/';
        $path = $this->normalize_url_by_permalink_settings( $path );

        $existing = get_option( 'uwb_priority_urls', '' );
        $lines    = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", '', $existing ) ) ) );

        $normalized_lines = array();
        foreach ( $lines as $line ) {
            $normalized_line = $this->normalize_url_by_permalink_settings( $line );
            if ( ! empty( $normalized_line ) ) {
                $normalized_lines[] = $normalized_line;
            }
        }
        $normalized_lines = array_unique( $normalized_lines );

        if ( in_array( $path, $normalized_lines, true ) ) {
            $normalized_lines = array_diff( $normalized_lines, array( $path ) );
            update_option( 'uwb_priority_urls', implode( "\n", $normalized_lines ) );

            $max_priority = $wpdb->get_var( "SELECT MAX(priority) FROM {$this->table_name}" );
            $new_priority = max( 1, intval( $max_priority ) + 1 );
            $wpdb->update( $this->table_name, array( 'priority' => $new_priority ), array( 'id' => $id ), array( '%d' ), array( '%d' ) );

            $updated_urls = implode( "\n", $normalized_lines );
            wp_send_json_success( array( 'message' => "Removed {$path} from Important URLs.", 'path' => $path, 'urls' => $updated_urls ) );
        } else {
            $normalized_lines[] = $path;
            $updated_urls = implode( "\n", $normalized_lines );
            update_option( 'uwb_priority_urls', $updated_urls );
            $wpdb->update( $this->table_name, array( 'priority' => 0 ), array( 'id' => $id ), array( '%d' ), array( '%d' ) );

            wp_send_json_success( array( 'message' => "Added {$path} to Important URLs.", 'path' => $path, 'urls' => $updated_urls ) );
        }
    }

    public function maybe_output_preload_links_script() {
        if ( is_admin() || is_customize_preview() ) {
            return;
        }

        $enabled = intval( get_option( 'uwb_preload_links', 0 ) );
        if ( $enabled !== 1 ) {
            return;
        }

        $wc_exclude = array( 'cart', 'checkout', 'my-account', 'wp-login.php' );
        if ( class_exists( 'WooCommerce' ) ) {
            $cart_id = wc_get_page_id( 'cart' );
            $checkout_id = wc_get_page_id( 'checkout' );
            $myaccount_id = wc_get_page_id( 'myaccount' );
            if ( $cart_id > 0 ) {
                $cart_url = get_permalink( $cart_id );
                if ( $cart_url ) {
                    $wc_exclude[] = trim( wp_parse_url( $cart_url, PHP_URL_PATH ), '/' );
                }
            }
            if ( $checkout_id > 0 ) {
                $checkout_url = get_permalink( $checkout_id );
                if ( $checkout_url ) {
                    $wc_exclude[] = trim( wp_parse_url( $checkout_url, PHP_URL_PATH ), '/' );
                }
            }
            if ( $myaccount_id > 0 ) {
                $myaccount_url = get_permalink( $myaccount_id );
                if ( $myaccount_url ) {
                    $wc_exclude[] = trim( wp_parse_url( $myaccount_url, PHP_URL_PATH ), '/' );
                }
            }
        }
        
        $exclusions_raw = get_option( 'uwb_excluded_urls', '' );
        $excluded_patterns = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", "", $exclusions_raw ) ) ) );
        
        $all_excludes = array_merge( $wc_exclude, $excluded_patterns );
        $all_excludes = array_values( array_unique( array_filter( $all_excludes ) ) );

        ?>
        <script id="uwb-preload-links-js">
        document.addEventListener('DOMContentLoaded', () => {
            const preloaded = new Set();
            const excludes = <?php echo json_encode( $all_excludes ); ?>;
            
            const isExcluded = (url) => {
                if (url.search) return true;
                if (url.pathname.match(/\.(wp-admin|xml|json|zip|pdf|jpg|jpeg|png|gif|svg|webp|mp4|mp3|ogg|wav)$/i)) return true;
                
                const path = url.pathname.replace(/^\/|\/$/g, '');
                const fullPath = url.pathname;
                for (let pattern of excludes) {
                    pattern = pattern.replace(/^\/|\/$/g, '');
                    if (pattern === '') continue;
                    
                    if (pattern.includes('*')) {
                        const regexStr = '^' + pattern.replace(/[-\/\\^$+?.()|[\]{}]/g, '\\$&').replace(/\\\*/g, '.*') + '$';
                        const regex = new RegExp(regexStr, 'i');
                        if (regex.test(path) || regex.test(fullPath) || regex.test(url.href)) return true;
                    } else {
                        if (path === pattern || fullPath === '/' + pattern || path.includes(pattern) || url.href.includes(pattern)) return true;
                    }
                }
                
                return false;
            };

            const preload = (url) => {
                if (preloaded.has(url)) return;
                preloaded.add(url);
                const link = document.createElement('link');
                link.rel = 'prefetch';
                link.href = url;
                document.head.appendChild(link);
            };

            const handle = (e) => {
                const a = e.target.closest('a');
                if (!a || !a.href) return;
                
                try {
                    const url = new URL(a.href, window.location.href);
                    if (url.origin !== window.location.origin) return;
                    if (url.hash && url.pathname === window.location.pathname) return;
                    if (isExcluded(url)) return;
                    preload(url.href);
                } catch(err) {}
            };

            document.addEventListener('mouseover', handle, { passive: true });
            document.addEventListener('touchstart', handle, { passive: true });
        });
        </script>
        <?php
    }
}
