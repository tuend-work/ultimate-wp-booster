<?php
namespace Ultimate_WP_Booster\Engine\Optimization;

use Ultimate_WP_Booster\Engine\Optimization\Minify\CSS as CSSMinifier;
use Ultimate_WP_Booster\Engine\Optimization\Minify\JS as JSMinifier;
use Ultimate_WP_Booster\Engine\Optimization\Media\Lazyload;
use Ultimate_WP_Booster\Engine\Optimization\JS\DelayJS;
use Ultimate_WP_Booster\Engine\Optimization\JS\DeferJS;
use Ultimate_WP_Booster\Engine\Optimization\HTML\Minify as HTMLMinifier;
use Ultimate_WP_Booster\Engine\Optimization\HTML\LazyElements;
use Ultimate_WP_Booster\Engine\CDN\CDNManager;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class Optimizer {

    public static function process( $html, $config ) {
        if ( empty( $html ) || ! is_array( $config ) ) {
            return $html;
        }

        $debug_enabled = ! empty( $config['debug_mode'] ) || ! empty( $config['optimizer_debug_log'] ) || ( defined( 'WP_DEBUG' ) && WP_DEBUG );
        $debug_logs = $debug_enabled ? array() : null;

        if ( $debug_enabled ) {
            $debug_logs[] = "Optimization Engine Started (Config version: " . ( defined( 'UWB_VERSION' ) ? UWB_VERSION : '2.2.3' ) . ")";
        }

        // 1. Critical CSS Injection
        if ( ! empty( $config['tuning_critical_css'] ) ) {
            $html = self::inject_critical_css( $html, $config['tuning_critical_css'] );
            if ( $debug_enabled ) $debug_logs[] = "Critical CSS: Injected inline critical stylesheet";
        } elseif ( $debug_enabled ) {
            $debug_logs[] = "Critical CSS: Disabled (no Critical CSS defined)";
        }

        // 2. Remove Google Fonts
        if ( ! empty( $config['html_remove_gfonts'] ) ) {
            $html = self::remove_google_fonts( $html );
            if ( $debug_enabled ) $debug_logs[] = "Google Fonts Removal: Applied";
        } elseif ( $debug_enabled ) {
            $debug_logs[] = "Google Fonts Removal: Disabled in settings";
        }

        // 3. Remove WordPress Emoji
        if ( ! empty( $config['html_remove_emoji'] ) ) {
            $html = self::remove_emoji( $html );
            if ( $debug_enabled ) $debug_logs[] = "Emoji Removal: Applied";
        } elseif ( $debug_enabled ) {
            $debug_logs[] = "Emoji Removal: Disabled in settings";
        }

        // 4. Remove Noscript Tags
        if ( ! empty( $config['html_remove_noscript'] ) ) {
            $html = self::remove_noscript( $html );
            if ( $debug_enabled ) $debug_logs[] = "Noscript Removal: Applied";
        } elseif ( $debug_enabled ) {
            $debug_logs[] = "Noscript Removal: Disabled in settings";
        }

        // 5. Remove Query Strings
        if ( ! empty( $config['html_remove_qs'] ) ) {
            $html = self::remove_query_strings( $html );
            if ( $debug_enabled ) $debug_logs[] = "Query Strings Removal: Applied";
        } elseif ( $debug_enabled ) {
            $debug_logs[] = "Query Strings Removal: Disabled in settings";
        }

        // 5.5. Lazy Load HTML Elements
        if ( ! empty( $config['html_lazy_load_elements_enabled'] ) && ! empty( $config['html_lazy_load_elements'] ) ) {
            $html = LazyElements::process( $html, $config['html_lazy_load_elements'], $debug_logs );
        } elseif ( $debug_enabled ) {
            $debug_logs[] = "Lazy Load Elements: Disabled in settings";
        }

        // 6. Lazy Load Images
        if ( ! empty( $config['media_lazy_load_images'] ) ) {
            $excludes = isset( $config['media_lazy_load_excludes'] ) ? $config['media_lazy_load_excludes'] : '';
            $class_excludes = isset( $config['media_lazy_load_class_excludes'] ) ? $config['media_lazy_load_class_excludes'] : '';
            $html = Lazyload::process_images( $html, $excludes, $class_excludes, $debug_logs );
        } elseif ( $debug_enabled ) {
            $debug_logs[] = "Lazy Load Images: Disabled in settings";
        }

        // 7. Lazy Load Iframes & HTML5 Videos
        if ( ! empty( $config['media_lazy_load_iframes'] ) ) {
            $html = Lazyload::process_iframes( $html, $debug_logs );
            $html = Lazyload::process_videos( $html, $debug_logs );
        } elseif ( $debug_enabled ) {
            $debug_logs[] = "Lazy Load Iframes & Videos: Disabled in settings";
        }

        // 8. Add Missing Image Sizes
        if ( ! empty( $config['media_add_missing_sizes'] ) ) {
            $html = Lazyload::add_missing_sizes( $html );
            if ( $debug_enabled ) $debug_logs[] = "Add Missing Image Sizes: Applied";
        } elseif ( $debug_enabled ) {
            $debug_logs[] = "Add Missing Image Sizes: Disabled in settings";
        }

        // 10. Combine or Minify CSS
        if ( ! empty( $config['css_combine'] ) ) {
            $css_excludes = isset( $config['tuning_css_excludes'] ) ? $config['tuning_css_excludes'] : '';
            $include_ext = ! empty( $config['css_combine_ext_inline'] );
            $font_display_opt = ! empty( $config['css_font_display_opt'] );
            $html = CSSMinifier::combine( $html, $css_excludes, $include_ext, $font_display_opt, $debug_logs );
        } elseif ( ! empty( $config['css_minify'] ) ) {
            $html = CSSMinifier::minify_external( $html, $debug_logs );
            $html = CSSMinifier::minify_inline( $html );
        } elseif ( $debug_enabled ) {
            $debug_logs[] = "CSS Optimization (Combine & Minify): Disabled in settings";
        }

        // 10.2. Load CSS Asynchronously
        if ( ! empty( $config['css_load_async'] ) ) {
            $html = self::make_css_async( $html );
            if ( $debug_enabled ) $debug_logs[] = "Async CSS Loading: Applied";
        } elseif ( $debug_enabled ) {
            $debug_logs[] = "Async CSS Loading: Disabled in settings";
        }

        // 10.5. Font Display Swap Optimization
        if ( ! empty( $config['css_font_display_opt'] ) ) {
            $html = self::apply_font_display_swap( $html );
            if ( $debug_enabled ) $debug_logs[] = "Font Display Swap: Applied";
        } elseif ( $debug_enabled ) {
            $debug_logs[] = "Font Display Swap: Disabled in settings";
        }

        // 11. Combine or Minify JS
        if ( ! empty( $config['js_combine'] ) ) {
            $js_excludes = isset( $config['tuning_js_excludes'] ) ? $config['tuning_js_excludes'] : '';
            $include_ext = ! empty( $config['js_combine_ext_inline'] );
            $html = JSMinifier::combine( $html, $js_excludes, $include_ext, $debug_logs );
        } elseif ( ! empty( $config['js_minify'] ) ) {
            $html = JSMinifier::minify_external( $html, $debug_logs );
            $html = JSMinifier::minify_inline( $html );
        } elseif ( $debug_enabled ) {
            $debug_logs[] = "JS Optimization (Combine & Minify): Disabled in settings";
        }

        // 11.5. Defer Javascript
        if ( ! empty( $config['js_load_defer'] ) ) {
            $js_defer_excludes = isset( $config['tuning_js_defer_excludes'] ) ? $config['tuning_js_defer_excludes'] : ( isset( $config['tuning_js_excludes'] ) ? $config['tuning_js_excludes'] : '' );
            $html = DeferJS::process( $html, $js_defer_excludes, $debug_logs );
        } elseif ( $debug_enabled ) {
            $debug_logs[] = "Defer JS: Disabled in settings";
        }

        // 13. Preconnect External Domains
        if ( ! empty( $config['preconnect_domains'] ) ) {
            $html = self::inject_preconnect( $html, $config['preconnect_domains'] );
            if ( $debug_enabled ) $debug_logs[] = "Preconnect Domains: Injected";
        }

        // 14. Preload Key Fonts
        if ( ! empty( $config['preload_fonts'] ) ) {
            $html = self::inject_preload_fonts( $html, $config['preload_fonts'] );
            if ( $debug_enabled ) $debug_logs[] = "Preload Fonts: Injected";
        }

        // 15. Delay JS Execution
        if ( ! empty( $config['delay_js'] ) ) {
            $excludes = isset( $config['delay_js_exclusions'] ) ? $config['delay_js_exclusions'] : '';
            $html = DelayJS::process( $html, $excludes );
            if ( $debug_enabled ) $debug_logs[] = "Delay JS Execution: Applied";
        } elseif ( $debug_enabled ) {
            $debug_logs[] = "Delay JS Execution: Disabled in settings";
        }

        // 16. CDN Static Assets URL Rewriter
        if ( ! empty( $config['cdn_enabled'] ) ) {
            $html = CDNManager::process_html( $html, $config );
            if ( $debug_enabled ) $debug_logs[] = "CDN URL Rewriter: Applied";
        } elseif ( $debug_enabled ) {
            $debug_logs[] = "CDN URL Rewriter: Disabled in settings";
        }

        // 12. Minify HTML markup
        if ( ! empty( $config['html_minify'] ) ) {
            $html = HTMLMinifier::process( $html );
            if ( $debug_enabled ) $debug_logs[] = "HTML Minification: Applied";
        } elseif ( $debug_enabled ) {
            $debug_logs[] = "HTML Minification: Disabled in settings";
        }

        // Append Debug Log Comment if enabled
        if ( $debug_enabled && ! empty( $debug_logs ) ) {
            $log_lines = implode( "\n", array_map( function( $line ) {
                return " - " . $line;
            }, $debug_logs ) );

            $debug_block = "\n<!--\n" .
                           "===================================================================\n" .
                           " [ULTIMATE WP BOOSTER OPTIMIZER DEBUG LOG]\n" .
                           "-------------------------------------------------------------------\n" .
                           $log_lines . "\n" .
                           "===================================================================\n" .
                           "-->\n";

            if ( stripos( $html, '</html>' ) !== false ) {
                $html = str_ireplace( '</html>', $debug_block . '</html>', $html );
            } else {
                $html .= $debug_block;
            }
        }

        return $html;
    }

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

    public static function remove_query_strings( $html ) {
        return preg_replace_callback('/(src|href)=([\'"])(.*?)\.(css|js)\?(.*?)\2/i', function( $matches ) {
            return $matches[1] . '=' . $matches[2] . $matches[3] . '.' . $matches[4] . $matches[2];
        }, $html);
    }

    public static function remove_google_fonts( $html ) {
        $html = preg_replace('/<link[^>]+href=[\'"][^\'"]*fonts\.(googleapis|gstatic)\.com[^\'"]*[\'"][^>]*>/i', '', $html);
        $html = preg_replace('/@import\s+url\([\'"][^\'"]*fonts\.(googleapis|gstatic)\.com[^\'"]*[\'"]\);/i', '', $html);
        return $html;
    }

    public static function remove_emoji( $html ) {
        $html = preg_replace('#<script\b[^>]*>[^<]*?window\._wpemojiSettings[^<]*?<\/script>#is', '', $html);
        $html = preg_replace('/<style[^>]*>[^<]*img\.wp-smiley[^<]*<\/style>/is', '', $html);
        return $html;
    }

    public static function remove_noscript( $html ) {
        return preg_replace('#<noscript\b[^>]*>(?>[^<]++|<(?!/noscript>))*?</noscript>#is', '', $html);
    }

    public static function make_css_async( $html ) {
        return preg_replace_callback(
            '#<link\b([^>]*?)href=([\'"])(.*?)\2([^>]*?)>#is',
            function( $m ) {
                $tag = $m[0];
                $url = $m[3];

                if ( stripos( $tag, 'rel=' ) === false || stripos( $tag, 'stylesheet' ) === false ) {
                    return $tag;
                }

                if ( stripos( $tag, 'media="print"' ) !== false || stripos( $tag, "media='print'" ) !== false || stripos( $tag, 'onload=' ) !== false ) {
                    return $tag;
                }

                $async_tag = preg_replace( '/\s*media=([\'"])[^\'"]*\1/i', '', $tag );
                $async_tag = preg_replace( '/\s*\/?>\s*$/', ' media="print" onload="this.media=\'all\'">', $async_tag );

                return '<link rel="preload" as="style" href="' . esc_attr( $url ) . '">' . "\n" .
                       $async_tag . "\n" .
                       '<noscript>' . $tag . '</noscript>';
            },
            $html
        );
    }

    public static function apply_font_display_swap( $html ) {
        $html = preg_replace_callback(
            '#(<style\b[^>]*?>)(.*?)(</style>)#is',
            function( $m ) {
                $css = CSSMinifier::add_font_display_to_css( $m[2] );
                return $m[1] . $css . $m[3];
            },
            $html
        );

        $html = preg_replace_callback(
            '#<link\b[^>]*?href=[\'"]([^\'"]*fonts\.googleapis\.com[^\'"]*)[\'"][^>]*?>#is',
            function( $m ) {
                $url = $m[1];
                if ( stripos( $url, 'display=' ) === false ) {
                    $sep = ( strpos( $url, '?' ) !== false ) ? '&' : '?';
                    $new_url = $url . $sep . 'display=swap';
                    return str_replace( $url, $new_url, $m[0] );
                }
                return $m[0];
            },
            $html
        );

        return $html;
    }

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

        if ( preg_match( '/<head[^>]*>/i', $html, $matches ) ) {
            return str_replace( $matches[0], $matches[0] . $tags, $html );
        }

        return $html;
    }

    public static function inject_preload_fonts( $html, $fonts_raw ) {
        $fonts = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", "", $fonts_raw ) ) ) );
        if ( empty( $fonts ) ) {
            return $html;
        }

        $home_url = function_exists( 'home_url' ) ? home_url() : '';
        $tags = '';
        foreach ( $fonts as $font_url ) {
            if ( strpos( $font_url, 'http' ) !== 0 ) {
                $font_url = rtrim( $home_url, '/' ) . '/' . ltrim( $font_url, '/' );
            }
            $font_url = esc_url( $font_url );
            if ( empty( $font_url ) ) {
                continue;
            }
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
}
