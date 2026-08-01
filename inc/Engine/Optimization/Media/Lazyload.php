<?php
namespace Ultimate_WP_Booster\Engine\Optimization\Media;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class Lazyload {

    public static function process_images( $html, $excludes_str = '', $class_excludes_str = '' ) {
        $excludes = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", "", $excludes_str ) ) ) );
        $class_excludes = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", "", $class_excludes_str ) ) ) );

        $processed = preg_replace_callback('/<img\s+([^>]+)>/i', function( $matches ) use ( $excludes, $class_excludes ) {
            $attrs = $matches[1];
            
            $src = '';
            if ( preg_match('/src=([\'"])(.*?)\1/i', $attrs, $src_match) ) {
                $src = $src_match[2];
            } elseif ( preg_match('/data-src=([\'"])(.*?)\1/i', $attrs, $src_match) ) {
                $src = $src_match[2];
            }

            if ( empty( $src ) ) {
                return $matches[0];
            }

            foreach ( $excludes as $ex ) {
                if ( ! empty( $ex ) && stripos( $src, $ex ) !== false ) {
                    return $matches[0];
                }
            }

            if ( preg_match('/class=([\'"])(.*?)\1/i', $attrs, $class_match) ) {
                $class = $class_match[2];
                foreach ( $class_excludes as $cx ) {
                    if ( ! empty( $cx ) && stripos( $class, $cx ) !== false ) {
                        return $matches[0];
                    }
                }
            }

            if ( stripos( $attrs, 'no-lazy' ) !== false || stripos( $attrs, 'skip-lazy' ) !== false ) {
                return $matches[0];
            }

            $new_attrs = $attrs;

            if ( stripos( $new_attrs, 'loading=' ) === false ) {
                $new_attrs .= ' loading="lazy"';
            }

            if ( stripos( $new_attrs, 'data-src=' ) !== false && ( stripos( $new_attrs, 'src=' ) === false || stripos( $new_attrs, 'src="data:' ) !== false || stripos( $new_attrs, "src='data:" ) !== false ) ) {
                if ( preg_match('/data-src=([\'"])(.*?)\1/i', $new_attrs, $ds_match) ) {
                    $real_url = $ds_match[2];
                    if ( preg_match('/src=([\'"])(.*?)\1/i', $new_attrs) ) {
                        $new_attrs = preg_replace('/src=([\'"])(.*?)\1/i', 'src="' . $real_url . '"', $new_attrs);
                    } else {
                        $new_attrs .= ' src="' . $real_url . '"';
                    }
                }
            }

            return '<img ' . $new_attrs . '>';
        }, $html);

        if ( $processed !== $html ) {
            $lazy_js = "\n<script id=\"uwb-lazy-load-js\">
(function() {
    var lazyObserver = null;

    function uwbObserveImg(img) {
        if (!img || !img.getAttribute) return;
        var dataSrc = img.getAttribute('data-src');
        if (!dataSrc) return;
        
        if ('IntersectionObserver' in window) {
            if (!lazyObserver) {
                lazyObserver = new IntersectionObserver(function(entries) {
                    entries.forEach(function(entry) {
                        if (entry.isIntersecting || entry.intersectionRatio > 0) {
                            var el = entry.target;
                            var ds = el.getAttribute('data-src');
                            if (ds) {
                                el.src = ds;
                                el.removeAttribute('data-src');
                            }
                            var dss = el.getAttribute('data-srcset');
                            if (dss) {
                                el.srcset = dss;
                                el.removeAttribute('data-srcset');
                            }
                            lazyObserver.unobserve(el);
                        }
                    });
                }, { rootMargin: '300px 0px' });
            }
            lazyObserver.observe(img);
        } else {
            img.src = dataSrc;
            img.removeAttribute('data-src');
            var dss = img.getAttribute('data-srcset');
            if (dss) {
                img.srcset = dss;
                img.removeAttribute('data-srcset');
            }
        }
    }

    function uwbInitLazyImages() {
        var imgs = document.querySelectorAll('img[data-src]');
        for (var i = 0; i < imgs.length; i++) {
            uwbObserveImg(imgs[i]);
        }
    }

    window.uwbInitLazyImages = uwbInitLazyImages;

    if ('MutationObserver' in window) {
        var mutationObs = new MutationObserver(function(mutations) {
            mutations.forEach(function(m) {
                if (m.addedNodes && m.addedNodes.length) {
                    for (var i = 0; i < m.addedNodes.length; i++) {
                        var node = m.addedNodes[i];
                        if (node.nodeType === 1) {
                            if (node.tagName === 'IMG' && node.getAttribute('data-src')) {
                                uwbObserveImg(node);
                            }
                            if (node.querySelectorAll) {
                                var subImgs = node.querySelectorAll('img[data-src]');
                                for (var j = 0; j < subImgs.length; j++) {
                                    uwbObserveImg(subImgs[j]);
                                }
                            }
                        }
                    }
                }
            });
        });
        var startObs = function() {
            var target = document.body || document.documentElement;
            if (target) {
                mutationObs.observe(target, { childList: true, subtree: true });
            }
        };
        if (document.body) { startObs(); } else { document.addEventListener('DOMContentLoaded', startObs); }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', uwbInitLazyImages);
    } else {
        uwbInitLazyImages();
    }

    document.addEventListener('post-load', uwbInitLazyImages);
    window.addEventListener('load', uwbInitLazyImages);

    if (window.jQuery) {
        window.jQuery(document).ajaxComplete(function() {
            setTimeout(uwbInitLazyImages, 50);
        });
    }
})();
</script>";
            
            if ( stripos( $processed, '</body>' ) !== false ) {
                $processed = str_ireplace( '</body>', $lazy_js . '</body>', $processed );
            } else {
                $processed .= $lazy_js;
            }
        }

        return $processed;
    }

