<?php
/**
 * YITH WooCommerce Ajax Product Filter Compatibility Layer
 * Automatic cache bypass for YITH WCAN filter query parameters and AJAX requests,
 * and automatic JS exclusion for YITH WCAN frontend scripts.
 */

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

/**
 * Returns YITH WCAN query parameters that must bypass static caching.
 */
function uwb_yith_wcan_get_bypass_query_params() {
    return array(
        'yith_wcan',
        'yith-wcan-ajax',
        'yith_wcan_cat',
        'yith_wcan_tag',
        'yith_wcan_brand',
        'yith_wcan_instock',
        'yith_wcan_onsale',
        'yith_wcan_type',
        'yith_wcan_frontend_nonce',
        'preset',
    );
}

/**
 * Automatically bypass cache when a YITH WCAN filter request or AJAX is detected.
 */
add_filter( 'uwb_should_bypass_page_cache', 'uwb_yith_wcan_bypass_page_cache' );
function uwb_yith_wcan_bypass_page_cache( $bypass ) {
    if ( $bypass ) {
        return true;
    }

    // Check query string parameters
    if ( ! empty( $_GET ) ) {
        foreach ( $_GET as $key => $value ) {
            if ( strpos( $key, 'yith_wcan' ) !== false || strpos( $key, 'yith-wcan' ) !== false || $key === 'preset' ) {
                return true;
            }
        }
    }

    // Check AJAX action
    if ( isset( $_REQUEST['action'] ) && strpos( $_REQUEST['action'], 'yith_wcan' ) !== false ) {
        return true;
    }

    return $bypass;
}

/**
 * Automatically append YITH WCAN JS files to Delay JS / JS Combine exclusions
 */
add_filter( 'uwb_delay_js_exclusions', 'uwb_yith_wcan_delay_js_exclusions' );
add_filter( 'uwb_js_combine_exclusions', 'uwb_yith_wcan_delay_js_exclusions' );
function uwb_yith_wcan_delay_js_exclusions( $exclusions ) {
    $yith_scripts = array(
        'yith-wcan-frontend.js',
        'yith-wcan.js',
        'yith-wcan-shortcode.js',
        'yith-wcan-navigation.js',
        'yith-wcan-select.js',
    );

    if ( is_string( $exclusions ) ) {
        foreach ( $yith_scripts as $script ) {
            if ( strpos( $exclusions, $script ) === false ) {
                $exclusions .= "\n" . $script;
            }
        }
    } elseif ( is_array( $exclusions ) ) {
        $exclusions = array_merge( $exclusions, $yith_scripts );
    }

    return $exclusions;
}
