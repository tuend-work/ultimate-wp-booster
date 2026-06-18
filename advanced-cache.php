<?php
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
    // 1. Only cache GET requests
    if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || $_SERVER['REQUEST_METHOD'] !== 'GET' ) {
        return;
    }

    // 2. Do not cache command line/WP-CLI requests
    if ( php_sapi_name() === 'cli' ) {
        return;
    }

    // 3. Load config file
    $config_path = WP_CONTENT_DIR . '/cache/ultimate-wp-booster-config.json';
    $config = array(
        'cache_lifespan' => 36000, // 10 hours in seconds
        'excluded_urls'  => array(),
        'ignored_query'  => array( 'utm_source', 'utm_medium', 'utm_campaign', 'fbclid', 'gclid', 'age-verified' )
    );

    if ( file_exists( $config_path ) ) {
        $json_data = @file_get_contents( $config_path );
        if ( $json_data ) {
            $parsed_config = @json_decode( $json_data, true );
            if ( is_array( $parsed_config ) ) {
                $config = array_merge( $config, $parsed_config );
            }
        }
    }

    // 4. Check query string bypass
    if ( ! empty( $_SERVER['QUERY_STRING'] ) ) {
        // Parse query parameters
        parse_str( $_SERVER['QUERY_STRING'], $query_params );
        
        // If query parameters exist, check if there's any non-ignored parameter
        foreach ( $query_params as $param => $val ) {
            if ( ! in_array( $param, $config['ignored_query'], true ) ) {
                // Non-ignored parameter found (like 's' for search or custom filters). Bypass cache.
                return;
            }
        }
    }

    // 5. Bypass for logged-in users (if configured) and special cookies
    $cache_logged_in = isset( $config['cache_logged_in'] ) ? (bool) $config['cache_logged_in'] : false;
    if ( ! empty( $_COOKIE ) ) {
        foreach ( $_COOKIE as $key => $val ) {
            if ( ! $cache_logged_in && strpos( $key, 'wordpress_logged_in_' ) === 0 ) {
                return;
            }
            if ( preg_match( '/^(wp-postpass_|comment_author_|wordpress_no_cache_|yith_wcwl_products)/', $key ) ) {
                return;
            }
        }
    }

    // 6. Get normalized path
    $host = isset( $_SERVER['HTTP_HOST'] ) ? strtolower( $_SERVER['HTTP_HOST'] ) : '';
    if ( empty( $host ) ) {
        return;
    }

    // Remove port number if exists
    $host = explode( ':', $host )[0];

    // Clean URI (strip query string)
    $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
    $uri_parts = explode( '?', $request_uri );
    $uri_path = rawurldecode( $uri_parts[0] );
    $normalized_uri = trim( $uri_path, '/' );

    // 7. Check if URL matches exclusion list
    if ( ! empty( $config['excluded_urls'] ) ) {
        foreach ( $config['excluded_urls'] as $pattern ) {
            $pattern = trim( $pattern );
            if ( empty( $pattern ) ) {
                continue;
            }
            
            // Build absolute path for comparison
            $absolute_uri = '/' . $normalized_uri;
            if ( $normalized_uri === '' ) {
                $absolute_uri = '/';
            }

            // Support wildcard matching e.g., /cart(.*)
            $regex = str_replace( '\*', '.*', preg_quote( $pattern, '#' ) );
            if ( preg_match( '#^' . $regex . '$#i', $absolute_uri ) || preg_match( '#^' . $regex . '$#i', $uri_path ) ) {
                return; // Excluded URL. Bypass caching.
            }
        }
    }

    // 8. Determine cache file name (HTTPS vs HTTP)
    $is_https = false;
    if ( ( isset( $_SERVER['HTTPS'] ) && ( $_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] == 1 ) ) ||
         ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https' ) ||
         ( isset( $_SERVER['SERVER_PORT'] ) && $_SERVER['SERVER_PORT'] == 443 ) ) {
        $is_https = true;
    }

    $filename = $is_https ? 'index-https.html' : 'index.html';
    $cache_dir = WP_CONTENT_DIR . '/cache/wp-rocket/' . $host . '/' . $normalized_uri;
    
    // Normalize trailing slash directory structure
    if ( $normalized_uri === '' ) {
        $cache_dir = WP_CONTENT_DIR . '/cache/wp-rocket/' . $host;
    }
    
    $cache_file = $cache_dir . '/' . $filename;

    // 9. If cached file exists, check lifespan and serve
    if ( file_exists( $cache_file ) ) {
        $file_time = @filemtime( $cache_file );
        $lifespan = intval( $config['cache_lifespan'] );

        // If lifespan is 0, cache is unlimited unless cleared
        if ( $lifespan === 0 || ( time() - $file_time ) < $lifespan ) {
            
            // Check if client supports GZIP and we have the gzip file
            $gzip_file = $cache_file . '_gzip';
            $supports_gzip = isset( $_SERVER['HTTP_ACCEPT_ENCODING'] ) && strpos( $_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip' ) !== false;

            // Send headers
            header( 'X-Ultimate-WP-Booster-Serving-Static: Yes' );
            header( 'Pragma: public' );
            header( 'Cache-Control: max-age=3600, public' );
            header( 'Content-Type: text/html; charset=UTF-8' );

            if ( $supports_gzip && file_exists( $gzip_file ) && filesize( $gzip_file ) > 0 ) {
                header( 'Content-Encoding: gzip' );
                header( 'Vary: Accept-Encoding' );
                @readfile( $gzip_file );
                exit;
            } else {
                @readfile( $cache_file );
                exit;
            }
        }
    }

    // 10. Cache does not exist or is expired.
    // Register output buffer callback to save the generated HTML on shutdown.
    if ( ! defined( 'UWB_BUFFER_STARTED' ) ) {
        define( 'UWB_BUFFER_STARTED', true );
        
        // Define global variables so shutdown function can access them
        $GLOBALS['uwb_cache_file'] = $cache_file;
        $GLOBALS['uwb_cache_dir'] = $cache_dir;

        ob_start( 'uwb_advanced_cache_ob_callback' );
        register_shutdown_function( 'uwb_advanced_cache_shutdown' );
    }
}

