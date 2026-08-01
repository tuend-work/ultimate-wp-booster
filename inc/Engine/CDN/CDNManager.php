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

        // Match URLs starting with site host or relative wp-content / wp-includes
        $pattern = '/(href|src|data-src|data-srcset)=([\'"])((?:https?:\/\/' . $home_host_quoted . ')?\/(?:wp-content|wp-includes)\/[^\'"]+\.(' . $ext_pattern . ')(\?[^\'"]*)?)\2/i';

        return preg_replace_callback( $pattern, function( $matches ) use ( $home_host, $cdn_domain ) {
            $attr    = $matches[1];
            $quote   = $matches[2];
            $url     = $matches[3];
            $ext     = $matches[4];
            $query   = isset( $matches[5] ) ? $matches[5] : '';

            $path_part = parse_url( $url, PHP_URL_PATH );
            if ( empty( $path_part ) ) {
                return $matches[0];
            }

            $cdn_url = $cdn_domain . $path_part . $query;
            return $attr . '=' . $quote . esc_url( $cdn_url ) . $quote;
        }, $html );
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
}
