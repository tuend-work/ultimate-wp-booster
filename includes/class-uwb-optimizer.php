<?php
/**
 * HTML Optimization & Processing Engine
 */

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class Uwb_Optimizer {

    /**
     * Process the HTML buffer with enabled optimization options.
     * 
     * @param string $html Output buffer HTML content.
     * @param array  $config Configuration settings.
     * @return string Processed HTML content.
     */
    public static function process( $html, $config ) {
        if ( empty( $html ) || ! is_array( $config ) ) {
            return $html;
        }

        $debug_mode = ! empty( $config['debug_mode'] );
        if ( $debug_mode ) {
            $GLOBALS['uwb_debug_log'] = array();
            $GLOBALS['uwb_debug_log'][] = "=== UWB OPTIMIZER DEBUG LOG ===";
            $GLOBALS['uwb_debug_log'][] = "Home URL: " . (function_exists( 'home_url' ) ? home_url() : 'undefined');
            $GLOBALS['uwb_debug_log'][] = "Config keys: " . implode( ', ', array_keys( $config ) );
        }

        // 1. Critical CSS Injection
        if ( ! empty( $config['tuning_critical_css'] ) ) {
            $html = self::inject_critical_css( $html, $config['tuning_critical_css'] );
        }

        // 2. Remove Google Fonts
        if ( ! empty( $config['html_remove_gfonts'] ) ) {
            $html = self::remove_google_fonts( $html );
        }

        // 3. Remove WordPress Emoji
        if ( ! empty( $config['html_remove_emoji'] ) ) {
            $html = self::remove_emoji( $html );
        }

        // 4. Remove Noscript Tags
        if ( ! empty( $config['html_remove_noscript'] ) ) {
            $html = self::remove_noscript( $html );
        }

        // 5. Remove Query Strings
        if ( ! empty( $config['html_remove_qs'] ) ) {
            $html = self::remove_query_strings( $html );
        }

        // 6. Lazy Load Images
        if ( ! empty( $config['media_lazy_load_images'] ) ) {
            $excludes = isset( $config['media_lazy_load_excludes'] ) ? $config['media_lazy_load_excludes'] : '';
            $class_excludes = isset( $config['media_lazy_load_class_excludes'] ) ? $config['media_lazy_load_class_excludes'] : '';
            $html = self::lazy_load_images( $html, $excludes, $class_excludes );
        }

        // 7. Lazy Load Iframes
        if ( ! empty( $config['media_lazy_load_iframes'] ) ) {
            $html = self::lazy_load_iframes( $html );
        }

        // 8. Add Missing Image Sizes
        if ( ! empty( $config['media_add_missing_sizes'] ) ) {
            $html = self::add_missing_sizes( $html );
        }

        // 9. Defer Javascript
        if ( ! empty( $config['js_load_defer'] ) ) {
            $js_defer_excludes = isset( $config['tuning_js_defer_excludes'] ) ? $config['tuning_js_defer_excludes'] : ( isset( $config['tuning_js_excludes'] ) ? $config['tuning_js_excludes'] : '' );
            $html = self::defer_js( $html, $js_defer_excludes );
        }

        // 10. Combine or Minify CSS
        if ( ! empty( $config['css_combine'] ) ) {
            $css_excludes = isset( $config['tuning_css_excludes'] ) ? $config['tuning_css_excludes'] : '';
            $include_ext = ! empty( $config['css_combine_ext_inline'] );
            $html = self::combine_css( $html, $css_excludes, $include_ext );
        } elseif ( ! empty( $config['css_minify'] ) ) {
            $html = self::minify_external_css( $html );
            $html = self::minify_inline_css( $html );
        }

        // 11. Combine or Minify JS
        if ( ! empty( $config['js_combine'] ) ) {
            $js_excludes = isset( $config['tuning_js_excludes'] ) ? $config['tuning_js_excludes'] : '';
            $include_ext = ! empty( $config['js_combine_ext_inline'] );
            $html = self::combine_js( $html, $js_excludes, $include_ext );
        } elseif ( ! empty( $config['js_minify'] ) ) {
            $html = self::minify_external_js( $html );
            $html = self::minify_inline_js( $html );
        }

        // 12. Minify HTML markup
        if ( ! empty( $config['html_minify'] ) ) {
            $html = self::minify_html( $html );
        }

        // 13. Preconnect External Domains
        if ( ! empty( $config['preconnect_domains'] ) ) {
            $html = self::inject_preconnect( $html, $config['preconnect_domains'] );
        }

        // 14. Preload Key Fonts
        if ( ! empty( $config['preload_fonts'] ) ) {
            $html = self::inject_preload_fonts( $html, $config['preload_fonts'] );
        }

        // 15. Delay JS Execution
        if ( ! empty( $config['delay_js'] ) ) {
            $excludes = isset( $config['delay_js_exclusions'] ) ? $config['delay_js_exclusions'] : '';
            $html = self::delay_js_execution( $html, $excludes );
        }

        if ( $debug_mode && ! empty( $GLOBALS['uwb_debug_log'] ) ) {
            $html .= "\n<!-- UWB DEBUG LOG:\n" . implode( "\n", $GLOBALS['uwb_debug_log'] ) . "\n-->";
        }

        return $html;
    }

    /**
     * Inject <link rel="preconnect"> tags for external domains.
     *
     * @param string $html   HTML content.
     * @param string $domains_raw Newline-separated list of domain URLs.
     * @return string Modified HTML.
     */
    public static function inject_preconnect( $html, $domains_raw ) {
        $domains = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", "", $domains_raw ) ) ) );
        if ( empty( $domains ) ) {
            return $html;
        }

        $tags = '';
        foreach ( $domains as $domain ) {
            $domain = esc_url( $domain );
            if ( empty( $domain ) ) {
                continue;
            }
            $tags .= "\n<link rel=\"preconnect\" href=\"{$domain}\" crossorigin>";
        }

        if ( empty( $tags ) ) {
            return $html;
        }

        // Inject after opening <head> tag
        if ( preg_match( '/<head[^>]*>/i', $html, $matches ) ) {
            return str_replace( $matches[0], $matches[0] . $tags, $html );
        }

        return $html;
    }

    /**
     * Inject <link rel="preload" as="font"> tags for critical fonts.
     *
     * @param string $html      HTML content.
     * @param string $fonts_raw Newline-separated list of font URLs.
     * @return string Modified HTML.
     */
    public static function inject_preload_fonts( $html, $fonts_raw ) {
        $fonts = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", "", $fonts_raw ) ) ) );
        if ( empty( $fonts ) ) {
            return $html;
        }

        $home_url = function_exists( 'home_url' ) ? home_url() : '';
        $tags = '';
        foreach ( $fonts as $font_url ) {
            // Normalize relative paths to absolute URLs
            if ( strpos( $font_url, 'http' ) !== 0 ) {
                $font_url = rtrim( $home_url, '/' ) . '/' . ltrim( $font_url, '/' );
            }
            $font_url = esc_url( $font_url );
            if ( empty( $font_url ) ) {
                continue;
            }
            // Determine format from extension
            $ext = strtolower( pathinfo( parse_url( $font_url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
            $type_map = array(
                'woff2' => 'font/woff2',
                'woff'  => 'font/woff',
                'ttf'   => 'font/ttf',
                'otf'   => 'font/otf',
                'eot'   => 'application/vnd.ms-fontobject',
            );
            $font_type = isset( $type_map[ $ext ] ) ? $type_map[ $ext ] : 'font/woff2';
            $tags .= "\n<link rel=\"preload\" href=\"{$font_url}\" as=\"font\" type=\"{$font_type}\" crossorigin>";
        }

        if ( empty( $tags ) ) {
            return $html;
        }

        if ( preg_match( '/<head[^>]*>/i', $html, $matches ) ) {
            return str_replace( $matches[0], $matches[0] . $tags, $html );
        }

        return $html;
    }

    /**
     * Delay JavaScript execution until first user interaction.
     * Transforms script type to "text/uwb-lazyload" and injects a tiny loader
     * that restores and executes scripts on scroll/click/keydown/touchstart.
     *
     * @param string $html        HTML content.
     * @param string $excludes_str Newline-separated exclusion patterns.
     * @return string Modified HTML.
     */
    public static function delay_js_execution( $html, $excludes_str = '' ) {
        // Default exclusions — always exclude these critical scripts
        $default_excludes = array(
            'jquery.js',
            'jquery.min.js',
            'jquery-migrate',
            'noscript',
            'uwb-lazy',
        );
        $user_excludes = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", "", $excludes_str ) ) ) );
        $excludes = array_merge( $default_excludes, $user_excludes );

        $processed = preg_replace_callback(
            '#<script\b([^>]*?)>(.*?)</script>#ims',
            function( $matches ) use ( $excludes ) {
                $attrs   = $matches[1];
                $content = $matches[2];

                // Skip if already processed
                if ( stripos( $attrs, 'text/uwb-lazyload' ) !== false ) {
                    return $matches[0];
                }

                // Check exclusion patterns against attrs + content
                foreach ( $excludes as $ex ) {
                    if ( ! empty( $ex ) && stripos( $matches[0], $ex ) !== false ) {
                        return $matches[0];
                    }
                }

                // Only delay scripts with JS type or no type
                if ( preg_match( '/type\s*=\s*["\']([^"\']+)["\']/i', $attrs, $type_match ) ) {
                    $type = strtolower( trim( $type_match[1] ) );
                    $allowed = array( 'text/javascript', 'application/javascript', 'module' );
                    if ( ! in_array( $type, $allowed, true ) ) {
                        return $matches[0];
                    }
                    // Replace type attr
                    $attrs = preg_replace( '/type\s*=\s*["\'][^"\']*["\']/i', 'type="text/uwb-lazyload"', $attrs );
                } else {
                    // No type attr — add our delay type
                    $attrs = ' type="text/uwb-lazyload"' . $attrs;
                }

                // For external scripts: rename src → data-uwb-src
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

        // Inject the tiny loader script before </body>
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



    /**
     * Minify HTML source code.
     */
    public static function minify_html( $html ) {
        // Remove HTML comments, except IE conditional comments and cache/pipeline comment signatures
        $html = preg_replace('/<!--(?!\s*(?:\[if|Cached by WP Booster|Dynamic Page)).*?-->/s', '', $html);
        // Replace multiple whitespace/newline with a single space
        $html = preg_replace('/\s+/', ' ', $html);
        // Clean space between tags
        $html = preg_replace('/>\s+</', '><', $html);
        return trim( $html );
    }

    /**
     * Remove query strings from static assets (.css and .js).
     */
    public static function remove_query_strings( $html ) {
        return preg_replace_callback('/(src|href)=([\'"])(.*?)\.(css|js)\?(.*?)\2/i', function( $matches ) {
            return $matches[1] . '=' . $matches[2] . $matches[3] . '.' . $matches[4] . $matches[2];
        }, $html);
    }

    /**
     * Strip Google Fonts link & import elements.
     */
    public static function remove_google_fonts( $html ) {
        $html = preg_replace('/<link[^>]+href=[\'"][^\'"]*fonts\.(googleapis|gstatic)\.com[^\'"]*[\'"][^>]*>/i', '', $html);
        $html = preg_replace('/@import\s+url\([\'"][^\'"]*fonts\.(googleapis|gstatic)\.com[^\'"]*[\'"]\);/i', '', $html);
        return $html;
    }

    /**
     * Remove inline emoji JS and styles.
     */
    public static function remove_emoji( $html ) {
        $html = preg_replace('#<script\b[^>]*>[^<]*?window\._wpemojiSettings[^<]*?<\/script>#is', '', $html);
        $html = preg_replace('/<style[^>]*>[^<]*img\.wp-smiley[^<]*<\/style>/is', '', $html);
        return $html;
    }

    /**
     * Remove noscript blocks completely.
     */
    public static function remove_noscript( $html ) {
        return preg_replace('#<noscript\b[^>]*>(?>[^<]++|<(?!/noscript>))*?</noscript>#is', '', $html);
    }

    /**
     * Minify CSS inside style tags.
     */
    public static function minify_inline_css( $html ) {
        return preg_replace_callback('#<style\b([^>]*)>((?>[^<]++|<(?!/style>))*?)</style>#is', function( $matches ) {
            $attrs = $matches[1];
            $css = $matches[2];
            // Skip if this is critical css to avoid stripping placeholder signatures
            if ( strpos( $attrs, 'uwb-critical-css' ) !== false ) {
                return $matches[0];
            }
            // Remove CSS comments
            $css = preg_replace('!/\*[^*]*\*+([^/*][^*]*\*+)*/!', '', $css);
            // Remove spaces around brackets and punctuation
            $css = preg_replace('/\s*([{}|;:,])\s*/', '$1', $css);
            // Collapse multiple spaces
            $css = preg_replace('/\s+/', ' ', $css);
            return '<style' . $attrs . '>' . trim( $css ) . '</style>';
        }, $html);
    }

    /**
     * Minify inline script blocks using simple safe regex rules.
     */
    public static function minify_inline_js( $html ) {
        return preg_replace_callback('#<script\b([^>]*)>((?>[^<]++|<(?!/script>))*?)</script>#is', function( $matches ) {
            $attrs = $matches[1];
            $js = $matches[2];
            // Only process if it has no src attribute and is javascript
            if ( stripos( $attrs, 'src=' ) !== false ) {
                return $matches[0];
            }
            if ( ! empty( $attrs ) && stripos( $attrs, 'type=' ) !== false && stripos( $attrs, 'javascript' ) === false && stripos( $attrs, 'module' ) === false ) {
                return $matches[0];
            }
            
            $minified = self::minify_js_safe( $js );
            return '<script' . $attrs . '>' . $minified . '</script>';
        }, $html);
    }

    /**
     * Lazy load image elements.
     */
    public static function lazy_load_images( $html, $excludes_str = '', $class_excludes_str = '' ) {
        $excludes = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", "", $excludes_str ) ) ) );
        $class_excludes = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", "", $class_excludes_str ) ) ) );

        $processed = preg_replace_callback('/<img\s+([^>]+)>/i', function( $matches ) use ( $excludes, $class_excludes ) {
            $attrs = $matches[1];
            
            if ( ! preg_match('/src=([\'"])(.*?)\1/i', $attrs, $src_match) ) {
                return $matches[0];
            }
            $src = $src_match[2];

            // Exclude matching strings
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

            if ( stripos( $attrs, 'data-src' ) !== false || stripos( $attrs, 'lazyload' ) !== false ) {
                return $matches[0];
            }

            // Safe Base64 SVG placeholder (1x1 transparent) to avoid HTML attribute quote breakage
            $placeholder = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAxIDEiPjwvc3ZnPg==';

            $new_attrs = preg_replace_callback('/src=([\'"])(.*?)\1/i', function( $m ) use ( $placeholder ) {
                return 'src="' . $placeholder . '" data-src="' . $m[2] . '"';
            }, $attrs);
            
            if ( preg_match('/srcset=/i', $new_attrs) ) {
                $new_attrs = preg_replace_callback('/srcset=([\'"])(.*?)\1/i', function( $m ) {
                    return 'data-srcset="' . $m[2] . '"';
                }, $new_attrs);
            }

            if ( stripos( $new_attrs, 'loading=' ) === false ) {
                $new_attrs .= ' loading="lazy"';
            }

            return '<img ' . $new_attrs . '>';
        }, $html);

        // Inject script to load images when visible
        if ( $processed !== $html ) {
            $lazy_js = "\n<script id=\"uwb-lazy-load-js\">
(function() {
    function uwbInitLazyImages() {
        var lazyImages = [].slice.call(document.querySelectorAll(\"img[data-src]\"));
        if (!lazyImages.length) return;
        if (\"IntersectionObserver\" in window) {
            let lazyImageObserver = new IntersectionObserver(function(entries, observer) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting || entry.intersectionRatio > 0) {
                        let lazyImage = entry.target;
                        if (lazyImage.dataset.src) {
                            lazyImage.src = lazyImage.dataset.src;
                            lazyImage.removeAttribute(\"data-src\");
                        }
                        if (lazyImage.dataset.srcset) {
                            lazyImage.srcset = lazyImage.dataset.srcset;
                            lazyImage.removeAttribute(\"data-srcset\");
                        }
                        lazyImageObserver.unobserve(lazyImage);
                    }
                });
            }, { rootMargin: \"300px 0px\" });
            lazyImages.forEach(function(lazyImage) {
                lazyImageObserver.observe(lazyImage);
            });
        } else {
            lazyImages.forEach(function(img) {
                if (img.dataset.src) {
                    img.src = img.dataset.src;
                    img.removeAttribute(\"data-src\");
                }
                if (img.dataset.srcset) {
                    img.srcset = img.dataset.srcset;
                    img.removeAttribute(\"data-srcset\");
                }
            });
        }
    }
    if (document.readyState === \"loading\") {
        document.addEventListener(\"DOMContentLoaded\", uwbInitLazyImages);
    } else {
        uwbInitLazyImages();
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

    /**
     * Lazy load iframe elements.
     */
    public static function lazy_load_iframes( $html ) {
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

    /**
     * Automatically add missing width and height attributes to local images.
     */
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

    /**
     * Defer script tags.
     */
    public static function defer_js( $html, $excludes_str = '' ) {
        $user_excludes = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", "", $excludes_str ) ) ) );
        $default_excludes = array( 'jquery.js', 'jquery.min.js', 'jquery-migrate' );
        $excludes = array_merge( $default_excludes, $user_excludes );

        return preg_replace_callback('/<script\s+([^>]*src=[\'"][^\'"]+[\'"][^>]*)>/i', function( $matches ) use ( $excludes ) {
            $attrs = $matches[1];
            if ( stripos( $attrs, 'defer' ) !== false || stripos( $attrs, 'async' ) !== false || stripos( $attrs, 'text/uwb-lazyload' ) !== false ) {
                return $matches[0];
            }
            foreach ( $excludes as $ex ) {
                if ( ! empty( $ex ) && stripos( $matches[0], $ex ) !== false ) {
                    return $matches[0];
                }
            }
            return '<script ' . $attrs . ' defer>';
        }, $html);
    }

    /**
     * Combine external CSS stylesheets into a single cached stylesheet.
     *
     * @param string $html         HTML content.
     * @param string $excludes_str Newline-separated list of exclusion keywords/urls.
     * @param bool   $include_ext  Whether to combine external domain assets as well.
     * @return string Modified HTML.
     */
    public static function combine_css( $html, $excludes_str = '', $include_ext = false ) {
        $cache_dir = WP_CONTENT_DIR . '/cache/ultimate-wp-booster/combine';
        if ( ! is_dir( $cache_dir ) ) {
            @mkdir( $cache_dir, 0755, true );
        }

        $home_url = function_exists( 'home_url' ) ? home_url() : '';
        $home_host = ! empty( $home_url ) ? parse_url( $home_url, PHP_URL_HOST ) : '';
        $excludes = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", "", $excludes_str ) ) ) );

        preg_match_all('#<link\b[^>]*?href=([\'"])(.*?)\1[^>]*?>#is', $html, $matches, PREG_SET_ORDER);

        if ( empty( $matches ) ) {
            return $html;
        }

        $to_combine = array();
        $urls_hashes = array();

        foreach ( $matches as $m ) {
            $tag = $m[0];
            $url = $m[2];
            $url_clean = strtok( $url, '?' );

            if ( strtolower( substr( $url_clean, -4 ) ) !== '.css' ) {
                continue;
            }

            if ( stripos( $tag, 'rel=' ) === false || stripos( $tag, 'stylesheet' ) === false ) {
                continue;
            }

            // Skip critical CSS or print media
            if ( stripos( $tag, 'uwb-critical-css' ) !== false || stripos( $tag, 'media="print"' ) !== false || stripos( $tag, "media='print'" ) !== false ) {
                continue;
            }

            // Check exclusions
            $is_excluded = false;
            foreach ( $excludes as $ex ) {
                if ( ! empty( $ex ) && ( stripos( $url, $ex ) !== false || stripos( $tag, $ex ) !== false ) ) {
                    $is_excluded = true;
                    break;
                }
            }
            if ( $is_excluded ) {
                continue;
            }

            $local_path = self::resolve_local_path( $url_clean, $home_url, $home_host );
            if ( ! $local_path && ! $include_ext ) {
                continue;
            }

            $mtime = ( $local_path && file_exists( $local_path ) ) ? filemtime( $local_path ) : 0;
            $urls_hashes[] = $url_clean . '_' . $mtime;

            $to_combine[] = array(
                'tag'        => $tag,
                'url'        => $url,
                'url_clean'  => $url_clean,
                'local_path' => $local_path,
            );
        }

        if ( count( $to_combine ) < 2 ) {
            return $html;
        }

        $hash = md5( implode( '|', $urls_hashes ) );
        $cache_file = $cache_dir . '/uwb-combined-' . $hash . '.css';
        $cache_url = content_url( '/cache/ultimate-wp-booster/combine/uwb-combined-' . $hash . '.css' );

        if ( ! file_exists( $cache_file ) ) {
            $combined_content = '';
            foreach ( $to_combine as $item ) {
                $content = '';
                if ( $item['local_path'] && file_exists( $item['local_path'] ) ) {
                    $content = @file_get_contents( $item['local_path'] );
                } elseif ( $include_ext ) {
                    $content = self::download_url_content( $item['url'] );
                }

                if ( ! empty( $content ) ) {
                    $content = self::rewrite_css_urls( $content, $item['url'] );
                    // Strip comments & extra whitespace
                    $content = preg_replace('!/\*[^*]*\*+([^/*][^*]*\*+)*/!', '', $content);
                    $content = preg_replace('/\s*([{}|;:,])\s*/', '$1', $content);
                    $content = preg_replace('/\s+/', ' ', $content);
                    $combined_content .= "\n/* Combined: " . esc_html( $item['url_clean'] ) . " */\n" . trim( $content );
                }
            }

            if ( empty( $combined_content ) ) {
                return $html;
            }

            @file_put_contents( $cache_file, trim( $combined_content ) );
        }

        // Replace first combined tag with single <link>, remove the rest
        $first = true;
        foreach ( $to_combine as $item ) {
            if ( $first ) {
                $new_tag = '<link rel="stylesheet" id="uwb-combined-css" href="' . esc_url( $cache_url ) . '" media="all">';
                $html = str_replace( $item['tag'], $new_tag, $html );
                $first = false;
            } else {
                $html = str_replace( $item['tag'], '', $html );
            }
        }

        return $html;
    }

    /**
     * Combine external JavaScript files into a single combined script.
     *
     * @param string $html         HTML content.
     * @param string $excludes_str Newline-separated list of exclusion keywords/urls.
     * @param bool   $include_ext  Whether to combine external domain assets as well.
     * @return string Modified HTML.
     */
    public static function combine_js( $html, $excludes_str = '', $include_ext = false ) {
        $cache_dir = WP_CONTENT_DIR . '/cache/ultimate-wp-booster/combine';
        if ( ! is_dir( $cache_dir ) ) {
            @mkdir( $cache_dir, 0755, true );
        }

        $home_url = function_exists( 'home_url' ) ? home_url() : '';
        $home_host = ! empty( $home_url ) ? parse_url( $home_url, PHP_URL_HOST ) : '';
        $user_excludes = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", "", $excludes_str ) ) ) );
        
        $default_excludes = array( 'jquery.js', 'jquery.min.js', 'jquery-migrate' );
        $excludes = array_merge( $default_excludes, $user_excludes );

        preg_match_all('#<script\b([^>]*?)>(.*?)</script>#is', $html, $matches, PREG_SET_ORDER);

        if ( empty( $matches ) ) {
            return $html;
        }

        $current_chunk = array();

        $flush_chunk = function() use ( &$html, &$current_chunk, $cache_dir, $include_ext ) {
            if ( count( $current_chunk ) < 2 ) {
                $current_chunk = array();
                return;
            }

            $urls_hashes = array();
            foreach ( $current_chunk as $item ) {
                $mtime = ( $item['local_path'] && file_exists( $item['local_path'] ) ) ? filemtime( $item['local_path'] ) : 0;
                $urls_hashes[] = $item['url_clean'] . '_' . $mtime;
            }

            $hash = md5( implode( '|', $urls_hashes ) );
            $cache_file = $cache_dir . '/uwb-js-' . $hash . '.js';
            $cache_url = content_url( '/cache/ultimate-wp-booster/combine/uwb-js-' . $hash . '.js' );

            if ( ! file_exists( $cache_file ) ) {
                $combined_content = '';
                foreach ( $current_chunk as $item ) {
                    $content = '';
                    if ( $item['local_path'] && file_exists( $item['local_path'] ) ) {
                        $content = @file_get_contents( $item['local_path'] );
                    } elseif ( $include_ext ) {
                        $content = self::download_url_content( $item['url'] );
                    }

                    if ( ! empty( $content ) ) {
                        if ( stripos( $item['url_clean'], '.min.js' ) === false ) {
                            $content = self::minify_js_safe( $content );
                        }
                        $combined_content .= "\n;/* Combined: " . esc_html( $item['url_clean'] ) . " */\n" . trim( $content ) . ";";
                    }
                }

                if ( ! empty( $combined_content ) ) {
                    @file_put_contents( $cache_file, trim( $combined_content ) );
                }
            }

            if ( file_exists( $cache_file ) ) {
                $first = true;
                foreach ( $current_chunk as $item ) {
                    if ( $first ) {
                        $new_tag = '<script src="' . esc_url( $cache_url ) . '"></script>';
                        $html = str_replace( $item['tag'], $new_tag, $html );
                        $first = false;
                    } else {
                        $html = str_replace( $item['tag'], '', $html );
                    }
                }
            }

            $current_chunk = array();
        };

        foreach ( $matches as $m ) {
            $tag = $m[0];
            $attrs = $m[1];

            if ( preg_match('/src=([\'"])(.*?)\1/i', $attrs, $src_match) ) {
                // Script with src attribute
                $url = $src_match[2];
                $url_clean = strtok( $url, '?' );

                $is_js_file = strtolower( substr( $url_clean, -3 ) ) === '.js';
                $is_special = stripos( $attrs, 'async' ) !== false
                           || stripos( $attrs, 'text/uwb-lazyload' ) !== false
                           || stripos( $attrs, 'type="module"' ) !== false;

                $is_excluded = false;
                foreach ( $excludes as $ex ) {
                    if ( ! empty( $ex ) && ( stripos( $url, $ex ) !== false || stripos( $tag, $ex ) !== false ) ) {
                        $is_excluded = true;
                        break;
                    }
                }

                $local_path = self::resolve_local_path( $url_clean, $home_url, $home_host );

                if ( $is_js_file && ! $is_special && ! $is_excluded && ( $local_path || $include_ext ) ) {
                    // Combineable script — add to current chunk
                    $current_chunk[] = array(
                        'tag'        => $tag,
                        'url'        => $url,
                        'url_clean'  => $url_clean,
                        'local_path' => $local_path,
                    );
                }
                // else: external/excluded/async scripts — skip, do not flush.
            } else {
                // Inline script (no src) — skip, do not flush.
                // External scripts before and after inline scripts are all combined
                // into one chunk. Inline scripts remain in their original position.
                // Users should exclude scripts that depend on load order via the exclude list.
            }
        }

        $flush_chunk();

        return $html;
    }

    /**
     * Inject custom critical CSS inside head tag.
     */
    public static function inject_critical_css( $html, $css ) {
        if ( empty( $css ) ) {
            return $html;
        }
        $style_tag = '<style id="uwb-critical-css">' . strip_tags( $css ) . '</style>';
        if ( preg_match('/<head[^>]*>/i', $html, $matches) ) {
            return str_replace( $matches[0], $matches[0] . "\n" . $style_tag, $html );
        }
        return $html;
    }

    /**
     * Minify external CSS files and replace their URLs with cached minified versions.
     */
    public static function minify_external_css( $html ) {
        $cache_dir = WP_CONTENT_DIR . '/cache/ultimate-wp-booster/minify';
        if ( ! is_dir( $cache_dir ) ) {
            @mkdir( $cache_dir, 0755, true );
        }

        $home_url = function_exists( 'home_url' ) ? home_url() : '';
        $home_host = ! empty( $home_url ) ? parse_url( $home_url, PHP_URL_HOST ) : '';

        return preg_replace_callback('#<link\b[^>]*?href=([\'"])(.*?)\1[^>]*?>#is', function( $matches ) use ( $cache_dir, $home_url, $home_host ) {
            $tag = $matches[0];
            $url = $matches[2];
            $url_clean = strtok( $url, '?' );

            $debug_mode = ! empty( $GLOBALS['uwb_debug_log'] );
            if ( $debug_mode ) {
                $GLOBALS['uwb_debug_log'][] = "Found CSS link: " . $url_clean;
            }

            if ( strtolower( substr( $url_clean, -4 ) ) !== '.css' ) {
                return $tag;
            }

            if ( stripos( $tag, 'rel=' ) === false || stripos( $tag, 'stylesheet' ) === false ) {
                return $tag;
            }

            if ( stripos( $tag, 'uwb-critical-css' ) !== false ) {
                return $tag;
            }

            $local_path = self::resolve_local_path( $url_clean, $home_url, $home_host );

            // Check if sibling .min.css exists locally
            if ( $local_path && file_exists( $local_path ) && stripos( $url_clean, '.min.css' ) === false ) {
                $min_sibling = substr( $local_path, 0, -4 ) . '.min.css';
                if ( file_exists( $min_sibling ) ) {
                    $sibling_url = substr( $url_clean, 0, -4 ) . '.min.css';
                    if ( $debug_mode ) {
                        $GLOBALS['uwb_debug_log'][] = "CSS URL $url_clean has a .min.css sibling at $min_sibling, replacing.";
                    }
                    return str_replace( $url, $sibling_url, $tag );
                }
            }

            $file_mtime = ( $local_path && file_exists( $local_path ) ) ? filemtime( $local_path ) : '';
            $hash = md5( $url_clean . '_' . $file_mtime );
            $cache_file = $cache_dir . '/' . $hash . '.css';
            $cache_url = content_url( '/cache/ultimate-wp-booster/minify/' . $hash . '.css' );

            if ( $debug_mode ) {
                $GLOBALS['uwb_debug_log'][] = "CSS URL: $url -> Hash: $hash -> Cache path: $cache_file";
            }

            if ( ! file_exists( $cache_file ) ) {
                $content = '';
                if ( $local_path && file_exists( $local_path ) ) {
                    $content = @file_get_contents( $local_path );
                }
                
                if ( empty( $content ) ) {
                    if ( $debug_mode ) {
                        $GLOBALS['uwb_debug_log'][] = "CSS URL $url_clean: Local file empty or missing, downloading...";
                    }
                    $content = self::download_url_content( $url );
                }

                if ( ! empty( $content ) ) {
                    // Rewrite relative URLs to absolute relative to the source CSS URL
                    $content = self::rewrite_css_urls( $content, $url );

                    // Minify if not already minified
                    if ( stripos( $url_clean, '.min.css' ) === false ) {
                        $content = preg_replace('!/\*[^*]*\*+([^/*][^*]*\*+)*/!', '', $content);
                        $content = preg_replace('/\s*([{}|;:,])\s*/', '$1', $content);
                        $content = preg_replace('/\s+/', ' ', $content);
                    }

                    $write_ok = @file_put_contents( $cache_file, trim( $content ) );
                    if ( $debug_mode ) {
                        $GLOBALS['uwb_debug_log'][] = "CSS URL $url_clean: Written to cache: " . ($write_ok !== false ? 'YES' : 'NO');
                    }

                    if ( $write_ok === false ) {
                        return $tag;
                    }
                } else {
                    if ( $debug_mode ) {
                        $GLOBALS['uwb_debug_log'][] = "CSS URL $url_clean: Failed to obtain content.";
                    }
                    return $tag;
                }
            }

            $new_tag = preg_replace('/href=([\'"])(.*?)\1/i', 'href="' . esc_url( $cache_url ) . '"', $tag);
            return $new_tag;
        }, $html);
    }

    /**
     * Minify external JS files and replace their URLs with cached minified versions.
     */
    public static function minify_external_js( $html ) {
        $cache_dir = WP_CONTENT_DIR . '/cache/ultimate-wp-booster/minify';
        if ( ! is_dir( $cache_dir ) ) {
            @mkdir( $cache_dir, 0755, true );
        }

        $home_url = function_exists( 'home_url' ) ? home_url() : '';
        $home_host = ! empty( $home_url ) ? parse_url( $home_url, PHP_URL_HOST ) : '';

        return preg_replace_callback('#<script\b[^>]*?src=([\'"])(.*?)\1[^>]*?>\s*</script>#is', function( $matches ) use ( $cache_dir, $home_url, $home_host ) {
            $tag = $matches[0];
            $url = $matches[2];
            $url_clean = strtok( $url, '?' );

            $debug_mode = ! empty( $GLOBALS['uwb_debug_log'] );
            if ( $debug_mode ) {
                $GLOBALS['uwb_debug_log'][] = "Found JS script: " . $url_clean;
            }

            if ( strtolower( substr( $url_clean, -3 ) ) !== '.js' ) {
                return $tag;
            }

            $local_path = self::resolve_local_path( $url_clean, $home_url, $home_host );

            // Check if sibling .min.js exists locally
            if ( $local_path && file_exists( $local_path ) && stripos( $url_clean, '.min.js' ) === false ) {
                $min_sibling = substr( $local_path, 0, -3 ) . '.min.js';
                if ( file_exists( $min_sibling ) ) {
                    $sibling_url = substr( $url_clean, 0, -3 ) . '.min.js';
                    if ( $debug_mode ) {
                        $GLOBALS['uwb_debug_log'][] = "JS URL $url_clean has a .min.js sibling at $min_sibling, replacing.";
                    }
                    return str_replace( $url, $sibling_url, $tag );
                }
            }

            $file_mtime = ( $local_path && file_exists( $local_path ) ) ? filemtime( $local_path ) : '';
            $hash = md5( $url_clean . '_' . $file_mtime );
            $cache_file = $cache_dir . '/' . $hash . '.js';
            $cache_url = content_url( '/cache/ultimate-wp-booster/minify/' . $hash . '.js' );

            if ( $debug_mode ) {
                $GLOBALS['uwb_debug_log'][] = "JS URL: $url -> Hash: $hash -> Cache path: $cache_file";
            }

            if ( ! file_exists( $cache_file ) ) {
                $content = '';
                if ( $local_path && file_exists( $local_path ) ) {
                    $content = @file_get_contents( $local_path );
                }
                
                if ( empty( $content ) ) {
                    if ( $debug_mode ) {
                        $GLOBALS['uwb_debug_log'][] = "JS URL $url_clean: Local file empty or missing, downloading...";
                    }
                    $content = self::download_url_content( $url );
                }

                if ( ! empty( $content ) ) {
                    // Minify JS if not already minified
                    if ( stripos( $url_clean, '.min.js' ) === false ) {
                        $content = self::minify_js_safe( $content );
                    }

                    $write_ok = @file_put_contents( $cache_file, trim( $content ) );
                    if ( $debug_mode ) {
                        $GLOBALS['uwb_debug_log'][] = "JS URL $url_clean: Written to cache: " . ($write_ok !== false ? 'YES' : 'NO');
                    }

                    if ( $write_ok === false ) {
                        return $tag;
                    }
                } else {
                    if ( $debug_mode ) {
                        $GLOBALS['uwb_debug_log'][] = "JS URL $url_clean: Failed to obtain content.";
                    }
                    return $tag;
                }
            }

            $new_tag = preg_replace('/src=([\'"])(.*?)\1/i', 'src="' . esc_url( $cache_url ) . '"', $tag);
            return $new_tag;
        }, $html);
    }

    /**
     * Resolve a CSS/JS URL to its absolute local file path on the server.
     */
    private static function resolve_local_path( $url, $home_url, $home_host ) {
        $parsed = parse_url( $url );
        if ( ! empty( $parsed['host'] ) && ! empty( $home_host ) ) {
            if ( strcasecmp( $parsed['host'], $home_host ) !== 0 ) {
                return false;
            }
        }
        $path = isset( $parsed['path'] ) ? $parsed['path'] : '';
        
        // Handle subdirectory installations
        $home_path = parse_url( $home_url, PHP_URL_PATH );
        $home_path = $home_path ? rtrim( $home_path, '/' ) : '';
        
        if ( ! empty( $home_path ) && strpos( $path, $home_path ) === 0 ) {
            $path = substr( $path, strlen( $home_path ) );
        }
        
        $relative = ltrim( $path, '/' );
        if ( empty( $relative ) ) {
            return false;
        }
        return ABSPATH . $relative;
    }

    /**
     * Download CSS/JS contents via HTTP.
     */
    private static function download_url_content( $url ) {
        if ( strpos( $url, '//' ) === 0 ) {
            $is_https = ( isset( $_SERVER['HTTPS'] ) && ( $_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] == 1 ) ) ||
                        ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https' );
            $url = ( $is_https ? 'https:' : 'http:' ) . $url;
        }
        
        if ( strpos( $url, 'http://' ) !== 0 && strpos( $url, 'https://' ) !== 0 ) {
            $home_url = function_exists( 'home_url' ) ? home_url() : '';
            $url = rtrim( $home_url, '/' ) . '/' . ltrim( $url, '/' );
        }

        if ( function_exists( 'wp_remote_get' ) ) {
            $response = wp_remote_get( $url, array(
                'timeout'    => 10,
                'sslverify'  => false,
                'headers'    => array( 'Accept-Encoding' => 'identity' ),
                'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            ) );

            if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
                return wp_remote_retrieve_body( $response );
            }
        }

        if ( function_exists( 'curl_init' ) ) {
            $ch = curl_init();
            curl_setopt( $ch, CURLOPT_URL, $url );
            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
            curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
            curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
            curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false );
            curl_setopt( $ch, CURLOPT_TIMEOUT, 10 );
            curl_setopt( $ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36' );
            $content = curl_exec( $ch );
            $code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
            curl_close( $ch );
            if ( $code === 200 && $content !== false ) {
                return $content;
            }
        }

        if ( ini_get( 'allow_url_fopen' ) ) {
            $context = stream_context_create( array(
                'http' => array(
                    'timeout' => 10,
                    'header'  => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n"
                ),
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                )
            ) );
            $content = @file_get_contents( $url, false, $context );
            if ( $content !== false ) {
                return $content;
            }
        }

        return false;
    }

    /**
     * Rewrite relative URLs in CSS content to absolute URLs.
     */
    private static function rewrite_css_urls( $css_content, $css_url ) {
        // Resolve relative @import "path" (without url())
        $css_content = preg_replace_callback('/@import\s+([\'"])(.*?)\1\s*;/', function( $matches ) use ( $css_url ) {
            $url = $matches[2];
            if ( empty( $url ) || strpos( $url, 'http://' ) === 0 || strpos( $url, 'https://' ) === 0 || strpos( $url, '//' ) === 0 ) {
                return $matches[0];
            }
            $absolute_url = self::resolve_relative_url( $url, $css_url );
            return '@import url("' . $absolute_url . '");';
        }, $css_content);

        // Resolve url(...)
        return preg_replace_callback('/url\(\s*([\'"]?)(.*?)\1\s*\)/i', function( $matches ) use ( $css_url ) {
            $url = $matches[2];
            if ( empty( $url ) || strpos( $url, 'data:' ) === 0 || strpos( $url, 'http://' ) === 0 || strpos( $url, 'https://' ) === 0 || strpos( $url, '//' ) === 0 || strpos( $url, '#' ) === 0 ) {
                return $matches[0];
            }
            $absolute_url = self::resolve_relative_url( $url, $css_url );
            return 'url("' . $absolute_url . '")';
        }, $css_content);
    }

    /**
     * Resolve a relative URL based on a base URL.
     */
    private static function resolve_relative_url( $relative, $base ) {
        if ( strpos( $relative, '/' ) === 0 ) {
            $parsed_base = parse_url( $base );
            $scheme = isset( $parsed_base['scheme'] ) ? $parsed_base['scheme'] . '://' : '//';
            $host = isset( $parsed_base['host'] ) ? $parsed_base['host'] : '';
            $port = isset( $parsed_base['port'] ) ? ':' . $parsed_base['port'] : '';
            return $scheme . $host . $port . $relative;
        }

        $parsed_base = parse_url( $base );
        $path = isset( $parsed_base['path'] ) ? $parsed_base['path'] : '/';
        $dir = dirname( $path );
        $dir = str_replace( '\\', '/', $dir );
        $dir = rtrim( $dir, '/' ) . '/';

        $scheme = isset( $parsed_base['scheme'] ) ? $parsed_base['scheme'] . '://' : '//';
        $host = isset( $parsed_base['host'] ) ? $parsed_base['host'] : '';
        $port = isset( $parsed_base['port'] ) ? ':' . $parsed_base['port'] : '';
        
        $abs_path = $dir . $relative;
        
        $parts = explode( '/', $abs_path );
        $stack = array();
        foreach ( $parts as $part ) {
            if ( $part === '' || $part === '.' ) {
                continue;
            }
            if ( $part === '..' ) {
                array_pop( $stack );
            } else {
                $stack[] = $part;
            }
        }
        
        return $scheme . $host . $port . '/' . implode( '/', $stack );
    }

    /**
     * Safe character-by-character JS minifier that preserves strings, template literals, and regexes.
     */
    private static function minify_js_safe( $js ) {
        $len = strlen( $js );
        $out = '';
        $i = 0;
        
        $in_string = false; // false, or '"', "'", "`"
        $in_regex = false;
        $in_single_comment = false;
        $in_multi_comment = false;
        
        while ( $i < $len ) {
            $c = $js[$i];
            $next = ($i + 1 < $len) ? $js[$i + 1] : '';
            
            // 1. If inside single-line comment
            if ( $in_single_comment ) {
                if ( $c === "\n" || $c === "\r" ) {
                    $in_single_comment = false;
                    $out .= "\n";
                }
                $i++;
                continue;
            }
            
            // 2. If inside multi-line comment
            if ( $in_multi_comment ) {
                if ( $c === '*' && $next === '/' ) {
                    $in_multi_comment = false;
                    $i += 2;
                } else {
                    $i++;
                }
                continue;
            }
            
            // 3. If inside string literal
            if ( $in_string !== false ) {
                if ( $c === '\\' ) {
                    // Skip escaped character
                    $out .= $c . $next;
                    $i += 2;
                    continue;
                }
                if ( $c === $in_string ) {
                    $in_string = false;
                }
                $out .= $c;
                $i++;
                continue;
            }
            
            // 4. Check for comments start
            if ( $c === '/' && $next === '/' ) {
                $in_single_comment = true;
                $i += 2;
                continue;
            }
            if ( $c === '/' && $next === '*' ) {
                $in_multi_comment = true;
                $i += 2;
                continue;
            }
            
            // 5. Check for string literal start
            if ( $c === '"' || $c === "'" || $c === '`' ) {
                $in_string = $c;
                $out .= $c;
                $i++;
                continue;
            }
            
            // 6. Check for regular expression literal start
            if ( $c === '/' ) {
                $last_non_ws = '';
                $out_len = strlen( $out );
                for ( $k = $out_len - 1; $k >= 0; $k-- ) {
                    if ( ! ctype_space( $out[$k] ) ) {
                        $last_non_ws = $out[$k];
                        break;
                    }
                }
                
                $is_regex_start = false;
                if ( $last_non_ws === '' ) {
                    $is_regex_start = true;
                } else {
                    $operators = array( '=', ':', ',', '?', '&', '|', '^', '!', '~', '*', '+', '-', '%', '/', '<', '>', '(', '[', '{', ';' );
                    if ( in_array( $last_non_ws, $operators, true ) ) {
                        $is_regex_start = true;
                    } else {
                        $last_word = '';
                        for ( $k = $out_len - 1; $k >= 0; $k-- ) {
                            if ( preg_match( '/[a-zA-Z0-9_$]/', $out[$k] ) ) {
                                $last_word = $out[$k] . $last_word;
                            } else {
                                if ( ! empty( $last_word ) || ctype_space( $out[$k] ) ) {
                                    if ( ! empty( $last_word ) ) break;
                                }
                            }
                        }
                        $keywords = array( 'return', 'yield', 'typeof', 'delete', 'throw', 'instanceof', 'new', 'in', 'void', 'case' );
                        if ( in_array( $last_word, $keywords, true ) ) {
                            $is_regex_start = true;
                        }
                    }
                }
                
                if ( $is_regex_start ) {
                    $out .= $c;
                    $i++;
                    while ( $i < $len ) {
                        $rc = $js[$i];
                        $rnext = ($i + 1 < $len) ? $js[$i + 1] : '';
                        if ( $rc === '\\' ) {
                            $out .= $rc . $rnext;
                            $i += 2;
                            continue;
                        }
                        if ( $rc === '[' ) {
                            $out .= $rc;
                            $i++;
                            while ( $i < $len ) {
                                $bc = $js[$i];
                                $bnext = ($i + 1 < $len) ? $js[$i + 1] : '';
                                if ( $bc === '\\' ) {
                                    $out .= $bc . $bnext;
                                    $i += 2;
                                    continue;
                                }
                                $out .= $bc;
                                $i++;
                                if ( $bc === ']' ) {
                                    break;
                                }
                            }
                            continue;
                        }
                        $out .= $rc;
                        $i++;
                        if ( $rc === '/' ) {
                            break;
                        }
                    }
                    continue;
                }
            }
            
            $out .= $c;
            $i++;
        }
        
        $out = preg_replace('/[ \t]+/', ' ', $out);
        $out = preg_replace('/[\r\n]+/', "\n", $out);
        
        return trim( $out );
    }
}
