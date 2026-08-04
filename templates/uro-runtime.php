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

// ── 1. Resolve compiled lookup data ──────────────────────────────────────────
$_uro = _uro_data();

if ( ! is_array( $_uro ) || empty( $_uro['version'] ) ) {
    return; // Fail-safe: corrupt or not compiled
}

// ── 2. Register the filter ───────────────────────────────────────────────────
add_filter( 'option_active_plugins', static function ( $plugins ) use ( $_uro ): array {
    return _uro_apply( $plugins, $_uro );
}, 1 );

/**
 * Returns the compiled lookup data. Will be replaced at compile time.
 */
function _uro_data(): array {
    // {{COMPILED_DATA}}
    return [];
}

/**
 * Core runtime function: resolve context, lookup rules, filter plugins.
 *
 * @param array $plugins  Current active plugin list.
 * @param array $data     Compiled lookup data.
 * @return array          Filtered plugin list.
 */
function _uro_apply( array $plugins, array $data ): array {
    // Safety bypass: critical WordPress admin screens, themes/plugins setup, customizer, login
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $path = strtolower( trim( parse_url( $uri, PHP_URL_PATH ) ?? '/', '/' ) );

    if ( strpos( $path, 'wp-admin/plugins.php' ) !== false 
         || strpos( $path, 'wp-admin/update.php' ) !== false 
         || strpos( $path, 'wp-admin/update-core.php' ) !== false 
         || strpos( $path, 'wp-admin/themes.php' ) !== false 
         || strpos( $path, 'wp-admin/customize.php' ) !== false
         || strpos( $uri, 'wp-login.php' ) !== false
    ) {
        return $plugins;
    }

    // Safety bypass: URO's own AJAX actions to prevent breakages during rebuild
    if ( strpos( $path, 'admin-ajax.php' ) !== false ) {
        $action = $_REQUEST['action'] ?? '';
        if ( is_string( $action ) && strpos( $action, 'uwb_uro_' ) === 0 ) {
            return $plugins;
        }
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

        // Query parameters check (if specified for the matched path)
        $qp_rules = $rule['query_params'] ?? [];
        if ( ! empty( $qp_rules ) && isset( $qp_rules[ $path ] ) ) {
            $matched_qp = false;
            foreach ( $qp_rules[ $path ] as $required_args ) {
                $all_match = true;
                foreach ( $required_args as $k => $v ) {
                    $current_val = isset( $_GET[ $k ] ) ? strtolower( $_GET[ $k ] ) : null;
                    if ( $current_val !== $v ) {
                        $all_match = false;
                        break;
                    }
                }
                if ( $all_match ) {
                    $matched_qp = true;
                    break;
                }
            }
            if ( ! $matched_qp ) {
                continue; // Query params didn't match, skip this rule
            }
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

    return array_values( array_filter( $plugins, static function( $p ) use ( $to_disable ) {
        if ( $p === 'ultimate-wp-booster/ultimate-wp-booster.php' ) {
            return true; // Never disable ourselves
        }
        return ! isset( $to_disable[ $p ] );
    } ) );
}
