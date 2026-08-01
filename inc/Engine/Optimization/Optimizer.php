<?php
namespace Ultimate_WP_Booster\Engine\Optimization;

use Ultimate_WP_Booster\Engine\Optimization\Minify\CSS as CSSMinifier;
use Ultimate_WP_Booster\Engine\Optimization\Minify\JS as JSMinifier;
use Ultimate_WP_Booster\Engine\Optimization\Media\Lazyload;
use Ultimate_WP_Booster\Engine\Optimization\JS\DelayJS;
use Ultimate_WP_Booster\Engine\Optimization\JS\DeferJS;
use Ultimate_WP_Booster\Engine\Optimization\HTML\Minify as HTMLMinifier;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class Optimizer {

    public static function process( $html, $config ) {
        if ( empty( $html ) || ! is_array( $config ) ) {
            return $html;
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
            $html = Lazyload::process_images( $html, $excludes, $class_excludes );
        }

        // 7. Lazy Load Iframes
        if ( ! empty( $config['media_lazy_load_iframes'] ) ) {
            $html = Lazyload::process_iframes( $html );
        }

        // 8. Add Missing Image Sizes
        if ( ! empty( $config['media_add_missing_sizes'] ) ) {
            $html = Lazyload::add_missing_sizes( $html );
        }

        // 10. Combine or Minify CSS
        if ( ! empty( $config['css_combine'] ) ) {
            $css_excludes = isset( $config['tuning_css_excludes'] ) ? $config['tuning_css_excludes'] : '';
            $include_ext = ! empty( $config['css_combine_ext_inline'] );
            $font_display_opt = ! empty( $config['css_font_display_opt'] );
            $html = CSSMinifier::combine( $html, $css_excludes, $include_ext, $font_display_opt );
        } elseif ( ! empty( $config['css_minify'] ) ) {
            $html = CSSMinifier::minify_external( $html );
            $html = CSSMinifier::minify_inline( $html );
        }

        // 10.2. Load CSS Asynchronously
        if ( ! empty( $config['css_load_async'] ) ) {
            $html = self::make_css_async( $html );
        }

        // 10.5. Font Display Swap Optimization
        if ( ! empty( $config['css_font_display_opt'] ) ) {
            $html = self::apply_font_display_swap( $html );
        }

        // 11. Combine or Minify JS
        if ( ! empty( $config['js_combine'] ) ) {
            $js_excludes = isset( $config['tuning_js_excludes'] ) ? $config['tuning_js_excludes'] : '';
            $include_ext = ! empty( $config['js_combine_ext_inline'] );
            $html = JSMinifier::combine( $html, $js_excludes, $include_ext );
        } elseif ( ! empty( $config['js_minify'] ) ) {
            $html = JSMinifier::minify_external( $html );
            $html = JSMinifier::minify_inline( $html );
        }

        // 11.5. Defer Javascript
        if ( ! empty( $config['js_load_defer'] ) ) {
            $js_defer_excludes = isset( $config['tuning_js_defer_excludes'] ) ? $config['tuning_js_defer_excludes'] : ( isset( $config['tuning_js_excludes'] ) ? $config['tuning_js_excludes'] : '' );
            $html = DeferJS::process( $html, $js_defer_excludes );
        }

        // 12. Minify HTML markup
        if ( ! empty( $config['html_minify'] ) ) {
            $html = HTMLMinifier::process( $html );
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
            $html = DelayJS::process( $html, $excludes );
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
