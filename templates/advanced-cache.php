<?php
// Prevent loading from the plugins directory
if ( strpos( __FILE__, '/plugins/' ) !== false || strpos( __FILE__, '\\plugins\\' ) !== false ) {
    return;
}

/**
 * Early Cache Drop-in for Ultimate WordPress Booster
 * Bypasses PHP/Database execution if a cached static file is available.
 *
 * Target path: wp-content/advanced-cache.php
 */

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

// Main Cache Handler Execution
uwb_advanced_cache_run();

function uwb_advanced_cache_run() {
    $early_debug = defined( 'WP_DEBUG' ) && WP_DEBUG;
    if ( $early_debug ) {
        error_log( "UWB: Advanced cache run initialized. URI: " . (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '') . " Method: " . (isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '') );
    }

    // Initialize bypass reason tracker (used by wp_head hook to inject debug comment into HTML)
    $GLOBALS['uwb_bypass_reason'] = '';

    // 1. Only cache GET requests
    if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || $_SERVER['REQUEST_METHOD'] !== 'GET' ) {
        if ( $early_debug ) {
            error_log( "UWB: Run bypassed: Request method is not GET." );
        }
        $GLOBALS['uwb_bypass_reason'] = 'Not a GET request (' . ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : 'unknown' ) . ')';
        return;
    }

    // 2. Do not cache command line/WP-CLI requests
    if ( php_sapi_name() === 'cli' ) {
        if ( $early_debug ) {
            error_log( "UWB: Run bypassed: php_sapi_name() is cli." );
        }
        $GLOBALS['uwb_bypass_reason'] = 'WP-CLI / command line request';
        return;
    }

    // 3. Load config file
    $config_path = WP_CONTENT_DIR . '/cache/ultimate-wp-booster-config.php';
    $config      = array(
        'cache_lifespan'    => 36000,
        'cache_logged_in'   => false,
        'optimize_logged_in'=> false,
        'excluded_urls'     => array(),
        'ignored_query'     => array( 'utm_source', 'utm_medium', 'utm_campaign', 'fbclid', 'gclid', 'age-verified' ),
    );

    if ( file_exists( $config_path ) ) {
        $parsed_config = include $config_path;
        if ( is_array( $parsed_config ) ) {
            $config = array_merge( $config, $parsed_config );
        }
    }

    // Set final debug variable using both WP_DEBUG and config settings
    $debug = $early_debug || ( isset( $config['debug_mode'] ) && $config['debug_mode'] == 1 );

    // 3.5. Bypass cache completely for preloader key (external cron / trigger)
    $preload_secret_key = isset( $config['preload_secret_key'] ) ? $config['preload_secret_key'] : '';
    if ( ! empty( $preload_secret_key ) && isset( $_GET['uwb_preload_key'] ) && hash_equals( $preload_secret_key, $_GET['uwb_preload_key'] ) ) {
        if ( $debug ) {
            error_log( "UWB: Run bypassed: Preload trigger / external cron request detected." );
        }
        $GLOBALS['uwb_bypass_reason'] = 'Preload batch trigger / external cron';
        return;
    }

    $cache_page_enabled = isset( $config['cache_page_enabled'] ) ? (bool) $config['cache_page_enabled'] : true;
    if ( ! $cache_page_enabled ) {
        if ( $debug ) {
            error_log( "UWB: Run bypassed: Page caching is disabled in settings." );
        }
        $GLOBALS['uwb_bypass_reason'] = 'Page caching disabled in plugin settings';
        return;
    }

    $cache_logged_in = isset( $config['cache_logged_in'] ) ? intval( $config['cache_logged_in'] ) : 0;

    // 4. Check query string bypass & caching
    $active_cache_query_params = array();
    if ( ! empty( $_SERVER['QUERY_STRING'] ) ) {
        parse_str( $_SERVER['QUERY_STRING'], $query_params );
        
        // Dynamically check query bypass parameters from config (falls back to defaults if not set)
        $bypass_queries = isset( $config['bypass_query_params'] ) 
            ? $config['bypass_query_params'] 
            : array( 'wc-ajax', 'wc-api', 'add-to-cart', 'pay_for_order', 'magic_login', 'orderby', 'order', 'yith_wcan', 'yith-wcan-ajax', 'preset', 'rest_route', 'action', 'ajax', 'edd_action', 'xmlrpc', 'autoterm', 'app', 'uxbuilder', 'uxbuilder_action', 'uxbuilder_iframe', 'uxb_iframe', 'elementor-preview', 'et_fb', 'vc_editable', 'ct_builder', 'bricks', 'fl_builder' );

        $intersect = array_intersect( array_keys( $query_params ), $bypass_queries );
        if ( ! empty( $intersect ) ) {
            $matched_qs = implode( ', ', $intersect );
            if ( $debug ) {
                error_log( "UWB: Run bypassed: Query bypass parameters detected: {$matched_qs}." );
            }
            $GLOBALS['uwb_bypass_reason'] = 'Query string bypass: ' . $matched_qs;
            return;
        }

        // Check for any parameter starting or containing yith_wcan, yith-wcan, wc-api, rest_route, uxb, or page builder keys
        foreach ( array_keys( $query_params ) as $q_key ) {
            if ( strpos( $q_key, 'yith_wcan' ) !== false || strpos( $q_key, 'yith-wcan' ) !== false || strpos( $q_key, 'wc-api' ) !== false || strpos( $q_key, 'rest_route' ) !== false || strpos( $q_key, 'uxbuilder' ) !== false || strpos( $q_key, 'uxb' ) !== false || strpos( $q_key, 'elementor' ) !== false ) {
                if ( $debug ) {
                    error_log( "UWB: Run bypassed: API/Filter/PageBuilder query parameter detected '{$q_key}'." );
                }
                $GLOBALS['uwb_bypass_reason'] = 'API/Filter/PageBuilder parameter: ' . $q_key;
                return;
            }
        }
        
        $allowed_cache_queries = isset( $config['cache_query_strings'] ) ? $config['cache_query_strings'] : array();
        
        foreach ( $query_params as $param => $val ) {
            if ( in_array( $param, $config['ignored_query'], true ) ) {
                continue;
            }
            if ( in_array( $param, $allowed_cache_queries, true ) ) {
                $active_cache_query_params[$param] = $val;
                continue;
            }
            
            // Core WordPress query variables that must never be ignored (they route to specific inner pages)
            $core_wp_queries = array( 'p', 'page_id', 'post_id', 'cat', 'tag', 'm', 'name', 'category_name', 'post_type', 's', 'preview', 'orderby', 'order' );
            if ( in_array( $param, $core_wp_queries, true ) ) {
                // If it is 'p', 'page_id', or 'post_id', validate against the static valid post IDs JSON whitelist (Anti-DDoS 404)
                if ( ( $param === 'p' || $param === 'page_id' || $param === 'post_id' ) && ! empty( $val ) ) {
                    $post_id_val = intval( $val );
                    $wp_content_dir = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : dirname( __FILE__ );
                    $whitelist_json_path = $wp_content_dir . '/cache/uwb-valid-post-ids.json';
                    
                    if ( file_exists( $whitelist_json_path ) ) {
                        $whitelist_content = @file_get_contents( $whitelist_json_path );
                        $valid_ids = @json_decode( $whitelist_content, true );
                        
                        if ( is_array( $valid_ids ) && ! in_array( $post_id_val, $valid_ids, true ) ) {
                            // This Post ID is invalid (definitely a 404). Serve global 404 cache immediately!
                            if ( $debug ) {
                                error_log( "UWB: Anti-DDoS matched. Invalid routing parameter ID '{$post_id_val}'. Serving static 404." );
                            }
                            
                            $cache_file_404 = $wp_content_dir . '/cache/wp-rocket/' . $host . '/' . ( ( isset( $_SERVER['HTTPS'] ) && ( $_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] == 1 ) ) ? "404-https.html" : "404.html" );
                            header( 'HTTP/1.1 404 Not Found' );
                            header( 'X-Ultimate-WP-Booster-Serving-Static: Yes (Anti-DDoS 404 Whitelist)' );
                            header( 'Content-Type: text/html; charset=UTF-8' );
                            header( 'Cache-Control: no-cache, no-store, must-revalidate, private' );
                            header( 'Pragma: no-cache' );
                            
                            if ( file_exists( $cache_file_404 ) ) {
                                $supports_gzip = isset( $_SERVER['HTTP_ACCEPT_ENCODING'] ) && strpos( $_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip' ) !== false;
                                $gzip_file = $cache_file_404 . '_gzip';
                                if ( $supports_gzip && file_exists( $gzip_file ) && filesize( $gzip_file ) > 0 ) {
                                    @ini_set( 'zlib.output_compression', 'Off' );
                                    while ( ob_get_level() ) {
                                        ob_end_clean();
                                    }
                                    header( 'Content-Encoding: gzip' );
                                    header( 'Vary: Accept-Encoding' );
                                    @readfile( $gzip_file );
                                } else {
                                    @readfile( $cache_file_404 );
                                }
                            } else {
                                echo '<html><head><title>404 Not Found</title></head><body><h1>404 Not Found</h1><p>The requested URL was not found on this server.</p></body></html>';
                            }
                            exit;
                        }
                    }
                }

                if ( $debug ) {
                    error_log( "UWB: Run bypassed: Core WordPress routing parameter detected '{$param}'." );
                }
                $GLOBALS['uwb_bypass_reason'] = 'Core WP routing query param: ?' . $param . '=' . $val;
                return; // Bypass cache and let PHP generate the correct inner page
            }
            
            // Check if we should ignore strange query parameters and serve the clean URL cache
            $ignore_all = isset( $config['ignore_all_query_strings'] ) ? (bool)$config['ignore_all_query_strings'] : true;
            if ( ! $ignore_all ) {
                // If disabled, bypass cache completely for unrecognized query parameters (default WP behavior)
                if ( $debug ) {
                    error_log( "UWB: Run bypassed: Query string contains non-allowed parameter '{$param}'." );
                }
                $GLOBALS['uwb_bypass_reason'] = 'Unknown query parameter (Serve Cache for Strange Query Strings is OFF): ?' . $param . '=' . $val;
                return;
            }
        }
    }

    // 4.1 Check excluded cookies
    if ( ! empty( $_COOKIE ) && ! empty( $config['exclude_cookies'] ) ) {
        foreach ( $_COOKIE as $key => $val ) {
            foreach ( $config['exclude_cookies'] as $cookie_pattern ) {
                $cookie_pattern = trim( $cookie_pattern );
                if ( empty( $cookie_pattern ) ) continue;
                $regex = str_replace( '\*', '.*', preg_quote( $cookie_pattern, '#' ) );
                if ( preg_match( '#^' . $regex . '$#i', $key ) ) {
                    if ( $debug ) {
                        error_log( "UWB: Run bypassed: Excluded cookie matched: {$key}." );
                    }
                    $GLOBALS['uwb_bypass_reason'] = 'Excluded cookie matched: ' . $key;
                    return;
                }
            }
        }
    }

    // 4.2 Check excluded User Agent(s)
    if ( ! empty( $_SERVER['HTTP_USER_AGENT'] ) && ! empty( $config['exclude_user_agents'] ) ) {
        $ua = $_SERVER['HTTP_USER_AGENT'];
        foreach ( $config['exclude_user_agents'] as $ua_pattern ) {
            $ua_pattern = trim( $ua_pattern );
            if ( empty( $ua_pattern ) ) continue;
            if ( stripos( $ua, $ua_pattern ) !== false ) {
                if ( $debug ) {
                    error_log( "UWB: Run bypassed: Excluded User Agent matched: {$ua_pattern}." );
                }
                $GLOBALS['uwb_bypass_reason'] = 'Excluded User Agent matched: ' . $ua_pattern;
                return;
            }
        }
    }

    // 5. Detect logged-in state via cookies
    // NOTE: Cookie-based bypass has been removed. Cache is served to ALL visitors regardless of session cookies.
    // Only logged-in WordPress users are checked (controlled by the 'Cache logged-in users' setting).
    // WooCommerce pages (cart/checkout/my-account) are bypassed by URL pattern, not cookies.
    $logged_in_cookie_hash = '';
    if ( ! empty( $_COOKIE ) ) {
        foreach ( $_COOKIE as $key => $val ) {
            if ( strpos( $key, 'wordpress_logged_in_' ) === 0 ) {
                if ( intval( $cache_logged_in ) === 0 ) {
                    $optimize_logged_in = isset( $config['optimize_logged_in'] ) ? intval( $config['optimize_logged_in'] ) : 0;
                    if ( $optimize_logged_in === 0 ) {
                        if ( ! defined( 'UWB_BUFFER_STARTED' ) ) {
                            define( 'UWB_BUFFER_STARTED', true );
                        }
                        if ( $debug ) {
                            error_log( "UWB: Run bypassed: User is logged in but both cache_logged_in and optimize_logged_in are 0 (None)." );
                        }
                        $GLOBALS['uwb_bypass_reason'] = 'Logged-in user (cache & optimize for logged-in users is disabled)';
                        return;
                    }
                }
                $logged_in_cookie_hash = 'user-' . substr( md5( $val ), 0, 12 );
                $GLOBALS['uwb_logged_in_hash'] = $logged_in_cookie_hash;
            }
        }
    }
    // 6. Normalize host & URI
    $host = isset( $_SERVER['HTTP_HOST'] ) ? strtolower( $_SERVER['HTTP_HOST'] ) : '';
    if ( empty( $host ) ) {
        if ( $debug ) {
            error_log( "UWB: Run bypassed: Host header is empty." );
        }
        $GLOBALS['uwb_bypass_reason'] = 'HTTP_HOST header is empty';
        return;
    }
    $host = explode( ':', $host )[0];

    $request_uri    = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
    $uri_parts      = explode( '?', $request_uri );
    $uri_path       = rawurldecode( $uri_parts[0] );
    $normalized_uri = trim( $uri_path, '/' );

    // 6.0. Bypass REST API, Admin, XML-RPC, Non-HTML Accept requests, Page Builders, and WooCommerce protected pages
    $http_accept = isset( $_SERVER['HTTP_ACCEPT'] ) ? $_SERVER['HTTP_ACCEPT'] : '';
    if ( strpos( $uri_path, '/wp-json' ) === 0 || strpos( $normalized_uri, 'wp-json' ) === 0 || 
         strpos( $uri_path, '/wp-admin' ) === 0 || strpos( $uri_path, '/xmlrpc.php' ) === 0 ||
         strpos( $uri_path, 'uxbuilder' ) !== false || strpos( $uri_path, 'elementor-preview' ) !== false ||
         isset( $_GET['rest_route'] ) || isset( $_GET['uxbuilder'] ) || ( isset( $_GET['app'] ) && $_GET['app'] === 'uxbuilder' ) ||
         ( strpos( $http_accept, 'application/json' ) !== false && strpos( $http_accept, 'text/html' ) === false ) ) {
        if ( $debug ) {
            error_log( "UWB: Run bypassed: REST API / Page Builder / Non-HTML request detected ({$uri_path})." );
        }
        $GLOBALS['uwb_bypass_reason'] = 'REST API / Page Builder / Non-HTML request: ' . $uri_path;
        return;
    }

    if ( preg_match( '#/(cart|checkout|my-account)/?#i', $uri_path ) ||
         strpos( $normalized_uri, 'cart' ) === 0 || 
         strpos( $normalized_uri, 'checkout' ) === 0 || 
         strpos( $normalized_uri, 'my-account' ) === 0 ) {
        if ( $debug ) {
            error_log( "UWB: Run bypassed: WooCommerce cart/checkout/my-account page detected." );
        }
        $GLOBALS['uwb_bypass_reason'] = 'WooCommerce protected page (cart/checkout/my-account): ' . $uri_path;
        return;
    }

    // 6.1 Check XML Sitemap Caching bypass
    $is_xml = ( substr( strtolower( $normalized_uri ), -4 ) === '.xml' );
    if ( $is_xml && empty( $config['cache_xml_sitemaps'] ) ) {
        if ( $debug ) {
            error_log( "UWB: Run bypassed: Sitemap caching disabled for XML: {$normalized_uri}." );
        }
        $GLOBALS['uwb_bypass_reason'] = 'XML sitemap caching is disabled: ' . $normalized_uri;
        return;
    }

    // 6.2 Check PHP Caching bypass
    $is_php = ( substr( strtolower( $normalized_uri ), -4 ) === '.php' && strtolower( $normalized_uri ) !== 'index.php' );
    if ( $is_php && empty( $config['cache_php'] ) ) {
        if ( $debug ) {
            error_log( "UWB: Run bypassed: PHP caching disabled for PHP: {$normalized_uri}." );
        }
        $GLOBALS['uwb_bypass_reason'] = 'PHP file caching is disabled: ' . $normalized_uri;
        return;
    }

    $GLOBALS['uwb_is_php'] = $is_php;
    $GLOBALS['uwb_is_xml'] = $is_xml;

    // 7. Check excluded URLs
    if ( ! empty( $config['excluded_urls'] ) ) {
        $absolute_uri = ( $normalized_uri === '' ) ? '/' : '/' . $normalized_uri;
        foreach ( $config['excluded_urls'] as $pattern ) {
            $pattern = trim( $pattern );
            if ( empty( $pattern ) ) continue;
            $regex = str_replace( '\*', '.*', preg_quote( $pattern, '#' ) );
            if ( preg_match( '#^' . $regex . '$#i', $absolute_uri ) ||
                 preg_match( '#^' . $regex . '$#i', $uri_path ) ) {
                if ( $debug ) {
                    error_log( "UWB: Run bypassed: Excluded URL pattern matched: {$pattern}." );
                }
                $GLOBALS['uwb_bypass_reason'] = 'URL in exclusion list (pattern: ' . $pattern . ')';
                return;
            }
        }
    }

    // 8. Determine cache filename
    $is_https = false;
    if ( ( isset( $_SERVER['HTTPS'] ) && ( $_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] == 1 ) ) ||
         ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https' ) ||
         ( isset( $_SERVER['SERVER_PORT'] ) && $_SERVER['SERVER_PORT'] == 443 ) ) {
        $is_https = true;
    }
    
    $q_suffix = '';
    if ( ! empty( $active_cache_query_params ) ) {
        ksort( $active_cache_query_params );
        $q_suffix = '-q-' . md5( http_build_query( $active_cache_query_params ) );
    }
    $filename = $is_https ? "index-https{$q_suffix}.html" : "index{$q_suffix}.html";

    // 9. Build cache directory path (per-user subdirectory for logged-in users)
    $base_cache_dir = WP_CONTENT_DIR . '/cache/wp-rocket/' . $host;
    if ( $normalized_uri !== '' ) {
        $base_cache_dir .= '/' . $normalized_uri;
    }
    $cache_dir  = ( $logged_in_cookie_hash !== '' ) ? $base_cache_dir . '/' . $logged_in_cookie_hash : $base_cache_dir;
    $cache_file = $cache_dir . '/' . $filename;

    // 10. Serve cached file if valid
    $served = false;
    $cache_file_404 = $cache_dir . '/' . ( $is_https ? "404-https.html" : "404.html" );

    // Detect preload request: the preloader must bypass cache serving so it can regenerate & overwrite stale cache
    $is_preload_request = ( isset( $_SERVER['HTTP_X_ULTIMATE_WP_BOOSTER_PRELOAD'] ) && $_SERVER['HTTP_X_ULTIMATE_WP_BOOSTER_PRELOAD'] === '1' ) || ( isset( $_GET['uwb_preload_key'] ) && $_GET['uwb_preload_key'] !== '' );
    if ( $is_preload_request && $debug ) {
        error_log( "UWB: Preload request detected (HTTP_X_ULTIMATE_WP_BOOSTER_PRELOAD or uwb_preload_key). Bypassing cache serve to force regeneration." );
    }

    // Check normal cache first, then 404 cache
    $target_cache_file = '';
    $is_serving_404 = false;
    if ( ! $is_preload_request ) {
        if ( file_exists( $cache_file ) ) {
            $target_cache_file = $cache_file;
        } elseif ( file_exists( $cache_file_404 ) ) {
            $target_cache_file = $cache_file_404;
            $is_serving_404 = true;
        }
    } else {
        // Preload request: delete existing (potentially stale) cache file so shutdown can write fresh content
        if ( file_exists( $cache_file ) ) {
            @unlink( $cache_file );
            if ( file_exists( $cache_file . '_gzip' ) ) {
                @unlink( $cache_file . '_gzip' );
            }
            if ( $debug ) {
                error_log( "UWB: Preload: deleted stale cache file for regeneration: {$cache_file}" );
            }
        }
    }

    if ( $target_cache_file !== '' ) {
        // Guard Check: Verify file content integrity before serving to prevent serving corrupted or JSON files as HTML
        if ( ! $is_serving_404 && file_exists( $target_cache_file ) ) {
            $sample = @file_get_contents( $target_cache_file, false, null, 0, 300 );
            if ( $sample !== false ) {
                $trim_sample = ltrim( $sample );
                $is_corrupt_json = ( strpos( $trim_sample, '{' ) === 0 || strpos( $trim_sample, '[' ) === 0 );
                $is_valid_html   = ( stripos( $trim_sample, '<html' ) !== false || stripos( $trim_sample, '<!DOCTYPE' ) !== false || stripos( $trim_sample, '<!-- Cached by WP Booster' ) !== false || stripos( $trim_sample, '<head' ) !== false || stripos( $trim_sample, '<body' ) !== false );
                
                if ( ( ! $is_xml && ( $is_corrupt_json || ! $is_valid_html ) ) || 
                     ( $is_xml && strpos( $trim_sample, '<?xml' ) === false && strpos( $trim_sample, '<urlset' ) === false && strpos( $trim_sample, '<sitemapindex' ) === false ) ) {
                    // Corrupted/Non-HTML cache file detected on disk! Delete it immediately & bypass serving
                    @unlink( $target_cache_file );
                    @unlink( $target_cache_file . '_gzip' );
                    if ( $debug ) {
                        error_log( "UWB: Corrupted/Non-HTML cache file detected and deleted: {$target_cache_file}" );
                    }
                    $target_cache_file = ''; // Cancel serving target cache file
                }
            }
        }
    }

    if ( $target_cache_file !== '' ) {
        $file_time = @filemtime( $target_cache_file );
        $lifespan  = intval( $config['cache_lifespan'] );

        if ( $is_xml ) {
            $lifespan = isset( $config['cache_xml_sitemaps_lifespan'] ) ? intval( $config['cache_xml_sitemaps_lifespan'] ) : $lifespan;
        } elseif ( $is_php ) {
            $lifespan = isset( $config['cache_php_lifespan'] ) ? intval( $config['cache_php_lifespan'] ) : $lifespan;
        } elseif ( $logged_in_cookie_hash !== '' ) {
            // Cache logged-in users for the configured duration (default 10 minutes / 600 seconds)
            $logged_in_lifespan = isset( $config['cache_logged_in_lifespan'] ) ? intval( $config['cache_logged_in_lifespan'] ) : 600;
            $lifespan = ( $lifespan === 0 ) ? $logged_in_lifespan : min( $lifespan, $logged_in_lifespan );
        }

        if ( $lifespan === 0 || ( time() - $file_time ) < $lifespan ) {
            $gzip_file     = $target_cache_file . '_gzip';
            $supports_gzip = isset( $_SERVER['HTTP_ACCEPT_ENCODING'] ) &&
                             strpos( $_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip' ) !== false;

            if ( $is_serving_404 ) {
                header( 'HTTP/1.1 404 Not Found' );
                header( 'X-Ultimate-WP-Booster-Serving-Static: Yes (404 Cache)' );
            } else {
                header( 'X-Ultimate-WP-Booster-Serving-Static: Yes' );
            }
            
            if ( $logged_in_cookie_hash !== '' ) {
                $logged_in_lifespan = isset( $config['cache_logged_in_lifespan'] ) ? intval( $config['cache_logged_in_lifespan'] ) : 600;
                header( 'Cache-Control: private, max-age=' . $logged_in_lifespan );
                header( 'Pragma: private' );
                $server_software = isset( $_SERVER['SERVER_SOFTWARE'] ) ? $_SERVER['SERVER_SOFTWARE'] : '';
                if ( ! empty( $server_software ) && ( stripos( $server_software, 'litespeed' ) !== false || stripos( $server_software, 'openlitespeed' ) !== false ) ) {
                    header( 'X-LiteSpeed-Cache-Control: private, max-age=' . $logged_in_lifespan );
                    header( 'X-LiteSpeed-Vary: cookie=wordpress_logged_in' );
                }
            } else {
                $bc_enabled = isset( $config['browser_cache_enabled'] ) ? intval( $config['browser_cache_enabled'] ) : 1;
                
                // For HTML pages, we should always prevent browser/CDN local caching to avoid logged-in user issues.
                // XML sitemaps are safe to browser-cache.
                if ( $bc_enabled && $is_xml && ! $is_serving_404 ) {
                    $bc_lifespan = isset( $config['browser_cache_lifespan'] ) ? intval( $config['browser_cache_lifespan'] ) : 3600;
                    header( 'Pragma: public' );
                    header( 'Cache-Control: max-age=' . $bc_lifespan . ', public' );
                    
                    $server_software = isset( $_SERVER['SERVER_SOFTWARE'] ) ? $_SERVER['SERVER_SOFTWARE'] : '';
                    if ( ! empty( $server_software ) && ( stripos( $server_software, 'litespeed' ) !== false || stripos( $server_software, 'openlitespeed' ) !== false ) ) {
                        header( 'X-LiteSpeed-Cache-Control: public, max-age=' . $bc_lifespan );
                        header( 'X-LiteSpeed-Vary: cookie=wordpress_logged_in' );
                    }
                } else {
                    // Prevent browser/CDN caching for HTML pages or when browser cache is disabled
                    header( 'Cache-Control: no-cache, no-store, must-revalidate, private' );
                    header( 'Pragma: no-cache' );
                    
                    $server_software = isset( $_SERVER['SERVER_SOFTWARE'] ) ? $_SERVER['SERVER_SOFTWARE'] : '';
                    if ( ! $is_serving_404 && ! empty( $server_software ) && ( stripos( $server_software, 'litespeed' ) !== false || stripos( $server_software, 'openlitespeed' ) !== false ) ) {
                        // Still allow LiteSpeed server cache for guest HTML pages
                        $bc_lifespan = isset( $config['browser_cache_lifespan'] ) ? intval( $config['browser_cache_lifespan'] ) : 3600;
                        header( 'X-LiteSpeed-Cache-Control: public, max-age=' . $bc_lifespan );
                        header( 'X-LiteSpeed-Vary: cookie=wordpress_logged_in' );
                    } else {
                        header( 'X-LiteSpeed-Cache-Control: no-cache' );
                    }
                }
            }
            if ( isset( $is_xml ) && $is_xml ) {
                header( 'Content-Type: text/xml; charset=UTF-8' );
            } else {
                header( 'Content-Type: text/html; charset=UTF-8' );
            }

            if ( $supports_gzip && file_exists( $gzip_file ) && filesize( $gzip_file ) > 0 ) {
                @ini_set( 'zlib.output_compression', 'Off' );
                while ( ob_get_level() ) {
                    ob_end_clean();
                }
                header( 'Content-Encoding: gzip' );
                header( 'Vary: Accept-Encoding' );
                @readfile( $gzip_file );
            } else {
                @readfile( $target_cache_file );
            }
            if ( $debug ) {
                error_log( "UWB: Served static cache file: {$target_cache_file}" );
            }
            exit;
        } else {
            if ( $debug ) {
                error_log( "UWB: Cache file exists but expired: {$cache_file}" );
            }
        }
    } else {
        if ( $debug ) {
            error_log( "UWB: Cache file does not exist: {$cache_file}" );
        }
    }

    // 11. No valid cache – capture output to write on shutdown
    if ( ! defined( 'UWB_BUFFER_STARTED' ) ) {
        define( 'UWB_BUFFER_STARTED', true );

        $GLOBALS['uwb_cache_file']        = $cache_file;
        $GLOBALS['uwb_cache_dir']         = $cache_dir;
        $GLOBALS['uwb_config_path']       = $config_path;
        $GLOBALS['uwb_logged_in_segment'] = $logged_in_cookie_hash;
        $GLOBALS['uwb_config']            = $config;
        $GLOBALS['uwb_accumulated_html']  = '';

        $ob_ok = ob_start( 'uwb_advanced_cache_ob_callback' );
        if ( $debug ) {
            if ( ! $ob_ok ) {
                error_log( "UWB: ob_start('uwb_advanced_cache_ob_callback') failed!" );
            } else {
                error_log( "UWB: Started output buffering using ob_start." );
            }
        }
        register_shutdown_function( 'uwb_advanced_cache_shutdown' );
    }
}