/**
 * Output buffering callback
 */
function uwb_advanced_cache_ob_callback( $buffer ) {
    return $buffer;
}

/**
 * Shutdown function to write the buffer output to static files
 */
function uwb_advanced_cache_shutdown() {
    // Get the final buffer
    $html = ob_get_clean();
    if ( empty( $html ) ) {
        return;
    }

    // Echo the buffer to client so user sees the page instantly
    echo $html;

    // Determine cacheability of the request
    // 1. Check response code (only cache 200 OK)
    $response_code = http_response_code();
    if ( $response_code !== 200 ) {
        return;
    }

    // 2. Check content (do not cache blank pages or error messages)
    if ( strlen( $html ) < 200 ) {
        return;
    }

    // 3. Do not cache if search, feed or login/admin pages
    if ( is_admin() || is_search() || is_feed() || is_trackback() || is_robots() || is_404() ) {
        return;
    }

    // 4. Do not cache if user is logged in (double check via WordPress functions)
    $config_path = WP_CONTENT_DIR . '/cache/ultimate-wp-booster-config.json';
    $cache_logged_in = false;
    if ( file_exists( $config_path ) ) {
        $json_data = @file_get_contents( $config_path );
        if ( $json_data ) {
            $parsed_config = @json_decode( $json_data, true );
            if ( isset( $parsed_config['cache_logged_in'] ) ) {
                $cache_logged_in = (bool) $parsed_config['cache_logged_in'];
            }
        }
    }
    if ( ! $cache_logged_in && function_exists( 'is_user_logged_in' ) && is_user_logged_in() ) {
        return;
    }

    // 5. Save the cache files
    $cache_file = isset( $GLOBALS['uwb_cache_file'] ) ? $GLOBALS['uwb_cache_file'] : '';
    $cache_dir = isset( $GLOBALS['uwb_cache_dir'] ) ? $GLOBALS['uwb_cache_dir'] : '';

    if ( $cache_file && $cache_dir ) {
        // Create directory recursively
        if ( ! file_exists( $cache_dir ) ) {
            @mkdir( $cache_dir, 0755, true );
        }

        if ( is_dir( $cache_dir ) && is_writable( $cache_dir ) ) {
            // Write normal HTML cache file
            @file_put_contents( $cache_file, $html );

            // Write Gzip compressed HTML cache file
            $gzip_file = $cache_file . '_gzip';
            $gzipped_html = gzencode( $html, 9 );
            if ( $gzipped_html !== false ) {
                @file_put_contents( $gzip_file, $gzipped_html );
            }
        }
    }
}
