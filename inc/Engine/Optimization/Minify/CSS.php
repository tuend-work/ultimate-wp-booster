<?php
namespace Ultimate_WP_Booster\Engine\Optimization\Minify;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class CSS {

    public static function combine( $html, $excludes_str = '', $include_ext = false, $font_display_opt = true, &$logs = null ) {
        $cache_dir = WP_CONTENT_DIR . '/cache/ultimate-wp-booster/combine';
        if ( ! is_dir( $cache_dir ) ) {
            @mkdir( $cache_dir, 0755, true );
        }

        $home_url = function_exists( 'home_url' ) ? home_url() : '';
        $home_host = ! empty( $home_url ) ? parse_url( $home_url, PHP_URL_HOST ) : '';
        $excludes = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", "", $excludes_str ) ) ) );

        preg_match_all('#<link\b[^>]*?href=([\'"])(.*?)\1[^>]*?>#is', $html, $matches, PREG_SET_ORDER);

        if ( empty( $matches ) ) {
            if ( is_array( $logs ) ) {
                $logs[] = "CSS Combine: Skipped - No <link rel=\"stylesheet\"> tags found in HTML";
            }
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

            if ( stripos( $tag, 'uwb-critical-css' ) !== false || stripos( $tag, 'media="print"' ) !== false || stripos( $tag, "media='print'" ) !== false ) {
                if ( is_array( $logs ) ) {
                    $logs[] = "CSS Combine: Skipped {$url_clean} (Critical CSS or print media stylesheet)";
                }
                continue;
            }

            $is_excluded = false;
            foreach ( $excludes as $ex ) {
                if ( ! empty( $ex ) && ( stripos( $url, $ex ) !== false || stripos( $tag, $ex ) !== false ) ) {
                    $is_excluded = true;
                    if ( is_array( $logs ) ) {
                        $logs[] = "CSS Combine: Excluded {$url_clean} (Matched exclusion rule: '{$ex}')";
                    }
                    break;
                }
            }
            if ( $is_excluded ) {
                continue;
            }

            $local_path = self::resolve_local_path( $url_clean, $home_url, $home_host );
            if ( ! $local_path && ! $include_ext ) {
                if ( is_array( $logs ) ) {
                    $logs[] = "CSS Combine: Skipped {$url_clean} (External CSS file and css_combine_ext_inline is disabled)";
                }
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
            if ( is_array( $logs ) ) {
                $logs[] = "CSS Combine: Skipped - Only " . count( $to_combine ) . " CSS file(s) candidate found (requires at least 2 files to combine)";
            }
            return $html;
        }

        $hash = md5( implode( '|', $urls_hashes ) );
        $cache_file = $cache_dir . '/uwb-combined-' . $hash . '.css';
        $cache_url = self::safe_content_url( '/cache/ultimate-wp-booster/combine/uwb-combined-' . $hash . '.css' );

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
                    $content = preg_replace('!/\*[^*]*\*+([^/*][^*]*\*+)*/!', '', $content);
                    $content = preg_replace('/\s*([{}|;:,])\s*/', '$1', $content);
                    $content = preg_replace('/\s+/', ' ', $content);
                    $combined_content .= "\n/* Combined: " . htmlspecialchars( $item['url_clean'], ENT_QUOTES, 'UTF-8' ) . " */\n" . trim( $content );
                }
            }

            if ( empty( $combined_content ) ) {
                if ( is_array( $logs ) ) {
                    $logs[] = "CSS Combine: Failed - Combined content was empty after reading source files";
                }
                return $html;
            }

            if ( $font_display_opt ) {
                $combined_content = self::add_font_display_to_css( $combined_content );
            }