function uwb_advanced_cache_ob_callback( $buffer, $phase = 0 ) {
    $debug = defined( 'WP_DEBUG' ) && WP_DEBUG;
    if ( $debug ) {
        error_log( "UWB: Callback invoked. Phase: {$phase}, Buffer length: " . strlen( $buffer ) );
    }

    if ( ! isset( $GLOBALS['uwb_accumulated_html'] ) ) {
        $GLOBALS['uwb_accumulated_html'] = '';
    }

    // Only bypass cache if this is a true discard (clean without final and without write)
    // PHP_OUTPUT_HANDLER_CLEAN = 2, PHP_OUTPUT_HANDLER_WRITE = 0, PHP_OUTPUT_HANDLER_FINAL = 4
    $is_final = (bool) ( $phase & PHP_OUTPUT_HANDLER_FINAL );
    $is_clean = (bool) ( $phase & PHP_OUTPUT_HANDLER_CLEAN );
    $has_content = strlen( $buffer ) > 0;

    // If it's a clean phase without final AND without actual content, treat as discard
    if ( $is_clean && ! $is_final && ! $has_content ) {
        if ( $debug ) {
            error_log( "UWB: Callback empty clean phase. Resetting accumulated buffer." );
        }
        $GLOBALS['uwb_accumulated_html'] = '';
        return $buffer;
    }

    $GLOBALS['uwb_accumulated_html'] .= $buffer;
    return $buffer;
}

