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
    $config      = array(
        'cache_lifespan'  => 36000, // 10 hours in seconds
        'cache_logged_in' => false,
        'excluded_urls'   => array(),
        'ignored_query'   => array( 'utm_source', 'utm_medium', 'utm_campaign', 'fbclid', 'gclid', 'age-verified' ),
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

    $cache_logged_in = (bool) $config['cache_logged_in'];

    // 4. Check query string bypass
    if ( ! empty( $_SERVER['QUERY_STRING'] ) ) {
        parse_str( $_SERVER['QUERY_STRING'], $query_params );
        foreach ( $query_params as $param => $val ) {
            if ( ! in_array( $param, $config['ignored_query'], true ) ) {
                return; // Non-ignored query param – bypass cache
            }
        }
    }

    // 5. Detect logged-in state via cookies
    //    - If cache_logged_in = false: bypass entirely for logged-in users
    //    - If cache_logged_in = true:  use a per-user sub-directory so each
    //      user has their own cache, isolated from guests and other users.
    $logged_in_cookie_hash = '';
    if ( ! empty( $_COOKIE ) ) {
        foreach ( $_COOKIE as $key => $val ) {
            // Always bypass for special transient cookies
            if ( preg_match( '/^(wp-postpass_|comment_author_|wordpress_no_cache_|yith_wcwl_products)/', $key ) ) {
                return;
            }
            // Detect WordPress logged-in cookie
            if ( strpos( $key, 'wordpress_logged_in_' ) === 0 ) {
                if ( ! $cache_logged_in ) {
                    return; // Logged-in caching disabled → bypass entirely
                }
                // Build a stable, short, per-user segment from the cookie value
                $logged_in_cookie_hash = 'user-' . substr( md5( $val ), 0, 12 );
            }
        }
    }

    // 6. Get normalized host & URI
    $host = isset( $_SERVER['HTTP_HOST'] ) ? strtolower( $_SERVER['HTTP_HOST'] ) : '';
    if ( empty( $host ) ) {
        return;
    }
    $host = explode( ':', $host )[0]; // Strip port number

    $request_uri    = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
    $uri_parts      = explode( '?', $request_uri );
    $uri_path       = rawurldecode( $uri_parts[0] );
    $normalized_uri = trim( $uri_path, '/' );

    // 7. Check excluded URLs
    if ( ! empty( $config['excluded_urls'] ) ) {
        $absolute_uri = ( $normalized_uri === '' ) ? '/' : '/' . $normalized_uri;
        foreach ( $config['excluded_urls'] as $pattern ) {
            $pattern = trim( $pattern );
            if ( empty( $pattern ) ) {
                continue;
            }
            $regex = str_replace( '\*', '.*', preg_quote( $pattern, '#' ) );
            if ( preg_match( '#^' . $regex . '$#i', $absolute_uri ) ||
                 preg_match( '#^' . $regex . '$#i', $uri_path ) ) {
                return; // Excluded URL – bypass
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

    // 9. Build cache directory path
    //
    //    Guest users  →  .../wp-rocket/{host}/{uri}/index-https.html
    //    Logged-in    →  .../wp-rocket/{host}/{uri}/user-{hash}/index-https.html
    //
    //    The user-specific sub-directory ensures logged-in cache NEVER bleeds
    //    into the guest cache and is isolated between different user accounts.
    $base_cache_dir = WP_CONTENT_DIR . '/cache/wp-rocket/' . $host;
    if ( $normalized_uri !== '' ) {
        $base_cache_dir .= '/' . $normalized_uri;
    }

    if ( $logged_in_cookie_hash !== '' ) {
        $cache_dir = $base_cache_dir . '/' . $logged_in_cookie_hash;
    } else {
        $cache_dir = $base_cache_dir;
    }

    $cache_file = $cache_dir . '/' . $filename;

    // 10. Serve cached file if it exists and is still valid
    if ( file_exists( $cache_file ) ) {
        $file_time = @filemtime( $cache_file );
        $lifespan  = intval( $config['cache_lifespan'] );

        if ( $lifespan === 0 || ( time() - $file_time ) < $lifespan ) {
            $gzip_file     = $cache_file . '_gzip';
            $supports_gzip = isset( $_SERVER['HTTP_ACCEPT_ENCODING'] ) &&
                             strpos( $_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip' ) !== false;

            header( 'X-Ultimate-WP-Booster-Serving-Static: Yes' );
            header( 'Pragma: public' );
            header( 'Cache-Control: max-age=3600, public' );
            header( 'Content-Type: text/html; charset=UTF-8' );

            if ( $supports_gzip && file_exists( $gzip_file ) && filesize( $gzip_file ) > 0 ) {
                header( 'Content-Encoding: gzip' );
                header( 'Vary: Accept-Encoding' );
                @readfile( $gzip_file );
            } else {
                @readfile( $cache_file );
            }
            exit;
        }
        // Cache expired – fall through to regenerate
    }

    // 11. Cache does not exist or is expired – capture output to write cache on shutdown
    if ( ! defined( 'UWB_BUFFER_STARTED' ) ) {
        define( 'UWB_BUFFER_STARTED', true );

        $GLOBALS['uwb_cache_file']        = $cache_file;
        $GLOBALS['uwb_cache_dir']         = $cache_dir;
        $GLOBALS['uwb_config_path']       = $config_path;
        $GLOBALS['uwb_logged_in_segment'] = $logged_in_cookie_hash;

        ob_start( 'uwb_advanced_cache_ob_callback' );
        register_shutdown_function( 'uwb_advanced_cache_shutdown' );
    }
}

/**
 * Output buffering callback — pass-through only
 */
function uwb_advanced_cache_ob_callback( $buffer ) {
    return $buffer;
}

/**
 * Shutdown function: write the captured HTML output to static cache files
 */
function uwb_advanced_cache_shutdown() {
    $html = ob_get_clean();
    if ( empty( $html ) ) {
        return;
    }

    // Serve the page to the client immediately
    echo $html;

    // Only cache 200 OK responses
    if ( http_response_code() !== 200 ) {
        return;
    }

    // Skip suspiciously short responses
    if ( strlen( $html ) < 200 ) {
        return;
    }

    // Skip admin, search, feed, and other special pages
    if ( is_admin() || is_search() || is_feed() || is_trackback() || is_robots() || is_404() ) {
        return;
    }

    // Re-read config
    $config_path     = isset( $GLOBALS['uwb_config_path'] )
        ? $GLOBALS['uwb_config_path']
        : WP_CONTENT_DIR . '/cache/ultimate-wp-booster-config.json';
    $cache_logged_in = false;
    $timezone_str    = 'UTC';
    $timezone_offset = 0;

    if ( file_exists( $config_path ) ) {
        $json_data = @file_get_contents( $config_path );
        if ( $json_data ) {
            $parsed_config = @json_decode( $json_data, true );
            if ( is_array( $parsed_config ) ) {
                $cache_logged_in = ! empty( $parsed_config['cache_logged_in'] );
                if ( isset( $parsed_config['timezone'] ) ) {
                    if ( is_numeric( $parsed_config['timezone'] ) ) {
                        $timezone_offset = floatval( $parsed_config['timezone'] ) * 3600;
                        $timezone_str    = '';
                    } else {
                        $timezone_str = $parsed_config['timezone'];
                    }
                }
            }
        }
    }

    // Safety check: if logged-in caching is now disabled, don't write
    $logged_in_segment = isset( $GLOBALS['uwb_logged_in_segment'] ) ? $GLOBALS['uwb_logged_in_segment'] : '';
    if ( ! $cache_logged_in && $logged_in_segment !== '' ) {
        return;
    }
    if ( ! $cache_logged_in && function_exists( 'is_user_logged_in' ) && is_user_logged_in() ) {
        return;
    }

    // Resolve cache paths
    $cache_file = isset( $GLOBALS['uwb_cache_file'] ) ? $GLOBALS['uwb_cache_file'] : '';
    $cache_dir  = isset( $GLOBALS['uwb_cache_dir'] ) ? $GLOBALS['uwb_cache_dir'] : '';

    if ( ! $cache_file || ! $cache_dir ) {
        return;
    }

    // Create directory structure if needed
    if ( ! file_exists( $cache_dir ) ) {
        @mkdir( $cache_dir, 0755, true );
    }

    if ( ! is_dir( $cache_dir ) || ! is_writable( $cache_dir ) ) {
        return;
    }

    // Append cache timestamp comment
    if ( $timezone_str ) {
        @date_default_timezone_set( $timezone_str );
        $time_str = date( 'H:i d/m/Y' );
    } else {
        $time_str = gmdate( 'H:i d/m/Y', time() + $timezone_offset );
    }
    $html .= "\n<!-- Cached by WP Booster at " . $time_str . " -->";

    // Write plain HTML cache file
    @file_put_contents( $cache_file, $html );

    // Write Gzip-compressed cache file
    $gzip_file    = $cache_file . '_gzip';
    $gzipped_html = gzencode( $html, 9 );
    if ( $gzipped_html !== false ) {
        @file_put_contents( $gzip_file, $gzipped_html );
    }
}
