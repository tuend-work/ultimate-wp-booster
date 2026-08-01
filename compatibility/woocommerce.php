<?php
/**
 * WooCommerce Compatibility Layer
 * Automatic cache bypass for cart, checkout, account pages, and WooCommerce query strings.
 */

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

/**
 * WooCommerce Query Bypass Parameters
 * Returns array of query parameters that should force cache bypass.
 */
function uwb_woocommerce_get_bypass_query_params() {
    return array(
        'wc-ajax',
        'add-to-cart',
        'pay_for_order',
        'change_payment_method',
        'logout',
        'wc-api',
        'orderby',
        'order',
        'min_price',
        'max_price',
        'rating_filter',
        'filter_',
    );
}

/**
 * WooCommerce Default Excluded URLs
 * Returns standard WooCommerce URL paths to exclude from static caching.
 */
function uwb_woocommerce_get_excluded_urls() {
    $excluded = array(
        '/cart/',
        '/checkout/',
        '/my-account/',
        '/gio-hang/',
        '/thanhtoan/',
        '/tai-khoan/',
    );

    if ( function_exists( 'wc_get_page_id' ) ) {
        $cart_id = wc_get_page_id( 'cart' );
        if ( $cart_id > 0 ) {
            $path = wp_parse_url( get_permalink( $cart_id ), PHP_URL_PATH );
            if ( $path ) {
                $excluded[] = rtrim( $path, '/' ) . '/';
            }
        }
        $checkout_id = wc_get_page_id( 'checkout' );
        if ( $checkout_id > 0 ) {
            $path = wp_parse_url( get_permalink( $checkout_id ), PHP_URL_PATH );
            if ( $path ) {
                $excluded[] = rtrim( $path, '/' ) . '/';
            }
        }
        $myaccount_id = wc_get_page_id( 'myaccount' );
        if ( $myaccount_id > 0 ) {
            $path = wp_parse_url( get_permalink( $myaccount_id ), PHP_URL_PATH );
            if ( $path ) {
                $excluded[] = rtrim( $path, '/' ) . '/';
            }
        }
    }

    return array_values( array_unique( $excluded ) );
}

/**
 * Automatically bypass cache if WooCommerce dynamically detects Cart, Checkout, or Account pages.
 */
add_filter( 'uwb_should_bypass_page_cache', 'uwb_woocommerce_bypass_page_cache' );
function uwb_woocommerce_bypass_page_cache( $bypass ) {
    if ( $bypass ) {
        return true;
    }

    if ( function_exists( 'is_woocommerce' ) ) {
        if ( is_cart() || is_checkout() || is_account_page() ) {
            return true;
        }
    }

    return $bypass;
}

/**
 * Automatically purge product cache on WooCommerce stock updates
 */
add_action( 'woocommerce_product_set_stock', 'uwb_woocommerce_purge_product' );
add_action( 'woocommerce_variation_set_stock', 'uwb_woocommerce_purge_product' );
function uwb_woocommerce_purge_product( $product ) {
    if ( is_numeric( $product ) ) {
        $product_id = intval( $product );
    } elseif ( is_object( $product ) && method_exists( $product, 'get_id' ) ) {
        $product_id = $product->get_id();
    } else {
        return;
    }

    if ( $product_id > 0 ) {
        $cache = new \Ultimate_WP_Booster\Engine\Cache\CacheManager();
        $cache->purge_post_cache( $product_id );
    }
}