function uwb_advanced_cache_shutdown() {
    $debug = defined( 'WP_DEBUG' ) && WP_DEBUG;

    $html = '';
    while ( ob_get_level() > 0 ) {
        $level_content = @ob_get_clean();
        if ( $level_content !== false ) {
            $html = $level_content . $html;
        }
    }

    if ( empty( $html ) && isset( $GLOBALS['uwb_accumulated_html'] ) ) {
        $html = $GLOBALS['uwb_accumulated_html'];
    }

    if ( empty( $html ) ) {
        return;
    }

    $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
    $is_xml = ( stripos( $request_uri, 'sitemap' ) !== false || stripos( $request_uri, '.xml' ) !== false || ! empty( $GLOBALS['uwb_is_xml'] ) );

    // Re-read config
    $wp_content_dir = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : dirname( __FILE__ );
    $config_path = isset( $GLOBALS['uwb_config_path'] )
        ? $GLOBALS['uwb_config_path']
        : $wp_content_dir . '/cache/ultimate-wp-booster-config.php';

    $config = isset( $GLOBALS['uwb_config'] ) ? $GLOBALS['uwb_config'] : array();
    if ( empty( $config ) && file_exists( $config_path ) ) {
        $parsed_config = include $config_path;
        if ( is_array( $parsed_config ) ) {
            $config = $parsed_config;
        }
    }

    $cache_404 = ! empty( $config['cache_404'] );

    // Determine if we should cache
    $should_cache = true;
    $shutdown_bypass_reason = ''; // Track reason for 'Dynamic Page' comment
    if ( ! empty( $GLOBALS['uwb_do_not_cache'] ) ) {
        $should_cache = false;
        $shutdown_bypass_reason = 'Globals uwb_do_not_cache is set (buffer clean/discard or BufferSubscriber)';
    }
    $response_code = http_response_code();
    if ( $response_code !== 200 && ! ( $cache_404 && $response_code === 404 ) ) {
        $should_cache = false;
        $shutdown_bypass_reason = 'HTTP response code: ' . $response_code . ( $cache_404 ? '' : ' (cache_404 disabled)' );
        if ( $debug ) {
            error_log( "UWB: Caching bypassed: Response code is {$response_code} (cache_404 is " . ($cache_404 ? 'on' : 'off') . ")." );
        }
    }
    if ( strlen( $html ) < 200 ) {
        $should_cache = false;
        if ( empty( $shutdown_bypass_reason ) ) $shutdown_bypass_reason = 'HTML too short (' . strlen( $html ) . ' chars < 200)';
        if ( $debug ) {
            error_log( "UWB: Caching bypassed: HTML length (" . strlen( $html ) . ") is less than 200 characters." );
        }
    }
    
    if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
        $should_cache = false;
        $shutdown_bypass_reason = 'REST_REQUEST constant is true';
    }

    // Inspect emitted response headers
    if ( $should_cache && function_exists( 'headers_list' ) ) {
        foreach ( headers_list() as $header ) {
            if ( stripos( $header, 'Content-Type:' ) === 0 ) {
                $content_type = strtolower( trim( substr( $header, 13 ) ) );
                if ( ! $is_xml ) {
                    if ( strpos( $content_type, 'text/html' ) === false && strpos( $content_type, 'text/xhtml' ) === false ) {
                        $should_cache = false;
                        $shutdown_bypass_reason = 'Non-HTML Content-Type header emitted: ' . $content_type;
                        if ( $debug ) {
                            error_log( "UWB: Caching bypassed: Content-Type is {$content_type}" );
                        }
                        break;
                    }
                } else {
                    if ( strpos( $content_type, 'xml' ) === false ) {
                        $should_cache = false;
                        $shutdown_bypass_reason = 'Non-XML Content-Type header emitted for sitemap: ' . $content_type;
                        break;
                    }
                }
            } elseif ( stripos( $header, 'Location:' ) === 0 ) {
                $should_cache = false;
                $shutdown_bypass_reason = 'Redirect Location header emitted';
                break;
            }
        }
    }

    // Inspect HTML content structural integrity
    if ( $should_cache ) {
        $trim_html = ltrim( $html );
        if ( ! $is_xml ) {
            // Detect JSON array or object payload
            if ( strpos( $trim_html, '{' ) === 0 || strpos( $trim_html, '[' ) === 0 ) {
                $should_cache = false;
                $shutdown_bypass_reason = 'Output is JSON payload (starts with { or [)';
                if ( $debug ) {
                    error_log( "UWB: Caching bypassed: Output is JSON payload." );
                }
            }
            // Verify valid HTML structure
            elseif ( stripos( $trim_html, '<html' ) === false && stripos( $trim_html, '<!DOCTYPE' ) === false && stripos( $trim_html, '<head' ) === false && stripos( $trim_html, '<body' ) === false ) {
                $should_cache = false;
                $shutdown_bypass_reason = 'Output lacks valid HTML markup structure';
                if ( $debug ) {
                    error_log( "UWB: Caching bypassed: Output does not contain standard HTML tags." );
                }
            }
        } else {
            if ( strpos( $trim_html, '<?xml' ) === false && strpos( $trim_html, '<urlset' ) === false && strpos( $trim_html, '<sitemapindex' ) === false ) {
                $should_cache = false;
                $shutdown_bypass_reason = 'Sitemap output lacks XML declaration';
            }
        }
    }

    $is_special_page = is_admin() || is_search() || is_feed() || is_trackback() || is_robots();
    if ( $is_special_page || ( is_404() && ! $cache_404 ) ) {
        $should_cache = false;
        if ( empty( $shutdown_bypass_reason ) ) {
            $special = array();
            if ( is_admin() ) $special[] = 'is_admin';
            if ( is_search() ) $special[] = 'is_search';
            if ( is_feed() ) $special[] = 'is_feed';
            if ( is_trackback() ) $special[] = 'is_trackback';
            if ( is_robots() ) $special[] = 'is_robots';
            if ( is_404() ) $special[] = 'is_404';
            $shutdown_bypass_reason = 'Special page: ' . implode( ', ', $special );
        }
        if ( $debug ) {
            error_log( "UWB: Caching bypassed: Special page matched: is_admin=" . (is_admin()?'yes':'no') . ", is_search=" . (is_search()?'yes':'no') . ", is_feed=" . (is_feed()?'yes':'no') . ", is_trackback=" . (is_trackback()?'yes':'no') . ", is_robots=" . (is_robots()?'yes':'no') . ", is_404=" . (is_404()?'yes':'no') );
        }
    }

    $cache_logged_in    = isset( $config['cache_logged_in'] ) ? intval( $config['cache_logged_in'] ) : 0;
    $optimize_logged_in = isset( $config['optimize_logged_in'] ) ? intval( $config['optimize_logged_in'] ) : 0;
    $timezone_str    = isset( $config['timezone'] ) && ! is_numeric( $config['timezone'] ) ? $config['timezone'] : 'UTC';
    $timezone_offset = isset( $config['timezone'] ) && is_numeric( $config['timezone'] ) ? floatval( $config['timezone'] ) * 3600 : 0;
    if ( is_numeric( isset( $config['timezone'] ) ? $config['timezone'] : '' ) ) {
        $timezone_str = '';
    }
    $lifespan = isset( $config['cache_lifespan'] ) ? intval( $config['cache_lifespan'] ) : 36000;

    $is_xml = ! empty( $GLOBALS['uwb_is_xml'] );
    $is_php = ! empty( $GLOBALS['uwb_is_php'] );

    if ( $is_xml ) {
        $lifespan = isset( $config['cache_xml_sitemaps_lifespan'] ) ? intval( $config['cache_xml_sitemaps_lifespan'] ) : $lifespan;
    } elseif ( $is_php ) {
        $lifespan = isset( $config['cache_php_lifespan'] ) ? intval( $config['cache_php_lifespan'] ) : $lifespan;
    } else {
        $logged_in_segment = isset( $GLOBALS['uwb_logged_in_segment'] ) ? $GLOBALS['uwb_logged_in_segment'] : '';
        // Cache logged-in users for the configured duration (default 10 minutes / 600 seconds)
        if ( $logged_in_segment !== '' ) {
            $logged_in_lifespan = isset( $config['cache_logged_in_lifespan'] ) ? intval( $config['cache_logged_in_lifespan'] ) : 600;
            $lifespan = ( $lifespan === 0 ) ? $logged_in_lifespan : min( $lifespan, $logged_in_lifespan );
        }
    }

    $cache_logged_in_val = intval( $cache_logged_in );
    if ( $cache_logged_in_val !== 2 && $logged_in_segment !== '' ) {
        $should_cache = false;
        if ( empty( $shutdown_bypass_reason ) ) $shutdown_bypass_reason = 'Logged-in user (cache_logged_in is set to ' . ( $cache_logged_in_val === 1 ? 'Optimize Only' : 'None' ) . ')';
        if ( $debug ) {
            error_log( "UWB: Caching bypassed: User is logged in but cache_logged_in setting is " . $cache_logged_in_val );
        }
    }
    if ( $cache_logged_in_val !== 2 && function_exists( 'is_user_logged_in' ) && is_user_logged_in() ) {
        $should_cache = false;
        if ( empty( $shutdown_bypass_reason ) ) $shutdown_bypass_reason = 'Logged-in user (is_user_logged_in() = true, cache_logged_in is set to ' . ( $cache_logged_in_val === 1 ? 'Optimize Only' : 'None' ) . ')';
        if ( $debug ) {
            error_log( "UWB: Caching bypassed: is_user_logged_in() is true and cache_logged_in setting is " . $cache_logged_in_val );
        }
    }

    if ( $cache_logged_in_val !== 2 && function_exists( 'current_user_can' ) && current_user_can( 'manage_options' ) ) {
        $should_cache = false;
        if ( empty( $shutdown_bypass_reason ) ) {
            $shutdown_bypass_reason = 'Logged-in Administrator (manage_options)';
        }
        if ( $debug ) {
            error_log( "UWB: Caching bypassed: Logged-in Administrator detected." );
        }
    }

    $cache_file = isset( $GLOBALS['uwb_cache_file'] ) ? $GLOBALS['uwb_cache_file'] : '';
    $cache_dir  = isset( $GLOBALS['uwb_cache_dir'] ) ? $GLOBALS['uwb_cache_dir'] : '';

    // If this is a 404 page, rename the cache file target to prevent overriding index.html
    if ( $response_code === 404 && $cache_404 ) {
        $is_https = (strpos(basename($cache_file), 'index-https') === 0);
        $cache_file = $cache_dir . '/' . ($is_https ? '404-https.html' : '404.html');
    }

    if ( ! $cache_file || ! $cache_dir ) {
        $should_cache = false;
        if ( empty( $shutdown_bypass_reason ) ) $shutdown_bypass_reason = 'Cache file/dir path is not set (advanced-cache.php may not have run correctly)';
        if ( $debug ) {
            error_log( "UWB: Caching bypassed: cache_file or cache_dir is not set." );
        }
    }

    // Gather Object Cache statistics
    global $wp_object_cache;
    $oc_hits = 0;
    $oc_misses = 0;
    if ( isset( $wp_object_cache->cache_hits ) ) {
        $oc_hits = intval( $wp_object_cache->cache_hits );
    }
    if ( isset( $wp_object_cache->cache_misses ) ) {
        $oc_misses = intval( $wp_object_cache->cache_misses );
    }
    $oc_total = $oc_hits + $oc_misses;
    $oc_ratio = $oc_total > 0 ? round( ( $oc_hits / $oc_total ) * 100, 1 ) : 0;
    $oc_status = wp_using_ext_object_cache() ? 'Active (Redis)' : 'Inactive';
    $ua_str = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : 'Unknown UA';
    $ua_clean = str_replace( '--', '- -', $ua_str );
    $oc_comment = " | Object Cache: {$oc_status} (Hits: {$oc_hits} | Misses: {$oc_misses} | Ratio: {$oc_ratio}%) | User Agent: {$ua_clean}";

    // Only append comments to HTML responses and non-admin requests
    $is_html = true;
    $is_xml_response = false;
    if ( function_exists( 'headers_list' ) ) {
        foreach ( headers_list() as $header ) {
            if ( stripos( $header, 'Content-Type:' ) === 0 && stripos( $header, 'xml' ) !== false ) {
                $is_xml_response = true;
                break;
            }
        }
    }
    if ( is_admin() || is_feed() || is_trackback() || is_robots() || $is_xml_response ) {
        $is_html = false;
    }

    if ( $is_html ) {
        // Build timestamp with UTC offset notation
        $now = time();
        if ( $timezone_str ) {
            @date_default_timezone_set( $timezone_str );
            // Get UTC offset for the timezone
            $tz_obj      = timezone_open( $timezone_str );
            $utc_offset  = $tz_obj ? timezone_offset_get( $tz_obj, date_create( 'now', $tz_obj ) ) / 3600 : 0;
            $utc_label   = 'UTC' . ( $utc_offset >= 0 ? '+' : '' ) . $utc_offset;
            $time_str    = date( 'H:i:s d/m/Y' );
        } else {
            $local_ts   = $now + $timezone_offset;
            $utc_offset = $timezone_offset / 3600;
            $utc_label  = 'UTC' . ( $utc_offset >= 0 ? '+' : '' ) . $utc_offset;
            $time_str   = gmdate( 'H:i:s d/m/Y', $local_ts );
        }

        // Calculate next refresh time
        if ( $lifespan > 0 ) {
            if ( $timezone_str ) {
                $next_ts  = $now + $lifespan;
                $next_str = date( 'H:i:s d/m/Y', $next_ts );
            } else {
                $next_ts  = $now + $timezone_offset + $lifespan;
                $next_str = gmdate( 'H:i:s d/m/Y', $next_ts );
            }
            $refresh_comment = " | Next refresh: {$next_str} ({$utc_label})";
        } else {
            $refresh_comment = ' | Cache: unlimited lifespan';
        }

        $active_features = array();
        if ( ! empty( $config['cache_page_enabled'] ) ) $active_features[] = "Page Cache";
        if ( ! empty( $config['html_minify'] ) ) $active_features[] = "Minify HTML";
        if ( ! empty( $config['css_minify'] ) ) $active_features[] = "Minify CSS";
        if ( ! empty( $config['css_combine'] ) ) $active_features[] = "Combine CSS";
        if ( ! empty( $config['js_minify'] ) ) $active_features[] = "Minify JS";
        if ( ! empty( $config['js_combine'] ) ) $active_features[] = "Combine JS";
        if ( ! empty( $config['js_load_defer'] ) ) $active_features[] = "Defer JS";
        if ( ! empty( $config['html_remove_qs'] ) ) $active_features[] = "Remove Query Strings";
        if ( ! empty( $config['html_remove_gfonts'] ) ) $active_features[] = "Remove Google Fonts";
        if ( ! empty( $config['html_remove_emoji'] ) ) $active_features[] = "Remove Emoji";
        if ( ! empty( $config['html_remove_noscript'] ) ) $active_features[] = "Remove Noscript";
        if ( ! empty( $config['media_lazy_load_images'] ) ) $active_features[] = "Lazy Load Images";
        if ( ! empty( $config['media_lazy_load_iframes'] ) ) $active_features[] = "Lazy Load Iframes";
        if ( ! empty( $config['redis_enabled'] ) ) $active_features[] = "Redis Object Cache";

        $box_comment = '';
        if ( ! empty( $active_features ) ) {
            $lines = array();
            $lines[] = "Active Optimization Features:";
            foreach ( $active_features as $feature ) {
                $lines[] = "  [x] " . $feature;
            }
            
            $max_len = 0;
            foreach ( $lines as $line ) {
                $len = strlen( $line );
                if ( $len > $max_len ) {
                    $max_len = $len;
                }
            }
            $width = $max_len + 4;
            
            $border = "+" . str_repeat( "-", $width - 2 ) . "+";
            $box_comment .= "\n" . $border . "\n";
            foreach ( $lines as $line ) {
                $padding = $width - 2 - strlen( $line );
                $box_comment .= "| " . $line . str_repeat( " ", $padding ) . " |\n";
            }
            $box_comment .= $border;
        }

        // Run Page Optimization Processor (Runs for both cached and bypassed requests)
        $optimizer_path = '';
        if ( defined( 'UWB_PLUGIN_DIR' ) ) {
            $optimizer_path = UWB_PLUGIN_DIR . 'inc/Engine/Optimization/Optimizer.php';
        }
        if ( empty( $optimizer_path ) || ! file_exists( $optimizer_path ) ) {
            $plugin_dir = (defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : dirname( __FILE__ )) . '/plugins/ultimate-wp-booster/';
            $optimizer_path = $plugin_dir . 'inc/Engine/Optimization/Optimizer.php';
        }
        // Skip Optimizer if user is logged in and both cache_logged_in and optimize_logged_in are None (0)
        $logged_in_hash        = isset( $GLOBALS['uwb_logged_in_hash'] ) ? $GLOBALS['uwb_logged_in_hash'] : '';
        $is_user_logged_in_req = ( $logged_in_hash !== '' || ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() ) );
        $skip_optimization     = ( $is_user_logged_in_req && intval( $cache_logged_in ) === 0 && intval( $optimize_logged_in ) === 0 );

        if ( ! $skip_optimization && file_exists( $optimizer_path ) ) {
            require_once $optimizer_path;
            if ( class_exists( 'Ultimate_WP_Booster\Engine\Optimization\Optimizer' ) ) {
                $html = \Ultimate_WP_Booster\Engine\Optimization\Optimizer::process( $html, $config );
            }
        }

        if ( $should_cache ) {
            $comment_to_append = "<!-- Cached by WP Booster at {$time_str} ({$utc_label}){$refresh_comment}{$oc_comment}{$box_comment} | Status: Cache Valid / Serviced -->\n";
            $html = $comment_to_append . $html;
        } else {
            $early_reason    = isset( $GLOBALS['uwb_bypass_reason'] ) ? $GLOBALS['uwb_bypass_reason'] : '';
            $combined_reason = ! empty( $shutdown_bypass_reason ) ? $shutdown_bypass_reason : $early_reason;
            $bypass_str      = ! empty( $combined_reason ) ? " | Bypass Reason: {$combined_reason}" : ' | Bypass Reason: Unknown';
            $bypass_comment  = "\n<!-- Cached by WP Booster | Status: Bypassed{$bypass_str}{$oc_comment} -->\n";
            $html            = $html . $bypass_comment;
        }
    }

    if ( ! $should_cache ) {
        echo $html;
        return;
    }

    if ( ! file_exists( $cache_dir ) ) {
        $mkdir_ok = @mkdir( $cache_dir, 0755, true );
        if ( ! $mkdir_ok && $debug ) {
            error_log( "UWB: Failed to create cache directory: {$cache_dir}" );
        }
    }
    if ( ! is_dir( $cache_dir ) ) {
        if ( $debug ) {
            error_log( "UWB: Cache path exists but is not a directory: {$cache_dir}" );
        }
        echo $html;
        return;
    }
    if ( ! is_writable( $cache_dir ) ) {
        if ( $debug ) {
            error_log( "UWB: Cache directory is not writable: {$cache_dir}" );
        }
        echo $html;
        return;
    }

    $write_bytes = @file_put_contents( $cache_file, $html );
    if ( $write_bytes === false ) {
        if ( $debug ) {
            error_log( "UWB: Failed to write cache file: {$cache_file}" );
        }
    } else {
        if ( $debug ) {
            error_log( "UWB: Successfully cached file: {$cache_file} ({$write_bytes} bytes)" );
        }
    }

    $gzip_file    = $cache_file . '_gzip';
    $gzipped_html = gzencode( $html, 9 );
    if ( $gzipped_html !== false ) {
        $write_gzip_bytes = @file_put_contents( $gzip_file, $gzipped_html );
        if ( $write_gzip_bytes === false && $debug ) {
            error_log( "UWB: Failed to write gzip cache file: {$gzip_file}" );
        }
    }
    echo $html;
}

function uwb_start_output_buffering() {
    if ( ! defined( 'UWB_BUFFER_STARTED' ) ) {
        define( 'UWB_BUFFER_STARTED', true );
    }
    if ( ! isset( $GLOBALS['uwb_config'] ) ) {
        $config_path = WP_CONTENT_DIR . '/cache/ultimate-wp-booster-config.php';
        $config = array();
        if ( file_exists( $config_path ) ) {
            $parsed_config = include $config_path;
            if ( is_array( $parsed_config ) ) {
                $config = $parsed_config;
            }
        }
        $GLOBALS['uwb_config'] = $config;
        $GLOBALS['uwb_config_path'] = $config_path;
    }
    $GLOBALS['uwb_accumulated_html']  = '';
    $GLOBALS['uwb_do_not_cache']      = true;
    
    ob_start( 'uwb_advanced_cache_ob_callback' );
    register_shutdown_function( 'uwb_advanced_cache_shutdown' );
}
