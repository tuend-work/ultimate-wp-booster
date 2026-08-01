<?php
namespace Ultimate_WP_Booster\Engine\Optimization\HTML;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class Minify {

    public static function process( $html ) {
        $placeholders = array();
        $i = 0;
        
        $html = preg_replace_callback('#<(script|style|pre|code)\b[^>]*>.*?</\1>#is', function( $matches ) use ( &$placeholders, &$i ) {
            $placeholder = "%%%UWB_HTML_PLACEHOLDER_" . $i . "%%%";
            $placeholders[$placeholder] = $matches[0];
            $i++;
            return $placeholder;
        }, $html);

        $html = preg_replace('/<!--(?!\s*(?:\[if|Cached by WP Booster|Dynamic Page)).*?-->/s', '', $html);
        $html = preg_replace('/\s+/', ' ', $html);
        $html = preg_replace('/>\s+</', '><', $html);

        if ( ! empty( $placeholders ) ) {
            $html = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $html );
        }
        
        return trim( $html );
    }
}
