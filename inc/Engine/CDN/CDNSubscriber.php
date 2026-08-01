<?php
namespace Ultimate_WP_Booster\Engine\CDN;

use Ultimate_WP_Booster\EventManagement\Subscriber_Interface;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class CDNSubscriber implements Subscriber_Interface {

    public static function get_subscribed_events() {
        return array(
            'add_attachment'                  => 'on_add_attachment',
            'edit_attachment'                 => 'on_edit_attachment',
            'delete_attachment'               => 'on_delete_attachment',
            'wp_generate_attachment_metadata' => array( 'on_generate_attachment_metadata', 10, 2 ),
            'wp_get_attachment_url'           => array( 'filter_attachment_url', 10, 2 ),
            'wp_calculate_image_srcset'       => array( 'filter_attachment_srcset', 10, 5 ),
            'wp_get_attachment_image_src'     => array( 'filter_attachment_image_src', 10, 4 ),
            'manage_media_columns'            => 'add_media_columns',
            'manage_media_custom_column'      => array( 'render_media_column', 10, 2 ),
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

        // Auto optimize & convert image if enabled
        if ( get_option( 'uwb_media_opt_enabled', 0 ) ) {
            \Ultimate_WP_Booster\Engine\Optimization\Media\ImageOptimizer::optimize_attachment( $attachment_id, array(), false );
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

        // Check for new_file mode WebP/AVIF sidecar files
        foreach ( array( 'webp', 'avif' ) as $side_ext ) {
            $side_file = $file . '.' . $side_ext;
            if ( file_exists( $side_file ) ) {
                $side_key = $s3_key . '.' . $side_ext;
                $s3_client->put_object( $side_file, $side_key, '', $cache_control );
            }
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
            if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
                $dir = dirname( $file );
                foreach ( $meta['sizes'] as $info ) {
                    if ( ! empty( $info['file'] ) ) {
                        @unlink( $dir . '/' . $info['file'] );
                    }
                }
            }
            CDNManager::mark_local_deleted( $attachment_id, true );
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

    public function on_generate_attachment_metadata( $metadata, $attachment_id ) {
        if ( get_option( 'uwb_media_opt_enabled', 0 ) ) {
            \Ultimate_WP_Booster\Engine\Optimization\Media\ImageOptimizer::optimize_attachment( $attachment_id, array(), false );
        }
        return $metadata;
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

    public function filter_attachment_srcset( $sources, $size_array, $image_src, $image_meta, $attachment_id ) {
        if ( ! get_option( 'uwb_cdn_distribute_media', 0 ) ) {
            return $sources;
        }
        if ( ! get_option( 'uwb_cdn_auto_rewrite_attachment_url', 0 ) ) {
            return $sources;
        }

        $cdn_domain = get_option( 'uwb_cdn_custom_domain', '' );
        if ( empty( $cdn_domain ) || ! is_array( $sources ) ) {
            return $sources;
        }

        $cdn_domain = rtrim( $cdn_domain, '/' );
        if ( strpos( $cdn_domain, 'http://' ) !== 0 && strpos( $cdn_domain, 'https://' ) !== 0 ) {
            $cdn_domain = 'https://' . $cdn_domain;
        }

        $uploads  = wp_upload_dir();
        $base_url = rtrim( $uploads['baseurl'], '/' );

        foreach ( $sources as $width => &$source ) {
            if ( isset( $source['url'] ) && strpos( $source['url'], $base_url ) === 0 ) {
                $rel = ltrim( substr( $source['url'], strlen( $base_url ) ), '/' );
                $source['url'] = $cdn_domain . '/wp-content/uploads/' . $rel;
            }
        }

        return $sources;
    }

    public function filter_attachment_image_src( $image, $attachment_id, $size, $icon ) {
        if ( ! get_option( 'uwb_cdn_distribute_media', 0 ) ) {
            return $image;
        }
        if ( ! get_option( 'uwb_cdn_auto_rewrite_attachment_url', 0 ) ) {
            return $image;
        }

        $cdn_domain = get_option( 'uwb_cdn_custom_domain', '' );
        if ( empty( $cdn_domain ) || ! is_array( $image ) || empty( $image[0] ) ) {
            return $image;
        }

        $cdn_domain = rtrim( $cdn_domain, '/' );
        if ( strpos( $cdn_domain, 'http://' ) !== 0 && strpos( $cdn_domain, 'https://' ) !== 0 ) {
            $cdn_domain = 'https://' . $cdn_domain;
        }

        $uploads  = wp_upload_dir();
        $base_url = rtrim( $uploads['baseurl'], '/' );

        if ( strpos( $image[0], $base_url ) === 0 ) {
            $rel = ltrim( substr( $image[0], strlen( $base_url ) ), '/' );
            $image[0] = $cdn_domain . '/wp-content/uploads/' . $rel;
        }

        return $image;
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
        $output = '<div style="display:inline-flex; align-items:center; gap:4px; flex-wrap:wrap;">';
        $has_badge = false;

        $comp_status = get_post_meta( $post_id, '_uwb_img_compress_status', true );
        $webp_status = get_post_meta( $post_id, '_uwb_img_convert_webp_status', true );
        $avif_status = get_post_meta( $post_id, '_uwb_img_convert_avif_status', true );

        if ( 'converted' === $webp_status ) {
            $output .= '<span style="background:#fef3c7; color:#92400e; border:1px solid #fcd34d; padding:3px 8px; border-radius:12px; font-size:11px; font-weight:700;" title="_uwb_img_compress_status: ' . esc_attr( $comp_status ) . '&#10;_uwb_img_convert_webp_status: converted">⚡ WEBP</span>';
            $has_badge = true;
        } elseif ( 'converted' === $avif_status ) {
            $output .= '<span style="background:#fef3c7; color:#92400e; border:1px solid #fcd34d; padding:3px 8px; border-radius:12px; font-size:11px; font-weight:700;" title="_uwb_img_compress_status: ' . esc_attr( $comp_status ) . '&#10;_uwb_img_convert_avif_status: converted">⚡ AVIF</span>';
            $has_badge = true;
        } elseif ( 'compressed' === $comp_status ) {
            $output .= '<span style="background:#fef3c7; color:#92400e; border:1px solid #fcd34d; padding:3px 8px; border-radius:12px; font-size:11px; font-weight:700;" title="_uwb_img_compress_status: compressed">⚡ COMPRESSED</span>';
            $has_badge = true;
        }

        if ( CDNManager::is_attachment_offloaded( $post_id ) ) {
            $s3_key        = get_post_meta( $post_id, '_uwb_s3_key', true );
            $timestamp     = get_post_meta( $post_id, '_uwb_s3_uploaded', true );
            $date          = $timestamp ? date_i18n( 'd/m/Y H:i', $timestamp ) : '';
            $local_deleted = CDNManager::is_local_deleted( $post_id );
            $cloud_status  = get_post_meta( $post_id, '_uwb_s3_cloud_status', true );
            if ( empty( $cloud_status ) ) {
                $cloud_status = 'synced';
            }
            $local_status  = $local_deleted ? 'removed' : 'kept';

            $output .= '<span style="background:#d1fae5; color:#065f46; border:1px solid #6ee7b7; padding:3px 8px; border-radius:12px; font-size:11px; font-weight:700;" title="' . esc_attr( $s3_key ) . '&#10;_uwb_s3_cloud_status: ' . esc_attr( $cloud_status ) . '&#10;Uploaded: ' . esc_attr( $date ) . '">☁️ S3 CDN</span>';
            
            if ( $local_deleted ) {
                $output .= '<span style="background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; padding:3px 8px; border-radius:12px; font-size:11px; font-weight:700;" title="_uwb_s3_local_status: ' . esc_attr( $local_status ) . '">🗑️ Local Removed</span>';
            } else {
                $output .= '<span style="background:#e0f2fe; color:#0369a1; border:1px solid #7dd3fc; padding:3px 8px; border-radius:12px; font-size:11px; font-weight:700;" title="_uwb_s3_local_status: ' . esc_attr( $local_status ) . '">📁 Local Kept</span>';
            }
            $has_badge = true;
        }

        $output .= '</div>';

        if ( $has_badge ) {
            echo $output;
        } else {
            echo '<span style="color:#94a3b8; font-size:12px;">—</span>';
        }
    }
}
