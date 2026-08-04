<?php
/**
 * Ultimate Runtime Optimizer — MU Plugin Runtime
 *
 * Managed by Ultimate WP Booster. DO NOT EDIT MANUALLY.
 * Generated: will be overwritten on each compile.
 *
 * Runtime Contract (per spec):
 * - No DB queries
 * - No WP_Query
 * - No json_decode / serialize
 * - No get_option() outside the filter
 * - No include of other files
 * - Only: load compiled.php → resolve context → filter active_plugins
 */

defined( 'ABSPATH' ) || exit;

// ── 1. Load compiled lookup tables ──────────────────────────────────────────
$_uro_file = WP_CONTENT_DIR . '/cache/uro/compiled.php';

if ( ! file_exists( $_uro_file ) ) {
    return; // Fail-safe: compiled.php missing, load all plugins as normal
}

$_uro = @include $_uro_file;

if ( ! is_array( $_uro ) || empty( $_uro['version'] ) ) {
    return; // Fail-safe: corrupt data
}

// ── 2. Integrity check ───────────────────────────────────────────────────────
if ( ! empty( $_uro['checksum'] ) ) {
    $_uro_sig = md5( serialize( $_uro['url_trie'] ) . serialize( $_uro['rules'] ) . serialize( $_uro['masks'] ) );
    if ( $_uro_sig !== $_uro['checksum'] ) {
        return; // Fail-safe: corrupt compiled.php — do NOT apply any filter
    }
}

// ── 3. Register the filter ───────────────────────────────────────────────────
add_filter( 'option_active_plugins', static function ( $plugins ) use ( $_uro ): array {
    return _uro_apply( $plugins, $_uro );
}, 1 );

/**
 * Core runtime function: resolve context, lookup rules, filter plugins.
 *
 * @param array $plugins  Current active plugin list.
 * @param array $data     Compiled lookup data.
 * @return array          Filtered plugin list.
 */
