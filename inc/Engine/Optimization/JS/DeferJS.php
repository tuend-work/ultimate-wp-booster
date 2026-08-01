<?php
namespace Ultimate_WP_Booster\Engine\Optimization\JS;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class DeferJS {

    public static function process( $html, $excludes_str = '', &$logs = null ) {
        $excludes = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", "", $excludes_str ) ) ) );
        $deferred_count = 0;
        $skipped_count = 0;

        $html = preg_replace_callback(
            '#<script\b([^>]*?)src=([\'"])(.*?)\2([^>]*?)>\s*</script>#is',
            function( $matches ) use ( $excludes, &$logs, &$deferred_count, &$skipped_count ) {
                $full_tag     = $matches[0];
                $attrs_before = $matches[1];
                $url          = $matches[3];
                $attrs_after  = $matches[4];
                $all_attrs    = $attrs_before . ' ' . $attrs_after;
                $url_clean    = strtok( $url, '?' );

                if ( stripos( $all_attrs, 'defer' ) !== false ||
                     stripos( $all_attrs, 'async' ) !== false ||
                     stripos( $all_attrs, 'type="module"' ) !== false ||
                     stripos( $all_attrs, 'text/uwb-lazyload' ) !== false ) {
                    $skipped_count++;
                    if ( is_array( $logs ) ) {
                        $logs[] = "Defer JS: Skipped {$url_clean} (Script already has defer, async, module, or lazyload attribute)";
                    }
                    return $full_tag;
                }

                foreach ( $excludes as $ex ) {
                    if ( ! empty( $ex ) && ( stripos( $url, $ex ) !== false || stripos( $full_tag, $ex ) !== false ) ) {
                        $skipped_count++;
                        if ( is_array( $logs ) ) {
                            $logs[] = "Defer JS: Excluded {$url_clean} (Matched defer exclusion rule: '{$ex}')";
                        }
                        return $full_tag;
                    }
                }

                // Automatic Safety Exclusions for Critical Inline Variables (flatsomeVars, WooCommerce params)
                if ( stripos( $full_tag, 'flatsomeVars' ) !== false ||
                     stripos( $full_tag, 'wc_add_to_cart_params' ) !== false ||
                     stripos( $full_tag, 'woocommerce_params' ) !== false ) {
                    $skipped_count++;
                    if ( is_array( $logs ) ) {
                        $logs[] = "Defer JS: Excluded {$url_clean} (Automatic safety rule: Contains flatsomeVars or WooCommerce inline variables)";
                    }
                    return $full_tag;
                }

                $deferred_count++;
                return '<script defer="defer"' . $attrs_before . 'src="' . esc_url( $url ) . '"' . $attrs_after . '></script>';
            },
            $html
        );

        if ( is_array( $logs ) ) {
            $logs[] = "Defer JS: Applied to {$deferred_count} script(s), Skipped {$skipped_count} script(s)";
        }

        return $html;
    }
}
