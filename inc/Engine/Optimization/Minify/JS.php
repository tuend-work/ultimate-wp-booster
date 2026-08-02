<?php
namespace Ultimate_WP_Booster\Engine\Optimization\Minify;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class JS {

    public static function combine( $html, $excludes_str = '', $include_ext = true, &$logs = null ) {
        $cache_dir = WP_CONTENT_DIR . '/cache/ultimate-wp-booster/combine';
        if ( ! is_dir( $cache_dir ) ) {
            @mkdir( $cache_dir, 0755, true );
        }

        $home_url = function_exists( 'home_url' ) ? home_url() : '';
        $home_host = ! empty( $home_url ) ? parse_url( $home_url, PHP_URL_HOST ) : '';
        $excludes = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", "", $excludes_str ) ) ) );

        preg_match_all('#<script\b([^>]*?)>(.*?)</script>#is', $html, $matches, PREG_SET_ORDER);

        if ( empty( $matches ) ) {
            if ( is_array( $logs ) ) {
                $logs[] = "JS Combine: Skipped - No <script> tags found in HTML";
            }
            return $html;
        }

        $to_combine = array();
        $urls_hashes = array();

        foreach ( $matches as $m ) {
            $tag = $m[0];
            $attrs = $m[1];
            $inline_content = $m[2];

            if ( preg_match( '/\btype\s*=\s*(["\'])(.*?)\1/i', $attrs, $type_match ) ) {
                $type_value = strtolower( trim( $type_match[2] ) );
                $valid_types = array( 'text/javascript', 'application/javascript', 'application/x-javascript', 'text/ecmascript' );
                if ( ! in_array( $type_value, $valid_types ) ) {
                    if ( is_array( $logs ) ) {
                        $logs[] = "JS Combine: Skipped script tag (non-JS type '{$type_value}')";
                    }
                    continue;
                }
            }

            $is_excluded = false;
            foreach ( $excludes as $ex ) {
                if ( ! empty( $ex ) && ( stripos( $tag, $ex ) !== false || stripos( $inline_content, $ex ) !== false ) ) {
                    $is_excluded = true;
                    if ( is_array( $logs ) ) {
                        $label = preg_match( '/src=([\'"])(.*?)\1/i', $attrs, $src_m ) ? $src_m[2] : 'Inline Script';
                        $logs[] = "JS Combine: Excluded {$label} (Matched exclusion rule: '{$ex}')";
                    }
                    break;
                }
            }

            // Automatic Hardcoded Safety Exclusions for Specific Critical Inline Variables (flatsomeVars, WooCommerce params)
            if ( ! $is_excluded ) {
                if ( stripos( $tag, 'flatsomeVars' ) !== false ||
                     stripos( $inline_content, 'flatsomeVars' ) !== false ||
                     stripos( $inline_content, 'wc_add_to_cart_params' ) !== false ||
                     stripos( $inline_content, 'woocommerce_params' ) !== false ) {
                    $is_excluded = true;
                    if ( is_array( $logs ) ) {
                        $label = preg_match( '/src=([\'"])(.*?)\1/i', $attrs, $src_m ) ? $src_m[2] : 'Inline Script';
                        $logs[] = "JS Combine: Excluded {$label} (Automatic safety rule: Contains flatsomeVars or WooCommerce inline variables)";
                    }
                }
            }

            $is_special = stripos( $attrs, 'text/uwb-lazyload' ) !== false
                       || stripos( $attrs, 'type="module"' ) !== false;

            if ( $is_special || $is_excluded ) {
                if ( $is_special && is_array( $logs ) ) {
                    $logs[] = "JS Combine: Skipped script tag (Special script type: module or lazyload)";
                }
                continue;
            }

            if ( preg_match('/src=([\'"])(.*?)\1/i', $attrs, $src_match) ) {
                $url = $src_match[2];
                $url_clean = strtok( $url, '?' );
                $is_js_file = strtolower( substr( $url_clean, -3 ) ) === '.js';
                
                if ( ! $is_js_file ) {
                    continue;
                }

                $local_path = self::resolve_local_path( $url_clean, $home_url, $home_host );
                $is_webpack = self::is_webpack_bundle( $local_path, $url_clean );

                if ( $is_webpack ) {
                    if ( is_array( $logs ) ) {
                        $logs[] = "JS Combine: Excluded {$url_clean} (Webpack bundle / chunk detected)";
                    }
                    continue;
                }

                $urls_hashes[] = $url_clean . '_' . ( ($local_path && file_exists( $local_path )) ? filemtime( $local_path ) : 0 );
                $to_combine[] = array(
                    'tag'        => $tag,
                    'is_inline'  => false,
                    'url'        => $url,
                    'url_clean'  => $url_clean,
                    'local_path' => $local_path,
                );
            } else {
                $urls_hashes[] = 'inline_' . md5( $inline_content );
                $to_combine[] = array(
                    'tag'        => $tag,
                    'is_inline'  => true,
                    'content'    => $inline_content,
                    'url_clean'  => 'inline_script_' . md5( $inline_content ),
                    'local_path' => '',
                );
            }
        }

        if ( count( $to_combine ) < 1 ) {
            if ( is_array( $logs ) ) {
                $logs[] = "JS Combine: Skipped - No candidate JS scripts found to combine";
            }
            return $html;
        }

        $hash = md5( implode( '|', $urls_hashes ) );
        $cache_file = $cache_dir . '/uwb-js-' . $hash . '.js';
        $cache_url = self::safe_content_url( '/cache/ultimate-wp-booster/combine/uwb-js-' . $hash . '.js' );

        if ( ! file_exists( $cache_file ) ) {
            $combined_content = '';
            foreach ( $to_combine as $item ) {
                $content = '';
                if ( ! empty( $item['is_inline'] ) ) {
                    $content = $item['content'];
                } elseif ( $item['local_path'] && file_exists( $item['local_path'] ) ) {
                    $content = @file_get_contents( $item['local_path'] );
                } else {
                    $content = self::download_url_content( $item['url'] );
                }

                if ( ! empty( $content ) ) {
                    $content = self::minify_js_safe( $content );
                    $name_label = ! empty( $item['is_inline'] ) ? 'Inline Script' : $item['url_clean'];
                    $combined_content .= "\n;/* Combined: " . htmlspecialchars( $name_label, ENT_QUOTES, 'UTF-8' ) . " */\n" . trim( $content ) . ";";
                }
            }

            if ( ! empty( $combined_content ) ) {
                @file_put_contents( $cache_file, trim( $combined_content ) );
                \Ultimate_WP_Booster\Engine\CDN\CDNManager::upload_asset_to_cdn( $cache_file );
            }
        }

        if ( file_exists( $cache_file ) ) {
            \Ultimate_WP_Booster\Engine\CDN\CDNManager::upload_asset_to_cdn( $cache_file );
            foreach ( $to_combine as $item ) {
                $html = str_replace( $item['tag'], '', $html );
            }
            $new_tag = '<script src="' . esc_url( $cache_url ) . '"></script>';
            if ( stripos( $html, '</body>' ) !== false ) {
                $html = str_ireplace( '</body>', $new_tag . "\n" . '</body>', $html );
            } else {
                $html .= "\n" . $new_tag;
            }

            if ( is_array( $logs ) ) {
                $logs[] = "JS Combine: Applied - Combined " . count( $to_combine ) . " JS script(s) into uwb-js-{$hash}.js";
            }
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

        $html = preg_replace_callback('#<script\b[^>]*?src=([\'"])(.*?)\1[^>]*?>\s*</script>#is', function( $matches ) use ( $cache_dir, $home_url, $home_host, &$logs, &$minified_count, &$skipped_count ) {
            $tag = $matches[0];
            $url = $matches[2];
            $url_clean = strtok( $url, '?' );

            if ( strtolower( substr( $url_clean, -3 ) ) !== '.js' ) {
                return $tag;
            }

            $local_path = self::resolve_local_path( $url_clean, $home_url, $home_host );

            if ( $local_path && file_exists( $local_path ) && stripos( $url_clean, '.min.js' ) === false ) {
                $min_sibling = substr( $local_path, 0, -3 ) . '.min.js';
                if ( file_exists( $min_sibling ) ) {
                    $sibling_url = substr( $url_clean, 0, -3 ) . '.min.js';
                    \Ultimate_WP_Booster\Engine\CDN\CDNManager::upload_asset_to_cdn( $min_sibling );
                    if ( is_array( $logs ) ) {
                        $logs[] = "JS Minify: Replaced {$url_clean} with pre-minified sibling {$sibling_url}";
                    }
                    $minified_count++;
                    return str_replace( $url, $sibling_url, $tag );
                }
            }

            if ( stripos( $url_clean, '.min.js' ) !== false ) {
                if ( $local_path && file_exists( $local_path ) ) {
                    \Ultimate_WP_Booster\Engine\CDN\CDNManager::upload_asset_to_cdn( $local_path );
                }
                $skipped_count++;
                if ( is_array( $logs ) ) {
                    $logs[] = "JS Minify: Skipped {$url_clean} (File is already minified .min.js)";
                }
                return $tag;
            }

            $file_mtime = ( $local_path && file_exists( $local_path ) ) ? filemtime( $local_path ) : '';
            $hash = md5( $url_clean . '_' . $file_mtime );
            $cache_file = $cache_dir . '/' . $hash . '.js';
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
                    if ( stripos( $url_clean, '.min.js' ) === false ) {
                        $content = self::minify_js_safe( $content );
                    }

                    $write_ok = @file_put_contents( $cache_file, trim( $content ) );
                    if ( $write_ok !== false ) {
                        \Ultimate_WP_Booster\Engine\CDN\CDNManager::upload_asset_to_cdn( $cache_file );
                    } else {
                        if ( is_array( $logs ) ) {
                            $logs[] = "JS Minify: Failed to write minified file for {$url_clean}";
                        }
                        return $tag;
                    }
                } else {
                    if ( is_array( $logs ) ) {
                        $logs[] = "JS Minify: Skipped {$url_clean} (Content could not be read or fetched)";
                    }
                    return $tag;
                }
            }

            if ( file_exists( $cache_file ) ) {
                \Ultimate_WP_Booster\Engine\CDN\CDNManager::upload_asset_to_cdn( $cache_file );
            }

            $minified_count++;
            return preg_replace('/src=([\'"])(.*?)\1/i', 'src="' . esc_url( $cache_url ) . '"', $tag);
        }, $html);