function _uro_apply( array $plugins, array $data ): array {
    // Do not apply any runtime optimization inside wp-admin dashboard or login page
    if ( is_admin() 
         || ( defined( 'WP_ADMIN' ) && WP_ADMIN ) 
         || ( isset( $_SERVER['REQUEST_URI'] ) && ( strpos( $_SERVER['REQUEST_URI'], '/wp-admin/' ) !== false || strpos( $_SERVER['REQUEST_URI'], 'wp-login.php' ) !== false ) )
    ) {
        return $plugins;
    }

    // ── A. Resolve current context ───────────────────────────────────────────

    // A1. URL path segments
    $uri      = $_SERVER['REQUEST_URI'] ?? '/';
    $path     = strtolower( trim( parse_url( $uri, PHP_URL_PATH ) ?? '/', '/' ) );
    $segments = $path !== '' ? explode( '/', $path ) : [];

    // A2. User role (cookie-based — no DB)
    $role = 'guest';
    foreach ( $_COOKIE as $k => $v ) {
        if ( strpos( $k, 'wordpress_logged_in_' ) === 0 ) {
            $role = 'logged_in';
            break;
        }
    }

    // A3. Device (User-Agent based)
    $ua     = strtolower( $_SERVER['HTTP_USER_AGENT'] ?? '' );
    $device = 'desktop';
    if ( strpos( $ua, 'mobile' ) !== false || strpos( $ua, 'android' ) !== false
         || strpos( $ua, 'iphone' ) !== false ) {
        $device = 'mobile';
    } elseif ( strpos( $ua, 'tablet' ) !== false || strpos( $ua, 'ipad' ) !== false ) {
        $device = 'tablet';
    }

    // A4. AJAX / REST flags (constants may not be defined this early — check globals)
    $is_ajax = ( defined( 'DOING_AJAX' ) && DOING_AJAX )
               || ( isset( $_SERVER['HTTP_X_REQUESTED_WITH'] )
                    && strtolower( $_SERVER['HTTP_X_REQUESTED_WITH'] ) === 'xmlhttprequest' );
    $is_rest = defined( 'REST_REQUEST' ) && REST_REQUEST;

    // A5. WooCommerce URL context (slug-based, no DB)
    $woo_ctx    = '';
    $woo_slugs  = [ 'cart' => 'cart', 'checkout' => 'checkout', 'my-account' => 'account', 'shop' => 'shop' ];
    if ( ! empty( $segments ) ) {
        $woo_ctx = $woo_slugs[ $segments[0] ] ?? '';
        if ( $woo_ctx === '' && count( $segments ) >= 2 && $segments[0] === 'product' ) {
            $woo_ctx = 'product';
        }
    }

    // ── B. Trie traversal → collect matched rule IDs ─────────────────────────

    $matched = [];
    $trie    = $data['url_trie'] ?? [];

    // Root-level rules (only if segments list is empty, i.e., exact homepage request)
    if ( empty( $segments ) && isset( $trie['_root_rules'] ) ) {
        foreach ( $trie['_root_rules'] as $rid ) {
            $matched[ $rid ] = true;
        }
    }

    $node = $trie;
    foreach ( $segments as $seg ) {
        // Wildcard at current level
        if ( isset( $node['*']['_rules'] ) ) {
            foreach ( $node['*']['_rules'] as $rid ) {
                $matched[ $rid ] = true;
            }
        }
        // Exact segment match
        if ( isset( $node[ $seg ] ) ) {
            $node = $node[ $seg ];
            if ( isset( $node['_rules'] ) ) {
                foreach ( $node['_rules'] as $rid ) {
                    $matched[ $rid ] = true;
                }
            }
        } elseif ( isset( $node['*'] ) ) {
            $node = $node['*'];
            if ( isset( $node['_rules'] ) ) {
                foreach ( $node['_rules'] as $rid ) {
                    $matched[ $rid ] = true;
                }
            }
            break; // wildcard consumed remaining path
        } else {
            break;
        }
    }

    // ── C. Merge global rules (no URL condition) ─────────────────────────────

    foreach ( array_keys( $data['global_rules'] ?? [] ) as $rid ) {
        $matched[ $rid ] = true;
    }

    if ( empty( $matched ) ) {
        return $plugins; // Nothing matched — return all plugins unchanged
    }

    // ── D. Filter matched rules by context conditions ─────────────────────────

    $to_disable = [];
    $all_rules  = $data['rules'] ?? [];

    foreach ( array_keys( $matched ) as $rid ) {
        $rule = $all_rules[ $rid ] ?? null;
        if ( ! $rule ) {
            continue;
        }

        // Role check
        $roles = $rule['roles'];
        if ( ! empty( $roles ) && ! in_array( 'any', $roles, true ) && ! in_array( $role, $roles, true ) ) {
            continue;
        }

        // Device check
        $devices = $rule['devices'];
        if ( ! empty( $devices ) && ! in_array( 'any', $devices, true ) && ! in_array( $device, $devices, true ) ) {
            continue;
        }

        // AJAX check
        if ( $rule['is_ajax'] !== null && (bool) $rule['is_ajax'] !== $is_ajax ) {
            continue;
        }

        // REST check
        if ( $rule['is_rest'] !== null && (bool) $rule['is_rest'] !== $is_rest ) {
            continue;
        }

        // WooCommerce context check
        $woo_cond = $rule['woocommerce'];
        if ( ! empty( $woo_cond ) && ! in_array( 'any', $woo_cond, true ) && ! in_array( $woo_ctx, $woo_cond, true ) ) {
            continue;
        }

        // Callback check (only if function was compiled + exists)
        $cb = $rule['callback'] ?? null;
        if ( $cb !== null && ( ! function_exists( $cb ) || ! call_user_func( $cb ) ) ) {
            continue;
        }

        // Apply rule
        if ( $rule['action'] === 'deny' ) {
            foreach ( $rule['plugin_files'] as $pf ) {
                $to_disable[ $pf ] = true;
            }
        }
    }

    if ( empty( $to_disable ) ) {
        return $plugins;
    }

    // ── E. Return filtered plugin list ───────────────────────────────────────

    return array_values( array_filter( $plugins, static fn( $p ) => ! isset( $to_disable[ $p ] ) ) );
}
