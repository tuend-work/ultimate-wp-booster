<?php
namespace Ultimate_WP_Booster\Engine\CDN;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class CloudflareAPI {

    public static function is_configured( $zone_id = '', $token = '' ) {
        if ( empty( $zone_id ) ) {
            $zone_id = get_option( 'uwb_cf_zone_id', '' );
        }
        if ( empty( $token ) ) {
            $token = get_option( 'uwb_cf_api_token', '' );
        }
        return ! empty( $zone_id ) && ! empty( $token );
    }

    public static function test_connection( $zone_id = '', $token = '' ) {
        if ( empty( $zone_id ) ) {
            $zone_id = get_option( 'uwb_cf_zone_id', '' );
        }
        if ( empty( $token ) ) {
            $token = get_option( 'uwb_cf_api_token', '' );
        }

        if ( empty( $zone_id ) || empty( $token ) ) {
            return new \WP_Error( 'cf_missing_credentials', 'Missing Cloudflare Zone ID or API Token.' );
        }

        $url = "https://api.cloudflare.com/client/v4/zones/{$zone_id}";
        $args = array(
            'method'    => 'GET',
            'headers'   => array(
                'Authorization' => 'Bearer ' . trim( $token ),
                'Content-Type'  => 'application/json',
            ),
            'timeout'   => 12,
            'sslverify' => true,
        );

        $response = wp_remote_request( $url, $args );
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code === 200 && ! empty( $body['success'] ) ) {
            $name = isset( $body['result']['name'] ) ? $body['result']['name'] : '';
            return array(
                'success' => true,
                'name'    => $name,
            );
        }

        $err = 'Cloudflare API HTTP ' . $code;
        if ( ! empty( $body['errors'][0]['message'] ) ) {
            $err .= ': ' . $body['errors'][0]['message'];
        }
        return new \WP_Error( 'cf_test_failed', $err );
    }

    public static function purge_everything( $zone_id = '', $token = '' ) {
        if ( empty( $zone_id ) ) {
            $zone_id = get_option( 'uwb_cf_zone_id', '' );
        }
        if ( empty( $token ) ) {
            $token = get_option( 'uwb_cf_api_token', '' );
        }

        if ( empty( $zone_id ) || empty( $token ) ) {
            return new \WP_Error( 'cf_missing_credentials', 'Missing Cloudflare Zone ID or API Token.' );
        }

        $url = "https://api.cloudflare.com/client/v4/zones/{$zone_id}/purge_cache";
        $args = array(
            'method'    => 'POST',
            'headers'   => array(
                'Authorization' => 'Bearer ' . trim( $token ),
                'Content-Type'  => 'application/json',
            ),
            'body'      => wp_json_encode( array( 'purge_everything' => true ) ),
            'timeout'   => 15,
            'sslverify' => true,
        );

        $response = wp_remote_request( $url, $args );
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code === 200 && ! empty( $body['success'] ) ) {
            return true;
        }

        $err = 'Cloudflare Purge HTTP ' . $code;
        if ( ! empty( $body['errors'][0]['message'] ) ) {
            $err .= ': ' . $body['errors'][0]['message'];
        }
        return new \WP_Error( 'cf_purge_failed', $err );
    }

    public static function purge_urls( $urls = array(), $zone_id = '', $token = '' ) {
        if ( empty( $urls ) ) {
            return true;
        }

        if ( empty( $zone_id ) ) {
            $zone_id = get_option( 'uwb_cf_zone_id', '' );
        }
        if ( empty( $token ) ) {
            $token = get_option( 'uwb_cf_api_token', '' );
        }

        if ( empty( $zone_id ) || empty( $token ) ) {
            return new \WP_Error( 'cf_missing_credentials', 'Missing Cloudflare Zone ID or API Token.' );
        }

        $url = "https://api.cloudflare.com/client/v4/zones/{$zone_id}/purge_cache";
        $args = array(
            'method'    => 'POST',
            'headers'   => array(
                'Authorization' => 'Bearer ' . trim( $token ),
                'Content-Type'  => 'application/json',
            ),
            'body'      => wp_json_encode( array( 'files' => array_values( $urls ) ) ),
            'timeout'   => 15,
            'sslverify' => true,
        );

        $response = wp_remote_request( $url, $args );
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code === 200 && ! empty( $body['success'] ) ) {
            return true;
        }

        $err = 'Cloudflare Purge HTTP ' . $code;
        if ( ! empty( $body['errors'][0]['message'] ) ) {
            $err .= ': ' . $body['errors'][0]['message'];
        }
        return new \WP_Error( 'cf_purge_failed', $err );
    }
}
