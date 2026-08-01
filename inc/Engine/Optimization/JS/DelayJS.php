<?php
namespace Ultimate_WP_Booster\Engine\Optimization\JS;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class DelayJS {

    public static function process( $html, $excludes_str = '' ) {
        $excludes = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", "", $excludes_str ) ) ) );

        $processed = preg_replace_callback(
            '#<script\b([^>]*?)>(.*?)</script>#ims',
            function( $matches ) use ( $excludes ) {
                $attrs   = $matches[1];
                $content = $matches[2];

                if ( stripos( $attrs, 'text/uwb-lazyload' ) !== false ) {
                    return $matches[0];
                }

                foreach ( $excludes as $ex ) {
                    if ( ! empty( $ex ) && stripos( $matches[0], $ex ) !== false ) {
                        return $matches[0];
                    }
                }

                if ( ! preg_match( '/\bsrc\s*=/i', $attrs ) ) {
                    if ( stripos( $content, 'wp.' ) !== false || stripos( $content, 'wp-i18n' ) !== false || stripos( $content, 'wp-hooks' ) !== false || stripos( $content, 'translations' ) !== false || stripos( $attrs, 'wp-' ) !== false ) {
                        return $matches[0];
                    }
                }

                if ( preg_match( '/type\s*=\s*["\']([^"\']+)["\']/i', $attrs, $type_match ) ) {
                    $type = strtolower( trim( $type_match[1] ) );
                    $allowed = array( 'text/javascript', 'application/javascript', 'module' );
                    if ( ! in_array( $type, $allowed, true ) ) {
                        return $matches[0];
                    }
                    $attrs = preg_replace( '/type\s*=\s*["\'][^"\']*["\']/i', 'type="text/uwb-lazyload"', $attrs );
                } else {
                    $attrs = ' type="text/uwb-lazyload"' . $attrs;
                }

                if ( preg_match( '/\bsrc\s*=/i', $attrs ) ) {
                    $attrs = preg_replace( '/\bsrc\s*=\s*(["\'])/i', 'data-uwb-src=$1', $attrs );
                }

                return '<script' . $attrs . '>' . $content . '</script>';
            },
            $html
        );

        if ( $processed === null || $processed === $html ) {
            return $html;
        }

        $loader = "\n<script id=\"uwb-delay-js-loader\">
(function(){
    var loaded = false;
    function uwbLoadDelayedScripts() {
        if (loaded) return;
        loaded = true;
        var scripts = document.querySelectorAll('script[type=\"text/uwb-lazyload\"]');
        var len = scripts.length;
        var idx = 0;
        function loadNext() {
            if (idx >= len) return;
            var s = scripts[idx++];
            var n = document.createElement('script');
            for (var i = 0; i < s.attributes.length; i++) {
                var attr = s.attributes[i];
                if (attr.name === 'type') {
                    n.type = 'text/javascript';
                } else if (attr.name === 'data-uwb-src') {
                    n.src = attr.value;
                    n.onload = n.onerror = loadNext;
                } else {
                    n.setAttribute(attr.name, attr.value);
                }
            }
            if (!n.src) {
                n.text = s.innerHTML;
            }
            s.parentNode.replaceChild(n, s);
            if (!n.src) { loadNext(); }
        }
        loadNext();
        ['scroll','click','keydown','touchstart','mousemove'].forEach(function(e){
            document.removeEventListener(e, uwbLoadDelayedScripts, {passive:true});
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            ['scroll','click','keydown','touchstart','mousemove'].forEach(function(e){
                document.addEventListener(e, uwbLoadDelayedScripts, {passive:true});
            });
            setTimeout(uwbLoadDelayedScripts, 5000);
        });
    } else {
        ['scroll','click','keydown','touchstart','mousemove'].forEach(function(e){
            document.addEventListener(e, uwbLoadDelayedScripts, {passive:true});
        });
        setTimeout(uwbLoadDelayedScripts, 5000);
    }
})();
</script>";

        if ( stripos( $processed, '</body>' ) !== false ) {
            $processed = str_ireplace( '</body>', $loader . '</body>', $processed );
        } else {
            $processed .= $loader;
        }

        return $processed;
    }
}
