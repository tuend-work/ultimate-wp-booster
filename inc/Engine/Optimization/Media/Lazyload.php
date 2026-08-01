<?php
namespace Ultimate_WP_Booster\Engine\Optimization\Media;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class Lazyload {

    public static function process_images( $html, $excludes_str = '', $class_excludes_str = '', &$logs = null ) {
        $excludes = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", "", $excludes_str ) ) ) );
        $class_excludes = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", "", $class_excludes_str ) ) ) );

        $placeholder = "data:image/svg+xml,%3Csvg%20xmlns='http://www.w3.org/2000/svg'%20viewBox='0%200%201%201'%3E%3C/svg%3E";
        $lazy_count = 0;
        $skipped_count = 0;

        $processed = preg_replace_callback('/<img\s+([^>]+)>/i', function( $matches ) use ( $excludes, $class_excludes, $placeholder, &$lazy_count, &$skipped_count ) {
            $attrs = $matches[1];

            // 1. Skip if already lazyloaded or marked no-lazy / eager
            if ( stripos( $attrs, 'no-lazy' ) !== false ||
                 stripos( $attrs, 'skip-lazy' ) !== false ||
                 stripos( $attrs, 'data-no-lazy' ) !== false ||
                 stripos( $attrs, 'loading="eager"' ) !== false ||
                 stripos( $attrs, "loading='eager'" ) !== false ) {
                $skipped_count++;
                return $matches[0];
            }

            // 2. Check src or data-src
            $src = '';
            if ( preg_match('/src=([\'"])(.*?)\1/i', $attrs, $src_match) ) {
                $src = $src_match[2];
            } elseif ( preg_match('/data-src=([\'"])(.*?)\1/i', $attrs, $src_match) ) {
                $src = $src_match[2];
            }

            if ( empty( $src ) || strpos( $src, 'data:image/svg+xml' ) === 0 ) {
                $skipped_count++;
                return $matches[0];
            }

            // 3. URL Exclusions
            foreach ( $excludes as $ex ) {
                if ( ! empty( $ex ) && stripos( $src, $ex ) !== false ) {
                    $skipped_count++;
                    return $matches[0];
                }
            }

            // 4. Class Exclusions
            if ( preg_match('/class=([\'"])(.*?)\1/i', $attrs, $class_match) ) {
                $class = $class_match[2];
                foreach ( $class_excludes as $cx ) {
                    if ( ! empty( $cx ) && stripos( $class, $cx ) !== false ) {
                        $skipped_count++;
                        return $matches[0];
                    }
                }
            }

            $new_attrs = $attrs;

            // If image doesn't have data-src yet, convert src to data-src and replace src with SVG placeholder
            if ( stripos( $new_attrs, 'data-src=' ) === false && preg_match('/src=([\'"])(.*?)\1/i', $new_attrs, $sm) ) {
                $real_src = $sm[2];
                $new_attrs = preg_replace('/src=([\'"])(.*?)\1/i', 'src="' . $placeholder . '" data-src="' . $real_src . '"', $new_attrs);
            }

            // Convert srcset to data-srcset
            if ( stripos( $new_attrs, 'data-srcset=' ) === false && preg_match('/srcset=([\'"])(.*?)\1/i', $new_attrs, $ssm) ) {
                $real_srcset = $ssm[2];
                $new_attrs = preg_replace('/srcset=([\'"])(.*?)\1/i', 'data-srcset="' . $real_srcset . '"', $new_attrs);
            }

            // Ensure loading="lazy" is present
            if ( stripos( $new_attrs, 'loading=' ) === false ) {
                $new_attrs .= ' loading="lazy"';
            }

            $lazy_count++;
            return '<img ' . $new_attrs . '>';
        }, $html);

        if ( is_array( $logs ) ) {
            $logs[] = "Lazy Load Images: Applied to {$lazy_count} image(s), Skipped {$skipped_count} image(s)";
        }

        if ( $processed !== $html ) {
            $lazy_js = "\n<script id=\"uwb-lazy-load-js\">
(function() {
    var lazyObserver = null;

    function uwbObserveImg(img) {
        if (!img || !img.getAttribute) return;
        var dataSrc = img.getAttribute('data-src');
        var dataSrcset = img.getAttribute('data-srcset');
        if (!dataSrc && !dataSrcset) return;

        function loadImg(target) {
            var ds = target.getAttribute('data-src');
            var dss = target.getAttribute('data-srcset');
            if (ds) {
                target.src = ds;
                target.removeAttribute('data-src');
            }
            if (dss) {
                target.srcset = dss;
                target.removeAttribute('data-srcset');
            }
            target.classList.add('uwb-loaded');
        }

        if ('IntersectionObserver' in window) {
            if (!lazyObserver) {
                lazyObserver = new IntersectionObserver(function(entries) {
                    entries.forEach(function(entry) {
                        if (entry.isIntersecting || entry.intersectionRatio > 0) {
                            loadImg(entry.target);
                            lazyObserver.unobserve(entry.target);
                        }
                    });
                }, { rootMargin: '300px 0px' });
            }
            lazyObserver.observe(img);
        } else {
            loadImg(img);
        }
    }

    function uwbInitLazyImages() {
        var imgs = document.querySelectorAll('img[data-src], img[data-srcset]');
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
                            if (node.tagName === 'IMG' && (node.getAttribute('data-src') || node.getAttribute('data-srcset'))) {
                                uwbObserveImg(node);
                            }
                            if (node.querySelectorAll) {
                                var subImgs = node.querySelectorAll('img[data-src], img[data-srcset]');
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

    public static function process_iframes( $html, &$logs = null ) {
        $iframe_count = 0;
        $processed = preg_replace_callback('/<iframe\s+([^>]+)>/i', function( $matches ) use ( &$iframe_count ) {
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
                $iframe_count++;
                return '<iframe ' . $new_attrs . '>';
            }
            return $matches[0];
        }, $html);

        if ( is_array( $logs ) ) {
            $logs[] = "Lazy Load Iframes: Applied to {$iframe_count} iframe(s)";
        }

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