        if ( is_array( $logs ) ) {
            $logs[] = "JS Minify: Applied - Minified {$minified_count} file(s), Skipped {$skipped_count} pre-minified file(s)";
        }

        return $html;
    }

    public static function minify_inline( $html ) {
        return preg_replace_callback('#<script\b([^>]*)>((?>[^<]++|<(?!/script>))*?)</script>#is', function( $matches ) {
            $attrs = $matches[1];
            $js = $matches[2];
            if ( stripos( $attrs, 'src=' ) !== false ) {
                return $matches[0];
            }
            if ( ! empty( $attrs ) && stripos( $attrs, 'type=' ) !== false && stripos( $attrs, 'javascript' ) === false && stripos( $attrs, 'module' ) === false ) {
                return $matches[0];
            }
            
            $js_len = strlen( trim( $js ) );
            if ( $js_len === 0 || $js_len > 10000 ) {
                return $matches[0];
            }
            
            $minified = self::minify_js_safe( $js );
            return '<script' . $attrs . '>' . $minified . '</script>';
        }, $html);
    }

    public static function is_webpack_bundle( $local_path, $url = '' ) {
        if ( ! empty( $url ) && stripos( $url, 'chunk.' ) !== false ) {
            return true;
        }

        if ( $local_path && stripos( $local_path, 'chunk.' ) !== false ) {
            return true;
        }

        if ( $local_path && file_exists( $local_path ) ) {
            $handle = @fopen( $local_path, 'r' );
            if ( $handle ) {
                $chunk = fread( $handle, 262144 );
                fclose( $handle );
                if ( $chunk !== false ) {
                    if ( strpos( $chunk, 'webpackChunk' ) !== false ||
                         strpos( $chunk, '__webpack_require__' ) !== false ||
                         strpos( $chunk, 'document.currentScript.src' ) !== false ) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    public static function minify_js_safe( $js ) {
        if ( class_exists( 'Ultimate_WP_Booster\Dependencies\Minify\JS' ) ) {
            try {
                $minifier = new \Ultimate_WP_Booster\Dependencies\Minify\JS( $js );
                $minified = $minifier->minify();
                if ( $minified !== false && $minified !== '' ) {
                    return $minified;
                }
            } catch ( \Throwable $e ) {
                // Return original on error
            }
        }
        return $js;
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

    private static function safe_content_url( $path = '' ) {
        if ( function_exists( 'content_url' ) ) {
            return content_url( $path );
        }
        $home_url = function_exists( 'home_url' ) ? home_url() : '';
        return rtrim( $home_url, '/' ) . '/wp-content' . $path;
    }
}
