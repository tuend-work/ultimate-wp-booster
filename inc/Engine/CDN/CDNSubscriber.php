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

    // -------------------------------------------------------------------------
    // Shared helper: upload a single attachment (+ thumbnails) to S3 if needed.
    // $force = true  → always upload (e.g. edit_attachment: file has changed)
    // $force = false → skip if already offloaded flag is set
    // -------------------------------------------------------------------------
    private function upload_attachment_to_s3( $attachment_id, $force = false ) {
        if ( ! get_option( 'uwb_cdn_distribute_media', 0 ) ) {
            return false;
        }

        $s3_client = CDNManager::get_s3_client();
        if ( ! $s3_client->is_configured() ) {
            return false;
        }

        // Already on S3 and not forced → skip
        if ( ! $force && CDNManager::is_attachment_offloaded( $attachment_id ) ) {
            return true;
        }

        $file = get_attached_file( $attachment_id );
        if ( ! $file || ! file_exists( $file ) ) {
            return false;
        }

        $uploads        = wp_upload_dir();
        $base_dir       = rtrim( str_replace( '\\', '/', $uploads['basedir'] ), '/' );
        $file_norm      = str_replace( '\\', '/', $file );

        if ( strpos( $file_norm, $base_dir ) !== 0 ) {
            return false;
        }

        $relative_path  = ltrim( substr( $file_norm, strlen( $base_dir ) ), '/' );
        $s3_key         = 'wp-content/uploads/' . $relative_path;
        $cache_control  = get_option( 'uwb_cdn_cache_control', 'public, max-age=31536000, immutable' );

        // Upload main file
        $res = $s3_client->put_object( $file, $s3_key, '', $cache_control );
        if ( $res ) {
            CDNManager::mark_attachment_offloaded( $attachment_id, $s3_key );
        }

        // Upload thumbnails
        $meta = wp_get_attachment_metadata( $attachment_id );
        if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
            $dir         = dirname( $file );
            $relative_dir = dirname( $relative_path );
            $relative_dir = ( $relative_dir === '.' ) ? '' : $relative_dir . '/';

            foreach ( $meta['sizes'] as $info ) {
                if ( ! empty( $info['file'] ) ) {
                    $thumb_file = $dir . '/' . $info['file'];
                    if ( file_exists( $thumb_file ) ) {
                        $thumb_key = 'wp-content/uploads/' . $relative_dir . $info['file'];
                        $s3_client->put_object( $thumb_file, $thumb_key, '', $cache_control );
                    }
                }
            }
        }

        // Optional: Delete local file after successful offload
        if ( $res && get_option( 'uwb_cdn_delete_local', 0 ) ) {
            @unlink( $file );
        }

        return (bool) $res;
    }

    // -------------------------------------------------------------------------
    // EVENT 1: New upload  (add_attachment hook)
    // Checkbox: uwb_cdn_auto_upload_attachment
    // Behaviour: upload only if NOT already on S3 (first-time offload)
    // -------------------------------------------------------------------------
    public function on_add_attachment( $attachment_id ) {
        if ( ! get_option( 'uwb_cdn_auto_upload_attachment', 0 ) ) {
            return;
        }
        // $force = false: skip if already uploaded
        $this->upload_attachment_to_s3( $attachment_id, false );
    }

    // -------------------------------------------------------------------------
    // EVENT 2: Attachment updated  (edit_attachment hook)
    // Checkbox: uwb_cdn_auto_update_attachment
    // Behaviour: always re-upload (file may have changed)
    // -------------------------------------------------------------------------
    public function on_edit_attachment( $attachment_id ) {
        if ( ! get_option( 'uwb_cdn_auto_update_attachment', 0 ) ) {
            return;
        }
        // $force = true: file was edited → always re-upload even if flag exists
        $this->upload_attachment_to_s3( $attachment_id, true );
    }

    // -------------------------------------------------------------------------
    // EVENT 3: Get attachment URL  (wp_get_attachment_url filter)
    // Checkbox: uwb_cdn_auto_rewrite_attachment_url
    // Behaviour A: if already on S3 → rewrite URL to CDN domain
    // Behaviour B: if NOT on S3 yet → upload first, then rewrite URL
    // -------------------------------------------------------------------------
    public function filter_attachment_url( $url, $post_id ) {
        if ( ! get_option( 'uwb_cdn_distribute_media', 0 ) ) {
            return $url;
        }
        if ( ! get_option( 'uwb_cdn_auto_rewrite_attachment_url', 0 ) ) {
            return $url;
        }

        $cdn_domain = get_option( 'uwb_cdn_custom_domain', '' );
        if ( empty( $cdn_domain ) ) {
            return $url;
        }

        // If not yet on S3, upload now (lazy offload on first access)
        if ( ! CDNManager::is_attachment_offloaded( $post_id ) ) {
            $this->upload_attachment_to_s3( $post_id, false );
        }

        // Only rewrite URL if offloaded (upload may have failed)
        if ( ! CDNManager::is_attachment_offloaded( $post_id ) ) {
            return $url;
        }

        $cdn_domain = rtrim( $cdn_domain, '/' );
        if ( strpos( $cdn_domain, 'http://' ) !== 0 && strpos( $cdn_domain, 'https://' ) !== 0 ) {
            $cdn_domain = 'https://' . $cdn_domain;
        }

        $uploads  = wp_upload_dir();
        $base_url = rtrim( $uploads['baseurl'], '/' );

        if ( strpos( $url, $base_url ) === 0 ) {
            $rel = ltrim( substr( $url, strlen( $base_url ) ), '/' );
            return $cdn_domain . '/wp-content/uploads/' . $rel;
        }

        return $url;
    }

    // -------------------------------------------------------------------------
    // Delete attachment: remove from S3 + clear flag
    // -------------------------------------------------------------------------
    public function on_delete_attachment( $attachment_id ) {
        if ( ! get_option( 'uwb_cdn_distribute_media', 0 ) ) {
            return;
        }
        if ( ! get_option( 'uwb_cdn_auto_delete_attachment', 0 ) ) {
            return;
        }

        $s3_client = CDNManager::get_s3_client();
        if ( ! $s3_client->is_configured() ) {
            return;
        }

        // Clear flag first
        CDNManager::remove_attachment_offload_flag( $attachment_id );

        $file = get_attached_file( $attachment_id );
        if ( ! $file ) {
            return;
        }

        $uploads      = wp_upload_dir();
        $base_dir     = rtrim( str_replace( '\\', '/', $uploads['basedir'] ), '/' );
        $file_norm    = str_replace( '\\', '/', $file );

        if ( strpos( $file_norm, $base_dir ) === 0 ) {
            $relative_path = ltrim( substr( $file_norm, strlen( $base_dir ) ), '/' );
            $s3_key        = 'wp-content/uploads/' . $relative_path;

            $s3_client->delete_object( $s3_key );

            // Delete thumbnails from S3
            $meta = wp_get_attachment_metadata( $attachment_id );
            if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
                $relative_dir = dirname( $relative_path );
                $relative_dir = ( $relative_dir === '.' ) ? '' : $relative_dir . '/';

                foreach ( $meta['sizes'] as $info ) {
                    if ( ! empty( $info['file'] ) ) {
                        $thumb_key = 'wp-content/uploads/' . $relative_dir . $info['file'];
                        $s3_client->delete_object( $thumb_key );
                    }
                }
            }
        }
    }

    // -------------------------------------------------------------------------
    // Media Library: add "CDN Offload" column
    // -------------------------------------------------------------------------
    public function add_media_columns( $columns ) {
        $columns['uwb_cdn'] = 'CDN Offload';
        return $columns;
    }

    public function render_media_column( $column_name, $post_id ) {
        if ( $column_name !== 'uwb_cdn' ) {
            return;
        }
        if ( CDNManager::is_attachment_offloaded( $post_id ) ) {
            $s3_key    = get_post_meta( $post_id, '_uwb_s3_key', true );
            $timestamp = get_post_meta( $post_id, '_uwb_s3_uploaded', true );
            $date      = $timestamp ? date_i18n( 'd/m/Y H:i', $timestamp ) : '';
            echo '<span style="background:#d1fae5; color:#065f46; border:1px solid #6ee7b7; padding:3px 8px; border-radius:12px; font-size:11px; font-weight:700;" title="' . esc_attr( $s3_key ) . '&#10;Uploaded: ' . esc_attr( $date ) . '">☁️ S3 CDN</span>';
        } else {
            echo '<span style="color:#94a3b8; font-size:12px;">—</span>';
        }
    }
}
