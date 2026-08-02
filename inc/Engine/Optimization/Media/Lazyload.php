<?php
namespace Ultimate_WP_Booster\Engine\Optimization\Media;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class Lazyload {

    private static function get_dom( $html ) {
        if ( empty( $html ) ) return false;
        if ( ! function_exists( 'str_get_html' ) ) {
            $dep_path = defined( 'UWB_PLUGIN_DIR' ) ? UWB_PLUGIN_DIR . 'inc/Dependencies/simple_html_dom.php' : dirname( __DIR__, 3 ) . '/Dependencies/simple_html_dom.php';
            if ( file_exists( $dep_path ) ) {
                require_once $dep_path;
            }
        }
        if ( ! function_exists( 'str_get_html' ) ) {
            return false;
        }
        return str_get_html( $html );
    }

    private static function is_element_excluded( $node, $class_excludes ) {
        if ( empty( $class_excludes ) || ! is_array( $class_excludes ) ) {
            return false;
        }

        $curr = $node;
        while ( $curr ) {
            if ( isset( $curr->nodetype ) && $curr->nodetype !== 1 && $curr->nodetype !== 5 ) {
                $curr = isset( $curr->parent ) ? $curr->parent : null;
                continue;
            }

            $curr_class = (string) $curr->getAttribute( 'class' );
            $curr_id    = (string) $curr->getAttribute( 'id' );
            $curr_tag   = strtolower( (string) $curr->tag );

            $curr_classes = ! empty( $curr_class ) ? preg_split( '/\s+/', trim( $curr_class ) ) : array();

            foreach ( $class_excludes as $rule ) {
                $rule = trim( $rule );
                if ( empty( $rule ) ) continue;

                $tokens = preg_split( '/\s+/', $rule );
                foreach ( $tokens as $token ) {
                    $token = trim( $token );
                    if ( empty( $token ) ) continue;

                    // ID rule: #section_531219969 or #header
                    if ( strpos( $token, '#' ) === 0 ) {
                        $target_id = substr( $token, 1 );
                        if ( ! empty( $curr_id ) && $curr_id === $target_id ) {
                            return true;
                        }
                    }

                    // Class rule starting with dot or plain: .slider-section or section.slider-section
                    $target_class = $token;
                    if ( strpos( $target_class, '#' ) !== false ) {
                        $parts = explode( '#', $target_class, 2 );
                        $target_id = $parts[1];
                        if ( ! empty( $curr_id ) && $curr_id === $target_id ) {
                            return true;
                        }
                        $target_class = $parts[0];
                    }

                    if ( strpos( $target_class, '.' ) !== false ) {
                        $parts = explode( '.', $target_class );
                        foreach ( $parts as $p ) {
                            $p = trim( $p );
                            if ( ! empty( $p ) && ! empty( $curr_classes ) && in_array( $p, $curr_classes, true ) ) {
                                return true;
                            }
                        }
                    } else {
                        $clean_token = ltrim( $token, '.' );
                        if ( ! empty( $clean_token ) && ! empty( $curr_classes ) && in_array( $clean_token, $curr_classes, true ) ) {
                            return true;
                        }
                    }

                    if ( ! empty( $curr_class ) && stripos( $curr_class, ltrim( $token, '.' ) ) !== false ) {
                        return true;
                    }

                    if ( strtolower( $token ) === $curr_tag ) {
                        return true;
                    }
                }
            }

            $curr = isset( $curr->parent ) ? $curr->parent : null;
        }

        return false;
    }

