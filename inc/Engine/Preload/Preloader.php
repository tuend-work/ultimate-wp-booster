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

        $manual_urls = $this->get_manual_priority_urls();
        $homepage_urls = $this->scrape_homepage_links();
        $taxonomy_urls = $this->collect_public_taxonomy_urls();

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

    private function collect_public_taxonomy_urls() {
        $urls = array();
        $home_url = home_url( '/' );
        $urls[] = $home_url;

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

        foreach ( $queue_items as $item ) {
            $url = $item->url;
            if ( strpos( $url, 'http' ) !== 0 ) {
                $url = home_url( '/' . ltrim( $url, '/' ) );
            }
            $id = $item->id;

            $wpdb->query( $wpdb->prepare( "UPDATE {$this->table_name} SET attempts = attempts + 1, last_attempt = %s WHERE id = %d", current_time( 'mysql' ), $id ) );

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
            usleep( 100000 );
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
}
