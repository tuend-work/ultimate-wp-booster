<?php
namespace Ultimate_WP_Booster\Engine\CDN;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class S3Client {

    private $provider;
    private $access_key;
    private $secret_key;
    private $bucket;
    private $account_id;
    private $endpoint;
    private $region;

    public function __construct( $config = array() ) {
        $this->provider   = isset( $config['provider'] ) ? $config['provider'] : 'cloudflare_r2';
        $this->access_key = isset( $config['access_key'] ) ? trim( $config['access_key'] ) : '';
        $this->secret_key = isset( $config['secret_key'] ) ? trim( $config['secret_key'] ) : '';
        $this->bucket     = isset( $config['bucket'] ) ? trim( $config['bucket'] ) : '';
        $this->account_id = isset( $config['account_id'] ) ? trim( $config['account_id'] ) : '';
        $this->endpoint   = isset( $config['endpoint'] ) ? trim( $config['endpoint'] ) : '';
        $this->region     = isset( $config['region'] ) && ! empty( $config['region'] ) ? trim( $config['region'] ) : 'auto';

        if ( $this->provider === 'cloudflare_r2' ) {
            if ( ! empty( $this->account_id ) ) {
                $this->endpoint = "https://{$this->account_id}.r2.cloudflarestorage.com";
            }
            if ( empty( $this->region ) ) {
                $this->region = 'auto';
            }
        } else {
            if ( empty( $this->endpoint ) ) {
                $this->endpoint = 'https://s3.amazonaws.com';
            }
            if ( empty( $this->region ) ) {
                $this->region = 'us-east-1';
            }
        }

        $this->endpoint = rtrim( $this->endpoint, '/' );
    }

    public function is_configured() {
        return ! empty( $this->access_key ) && ! empty( $this->secret_key ) && ! empty( $this->bucket );
    }

    public function put_object( $source, $key, $content_type = '', $cache_control = 'public, max-age=31536000, immutable' ) {
        if ( ! $this->is_configured() ) {
            return new \WP_Error( 'cdn_not_configured', 'S3/R2 credentials are incomplete.' );
        }

        $body = '';
        if ( file_exists( $source ) ) {
            $body = @file_get_contents( $source );
            if ( $body === false ) {
                return new \WP_Error( 'file_read_error', "Cannot read file: {$source}" );
            }
            if ( empty( $content_type ) ) {
                $content_type = $this->get_mime_type( $source );
            }
        } else {
            $body = $source;
            if ( empty( $content_type ) ) {
                $content_type = 'application/octet-stream';
            }
        }

        $key = ltrim( $key, '/' );
        $ext = strtolower( pathinfo( $key, PATHINFO_EXTENSION ) );
        if ( empty( $content_type ) || in_array( $ext, array( 'css', 'js', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'woff', 'woff2', 'ttf', 'otf' ) ) ) {
            $content_type = $this->get_mime_type( $key );
        }

        $headers = array(
            'content-type' => $content_type,
        );

        if ( ! empty( $cache_control ) ) {
            $headers['cache-control'] = $cache_control;
        }

        return $this->request( 'PUT', "/{$this->bucket}/{$key}", array(), $headers, $body );
    }

    public function delete_object( $key ) {
        if ( ! $this->is_configured() ) {
            return new \WP_Error( 'cdn_not_configured', 'S3/R2 credentials are incomplete.' );
        }

        $key = ltrim( $key, '/' );
        return $this->request( 'DELETE', "/{$this->bucket}/{$key}" );
    }

    public function head_object( $key ) {
        if ( ! $this->is_configured() ) {
            return new \WP_Error( 'cdn_not_configured', 'S3/R2 credentials are incomplete.' );
        }

        $key = ltrim( $key, '/' );
        return $this->request( 'HEAD', "/{$this->bucket}/{$key}" );
    }

    public function test_connection( $custom_domain = '' ) {
        if ( ! $this->is_configured() ) {
            return new \WP_Error( 'cdn_not_configured', 'Missing S3/R2 access key, secret key, or bucket name.' );
        }

        $test_key = 'uwb-test-connection.txt';
        $content  = 'Ultimate WP Booster CDN Test File - Created at ' . gmdate( 'Y-m-d H:i:s UTC' );
        $put_res  = $this->put_object( $content, $test_key, 'text/plain; charset=utf-8', 'public, max-age=86400' );

        if ( is_wp_error( $put_res ) ) {
            return $put_res;
        }

        $file_url = '';
        if ( ! empty( $custom_domain ) ) {
            $custom_domain = rtrim( $custom_domain, '/' );
            if ( strpos( $custom_domain, 'http://' ) !== 0 && strpos( $custom_domain, 'https://' ) !== 0 ) {
                $custom_domain = 'https://' . $custom_domain;
            }
            $file_url = $custom_domain . '/' . $test_key;
        }

        return array(
            'test_key' => $test_key,
            'file_url' => $file_url,
        );
    }

