<?php
namespace Ultimate_WP_Booster\Engine\Optimization\CSS;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

/**
 * Server-Side Automatic Critical CSS Generator & Extractor for Above-The-Fold Elements
 */
class CriticalCSS {

    /**
     * Cache directory for Critical CSS files
     */
    public static function get_cache_dir() {
        return WP_CONTENT_DIR . '/cache/ultimate-wp-booster/critical-css/';
    }

    /**
     * Main entry point: Extract or retrieve cached Critical CSS for HTML response
     *
     * @param string $html
     * @param string $url
     * @return string Minified Critical CSS
     */
    public static function generate( $html, $url = '' ) {
        if ( empty( $html ) ) {
            return '';
        }

        $enabled = (int) get_option( 'uwb_auto_critical_css', 1 );
        if ( ! $enabled ) {
            return '';
        }

        if ( empty( $url ) ) {
            $url = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '/';
        }

        $url_hash = md5( strtok( $url, '?' ) );
        $cache_dir = self::get_cache_dir();
        $cache_file = $cache_dir . $url_hash . '.css';

        // 1. Return cached Critical CSS if available
        if ( file_exists( $cache_file ) && filesize( $cache_file ) > 10 ) {
            $cached_css = @file_get_contents( $cache_file );
            if ( ! empty( $cached_css ) ) {
                return $cached_css;
            }
        }

        // 2. Extract Above-The-Fold selectors from DOM
        $target_selectors = self::extract_above_the_fold_selectors( $html );
        if ( empty( $target_selectors ) ) {
            return '';
        }

        // 3. Collect CSS content from inline <style> and local <link rel="stylesheet"> files
        $raw_css = self::collect_css_content( $html );
        if ( empty( $raw_css ) ) {
            return '';
        }

        // 4. Extract matching CSS rules for Above-The-Fold elements
        $critical_css = self::extract_matching_css_rules( $raw_css, $target_selectors );
        if ( empty( $critical_css ) ) {
            return '';
        }

        // 5. Minify Critical CSS
        $minified_css = self::minify_css( $critical_css );

        // 6. Save to cache file
        if ( ! is_dir( $cache_dir ) ) {
            @wp_mkdir_p( $cache_dir );
        }
        @file_put_contents( $cache_file, $minified_css );

        return $minified_css;
    }

    /**
     * Scan Above-The-Fold DOM nodes (top ~30 elements) and extract IDs, Classes, and Tag names
     *
     * @param string $html
     * @return array
     */
    private static function extract_above_the_fold_selectors( $html ) {
        if ( ! function_exists( 'str_get_html' ) ) {
            $dep_path = defined( 'UWB_PLUGIN_DIR' ) ? UWB_PLUGIN_DIR . 'inc/Dependencies/simple_html_dom.php' : dirname( __DIR__, 3 ) . '/Dependencies/simple_html_dom.php';
            if ( file_exists( $dep_path ) ) {
                require_once $dep_path;
            }
        }

        if ( ! function_exists( 'str_get_html' ) ) {
            return array();
        }

        $dom = str_get_html( $html );
        if ( ! $dom ) {
            return array();
        }

        $classes = array( 'header', 'site-header', 'nav', 'main-menu', 'hero', 'slider', 'banner', 'section-bg', 'container', 'row', 'col' );
        $ids     = array( 'header', 'wrapper', 'main', 'content' );
        $tags    = array( 'html', 'body', 'header', 'nav', 'h1', 'h2', 'h3', 'a', 'img', 'p', 'section', 'div' );

        $nodes = $dom->find( 'header, nav, section, div, h1, h2, h3, a, img, form, input, button' );
        if ( ! empty( $nodes ) ) {
            $max_check = min( count( $nodes ), 35 );
            for ( $i = 0; $i < $max_check; $i++ ) {
                $node = $nodes[$i];
                $tag  = strtolower( (string) $node->tag );
                if ( ! empty( $tag ) ) {
                    $tags[] = $tag;
                }

                $node_id = (string) $node->getAttribute( 'id' );
                if ( ! empty( $node_id ) ) {
                    $ids[] = $node_id;
                }

                $node_class = (string) $node->getAttribute( 'class' );
                if ( ! empty( $node_class ) ) {
                    $cls_parts = preg_split( '/\s+/', trim( $node_class ) );
                    foreach ( $cls_parts as $c ) {
                        $c = trim( $c );
                        if ( ! empty( $c ) ) {
                            $classes[] = $c;
                        }
                    }
                }
            }
        }

        $dom->clear();

        return array(
            'classes' => array_unique( $classes ),
            'ids'     => array_unique( $ids ),
            'tags'    => array_unique( $tags ),
        );
    }

