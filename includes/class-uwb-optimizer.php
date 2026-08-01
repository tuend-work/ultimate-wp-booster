<?php
/**
 * HTML Optimization & Processing Engine (Backward Compatibility Proxy)
 */

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class Uwb_Optimizer {

    public static function process( $html, $config ) {
        return \Ultimate_WP_Booster\Engine\Optimization\Optimizer::process( $html, $config );
    }

    public static function combine_css( $html, $excludes_str = '', $include_ext = false, $font_display_opt = true ) {
        return \Ultimate_WP_Booster\Engine\Optimization\Minify\CSS::combine( $html, $excludes_str, $include_ext, $font_display_opt );
    }

    public static function combine_js( $html, $excludes_str = '', $include_ext = true ) {
        return \Ultimate_WP_Booster\Engine\Optimization\Minify\JS::combine( $html, $excludes_str, $include_ext );
    }

    public static function minify_html( $html ) {
        return \Ultimate_WP_Booster\Engine\Optimization\HTML\Minify::process( $html );
    }
}
