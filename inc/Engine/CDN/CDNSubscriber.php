<?php
namespace Ultimate_WP_Booster\Engine\CDN;

use Ultimate_WP_Booster\EventManagement\Subscriber_Interface;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class CDNSubscriber implements Subscriber_Interface {

    public static function get_subscribed_events() {
        return array(
            'add_attachment'    => 'on_add_attachment',
            'delete_attachment' => 'on_delete_attachment',
        );
    }

    public function on_add_attachment( $attachment_id ) {
        if ( ! get_option( 'uwb_cdn_enabled', 0 ) || ! get_option( 'uwb_cdn_auto_upload', 1 ) ) {
            return;
        }

        $s3_client = CDNManager::get_s3_client();
        if ( ! $s3_client->is_configured() ) {
            return;
        }

        $file = get_attached_file( $attachment_id );
        if ( ! $file || ! file_exists( $file ) ) {
            return;
        }

        $uploads = wp_upload_dir();
        $base_dir = rtrim( str_replace( '\\', '/', $uploads['basedir'] ), '/' );
        $file_normalized = str_replace( '\\', '/', $file );

        if ( strpos( $file_normalized, $base_dir ) === 0 ) {
            $relative_path = ltrim( substr( $file_normalized, strlen( $base_dir ) ), '/' );
            $s3_key = 'wp-content/uploads/' . $relative_path;

            $cache_control = get_option( 'uwb_cdn_cache_control', 'public, max-age=31536000, immutable' );
            $s3_client->put_object( $file, $s3_key, '', $cache_control );

            // Also upload metadata thumbnails
            $meta = wp_get_attachment_metadata( $attachment_id );
            if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
                $dir = dirname( $file );
                $relative_dir = dirname( $relative_path );
                $relative_dir = ( $relative_dir === '.' ) ? '' : $relative_dir . '/';

                foreach ( $meta['sizes'] as $size => $info ) {
                    if ( ! empty( $info['file'] ) ) {
                        $thumb_file = $dir . '/' . $info['file'];
                        if ( file_exists( $thumb_file ) ) {
                            $thumb_key = 'wp-content/uploads/' . $relative_dir . $info['file'];
                            $s3_client->put_object( $thumb_file, $thumb_key, '', $cache_control );
                        }
                    }
                }
            }

            // Optional: Delete local file if configured
            if ( get_option( 'uwb_cdn_delete_local', 0 ) ) {
                @unlink( $file );
            }
        }
    }

    public function on_delete_attachment( $attachment_id ) {
        if ( ! get_option( 'uwb_cdn_enabled', 0 ) || ! get_option( 'uwb_cdn_auto_delete', 1 ) ) {
            return;
        }

        $s3_client = CDNManager::get_s3_client();
        if ( ! $s3_client->is_configured() ) {
            return;
        }

        $file = get_attached_file( $attachment_id );
        if ( ! $file ) {
            return;
        }

        $uploads = wp_upload_dir();
        $base_dir = rtrim( str_replace( '\\', '/', $uploads['basedir'] ), '/' );
        $file_normalized = str_replace( '\\', '/', $file );

        if ( strpos( $file_normalized, $base_dir ) === 0 ) {
            $relative_path = ltrim( substr( $file_normalized, strlen( $base_dir ) ), '/' );
            $s3_key = 'wp-content/uploads/' . $relative_path;

            $s3_client->delete_object( $s3_key );

            // Delete metadata thumbnails from S3/R2
            $meta = wp_get_attachment_metadata( $attachment_id );
            if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
                $relative_dir = dirname( $relative_path );
                $relative_dir = ( $relative_dir === '.' ) ? '' : $relative_dir . '/';

                foreach ( $meta['sizes'] as $size => $info ) {
                    if ( ! empty( $info['file'] ) ) {
                        $thumb_key = 'wp-content/uploads/' . $relative_dir . $info['file'];
                        $s3_client->delete_object( $thumb_key );
                    }
                }
            }
        }
    }
}
