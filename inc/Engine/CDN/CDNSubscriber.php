<?php
namespace Ultimate_WP_Booster\Engine\CDN;

use Ultimate_WP_Booster\EventManagement\Subscriber_Interface;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class CDNSubscriber implements Subscriber_Interface {

    public static function get_subscribed_events() {
        return array(
            'add_attachment'             => 'on_add_attachment',
            'edit_attachment'            => 'on_edit_attachment',
            'delete_attachment'          => 'on_delete_attachment',
            'wp_get_attachment_url'      => array( 'filter_attachment_url', 10, 2 ),
            'manage_media_columns'       => 'add_media_columns',
            'manage_media_custom_column' => array( 'render_media_column', 10, 2 ),
        );
    }

    public function on_add_attachment( $attachment_id ) {
        if ( ! get_option( 'uwb_cdn_enabled', 1 ) && ! get_option( 'uwb_cdn_distribute_media', 1 ) ) {
            return;
        }
        if ( ! get_option( 'uwb_cdn_auto_upload', 1 ) && ! get_option( 'uwb_cdn_auto_upload_attachment', 1 ) ) {
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
            $res = $s3_client->put_object( $file, $s3_key, '', $cache_control );

            if ( $res ) {
                CDNManager::mark_attachment_offloaded( $attachment_id, $s3_key );
            }

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

    public function on_edit_attachment( $attachment_id ) {
        if ( ! get_option( 'uwb_cdn_distribute_media', 1 ) || ! get_option( 'uwb_cdn_auto_update_attachment', 1 ) ) {
            return;
        }
        $this->on_add_attachment( $attachment_id );
    }

    public function on_delete_attachment( $attachment_id ) {
        if ( ! get_option( 'uwb_cdn_enabled', 1 ) && ! get_option( 'uwb_cdn_distribute_media', 1 ) ) {
            return;
        }
        if ( ! get_option( 'uwb_cdn_auto_delete', 1 ) && ! get_option( 'uwb_cdn_auto_delete_attachment', 1 ) ) {
            return;
        }

        $s3_client = CDNManager::get_s3_client();
        if ( ! $s3_client->is_configured() ) {
            return;
        }

        CDNManager::remove_attachment_offload_flag( $attachment_id );

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

    public function filter_attachment_url( $url, $post_id ) {
        if ( ! get_option( 'uwb_cdn_distribute_media', 1 ) || ! get_option( 'uwb_cdn_auto_rewrite_attachment_url', 1 ) ) {
            return $url;
        }

        $cdn_domain = get_option( 'uwb_cdn_custom_domain', '' );
        if ( empty( $cdn_domain ) ) {
            return $url;
        }

        $cdn_domain = rtrim( $cdn_domain, '/' );
        if ( strpos( $cdn_domain, 'http://' ) !== 0 && strpos( $cdn_domain, 'https://' ) !== 0 ) {
            $cdn_domain = 'https://' . $cdn_domain;
        }

        $uploads = wp_upload_dir();
        $base_url = rtrim( $uploads['baseurl'], '/' );

        if ( strpos( $url, $base_url ) === 0 ) {
            $rel = ltrim( substr( $url, strlen( $base_url ) ), '/' );
            return $cdn_domain . '/wp-content/uploads/' . $rel;
        }

        return $url;
    }

    public function add_media_columns( $columns ) {
        $columns['uwb_cdn'] = 'CDN Offload';
        return $columns;
    }

    public function render_media_column( $column_name, $post_id ) {
        if ( $column_name === 'uwb_cdn' ) {
            if ( CDNManager::is_attachment_offloaded( $post_id ) ) {
                echo '<span style="background:#d1fae5; color:#065f46; border:1px solid #6ee7b7; padding:3px 8px; border-radius:12px; font-size:11px; font-weight:700;">☁️ S3 CDN</span>';
            } else {
                echo '<span style="color:#94a3b8; font-size:12px;">—</span>';
            }
        }
    }
}