    private function request( $method, $path, $query = array(), $headers = array(), $body = '' ) {
        $parsed_url = parse_url( $this->endpoint );
        $host = isset( $parsed_url['host'] ) ? $parsed_url['host'] : '';
        if ( ! empty( $parsed_url['port'] ) ) {
            $host .= ':' . $parsed_url['port'];
        }

        $request_url = $this->endpoint . $path;
        if ( ! empty( $query ) ) {
            $request_url .= '?' . http_build_query( $query );
        }

        $timestamp = time();
        $date_long = gmdate( 'Ymd\THis\Z', $timestamp );
        $date_short = gmdate( 'Ymd', $timestamp );

        $headers['host'] = $host;
        $headers['x-amz-date'] = $date_long;
        $headers['x-amz-content-sha256'] = hash( 'sha256', $body );

        // Build Canonical Request
        ksort( $headers );
        $canonical_headers = '';
        $signed_headers_arr = array();
        foreach ( $headers as $k => $v ) {
            $k_lower = strtolower( trim( $k ) );
            $canonical_headers .= $k_lower . ':' . trim( $v ) . "\n";
            $signed_headers_arr[] = $k_lower;
        }
        $signed_headers = implode( ';', $signed_headers_arr );

        $canonical_query = '';
        if ( ! empty( $query ) ) {
            ksort( $query );
            $q_parts = array();
            foreach ( $query as $qk => $qv ) {
                $q_parts[] = rawurlencode( $qk ) . '=' . rawurlencode( $qv );
            }
            $canonical_query = implode( '&', $q_parts );
        }

        $canonical_request = implode( "\n", array(
            strtoupper( $method ),
            $path,
            $canonical_query,
            $canonical_headers,
            $signed_headers,
            $headers['x-amz-content-sha256'],
        ) );

        // String to sign
        $credential_scope = "{$date_short}/{$this->region}/s3/aws4_request";
        $string_to_sign = implode( "\n", array(
            'AWS4-HMAC-SHA256',
            $date_long,
            $credential_scope,
            hash( 'sha256', $canonical_request ),
        ) );

        // Calculate Signature
        $k_date    = hash_hmac( 'sha256', $date_short, 'AWS4' . $this->secret_key, true );
        $k_region  = hash_hmac( 'sha256', $this->region, $k_date, true );
        $k_service = hash_hmac( 'sha256', 's3', $k_region, true );
        $k_signing = hash_hmac( 'sha256', 'aws4_request', $k_service, true );
        $signature = hash_hmac( 'sha256', $string_to_sign, $k_signing );

        $authorization = "AWS4-HMAC-SHA256 Credential={$this->access_key}/{$credential_scope}, SignedHeaders={$signed_headers}, Signature={$signature}";
        $headers['Authorization'] = $authorization;

        $wp_headers = array();
        foreach ( $headers as $hk => $hv ) {
            $hk_lower = strtolower( $hk );
            if ( $hk_lower === 'content-type' ) {
                $wp_headers['Content-Type'] = $hv;
            } elseif ( $hk_lower === 'cache-control' ) {
                $wp_headers['Cache-Control'] = $hv;
            } else {
                $wp_headers[ $hk ] = $hv;
            }
        }

        $args = array(
            'method'    => strtoupper( $method ),
            'headers'   => $wp_headers,
            'body'      => $body,
            'timeout'   => 15,
            'sslverify' => false,
        );

        $response = wp_remote_request( $request_url, $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );

        if ( $code >= 200 && $code < 300 ) {
            return true;
        }

        $res_body = wp_remote_retrieve_body( $response );
        $error_msg = "S3/R2 API HTTP {$code}";
        if ( preg_match( '/<Message>(.*?)<\/Message>/i', $res_body, $m ) ) {
            $error_msg .= ': ' . $m[1];
        }

        return new \WP_Error( "s3_api_error_{$code}", $error_msg );
    }

    private function get_mime_type( $file ) {
        $ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
        $mimes = array(
            'css'   => 'text/css; charset=utf-8',
            'js'    => 'application/javascript; charset=utf-8',
            'jpg'   => 'image/jpeg',
            'jpeg'  => 'image/jpeg',
            'png'   => 'image/png',
            'gif'   => 'image/gif',
            'webp'  => 'image/webp',
            'svg'   => 'image/svg+xml',
            'ico'   => 'image/x-icon',
            'woff'  => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf'   => 'font/ttf',
            'eot'   => 'application/vnd.ms-fontobject',
            'otf'   => 'font/otf',
            'pdf'   => 'application/pdf',
            'zip'   => 'application/zip',
            'mp4'   => 'video/mp4',
            'webm'  => 'video/webm',
            'mp3'   => 'audio/mpeg',
        );

        if ( isset( $mimes[ $ext ] ) ) {
            return $mimes[ $ext ];
        }

        if ( function_exists( 'mime_content_type' ) ) {
            $mime = @mime_content_type( $file );
            if ( $mime ) return $mime;
        }

        return 'application/octet-stream';
    }
}
