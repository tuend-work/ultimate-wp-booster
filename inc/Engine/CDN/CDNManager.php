<?php
namespace Ultimate_WP_Booster\Engine\CDN;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class CDNManager {

    public static function process_html( $html, $config = array() ) {
        if ( empty( $html ) ) {
            return $html;
        }

        $cdn_enabled = ! empty( $config['cdn_enabled'] );
        $cdn_domain  = isset( $config['cdn_custom_domain'] ) ? trim( $config['cdn_custom_domain'] ) : '';

        if ( ! $cdn_enabled || empty( $cdn_domain ) ) {
            return $html;
        }

        $cdn_domain = rtrim( $cdn_domain, '/' );
        if ( strpos( $cdn_domain, 'http://' ) !== 0 && strpos( $cdn_domain, 'https://' ) !== 0 ) {
            $cdn_domain = 'https://' . $cdn_domain;
        }

        $home_url  = function_exists( 'home_url' ) ? home_url() : '';
        $home_host = ! empty( $home_url ) ? parse_url( $home_url, PHP_URL_HOST ) : '';

        if ( empty( $home_host ) ) {
            return $html;
        }

        $allowed_exts = array();
        if ( ! empty( $config['cdn_file_types_images'] ) ) {
            $allowed_exts = array_merge( $allowed_exts, array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico' ) );
        }
        if ( ! empty( $config['cdn_file_types_css'] ) ) {
            $allowed_exts[] = 'css';
        }
        if ( ! empty( $config['cdn_file_types_js'] ) ) {
            $allowed_exts[] = 'js';
        }
        if ( ! empty( $config['cdn_file_types_fonts'] ) ) {
            $allowed_exts = array_merge( $allowed_exts, array( 'woff', 'woff2', 'ttf', 'eot', 'otf' ) );
        }
        if ( ! empty( $config['cdn_file_types_media'] ) ) {
            $allowed_exts = array_merge( $allowed_exts, array( 'mp4', 'webm', 'mp3', 'pdf', 'zip', 'rar' ) );
        }

        if ( empty( $allowed_exts ) ) {
            return $html;
        }

        $ext_pattern = implode( '|', array_map( 'preg_quote', $allowed_exts ) );
        $home_host_quoted = preg_quote( $home_host, '/' );
        $version = defined( 'UWB_VERSION' ) ? UWB_VERSION : time();

        // 1. Rewrite single URL attributes: href, src, data-src
        $pattern = '/(href|src|data-src)=([\'"])((?:https?:\/\/' . $home_host_quoted . ')?\/(?:wp-content|wp-includes)\/[^\'"]+\.(' . $ext_pattern . ')(\?[^\'"]*)?)\2/i';

        $html = preg_replace_callback( $pattern, function( $matches ) use ( $home_host, $cdn_domain, $version ) {
            $attr    = $matches[1];
            $quote   = $matches[2];
            $url     = $matches[3];
            $query   = isset( $matches[5] ) ? $matches[5] : '';

            $path_part = parse_url( $url, PHP_URL_PATH );
            if ( empty( $path_part ) ) {
                return $matches[0];
            }

            if ( empty( $query ) ) {
                $query = '?ver=' . $version;
            } else {
                if ( strpos( $query, 'ver=' ) === false && strpos( $query, 'v=' ) === false ) {
                    $query .= '&ver=' . $version;
                }
            }

            $cdn_url = $cdn_domain . $path_part . $query;
            return $attr . '=' . $quote . esc_url( $cdn_url ) . $quote;
        }, $html );

        // 2. Rewrite multi-entry attributes: srcset and data-srcset (supports descriptors like 300w, 2x)
        $srcset_pattern = '/(srcset|data-srcset)=([\'"])(.*?)\2/i';
        $html = preg_replace_callback( $srcset_pattern, function( $matches ) use ( $home_host_quoted, $cdn_domain, $version, $ext_pattern ) {
            $attr  = $matches[1];
            $quote = $matches[2];
            $val   = $matches[3];

            if ( empty( trim( $val ) ) ) {
                return $matches[0];
            }

            $entries = explode( ',', $val );
            $new_entries = array();

            foreach ( $entries as $entry ) {
                $entry_trimmed = trim( $entry );
                if ( empty( $entry_trimmed ) ) {
                    continue;
                }

                $parts = preg_split( '/\s+/', $entry_trimmed, 2 );
                $url = $parts[0];
                $descriptor = isset( $parts[1] ) ? ' ' . $parts[1] : '';

                if ( preg_match( '/^(?:https?:\/\/' . $home_host_quoted . ')?\/(?:wp-content|wp-includes)\/[^\'"]+\.(' . $ext_pattern . ')(\?[^\'"]*)?$/i', $url, $m ) ) {
                    $path_part = parse_url( $url, PHP_URL_PATH );
                    $query     = isset( $m[2] ) ? $m[2] : '';

                    if ( empty( $query ) ) {
                        $query = '?ver=' . $version;
                    } elseif ( strpos( $query, 'ver=' ) === false && strpos( $query, 'v=' ) === false ) {
                        $query .= '&ver=' . $version;
                    }

                    $url = $cdn_domain . $path_part . $query;
                }

                $new_entries[] = esc_url( $url ) . $descriptor;
            }

            return $attr . '=' . $quote . implode( ', ', $new_entries ) . $quote;
        }, $html );

        return $html;
    }

    public static function get_s3_client( $config = array() ) {
        if ( empty( $config ) ) {
            $config = array(
                'provider'   => get_option( 'uwb_cdn_provider', 'cloudflare_r2' ),
                'access_key' => get_option( 'uwb_cdn_access_key', '' ),
                'secret_key' => get_option( 'uwb_cdn_secret_key', '' ),
                'bucket'     => get_option( 'uwb_cdn_bucket', '' ),
                'account_id' => get_option( 'uwb_cdn_account_id', '' ),
                'endpoint'   => get_option( 'uwb_cdn_endpoint', '' ),
                'region'     => get_option( 'uwb_cdn_region', 'auto' ),
            );
        }

        return new S3Client( $config );
    }

    public static function upload_asset_to_cdn( $file_path ) {
        if ( ! get_option( 'uwb_cdn_enabled', 0 ) || ! get_option( 'uwb_cdn_auto_upload_combined', 1 ) ) {
            return false;
        }

        if ( empty( $file_path ) || ! file_exists( $file_path ) ) {
            return false;
        }

        $s3_client = self::get_s3_client();
        if ( ! $s3_client->is_configured() ) {
            return false;
        }

        $file_norm = str_replace( '\\', '/', $file_path );
        $content_dir = str_replace( '\\', '/', WP_CONTENT_DIR );

        if ( strpos( $file_norm, $content_dir ) === 0 ) {
            $rel = ltrim( substr( $file_norm, strlen( $content_dir ) ), '/' );
            $s3_key = 'wp-content/' . $rel;

            $cache_control = get_option( 'uwb_cdn_cache_control', 'public, max-age=31536000, immutable' );
            return $s3_client->put_object( $file_path, $s3_key, '', $cache_control );
        }

        return false;
    }

    public static function purge_cache_files_from_cdn() {
        $s3_client = self::get_s3_client();
        if ( ! $s3_client->is_configured() ) {
            return false;
        }

        $content_dir = str_replace( '\\', '/', WP_CONTENT_DIR );

        $dirs = array(
            WP_CONTENT_DIR . '/cache/ultimate-wp-booster/minify',
            WP_CONTENT_DIR . '/cache/ultimate-wp-booster/combine',
        );

        foreach ( $dirs as $dir ) {
            if ( is_dir( $dir ) ) {
                $files = glob( rtrim( $dir, '/' ) . '/*.*' );
                if ( is_array( $files ) ) {
                    foreach ( $files as $file ) {
                        $file_norm = str_replace( '\\', '/', $file );
                        if ( strpos( $file_norm, $content_dir ) === 0 ) {
                            $rel = ltrim( substr( $file_norm, strlen( $content_dir ) ), '/' );
                            $s3_key = 'wp-content/' . $rel;
                            $s3_client->delete_object( $s3_key );
                        }
                    }
                }
            }
        }

        return true;
    }

    public static function is_attachment_offloaded( $attachment_id ) {
        return (bool) get_post_meta( $attachment_id, '_uwb_s3_uploaded', true );
    }

    public static function mark_attachment_offloaded( $attachment_id, $s3_key = '' ) {
        update_post_meta( $attachment_id, '_uwb_s3_uploaded', time() );
        if ( ! empty( $s3_key ) ) {
            update_post_meta( $attachment_id, '_uwb_s3_key', sanitize_text_field( $s3_key ) );
        }
    }

    public static function remove_attachment_offload_flag( $attachment_id ) {
        delete_post_meta( $attachment_id, '_uwb_s3_uploaded' );
        delete_post_meta( $attachment_id, '_uwb_s3_key' );
    }
}