            @file_put_contents( $cache_file, trim( $combined_content ) );
            \Ultimate_WP_Booster\Engine\CDN\CDNManager::upload_asset_to_cdn( $cache_file );
        } else {
            \Ultimate_WP_Booster\Engine\CDN\CDNManager::upload_asset_to_cdn( $cache_file );
        }

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

        if ( is_array( $logs ) ) {
            $logs[] = "CSS Combine: Applied - Combined " . count( $to_combine ) . " CSS files into uwb-combined-{$hash}.css";
        }

        return $html;
    }

    public static function minify_external( $html, &$logs = null ) {
        $cache_dir = WP_CONTENT_DIR . '/cache/ultimate-wp-booster/minify';
        if ( ! is_dir( $cache_dir ) ) {
            @mkdir( $cache_dir, 0755, true );
        }

        $home_url = function_exists( 'home_url' ) ? home_url() : '';
        $home_host = ! empty( $home_url ) ? parse_url( $home_url, PHP_URL_HOST ) : '';
        $minified_count = 0;
        $skipped_count = 0;

        $html = preg_replace_callback('#<link\b[^>]*?href=([\'"])(.*?)\1[^>]*?>#is', function( $matches ) use ( $cache_dir, $home_url, $home_host, &$logs, &$minified_count, &$skipped_count ) {
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

            if ( $local_path && file_exists( $local_path ) && stripos( $url_clean, '.min.css' ) === false ) {
                $min_sibling = substr( $local_path, 0, -4 ) . '.min.css';
                if ( file_exists( $min_sibling ) ) {
                    $sibling_url = substr( $url_clean, 0, -4 ) . '.min.css';
                    if ( is_array( $logs ) ) {
                        $logs[] = "CSS Minify: Replaced {$url_clean} with pre-minified sibling {$sibling_url}";
                    }
                    $minified_count++;
                    return str_replace( $url, $sibling_url, $tag );
                }
            }

            if ( stripos( $url_clean, '.min.css' ) !== false ) {
                $skipped_count++;
                if ( is_array( $logs ) ) {
                    $logs[] = "CSS Minify: Skipped {$url_clean} (File is already minified .min.css)";
                }
                return $tag;
            }

            $file_mtime = ( $local_path && file_exists( $local_path ) ) ? filemtime( $local_path ) : '';
            $hash = md5( $url_clean . '_' . $file_mtime );
            $cache_file = $cache_dir . '/' . $hash . '.css';
            $cache_url = self::safe_content_url( '/cache/ultimate-wp-booster/minify/' . $hash . '.css' );

            if ( ! file_exists( $cache_file ) ) {
                $content = '';
                if ( $local_path && file_exists( $local_path ) ) {
                    $content = @file_get_contents( $local_path );
                }
                
                if ( empty( $content ) ) {
                    $content = self::download_url_content( $url );
                }

                if ( ! empty( $content ) ) {
                    $content = self::rewrite_css_urls( $content, $url );

                    if ( stripos( $url_clean, '.min.css' ) === false ) {
                        $content = preg_replace('!/\*[^*]*\*+([^/*][^*]*\*+)*/!', '', $content);
                        $content = preg_replace('/\s*([{}|;:,])\s*/', '$1', $content);
                        $content = preg_replace('/\s+/', ' ', $content);
                    }

                    $write_ok = @file_put_contents( $cache_file, trim( $content ) );
                    if ( $write_ok !== false ) {
                        \Ultimate_WP_Booster\Engine\CDN\CDNManager::upload_asset_to_cdn( $cache_file );
                    } else {
                        if ( is_array( $logs ) ) {
                            $logs[] = "CSS Minify: Failed to write minified file for {$url_clean}";
                        }
                        return $tag;
                    }
                } else {
                    if ( is_array( $logs ) ) {
                        $logs[] = "CSS Minify: Skipped {$url_clean} (Content could not be read or fetched)";
                    }
                    return $tag;
                }
            }

            $minified_count++;
            return preg_replace('/href=([\'"])(.*?)\1/i', 'href="' . esc_url( $cache_url ) . '"', $tag);
        }, $html);

        if ( is_array( $logs ) ) {
            $logs[] = "CSS Minify: Applied - Minified {$minified_count} file(s), Skipped {$skipped_count} pre-minified file(s)";
        }

        return $html;
    }

    public static function minify_inline( $html ) {
        return preg_replace_callback('#<style\b([^>]*)>((?>[^<]++|<(?!/style>))*?)</style>#is', function( $matches ) {
            $attrs = $matches[1];
            $css = $matches[2];
            if ( strpos( $attrs, 'uwb-critical-css' ) !== false ) {
                return $matches[0];
            }
            $css = preg_replace('!/\*[^*]*\*+([^/*][^*]*\*+)*/!', '', $css);
            $css = preg_replace('/\s*([{}|;:,])\s*/', '$1', $css);
            $css = preg_replace('/\s+/', ' ', $css);
            return '<style' . $attrs . '>' . trim( $css ) . '</style>';
        }, $html);
    }

    public static function add_font_display_to_css( $css ) {
        if ( stripos( $css, '@font-face' ) === false ) {
            return $css;
        }

        return preg_replace_callback(
            '#@font-face\s*\{([^}]+)\}#is',
            function( $font_m ) {
                $block = $font_m[1];
                if ( stripos( $block, 'font-display' ) === false ) {
                    return '@font-face{' . trim( $block, " \t\n\r\0\x0B;" ) . ';font-display:swap;}';
                } else {
                    $block = preg_replace( '#font-display\s*:\s*[^;]+;?#i', 'font-display:swap;', $block );
                    return '@font-face{' . $block . '}';
                }
            },
            $css
        );
    }

    private static function resolve_local_path( $url, $home_url, $home_host ) {
        $parsed = parse_url( $url );
        if ( ! empty( $parsed['host'] ) && ! empty( $home_host ) ) {
            if ( strcasecmp( $parsed['host'], $home_host ) !== 0 ) {
                return false;
            }
        }
        $path = isset( $parsed['path'] ) ? $parsed['path'] : '';
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

    private static function rewrite_css_urls( $css_content, $css_url ) {
        $css_content = preg_replace_callback('/@import\s+([\'"])(.*?)\1\s*;/', function( $matches ) use ( $css_url ) {
            $url = $matches[2];
            if ( empty( $url ) || strpos( $url, 'http://' ) === 0 || strpos( $url, 'https://' ) === 0 || strpos( $url, '//' ) === 0 ) {
                return $matches[0];
            }
            $absolute_url = self::resolve_relative_url( $url, $css_url );
            return '@import url("' . $absolute_url . '");';
        }, $css_content);

        return preg_replace_callback('/url\(\s*([\'"]?)(.*?)\1\s*\)/i', function( $matches ) use ( $css_url ) {
            $url = $matches[2];
            if ( empty( $url ) || strpos( $url, 'data:' ) === 0 || strpos( $url, 'http://' ) === 0 || strpos( $url, 'https://' ) === 0 || strpos( $url, '//' ) === 0 || strpos( $url, '#' ) === 0 ) {
                return $matches[0];
            }
            $absolute_url = self::resolve_relative_url( $url, $css_url );
            return 'url("' . $absolute_url . '")';
        }, $css_content);
    }

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

    private static function safe_content_url( $path = '' ) {
        if ( function_exists( 'content_url' ) ) {
            return content_url( $path );
        }
        $home_url = function_exists( 'home_url' ) ? home_url() : '';
        return rtrim( $home_url, '/' ) . '/wp-content' . $path;
    }
}