    public static function process_images( $html, $excludes_str = '', $class_excludes_str = '', &$logs = null ) {
        $dom = self::get_dom( $html );
        if ( ! $dom ) {
            return $html;
        }

        $excludes = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", "", $excludes_str ) ) ) );
        $class_excludes = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", "", $class_excludes_str ) ) ) );

        $placeholder = "data:image/svg+xml,%3Csvg%20xmlns='http://www.w3.org/2000/svg'%20viewBox='0%200%201%201'%3E%3C/svg%3E";
        $lazy_count = 0;
        $skipped_count = 0;

        $images = $dom->find( 'img' );
        if ( ! empty( $images ) ) {
            foreach ( $images as $img ) {
                $class = (string) $img->getAttribute( 'class' );
                $src   = (string) $img->getAttribute( 'src' );
                $data_src = (string) $img->getAttribute( 'data-src' );
                $loading  = (string) $img->getAttribute( 'loading' );

                $real_src = ! empty( $src ) ? $src : $data_src;

                // 1. URL Exclusions
                $is_excluded = false;
                if ( ! empty( $real_src ) ) {
                    foreach ( $excludes as $ex ) {
                        if ( ! empty( $ex ) && stripos( $real_src, $ex ) !== false ) {
                            $is_excluded = true;
                            break;
                        }
                    }
                }

                // 2. Class & Parent Container Exclusions
                if ( ! $is_excluded && self::is_element_excluded( $img, $class_excludes ) ) {
                    $is_excluded = true;
                }

                // 3. Markings Exclusion (no-lazy, skip-lazy, data-no-lazy, loading=eager)
                if ( ! $is_excluded ) {
                    if ( strpos( $class, 'no-lazy' ) !== false ||
                         strpos( $class, 'skip-lazy' ) !== false ||
                         $img->hasAttribute( 'data-no-lazy' ) ||
                         $loading === 'eager' ) {
                        $is_excluded = true;
                    }
                }

                if ( $is_excluded ) {
                    // Stripping browser native loading="lazy" attribute so excluded images load eagerly!
                    if ( $img->hasAttribute( 'loading' ) && $img->getAttribute( 'loading' ) === 'lazy' ) {
                        $img->removeAttribute( 'loading' );
                    }
                    if ( $img->hasAttribute( 'data-src' ) ) {
                        $img->setAttribute( 'src', $img->getAttribute( 'data-src' ) );
                        $img->removeAttribute( 'data-src' );
                    }
                    if ( $img->hasAttribute( 'data-srcset' ) ) {
                        $img->setAttribute( 'srcset', $img->getAttribute( 'data-srcset' ) );
                        $img->removeAttribute( 'data-srcset' );
                    }

                    $skipped_count++;
                    continue;
                }

                if ( empty( $real_src ) || strpos( $real_src, 'data:image/svg+xml' ) === 0 ) {
                    $skipped_count++;
                    continue;
                }

                // Set attributes for non-excluded images
                if ( ! $img->hasAttribute( 'data-src' ) && ! empty( $src ) ) {
                    $img->setAttribute( 'data-src', $src );
                    $img->setAttribute( 'src', $placeholder );
                }

                $srcset = (string) $img->getAttribute( 'srcset' );
                if ( ! empty( $srcset ) && ! $img->hasAttribute( 'data-srcset' ) ) {
                    $img->setAttribute( 'data-srcset', $srcset );
                    $img->removeAttribute( 'srcset' );
                }

                if ( ! $img->hasAttribute( 'loading' ) ) {
                    $img->setAttribute( 'loading', 'lazy' );
                }

                $lazy_count++;
            }
        }

        if ( is_array( $logs ) ) {
            $logs[] = "Lazy Load Images: Applied to {$lazy_count} image(s), Skipped {$skipped_count} image(s)";
        }

        $processed = $dom->save();
        $dom->clear();

        if ( $lazy_count > 0 ) {
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

    public static function process_iframes( $html, $class_excludes_str = '', &$logs = null ) {
        $dom = self::get_dom( $html );
        if ( ! $dom ) {
            return $html;
        }

        $class_excludes = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", "", $class_excludes_str ) ) ) );

        $iframe_count = 0;
        $iframes = $dom->find( 'iframe' );
        if ( ! empty( $iframes ) ) {
            foreach ( $iframes as $iframe ) {
                if ( $iframe->hasAttribute( 'data-src' ) ) {
                    continue;
                }
                if ( self::is_element_excluded( $iframe, $class_excludes ) ) {
                    continue;
                }
                $src = (string) $iframe->getAttribute( 'src' );
                if ( ! empty( $src ) && $src !== 'about:blank' ) {
                    $iframe->setAttribute( 'data-src', $src );
                    $iframe->setAttribute( 'src', 'about:blank' );
                    if ( ! $iframe->hasAttribute( 'loading' ) ) {
                        $iframe->setAttribute( 'loading', 'lazy' );
                    }
                    $iframe_count++;
                }
            }
        }

        if ( is_array( $logs ) ) {
            $logs[] = "Lazy Load Iframes: Applied to {$iframe_count} iframe(s)";
        }

        $processed = $dom->save();
        $dom->clear();

        if ( $iframe_count > 0 ) {
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

    public static function process_videos( $html, $class_excludes_str = '', &$logs = null ) {
        $dom = self::get_dom( $html );
        if ( ! $dom ) {
            return $html;
        }

        $class_excludes = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", "", $class_excludes_str ) ) ) );

        $video_count = 0;
        $skipped_count = 0;

        $videos = $dom->find( 'video' );
        if ( ! empty( $videos ) ) {
            foreach ( $videos as $video ) {
                $class = (string) $video->getAttribute( 'class' );

                if ( $video->hasAttribute( 'autoplay' ) ||
                     strpos( $class, 'video-bg' ) !== false ||
                     strpos( $class, 'no-lazy' ) !== false ||
                     strpos( $class, 'skip-lazy' ) !== false ||
                     $video->hasAttribute( 'data-no-lazy' ) ||
                     self::is_element_excluded( $video, $class_excludes ) ) {
                    $skipped_count++;
                    continue;
                }

                $video->setAttribute( 'preload', 'none' );

                $src = (string) $video->getAttribute( 'src' );
                if ( ! empty( $src ) && ! $video->hasAttribute( 'data-src' ) ) {
                    $video->setAttribute( 'data-src', $src );
                    $video->removeAttribute( 'src' );
                }

                $sources = $video->find( 'source' );
                if ( ! empty( $sources ) ) {
                    foreach ( $sources as $source ) {
                        $s_src = (string) $source->getAttribute( 'src' );
                        if ( ! empty( $s_src ) && ! $source->hasAttribute( 'data-src' ) ) {
                            $source->setAttribute( 'data-src', $s_src );
                            $source->removeAttribute( 'src' );
                        }
                    }
                }

                $video_count++;
            }
        }

        if ( is_array( $logs ) ) {
            $logs[] = "Lazy Load Videos: Applied to {$video_count} video(s), Skipped {$skipped_count} autoplay/background video(s)";
        }

        $processed = $dom->save();
        $dom->clear();

        if ( $video_count > 0 ) {
            $lazy_js = "\n<script id=\"uwb-lazy-video-js\">
(function() {
    function uwbInitLazyVideos() {
        var vids = [].slice.call(document.querySelectorAll('video[data-src], video source[data-src]'));
        if (!vids.length) return;

        function loadVideo(videoEl) {
            if (!videoEl) return;
            var sources = videoEl.querySelectorAll('source[data-src]');
            for (var i = 0; i < sources.length; i++) {
                var source = sources[i];
                source.src = source.getAttribute('data-src');
                source.removeAttribute('data-src');
            }
            var ds = videoEl.getAttribute('data-src');
            if (ds) {
                videoEl.src = ds;
                videoEl.removeAttribute('data-src');
            }
            try {
                videoEl.load();
                if (videoEl.hasAttribute('autoplay')) {
                    var p = videoEl.play();
                    if (p && p.catch) { p.catch(function(){}); }
                }
            } catch(e) {}
            videoEl.classList.add('uwb-loaded');
        }

        if ('IntersectionObserver' in window) {
            var videoObserver = new IntersectionObserver(function(entries, observer) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting || entry.intersectionRatio > 0) {
                        var target = entry.target;
                        var parentVideo = target.tagName === 'SOURCE' ? target.parentNode : target;
                        loadVideo(parentVideo);
                        videoObserver.unobserve(target);
                    }
                });
            }, { rootMargin: '300px 0px' });

            vids.forEach(function(v) {
                videoObserver.observe(v);
            });
        } else {
            vids.forEach(function(v) {
                var parentVideo = v.tagName === 'SOURCE' ? v.parentNode : v;
                loadVideo(parentVideo);
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', uwbInitLazyVideos);
    } else {
        uwbInitLazyVideos();
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
        $dom = self::get_dom( $html );
        if ( ! $dom ) {
            return $html;
        }

        $home_url = function_exists( 'home_url' ) ? home_url() : '';
        $images = $dom->find( 'img' );
        if ( ! empty( $images ) ) {
            foreach ( $images as $img ) {
                if ( $img->hasAttribute( 'width' ) && $img->hasAttribute( 'height' ) ) {
                    continue;
                }
                $src = (string) $img->getAttribute( 'src' );
                if ( empty( $src ) || strpos( $src, 'data:' ) === 0 ) {
                    $src = (string) $img->getAttribute( 'data-src' );
                }
                if ( empty( $src ) || strpos( $src, 'data:' ) === 0 ) {
                    continue;
                }

                if ( ( ! empty( $home_url ) && stripos( $src, $home_url ) === 0 ) || strpos( $src, '/' ) === 0 ) {
                    $path = ( strpos( $src, '/' ) === 0 )
                        ? ABSPATH . ltrim( $src, '/' )
                        : ABSPATH . ltrim( str_ireplace( $home_url, '', $src ), '/' );

                    if ( file_exists( $path ) ) {
                        $size = @getimagesize( $path );
                        if ( $size ) {
                            if ( ! $img->hasAttribute( 'width' ) ) {
                                $img->setAttribute( 'width', $size[0] );
                            }
                            if ( ! $img->hasAttribute( 'height' ) ) {
                                $img->setAttribute( 'height', $size[1] );
                            }
                        }
                    }
                }
            }
        }

        $processed = $dom->save();
        $dom->clear();
        return $processed;
    }

    /**
     * Optimize first-view images: remove lazyload, add fetchpriority="high"
     *
     * @param string $html
     * @param array $first_view_images
     * @return string
     */
    public static function optimize_first_view_images( $html, $first_view_images ) {
        if ( empty( $html ) || empty( $first_view_images ) ) {
            return $html;
        }

        // Standardize image URLs to match both absolute and relative paths
        $normalized_urls = array();
        foreach ( $first_view_images as $url ) {
            $url = trim( $url );
            if ( empty( $url ) ) {
                continue;
            }
            $normalized_urls[] = $url;
            $parsed = wp_parse_url( $url );
            if ( isset( $parsed['path'] ) ) {
                $normalized_urls[] = $parsed['path'];
                $normalized_urls[] = ltrim( $parsed['path'], '/' );
            }
        }
        $normalized_urls = array_unique( $normalized_urls );

        // Find all <img> tags in HTML and update attributes
        return preg_replace_callback( '/<img\b[^>]*>/i', function( $matches ) use ( $normalized_urls ) {
            $img_tag = $matches[0];

            preg_match( '/\bsrc\s*=\s*["\']([^"\']+)["\']/i', $img_tag, $src_match );
            preg_match( '/\bdata-src\s*=\s*["\']([^"\']+)["\']/i', $img_tag, $data_src_match );

            $current_src = isset( $src_match[1] ) ? $src_match[1] : '';
            $current_data_src = isset( $data_src_match[1] ) ? $data_src_match[1] : '';

            $is_target = false;
            foreach ( $normalized_urls as $target_url ) {
                if ( ( ! empty( $current_src ) && strpos( $current_src, $target_url ) !== false ) ||
                     ( ! empty( $current_data_src ) && strpos( $current_data_src, $target_url ) !== false ) ) {
                    $is_target = true;
                    break;
                }
            }

            if ( ! $is_target ) {
                return $img_tag;
            }

            // It is a first-view image! Let's modify it.
            // 1. If data-src exists, copy it to src
            if ( ! empty( $current_data_src ) ) {
                if ( ! empty( $current_src ) ) {
                    $img_tag = preg_replace( '/\bsrc\s*=\s*["\']([^"\']+)["\']/i', 'src="' . esc_url( $current_data_src ) . '"', $img_tag );
                } else {
                    $img_tag = preg_replace( '/<img/i', '<img src="' . esc_url( $current_data_src ) . '"', $img_tag );
                }
                $img_tag = preg_replace( '/\bdata-src\s*=\s*["\']([^"\']+)["\']/i', '', $img_tag );
            }

            // 2. If data-srcset exists, copy it to srcset
            preg_match( '/\bdata-srcset\s*=\s*["\']([^"\']+)["\']/i', $img_tag, $data_srcset_match );
            if ( isset( $data_srcset_match[1] ) ) {
                $current_data_srcset = $data_srcset_match[1];
                if ( preg_match( '/\bsrcset\s*=\s*["\']([^"\']+)["\']/i', $img_tag ) ) {
                    $img_tag = preg_replace( '/\bsrcset\s*=\s*["\']([^"\']+)["\']/i', 'srcset="' . esc_attr( $current_data_srcset ) . '"', $img_tag );
                } else {
                    $img_tag = preg_replace( '/<img/i', '<img srcset="' . esc_attr( $current_data_srcset ) . '"', $img_tag );
                }
                $img_tag = preg_replace( '/\bdata-srcset\s*=\s*["\']([^"\']+)["\']/i', '', $img_tag );
            }

            // 3. Remove loading="lazy"
            if ( preg_match( '/\bloading\s*=\s*["\']lazy["\']/i', $img_tag ) ) {
                $img_tag = preg_replace( '/\bloading\s*=\s*["\']lazy["\']/i', '', $img_tag );
            }

            // 4. Remove lazyload classes from class="..."
            preg_match( '/\bclass\s*=\s*["\']([^"\']+)["\']/i', $img_tag, $class_match );
            if ( isset( $class_match[1] ) ) {
                $classes = preg_split( '/\s+/', $class_match[1] );
                $cleaned_classes = array();
                foreach ( $classes as $c ) {
                    if ( ! in_array( strtolower( $c ), array( 'lazyload', 'lazy', 'lazy-loaded', 'uwb-loaded' ), true ) ) {
                        $cleaned_classes[] = $c;
                    }
                }
                $img_tag = preg_replace( '/\bclass\s*=\s*["\']([^"\']+)["\']/i', 'class="' . esc_attr( implode( ' ', $cleaned_classes ) ) . '"', $img_tag );
            }

            // 5. Add fetchpriority="high" if not already present
            if ( strpos( $img_tag, 'fetchpriority' ) === false ) {
                $img_tag = preg_replace( '/<img/i', '<img fetchpriority="high"', $img_tag );
            } else {
                $img_tag = preg_replace( '/\bfetchpriority\s*=\s*["\']([^"\']+)["\']/i', 'fetchpriority="high"', $img_tag );
            }

            // Clean up double spaces or spaces before closing tag
            $img_tag = preg_replace( '/\s+/', ' ', $img_tag );
            $img_tag = str_replace( ' >', '>', $img_tag );

            return $img_tag;
        }, $html );
    }
}

