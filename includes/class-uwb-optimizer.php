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

        $debug_mode = isset( $_GET['uwb_debug'] );
        if ( $debug_mode ) {
            $GLOBALS['uwb_debug_log'] = array();
            $GLOBALS['uwb_debug_log'][] = "=== UWB OPTIMIZER DEBUG LOG ===";
            $GLOBALS['uwb_debug_log'][] = "ABSPATH: " . ABSPATH;
            $GLOBALS['uwb_debug_log'][] = "WP_CONTENT_DIR: " . (defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : 'undefined');
            $GLOBALS['uwb_debug_log'][] = "Home URL: " . (function_exists( 'home_url' ) ? home_url() : 'undefined');
            $GLOBALS['uwb_debug_log'][] = "Config: " . json_encode( $config );
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
            $js_excludes = isset( $config['tuning_js_excludes'] ) ? $config['tuning_js_excludes'] : '';
            $html = self::defer_js( $html, $js_excludes );
        }

        // 10. Minify CSS
        if ( ! empty( $config['css_minify'] ) ) {
            $html = self::minify_external_css( $html );
            $html = self::minify_inline_css( $html );
        }

        // 11. Minify JS
        if ( ! empty( $config['js_minify'] ) ) {
            $html = self::minify_external_js( $html );
            $html = self::minify_inline_js( $html );
        }

        // 12. Minify HTML markup
        if ( ! empty( $config['html_minify'] ) ) {
            $html = self::minify_html( $html );
        }

        if ( $debug_mode && ! empty( $GLOBALS['uwb_debug_log'] ) ) {
            $html .= "\n<!-- UWB DEBUG LOG:\n" . implode( "\n", $GLOBALS['uwb_debug_log'] ) . "\n-->";
        }

        return $html;
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
            
            // Remove single line comments
            $js = preg_replace('/(?<!:)\/\/.*$/m', '', $js);
            // Remove multi-line comments
            $js = preg_replace('!/\*[^*]*\*+([^/*][^*]*\*+)*/!', '', $js);
            // Collapse whitespaces
            $js = preg_replace('/\s+/', ' ', $js);
            return '<script' . $attrs . '>' . trim( $js ) . '</script>';
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
                if ( stripos( $src, $ex ) !== false ) {
                    return $matches[0];
                }
            }

            if ( preg_match('/class=([\'"])(.*?)\1/i', $attrs, $class_match) ) {
                $class = $class_match[2];
                foreach ( $class_excludes as $cx ) {
                    if ( stripos( $class, $cx ) !== false ) {
                        return $matches[0];
                    }
                }
            }

            if ( stripos( $attrs, 'data-src' ) !== false || stripos( $attrs, 'lazyload' ) !== false ) {
                return $matches[0];
            }

            // Replace src with lightweight inline SVG placeholder
            $placeholder = 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"></svg>';
            $new_attrs = preg_replace('/src=([\'"])(.*?)\1/i', 'src="' . $placeholder . '" data-src="$2"', $attrs);
            
            if ( preg_match('/srcset=/i', $new_attrs) ) {
                $new_attrs = preg_replace('/srcset=([\'"])(.*?)\1/i', 'data-srcset="$2"', $new_attrs);
            }

            if ( stripos( $new_attrs, 'loading=' ) === false ) {
                $new_attrs .= ' loading="lazy"';
            }

            return '<img ' . $new_attrs . '>';
        }, $html);

        // Inject script to load images when visible (lazyload fallback in case native loading="lazy" is not enough or data-src needs swap)
        if ( $processed !== $html ) {
            $lazy_js = "\n" . '<script id="uwb-lazy-load-js">
            document.addEventListener("DOMContentLoaded", function() {
                var lazyImages = [].slice.call(document.querySelectorAll("img[data-src]"));
                if ("IntersectionObserver" in window) {
                    let lazyImageObserver = new IntersectionObserver(function(entries, observer) {
                        entries.forEach(function(entry) {
                            if (entry.isIntersecting) {
                                let lazyImage = entry.target;
                                lazyImage.src = lazyImage.dataset.src;
                                if (lazyImage.dataset.srcset) {
                                    lazyImage.srcset = lazyImage.dataset.srcset;
                                }
                                lazyImage.removeAttribute("data-src");
                                lazyImage.removeAttribute("data-srcset");
                                lazyImageObserver.unobserve(lazyImage);
                            }
                        });
                    });
                    lazyImages.forEach(function(lazyImage) {
                        lazyImageObserver.observe(lazyImage);
                    });
                } else {
                    // Fallback
                    lazyImages.forEach(function(img) {
                        img.src = img.dataset.src;
                        if (img.dataset.srcset) img.srcset = img.dataset.srcset;
                    });
                }
            });
            </script>';
            
            // Append before body closure
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
                $new_attrs = preg_replace('/src=([\'"])(.*?)\1/i', 'src="about:blank" data-src="$2"', $attrs);
                if ( stripos( $new_attrs, 'loading=' ) === false ) {
                    $new_attrs .= ' loading="lazy"';
                }
                return '<iframe ' . $new_attrs . '>';
            }
            return $matches[0];
        }, $html);

        if ( $processed !== $html ) {
            $lazy_js = "\n" . '<script id="uwb-lazy-iframe-js">
            document.addEventListener("DOMContentLoaded", function() {
                var lazyIframes = [].slice.call(document.querySelectorAll("iframe[data-src]"));
                if ("IntersectionObserver" in window) {
                    let lazyIframeObserver = new IntersectionObserver(function(entries, observer) {
                        entries.forEach(function(entry) {
                            if (entry.isIntersecting) {
                                let iframe = entry.target;
                                iframe.src = iframe.dataset.src;
                                iframe.removeAttribute("data-src");
                                lazyIframeObserver.unobserve(iframe);
                            }
                        });
                    });
                    lazyIframes.forEach(function(iframe) {
                        lazyIframeObserver.observe(iframe);
                    });
                } else {
                    lazyIframes.forEach(function(iframe) {
                        iframe.src = iframe.dataset.src;
                    });
                }
            });
            </script>';
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
        $excludes = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", "", $excludes_str ) ) ) );
        return preg_replace_callback('/<script\s+([^>]*src=[\'"][^\'"]+[\'"][^>]*)>/i', function( $matches ) use ( $excludes ) {
            $attrs = $matches[1];
            if ( stripos( $attrs, 'defer' ) !== false || stripos( $attrs, 'async' ) !== false ) {
                return $matches[0];
            }
            foreach ( $excludes as $ex ) {
                if ( stripos( $attrs, $ex ) !== false ) {
                    return $matches[0];
                }
            }
            return '<script ' . $attrs . ' defer>';
        }, $html);
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

        return preg_replace_callback('#<link\b(?>[^>]*?)href=([\'"])(.*?)\1(?>[^>]*?)>#is', function( $matches ) use ( $cache_dir, $home_url, $home_host ) {
            $tag = $matches[0];
            $url = $matches[2];
            $url_clean = strtok( $url, '?' );

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
            $debug_mode = isset( $_GET['uwb_debug'] );

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

        return preg_replace_callback('#<script\b(?>[^>]*?)src=([\'"])(.*?)\1(?>[^>]*?)>\s*</script>#is', function( $matches ) use ( $cache_dir, $home_url, $home_host ) {
            $tag = $matches[0];
            $url = $matches[2];
            $url_clean = strtok( $url, '?' );

            if ( strtolower( substr( $url_clean, -3 ) ) !== '.js' ) {
                return $tag;
            }

            $local_path = self::resolve_local_path( $url_clean, $home_url, $home_host );
            $debug_mode = isset( $_GET['uwb_debug'] );

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
                        $content = preg_replace('/(?<!:)\/\/.*$/m', '', $content);
                        $content = preg_replace('!/\*[^*]*\*+([^/*][^*]*\*+)*/!', '', $content);
                        $content = preg_replace('/\s+/', ' ', $content);
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
}
