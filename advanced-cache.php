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
    // 1. Only cache GET requests
    if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || $_SERVER['REQUEST_METHOD'] !== 'GET' ) {
        return;
    }

    // 2. Do not cache command line/WP-CLI requests
    if ( php_sapi_name() === 'cli' ) {
        return;
    }

    // 3. Load config file
    $config_path = WP_CONTENT_DIR . '/cache/ultimate-wp-booster-config.php';
    $config      = array(
        'cache_lifespan'  => 36000,
        'cache_logged_in' => false,
        'excluded_urls'   => array(),
        'ignored_query'   => array( 'utm_source', 'utm_medium', 'utm_campaign', 'fbclid', 'gclid', 'age-verified' ),
    );

    if ( file_exists( $config_path ) ) {
        $parsed_config = include $config_path;
        if ( is_array( $parsed_config ) ) {
            $config = array_merge( $config, $parsed_config );
        }
    }

    $cache_logged_in = (bool) $config['cache_logged_in'];

    // 4. Check query string bypass
    if ( ! empty( $_SERVER['QUERY_STRING'] ) ) {
        parse_str( $_SERVER['QUERY_STRING'], $query_params );
        foreach ( $query_params as $param => $val ) {
            if ( ! in_array( $param, $config['ignored_query'], true ) ) {
                return;
            }
        }
    }

    // 5. Detect logged-in state via cookies
    $logged_in_cookie_hash = '';
    if ( ! empty( $_COOKIE ) ) {
        foreach ( $_COOKIE as $key => $val ) {
            if ( preg_match( '/^(wp-postpass_|comment_author_|wordpress_no_cache_|yith_wcwl_products)/', $key ) ) {
                return;
            }
            if ( strpos( $key, 'wordpress_logged_in_' ) === 0 ) {
                if ( ! $cache_logged_in ) {
                    return;
                }
                $logged_in_cookie_hash = 'user-' . substr( md5( $val ), 0, 12 );
            }
        }
    }

    // 6. Normalize host & URI
    $host = isset( $_SERVER['HTTP_HOST'] ) ? strtolower( $_SERVER['HTTP_HOST'] ) : '';
    if ( empty( $host ) ) {
        return;
    }
    $host = explode( ':', $host )[0];

    $request_uri    = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
    $uri_parts      = explode( '?', $request_uri );
    $uri_path       = rawurldecode( $uri_parts[0] );
    $normalized_uri = trim( $uri_path, '/' );

    // 7. Check excluded URLs
    if ( ! empty( $config['excluded_urls'] ) ) {
        $absolute_uri = ( $normalized_uri === '' ) ? '/' : '/' . $normalized_uri;
        foreach ( $config['excluded_urls'] as $pattern ) {
            $pattern = trim( $pattern );
            if ( empty( $pattern ) ) continue;
            $regex = str_replace( '\*', '.*', preg_quote( $pattern, '#' ) );
            if ( preg_match( '#^' . $regex . '$#i', $absolute_uri ) ||
                 preg_match( '#^' . $regex . '$#i', $uri_path ) ) {
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
    $filename = $is_https ? 'index-https.html' : 'index.html';

    // 9. Build cache directory path (per-user subdirectory for logged-in users)
    $base_cache_dir = WP_CONTENT_DIR . '/cache/wp-rocket/' . $host;
    if ( $normalized_uri !== '' ) {
        $base_cache_dir .= '/' . $normalized_uri;
    }
    $cache_dir  = ( $logged_in_cookie_hash !== '' ) ? $base_cache_dir . '/' . $logged_in_cookie_hash : $base_cache_dir;
    $cache_file = $cache_dir . '/' . $filename;

    // 10. Serve cached file if valid
    if ( file_exists( $cache_file ) ) {
        $file_time = @filemtime( $cache_file );
        $lifespan  = intval( $config['cache_lifespan'] );

        // Cache logged-in users for at most 10 minutes (600 seconds) to prevent wpnonce expiration
        if ( $logged_in_cookie_hash !== '' ) {
            $lifespan = ( $lifespan === 0 ) ? 600 : min( $lifespan, 600 );
        }

        if ( $lifespan === 0 || ( time() - $file_time ) < $lifespan ) {
            $gzip_file     = $cache_file . '_gzip';
            $supports_gzip = isset( $_SERVER['HTTP_ACCEPT_ENCODING'] ) &&
                             strpos( $_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip' ) !== false;

            header( 'X-Ultimate-WP-Booster-Serving-Static: Yes' );
            if ( $logged_in_cookie_hash !== '' ) {
                header( 'Cache-Control: no-cache, no-store, must-revalidate, private' );
                header( 'Pragma: no-cache' );
            } else {
                $bc_enabled = isset( $config['browser_cache_enabled'] ) ? intval( $config['browser_cache_enabled'] ) : 1;
                if ( $bc_enabled ) {
                    $bc_lifespan = isset( $config['browser_cache_lifespan'] ) ? intval( $config['browser_cache_lifespan'] ) : 3600;
                    header( 'Pragma: public' );
                    header( 'Cache-Control: max-age=' . $bc_lifespan . ', public' );
                } else {
                    header( 'Cache-Control: no-cache, no-store, must-revalidate, private' );
                    header( 'Pragma: no-cache' );
                }
            }
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
    }

    // 11. No valid cache – capture output to write on shutdown
    if ( ! defined( 'UWB_BUFFER_STARTED' ) ) {
        define( 'UWB_BUFFER_STARTED', true );

        $GLOBALS['uwb_cache_file']        = $cache_file;
        $GLOBALS['uwb_cache_dir']         = $cache_dir;
        $GLOBALS['uwb_config_path']       = $config_path;
        $GLOBALS['uwb_logged_in_segment'] = $logged_in_cookie_hash;
        $GLOBALS['uwb_config']            = $config;

        ob_start( 'uwb_advanced_cache_ob_callback' );
        register_shutdown_function( 'uwb_advanced_cache_shutdown' );
    }
}

function uwb_advanced_cache_ob_callback( $buffer ) {
    return $buffer;
}

function uwb_advanced_cache_shutdown() {
    $html = ob_get_clean();
    if ( empty( $html ) ) return;

    // Determine if we should cache
    $should_cache = true;
    if ( http_response_code() !== 200 ) {
        $should_cache = false;
    }
    if ( strlen( $html ) < 200 ) {
        $should_cache = false;
    }
    if ( is_admin() || is_search() || is_feed() || is_trackback() || is_robots() || is_404() ) {
        $should_cache = false;
    }

    // Re-read config
    $config_path = isset( $GLOBALS['uwb_config_path'] )
        ? $GLOBALS['uwb_config_path']
        : WP_CONTENT_DIR . '/cache/ultimate-wp-booster-config.php';

    $config          = isset( $GLOBALS['uwb_config'] ) ? $GLOBALS['uwb_config'] : array();
    $cache_logged_in = ! empty( $config['cache_logged_in'] );
    $timezone_str    = isset( $config['timezone'] ) && ! is_numeric( $config['timezone'] ) ? $config['timezone'] : 'UTC';
    $timezone_offset = isset( $config['timezone'] ) && is_numeric( $config['timezone'] ) ? floatval( $config['timezone'] ) * 3600 : 0;
    if ( is_numeric( isset( $config['timezone'] ) ? $config['timezone'] : '' ) ) {
        $timezone_str = '';
    }
    $lifespan = isset( $config['cache_lifespan'] ) ? intval( $config['cache_lifespan'] ) : 36000;

    $logged_in_segment = isset( $GLOBALS['uwb_logged_in_segment'] ) ? $GLOBALS['uwb_logged_in_segment'] : '';
    // Cache logged-in users for at most 10 minutes (600 seconds) to prevent wpnonce expiration
    if ( $logged_in_segment !== '' ) {
        $lifespan = ( $lifespan === 0 ) ? 600 : min( $lifespan, 600 );
    }

    if ( ! $cache_logged_in && $logged_in_segment !== '' ) {
        $should_cache = false;
    }
    if ( ! $cache_logged_in && function_exists( 'is_user_logged_in' ) && is_user_logged_in() ) {
        $should_cache = false;
    }

    $cache_file = isset( $GLOBALS['uwb_cache_file'] ) ? $GLOBALS['uwb_cache_file'] : '';
    $cache_dir  = isset( $GLOBALS['uwb_cache_dir'] ) ? $GLOBALS['uwb_cache_dir'] : '';
    if ( ! $cache_file || ! $cache_dir ) {
        $should_cache = false;
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
    $oc_comment = " | Object Cache: {$oc_status} (Hits: {$oc_hits} | Misses: {$oc_misses} | Ratio: {$oc_ratio}%)";

    // Only append comments to HTML responses and non-admin requests
    $is_html = true;
    if ( is_admin() || is_feed() || is_trackback() || is_robots() ) {
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

        if ( $should_cache ) {
            $comment_to_append = "\n<!-- Cached by WP Booster at {$time_str} ({$utc_label}){$refresh_comment}{$oc_comment} -->";
        } else {
            $comment_to_append = "\n<!-- Dynamic Page{$oc_comment} -->";
        }

        $html .= $comment_to_append;
    }

    echo $html;

    if ( ! $should_cache ) return;

    if ( ! file_exists( $cache_dir ) ) @mkdir( $cache_dir, 0755, true );
    if ( ! is_dir( $cache_dir ) || ! is_writable( $cache_dir ) ) return;

    @file_put_contents( $cache_file, $html );

    $gzip_file    = $cache_file . '_gzip';
    $gzipped_html = gzencode( $html, 9 );
    if ( $gzipped_html !== false ) {
        @file_put_contents( $gzip_file, $gzipped_html );
    }
}
