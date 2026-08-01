<?php
namespace Ultimate_WP_Booster\Engine\Optimization\JS;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class DeferJS {

    public static function process( $html, $excludes_str = '' ) {
        $excludes = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", "", $excludes_str ) ) ) );

        return preg_replace_callback(
            '#<script\b([^>]*?)src=([\'"])(.*?)\2([^>]*?)>\s*</script>#is',
            function( $matches ) use ( $excludes ) {
                $full_tag     = $matches[0];
                $attrs_before = $matches[1];
                $url          = $matches[3];
                $attrs_after  = $matches[4];
                $all_attrs    = $attrs_before . ' ' . $attrs_after;

                if ( stripos( $all_attrs, 'defer' ) !== false ||
                     stripos( $all_attrs, 'async' ) !== false ||
                     stripos( $all_attrs, 'type="module"' ) !== false ||
                     stripos( $all_attrs, 'text/uwb-lazyload' ) !== false ) {
                    return $full_tag;
                }

                foreach ( $excludes as $ex ) {
                    if ( ! empty( $ex ) && ( stripos( $url, $ex ) !== false || stripos( $full_tag, $ex ) !== false ) ) {
                        return $full_tag;
                    }
                }

                return '<script defer="defer"' . $attrs_before . 'src="' . esc_url( $url ) . '"' . $attrs_after . '></script>';
            },
            $html
        );
    }
}