    public static function process_iframes( $html ) {
        $processed = preg_replace_callback('/<iframe\s+([^>]+)>/i', function( $matches ) {
            $attrs = $matches[1];
            if ( stripos( $attrs, 'data-src' ) !== false ) {
                return $matches[0];
            }
            if ( preg_match('/src=([\'"])(.*?)\1/i', $attrs, $src_match) ) {
                $new_attrs = preg_replace_callback('/src=([\'"])(.*?)\1/i', function( $m ) {
                    return 'src="about:blank" data-src="' . $m[2] . '"';
                }, $attrs);
                if ( stripos( $new_attrs, 'loading=' ) === false ) {
                    $new_attrs .= ' loading="lazy"';
                }
                return '<iframe ' . $new_attrs . '>';
            }
            return $matches[0];
        }, $html);

        if ( $processed !== $html ) {
            $lazy_js = "\n<script id=\"uwb-lazy-iframe-js\">
(function() {
    function uwbInitLazyIframes() {
        var lazyIframes = [].slice.call(document.querySelectorAll(\"iframe[data-src]\"));
        if (!lazyIframes.length) return;
        if (\"IntersectionObserver\" in window) {
            let lazyIframeObserver = new IntersectionObserver(function(entries, observer) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting || entry.intersectionRatio > 0) {
                        let iframe = entry.target;
                        if (iframe.dataset.src) {
                            iframe.src = iframe.dataset.src;
                            iframe.removeAttribute(\"data-src\");
                        }
                        lazyIframeObserver.unobserve(iframe);
                    }
                });
            }, { rootMargin: \"300px 0px\" });
            lazyIframes.forEach(function(iframe) {
                lazyIframeObserver.observe(iframe);
            });
        } else {
            lazyIframes.forEach(function(iframe) {
                if (iframe.dataset.src) {
                    iframe.src = iframe.dataset.src;
                    iframe.removeAttribute(\"data-src\");
                }
            });
        }
    }
    if (document.readyState === \"loading\") {
        document.addEventListener(\"DOMContentLoaded\", uwbInitLazyIframes);
    } else {
        uwbInitLazyIframes();
    }
})();
</script>";
            if ( stripos( $processed, '</body>' ) !== false ) {
                $processed = str_ireplace( '</body>', $lazy_js . '</body>', $processed );
            } else {
                $processed .= $lazy_js;
            }
        }
        return $processed;
    }

    public static function add_missing_sizes( $html ) {
        return preg_replace_callback('/<img\s+([^>]+)>/i', function( $matches ) {
            $attrs = $matches[1];
            if ( stripos( $attrs, 'width=' ) !== false && stripos( $attrs, 'height=' ) !== false ) {
                return $matches[0];
            }
            
            if ( preg_match('/src=([\'"])(.*?)\1/i', $attrs, $src_match) ) {
                $src = $src_match[2];
                $home_url = function_exists( 'home_url' ) ? home_url() : '';
                if ( empty( $home_url ) ) {
                    return $matches[0];
                }

                if ( stripos( $src, $home_url ) === 0 || strpos( $src, '/' ) === 0 ) {
                    $path = '';
                    if ( strpos( $src, '/' ) === 0 ) {
                        $path = ABSPATH . ltrim( $src, '/' );
                    } else {
                        $path = ABSPATH . ltrim( str_ireplace( $home_url, '', $src ), '/' );
                    }
                    
                    if ( file_exists( $path ) ) {
                        $size = @getimagesize( $path );
                        if ( $size ) {
                            $w = $size[0];
                            $h = $size[1];
                            $add = '';
                            if ( stripos( $attrs, 'width=' ) === false ) {
                                $add .= ' width="' . $w . '"';
                            }
                            if ( stripos( $attrs, 'height=' ) === false ) {
                                $add .= ' height="' . $h . '"';
                            }
                            return '<img ' . $attrs . $add . '>';
                        }
                    }
                }
            }
            return $matches[0];
        }, $html);
    }
}