    /**
     * Collect raw CSS from page HTML (inline <style> and local linked CSS files)
     *
     * @param string $html
     * @return string
     */
    private static function collect_css_content( $html ) {
        $css_buffer = '';

        // 1. Collect inline <style> contents
        preg_match_all( '#<style\b[^>]*?>(.*?)</style>#is', $html, $style_matches );
        if ( ! empty( $style_matches[1] ) ) {
            foreach ( $style_matches[1] as $inline_css ) {
                if ( stripos( $inline_css, 'uwb-critical-css' ) === false ) {
                    $css_buffer .= "\n" . $inline_css;
                }
            }
        }

        // 2. Collect local <link rel="stylesheet"> CSS files
        preg_match_all( '#<link\b[^>]*?href=([\'"])(.*?)\1[^>]*?>#is', $html, $link_matches );
        if ( ! empty( $link_matches[2] ) ) {
            $home_url = function_exists( 'home_url' ) ? home_url() : '';
            foreach ( $link_matches[2] as $href ) {
                // Check if it's a CSS file
                if ( stripos( $href, '.css' ) === false && stripos( $href, 'styles' ) === false ) {
                    continue;
                }

                $path = '';
                if ( ! empty( $home_url ) && stripos( $href, $home_url ) === 0 ) {
                    $rel_path = ltrim( str_ireplace( $home_url, '', strtok( $href, '?' ) ), '/' );
                    $path = ABSPATH . $rel_path;
                } elseif ( strpos( $href, '/' ) === 0 && strpos( $href, '//' ) !== 0 ) {
                    $path = ABSPATH . ltrim( strtok( $href, '?' ), '/' );
                }

                if ( ! empty( $path ) && file_exists( $path ) && filesize( $path ) < 1048576 ) {
                    $file_css = @file_get_contents( $path );
                    if ( ! empty( $file_css ) ) {
                        $css_buffer .= "\n" . $file_css;
                    }
                }
            }
        }

        return $css_buffer;
    }

    /**
     * Extract CSS rules matching Above-The-Fold selectors
     *
     * @param string $css
     * @param array  $target
     * @return string
     */
    private static function extract_matching_css_rules( $css, $target ) {
        if ( empty( $css ) || empty( $target ) ) {
            return '';
        }

        $classes_map = array_flip( $target['classes'] );
        $ids_map     = array_flip( $target['ids'] );
        $tags_map    = array_flip( $target['tags'] );

        // Remove CSS comments
        $css = preg_replace( '!/\*.*?\*/!s', '', $css );

        // Parse CSS blocks: @media / @font-face or standard selector blocks
        $extracted = array();

        // 1. Always keep CSS Reset & Global Layout rules
        $extracted[] = '*,*::before,*::after{box-sizing:border-box}html,body{margin:0;padding:0}';

        // 2. Parse rules via regex matcher
        preg_match_all( '/([^{}@]+)\{([^{}]+)\}/i', $css, $matches, PREG_SET_ORDER );
        if ( ! empty( $matches ) ) {
            foreach ( $matches as $m ) {
                $selector_str = trim( $am_sel = $m[1] );
                $rule_body    = trim( $m[2] );

                if ( empty( $selector_str ) || empty( $rule_body ) ) {
                    continue;
                }

                // Skip @import or @charset
                if ( strpos( $selector_str, '@' ) === 0 ) {
                    if ( strpos( $selector_str, '@font-face' ) === 0 || strpos( $selector_str, '@keyframes' ) === 0 ) {
                        $extracted[] = $selector_str . '{' . $rule_body . '}';
                    }
                    continue;
                }

                // Check individual comma-separated selectors
                $selectors = explode( ',', $selector_str );
                $is_matched = false;

                foreach ( $selectors as $sel ) {
                    $sel = trim( $sel );
                    if ( empty( $sel ) ) continue;

                    // Match universal / body / html
                    if ( $sel === '*' || $sel === 'body' || $sel === 'html' || strpos( $sel, ':root' ) !== false ) {
                        $is_matched = true;
                        break;
                    }

                    // Extract classes (.class) and IDs (#id) from selector
                    preg_match_all( '/\.([a-z0-9_-]+)/i', $sel, $c_matches );
                    if ( ! empty( $c_matches[1] ) ) {
                        foreach ( $c_matches[1] as $c_name ) {
                            if ( isset( $classes_map[$c_name] ) ) {
                                $is_matched = true;
                                break 2;
                            }
                        }
                    }

                    preg_match_all( '/\#([a-z0-9_-]+)/i', $sel, $id_matches );
                    if ( ! empty( $id_matches[1] ) ) {
                        foreach ( $id_matches[1] as $id_name ) {
                            if ( isset( $ids_map[$id_name] ) ) {
                                $is_matched = true;
                                break 2;
                            }
                        }
                    }

                    // Match HTML tags (header, nav, section, img, etc.)
                    preg_match( '/^([a-z0-9]+)/i', $sel, $tag_match );
                    if ( ! empty( $tag_match[1] ) && isset( $tags_map[strtolower( $tag_match[1] )] ) ) {
                        $is_matched = true;
                        break;
                    }
                }

                if ( $is_matched ) {
                    $extracted[] = $selector_str . '{' . $rule_body . '}';
                }
            }
        }

        return implode( "\n", array_unique( $extracted ) );
    }

    /**
     * Minify Critical CSS string
     *
     * @param string $css
     * @return string
     */
    public static function minify_css( $css ) {
        if ( empty( $css ) ) {
            return '';
        }
        $css = preg_replace( '!/\*.*?\*/!s', '', $css );
        $css = preg_replace( '/\s+/', ' ', $css );
        $css = str_replace( array( " {", "{ ", " }", "} ", " :", ": ", " ;", "; ", " ,", ", " ), array( "{", "{", "}", "}", ":", ":", ";", ";", ",", "," ), $css );
        return trim( $css );
    }

    /**
     * Purge all generated Critical CSS files
     */
    public static function purge_cache() {
        $dir = self::get_cache_dir();
        if ( is_dir( $dir ) ) {
            $files = glob( $dir . '*.css' );
            if ( ! empty( $files ) ) {
                foreach ( $files as $f ) {
                    @unlink( $f );
                }
            }
        }
    }
}
