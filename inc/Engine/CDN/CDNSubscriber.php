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
            'attachment_fields_to_edit'       => array( 'filter_attachment_fields_to_edit', 10, 2 ),
            'media_row_actions'               => array( 'filter_media_row_actions', 10, 3 ),
            'restrict_manage_posts'           => 'add_media_filter_dropdown',
            'parse_query'                     => 'filter_media_query_by_status',
            'bulk_actions-upload'             => 'register_media_bulk_actions',
            'handle_bulk_actions-upload'      => array( 'handle_media_bulk_actions', 10, 3 ),
            'admin_notices'                   => 'show_media_bulk_action_notice',
            'admin_footer'                    => 'print_attachment_modal_script',
            'manage_media_columns'            => 'add_media_columns',
            'manage_media_custom_column'      => array( 'render_media_column', 10, 2 ),
            'wp_prepare_attachment_for_js'    => array( 'prepare_attachment_for_js', 10, 3 ),
            'wp_ajax_uwb_get_attachment_info' => 'ajax_get_attachment_info',
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
        if ( get_option( 'uwb_media_opt_enabled', 0 ) && get_option( 'uwb_img_opt_event_upload', 1 ) ) {
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

        $dir             = dirname( $file );
        $filename_no_ext = pathinfo( $file, PATHINFO_FILENAME );
        $relative_dir    = dirname( $relative_path );
        $relative_dir    = ( $relative_dir === '.' ) ? '' : $relative_dir . '/';

        // Check & Upload WebP/AVIF sidecar files (both filename.jpg.webp AND filename.webp)
        foreach ( array( 'webp', 'avif' ) as $side_ext ) {
            $side_file1 = $file . '.' . $side_ext;
            if ( file_exists( $side_file1 ) ) {
                $side_key1 = $s3_key . '.' . $side_ext;
                $s3_client->put_object( $side_file1, $side_key1, '', $cache_control );
            }

            $side_file2 = $dir . '/' . $filename_no_ext . '.' . $side_ext;
            if ( file_exists( $side_file2 ) && $side_file2 !== $file ) {
                $side_key2 = 'wp-content/uploads/' . $relative_dir . $filename_no_ext . '.' . $side_ext;
                $s3_client->put_object( $side_file2, $side_key2, '', $cache_control );
            }
        }

        // Upload thumbnails + thumbnail WebP/AVIF sidecars
        $meta = wp_get_attachment_metadata( $attachment_id );
        if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
            foreach ( $meta['sizes'] as $info ) {
                if ( ! empty( $info['file'] ) ) {
                    $thumb_file = $dir . '/' . $info['file'];
                    if ( file_exists( $thumb_file ) ) {
                        $thumb_key = 'wp-content/uploads/' . $relative_dir . $info['file'];
                        $s3_client->put_object( $thumb_file, $thumb_key, '', $cache_control );

                        $thumb_no_ext = pathinfo( $thumb_file, PATHINFO_FILENAME );
                        foreach ( array( 'webp', 'avif' ) as $side_ext ) {
                            $thumb_side1 = $thumb_file . '.' . $side_ext;
                            if ( file_exists( $thumb_side1 ) ) {
                                $thumb_side_key1 = $thumb_key . '.' . $side_ext;
                                $s3_client->put_object( $thumb_side1, $thumb_side_key1, '', $cache_control );
                            }

                            $thumb_side2 = $dir . '/' . $thumb_no_ext . '.' . $side_ext;
                            if ( file_exists( $thumb_side2 ) && $thumb_side2 !== $thumb_file ) {
                                $thumb_side_key2 = 'wp-content/uploads/' . $relative_dir . $thumb_no_ext . '.' . $side_ext;
                                $s3_client->put_object( $thumb_side2, $thumb_side_key2, '', $cache_control );
                            }
                        }
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
        if ( get_option( 'uwb_media_opt_enabled', 0 ) && get_option( 'uwb_img_opt_event_edit', 0 ) ) {
            \Ultimate_WP_Booster\Engine\Optimization\Media\ImageOptimizer::optimize_attachment( $attachment_id, array(), true );
        }

        if ( ! get_option( 'uwb_cdn_auto_update_attachment', 0 ) ) {
            return;
        }
        // $force = true: file was edited → always re-upload even if flag exists
        $this->upload_attachment_to_s3( $attachment_id, true );
    }

    public function on_generate_attachment_metadata( $metadata, $attachment_id ) {
        if ( get_option( 'uwb_media_opt_enabled', 0 ) && get_option( 'uwb_img_opt_event_upload', 1 ) ) {
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
        if ( get_option( 'uwb_media_opt_enabled', 0 ) && get_option( 'uwb_img_opt_event_get_url', 0 ) ) {
            if ( ! get_post_meta( $post_id, '_uwb_img_compress_status', true ) ) {
                \Ultimate_WP_Booster\Engine\Optimization\Media\ImageOptimizer::optimize_attachment( $post_id, array(), false );
            }
        }
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

    public function prepare_attachment_data( $post_id ) {
        $post          = get_post( $post_id );
        $offloaded     = CDNManager::is_attachment_offloaded( $post_id );
        $local_deleted = CDNManager::is_local_deleted( $post_id );
        $s3_key        = CDNManager::get_attachment_s3_key( $post_id );
        $file          = get_attached_file( $post_id );

        return array(
            'id'                 => $post_id,
            'ID'                 => $post_id,
            'mime'               => $post ? $post->post_mime_type : 'image',
            'url'                => wp_get_attachment_url( $post_id ),
            'link'               => get_attachment_link( $post_id ),
            'uwb_offloaded'      => $offloaded,
            'uwb_local_deleted'  => $local_deleted,
            'uwb_s3_key'         => $s3_key ? $s3_key : ( $file ? 'wp-content/uploads/' . _wp_relative_upload_path( $file ) : '' ),
            'uwb_compressed'     => ( 'compressed' === get_post_meta( $post_id, '_uwb_img_compress_status', true ) ),
            'uwb_webp'           => ( 'converted' === get_post_meta( $post_id, '_uwb_img_convert_webp_status', true ) ),
            'uwb_avif'           => ( 'converted' === get_post_meta( $post_id, '_uwb_img_convert_avif_status', true ) ),
            'uwb_has_bak'        => ( $file && file_exists( $file . '.bak' ) ),
            'uwb_bucket'         => get_option( 'uwb_cdn_bucket', '' ),
            'uwb_provider'       => get_option( 'uwb_cdn_provider', 'cloudflare_r2' ),
            'uwb_cdn_domain'     => get_option( 'uwb_cdn_custom_domain', '' ),
            'uwb_cache_control'  => get_option( 'uwb_cdn_cache_control', 'public, max-age=31536000, immutable' ),
        );
    }

    public function prepare_attachment_for_js( $response, $attachment, $meta ) {
        if ( ! is_object( $attachment ) ) {
            return $response;
        }

        $data = $this->prepare_attachment_data( $attachment->ID );
        foreach ( $data as $k => $v ) {
            $response[ $k ] = $v;
        }

        return $response;
    }

    public function ajax_get_attachment_info() {
        check_ajax_referer( 'uwb_admin_nonce', 'nonce' );
        $id = isset( $_POST['attachment_id'] ) ? intval( $_POST['attachment_id'] ) : 0;
        if ( ! $id ) {
            wp_send_json_error( array( 'message' => 'Invalid Attachment ID' ) );
        }

        $data = $this->prepare_attachment_data( $id );
        wp_send_json_success( $data );
    }

    public function render_media_column( $column_name, $post_id ) {
        if ( $column_name !== 'uwb_cdn' ) {
            return;
        }

        $offloaded   = CDNManager::is_attachment_offloaded( $post_id );
        $icon_color  = $offloaded ? '#0284c7' : '#64748b';
        $fill_color  = $offloaded ? '#0284c7' : 'none';
        $bg_color    = $offloaded ? '#e0f2fe' : '#f1f5f9';
        $border_col  = $offloaded ? '#7dd3fc' : '#cbd5e1';
        $data_json   = esc_attr( wp_json_encode( $this->prepare_attachment_data( $post_id ) ) );

        $output  = '<div style="display:inline-flex; align-items:center; gap:4px; flex-wrap:wrap;">';

        $output .= sprintf(
            '<div class="uwb-cloud-list-icon" data-att-data="%s" style="cursor:pointer; background:%s; border:1px solid %s; border-radius:50%%; width:26px; height:26px; display:inline-flex; align-items:center; justify-content:center; vertical-align:middle; margin-right:4px;" title="%s">' .
            '<svg width="14" height="14" viewBox="0 0 24 24" fill="%s" stroke="%s" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.5 19H9a7 7 0 1 1 6.71-9h1.79a4.5 4.5 0 1 1 0 9Z"/></svg>' .
            '</div>',
            $data_json,
            $bg_color,
            $border_col,
            $offloaded ? '☁️ Synced to S3 CDN' : '☁️ Not Synced',
            $fill_color,
            $icon_color
        );

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

        if ( $offloaded ) {
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

        echo $output;
    }

    // -------------------------------------------------------------------------
    // Attachment Details Modal & Edit Screen: Status Labels + Action Buttons
    // -------------------------------------------------------------------------
    public function filter_attachment_fields_to_edit( $form_fields, $post ) {
        if ( ! is_object( $post ) || ! wp_attachment_is_image( $post->ID ) ) {
            return $form_fields;
        }

        $post_id       = $post->ID;
        $comp_status   = get_post_meta( $post_id, '_uwb_img_compress_status', true );
        $webp_status   = get_post_meta( $post_id, '_uwb_img_convert_webp_status', true );
        $avif_status   = get_post_meta( $post_id, '_uwb_img_convert_avif_status', true );
        $local_deleted = CDNManager::is_local_deleted( $post_id );
        $offloaded     = CDNManager::is_attachment_offloaded( $post_id );

        $file    = get_attached_file( $post_id );
        $has_bak = $file && file_exists( $file . '.bak' );

        $html = '<div class="uwb-attachment-modal-status-box" data-id="' . esc_attr( $post_id ) . '" style="padding:12px; background:#f8fafc; border:1px solid #cbd5e1; border-radius:8px; margin-bottom:10px;">';
        
        // Status Labels List
        $html .= '<div style="display:flex; flex-direction:column; gap:5px; margin-bottom:12px; font-size:12px;">';
        
        // Compression & Conversion Status
        $html .= '<div style="display:flex; align-items:center; gap:6px;"><strong>Optimization:</strong> ';
        if ( 'converted' === $webp_status ) {
            $html .= '<span style="background:#fef3c7; color:#92400e; border:1px solid #fcd34d; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:700;">⚡ WEBP Converted</span>';
        } elseif ( 'converted' === $avif_status ) {
            $html .= '<span style="background:#fef3c7; color:#92400e; border:1px solid #fcd34d; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:700;">⚡ AVIF Converted</span>';
        } elseif ( 'compressed' === $comp_status ) {
            $html .= '<span style="background:#fef3c7; color:#92400e; border:1px solid #fcd34d; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:700;">⚡ Compressed</span>';
        } else {
            $html .= '<span style="background:#f1f5f9; color:#64748b; border:1px solid #cbd5e1; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:700;">Unoptimized</span>';
        }
        $html .= '</div>';

        // S3 Cloud Status
        $html .= '<div style="display:flex; align-items:center; gap:6px;"><strong>S3 Cloud:</strong> ';
        if ( $offloaded ) {
            $html .= '<span style="background:#d1fae5; color:#065f46; border:1px solid #6ee7b7; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:700;">☁️ Synced to S3</span>';
        } else {
            $html .= '<span style="background:#f1f5f9; color:#64748b; border:1px solid #cbd5e1; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:700;">Not Synced</span>';
        }
        $html .= '</div>';

        // Local Server Status
        $html .= '<div style="display:flex; align-items:center; gap:6px;"><strong>Local Server:</strong> ';
        if ( $local_deleted ) {
            $html .= '<span style="background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:700;">🗑️ Local Removed</span>';
        } else {
            $html .= '<span style="background:#e0f2fe; color:#0369a1; border:1px solid #7dd3fc; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:700;">📁 Local Kept</span>';
        }
        $html .= '</div>';

        $html .= '</div>';

        // Action Buttons
        $html .= '<div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">';
        $html .= '<button type="button" class="button button-secondary button-small btn-uwb-opt-single" data-id="' . esc_attr( $post_id ) . '" style="font-weight:600; border-color:#0284c7; color:#0284c7;">⚡ Optimize Now</button>';
        $html .= '<button type="button" class="button button-secondary button-small btn-uwb-upload-s3-single" data-id="' . esc_attr( $post_id ) . '" style="font-weight:600; border-color:#16a34a; color:#16a34a;">☁️ Upload to S3</button>';

        if ( $local_deleted && $offloaded ) {
            $html .= '<button type="button" class="button button-secondary button-small btn-uwb-download-local-single" data-id="' . esc_attr( $post_id ) . '" style="font-weight:600; border-color:#d97706; color:#d97706;">📥 Download to Local</button>';
        }

        if ( $has_bak ) {
            $html .= '<button type="button" class="button button-secondary button-small btn-uwb-restore-single" data-id="' . esc_attr( $post_id ) . '" style="font-weight:600; border-color:#dc2626; color:#dc2626;">↺ Restore Original (.bak)</button>';
        }

        $html .= '</div>';
        $html .= '<div class="uwb-modal-opt-msg" style="font-size:11px; margin-top:6px; font-weight:600; color:#475569; display:none;"></div>';
        $html .= '</div>';

        $form_fields['uwb_booster_status'] = array(
            'label' => 'WP Booster',
            'input' => 'html',
            'html'  => $html,
        );

        return $form_fields;
    }

    public function filter_media_row_actions( $actions, $post, $detached ) {
        if ( ! is_object( $post ) || ! wp_attachment_is_image( $post->ID ) ) {
            return $actions;
        }

        $post_id       = $post->ID;
        $local_deleted = CDNManager::is_local_deleted( $post_id );
        $offloaded     = CDNManager::is_attachment_offloaded( $post_id );
        $file          = get_attached_file( $post_id );
        $has_bak       = $file && file_exists( $file . '.bak' );

        $actions['uwb_opt'] = sprintf(
            '<a href="#" class="btn-uwb-opt-single" data-id="%d" style="color:#0284c7; font-weight:600;">⚡ Tối ưu hóa</a>',
            $post_id
        );

        $actions['uwb_s3'] = sprintf(
            '<a href="#" class="btn-uwb-upload-s3-single" data-id="%d" style="color:#16a34a; font-weight:600;">☁️ Đồng bộ S3</a>',
            $post_id
        );

        if ( $local_deleted && $offloaded ) {
            $actions['uwb_download'] = sprintf(
                '<a href="#" class="btn-uwb-download-local-single" data-id="%d" style="color:#d97706; font-weight:600;">📥 Tải về Local</a>',
                $post_id
            );
        }

        if ( $has_bak ) {
            $actions['uwb_restore'] = sprintf(
                '<a href="#" class="btn-uwb-restore-single" data-id="%d" style="color:#dc2626; font-weight:600;">↺ Khôi phục gốc</a>',
                $post_id
            );
        }

        return $actions;
    }

    // -------------------------------------------------------------------------
    // Media Library List Filters & Bulk Actions
    // -------------------------------------------------------------------------
    public function add_media_filter_dropdown() {
        $scr = get_current_screen();
        if ( ! $scr || $scr->base !== 'upload' ) {
            return;
        }

        $selected = isset( $_GET['uwb_media_status_filter'] ) ? sanitize_text_field( $_GET['uwb_media_status_filter'] ) : '';
        ?>
        <select name="uwb_media_status_filter" id="filter-by-uwb-status">
            <option value=""><?php _e( 'All WP Booster Statuses', 'ultimate-wp-booster' ); ?></option>
            <option value="optimized" <?php selected( $selected, 'optimized' ); ?>><?php _e( '⚡ Optimized Images', 'ultimate-wp-booster' ); ?></option>
            <option value="unoptimized" <?php selected( $selected, 'unoptimized' ); ?>><?php _e( '⏳ Unoptimized Images', 'ultimate-wp-booster' ); ?></option>
            <option value="s3_synced" <?php selected( $selected, 's3_synced' ); ?>><?php _e( '☁️ Synced to S3', 'ultimate-wp-booster' ); ?></option>
            <option value="local_removed" <?php selected( $selected, 'local_removed' ); ?>><?php _e( '🗑️ Local Removed', 'ultimate-wp-booster' ); ?></option>
        </select>
        <?php
    }

    public function filter_media_query_by_status( $query ) {
        if ( ! is_admin() ) {
            return;
        }

        $filter = '';
        if ( isset( $_GET['uwb_media_status_filter'] ) ) {
            $filter = sanitize_text_field( $_GET['uwb_media_status_filter'] );
        } elseif ( isset( $_REQUEST['query']['uwb_media_status_filter'] ) ) {
            $filter = sanitize_text_field( $_REQUEST['query']['uwb_media_status_filter'] );
        } elseif ( isset( $_REQUEST['uwb_media_status_filter'] ) ) {
            $filter = sanitize_text_field( $_REQUEST['uwb_media_status_filter'] );
        }

        if ( empty( $filter ) ) {
            return;
        }

        // Auto-heal meta for offloaded attachments missing _uwb_s3_local_status flag
        if ( 'local_removed' === $filter || 's3_synced' === $filter ) {
            global $wpdb;
            $missing_ids = $wpdb->get_col( "
                SELECT p.ID FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON (p.ID = pm.post_id AND pm.meta_key = '_uwb_s3_uploaded')
                LEFT JOIN {$wpdb->postmeta} pm2 ON (p.ID = pm2.post_id AND pm2.meta_key = '_uwb_s3_local_status')
                WHERE p.post_type = 'attachment' AND pm2.meta_id IS NULL
                LIMIT 100
            " );
            if ( ! empty( $missing_ids ) ) {
                foreach ( $missing_ids as $att_id ) {
                    $file = get_attached_file( $att_id );
                    if ( $file && ! file_exists( $file ) ) {
                        CDNManager::mark_local_deleted( $att_id, true );
                    } else {
                        CDNManager::mark_local_deleted( $att_id, false );
                    }
                }
            }
        }

        $meta_query = $query->get( 'meta_query' );
        if ( ! is_array( $meta_query ) ) {
            $meta_query = array();
        }

        if ( 'optimized' === $filter ) {
            $meta_query[] = array(
                'relation' => 'OR',
                array(
                    'key'     => '_uwb_img_compress_status',
                    'value'   => 'compressed',
                    'compare' => '=',
                ),
                array(
                    'key'     => '_uwb_img_convert_webp_status',
                    'value'   => 'converted',
                    'compare' => '=',
                ),
                array(
                    'key'     => '_uwb_img_convert_avif_status',
                    'value'   => 'converted',
                    'compare' => '=',
                ),
            );
        } elseif ( 'unoptimized' === $filter ) {
            $meta_query[] = array(
                'key'     => '_uwb_img_compress_status',
                'compare' => 'NOT EXISTS',
            );
        } elseif ( 's3_synced' === $filter ) {
            $meta_query[] = array(
                'relation' => 'OR',
                array(
                    'key'     => '_uwb_s3_cloud_status',
                    'value'   => 'synced',
                    'compare' => '=',
                ),
                array(
                    'key'     => '_uwb_s3_uploaded',
                    'compare' => 'EXISTS',
                ),
            );
        } elseif ( 'local_removed' === $filter ) {
            $meta_query[] = array(
                'relation' => 'OR',
                array(
                    'key'     => '_uwb_s3_local_status',
                    'value'   => array( 'removed', 'deleted' ),
                    'compare' => 'IN',
                ),
                array(
                    'key'     => '_uwb_s3_local_deleted',
                    'value'   => array( '1', 1, 'true', true ),
                    'compare' => 'IN',
                ),
            );
        }

        $query->set( 'meta_query', $meta_query );
    }

    public function register_media_bulk_actions( $bulk_actions ) {
        $bulk_actions['uwb_bulk_optimize']       = __( '⚡ Optimize Selected Images', 'ultimate-wp-booster' );
        $bulk_actions['uwb_bulk_upload_s3']      = __( '☁️ Upload Selected to S3', 'ultimate-wp-booster' );
        $bulk_actions['uwb_bulk_download_local'] = __( '📥 Download Selected S3 to Local', 'ultimate-wp-booster' );
        $bulk_actions['uwb_bulk_restore_bak']    = __( '↺ Restore Selected Originals (.bak)', 'ultimate-wp-booster' );
        return $bulk_actions;
    }

    public function handle_media_bulk_actions( $redirect_to, $action, $post_ids ) {
        if ( empty( $post_ids ) || ! is_array( $post_ids ) ) {
            return $redirect_to;
        }

        $count = 0;
        if ( 'uwb_bulk_optimize' === $action ) {
            foreach ( $post_ids as $id ) {
                if ( \Ultimate_WP_Booster\Engine\Optimization\Media\ImageOptimizer::optimize_attachment( $id, array(), true ) ) {
                    $count++;
                }
            }
            $redirect_to = add_query_arg( 'uwb_bulk_msg', 'optimized_' . $count, $redirect_to );
        } elseif ( 'uwb_bulk_upload_s3' === $action ) {
            foreach ( $post_ids as $id ) {
                if ( $this->upload_attachment_to_s3( $id, true ) ) {
                    $count++;
                }
            }
            $redirect_to = add_query_arg( 'uwb_bulk_msg', 'uploaded_' . $count, $redirect_to );
        } elseif ( 'uwb_bulk_download_local' === $action ) {
            foreach ( $post_ids as $id ) {
                if ( CDNManager::download_attachment_from_s3( $id ) ) {
                    $count++;
                }
            }
            $redirect_to = add_query_arg( 'uwb_bulk_msg', 'downloaded_' . $count, $redirect_to );
        } elseif ( 'uwb_bulk_restore_bak' === $action ) {
            foreach ( $post_ids as $id ) {
                if ( \Ultimate_WP_Booster\Engine\Optimization\Media\ImageOptimizer::restore_attachment( $id ) ) {
                    $count++;
                }
            }
            $redirect_to = add_query_arg( 'uwb_bulk_msg', 'restored_' . $count, $redirect_to );
        }

        return $redirect_to;
    }

    public function show_media_bulk_action_notice() {
        if ( empty( $_GET['uwb_bulk_msg'] ) ) {
            return;
        }

        $msg = sanitize_text_field( $_GET['uwb_bulk_msg'] );
        $parts = explode( '_', $msg );
        $type  = isset( $parts[0] ) ? $parts[0] : '';
        $num   = isset( $parts[1] ) ? intval( $parts[1] ) : 0;

        $text = '';
        if ( 'optimized' === $type ) {
            $text = sprintf( 'Successfully optimized %d image(s).', $num );
        } elseif ( 'uploaded' === $type ) {
            $text = sprintf( 'Successfully uploaded %d file(s) to S3 CDN.', $num );
        } elseif ( 'downloaded' === $type ) {
            $text = sprintf( 'Successfully downloaded %d file(s) from S3 to local server.', $num );
        } elseif ( 'restored' === $type ) {
            $text = sprintf( 'Successfully restored %d original file(s) from .bak backup.', $num );
        }

        if ( $text ) {
            echo '<div class="notice notice-success is-dismissible"><p><strong>WP Booster:</strong> ' . esc_html( $text ) . '</p></div>';
        }
    }

    public function print_attachment_modal_script() {
        if ( ! is_admin() ) {
            return;
        }
        ?>
        <script id="uwb-attachment-modal-js">
        jQuery(document).ready(function($) {
            // 1. Optimize Single Attachment
            $(document).on('click', '.btn-uwb-opt-single', function(e) {
                e.preventDefault();
                var $btn    = $(this);
                var id      = $btn.data('id');
                var $box    = $btn.closest('.uwb-attachment-modal-status-box');
                var $msg    = $box.length ? $box.find('.uwb-modal-opt-msg') : null;
                var oldText = $btn.text();

                $btn.css('pointer-events', 'none').text('⚡ Đang nén...');
                if ($msg && $msg.length) {
                    $msg.show().css('color', '#0284c7').text('Processing optimization...');
                }

                $.post(ajaxurl, {
                    action: 'uwb_optimize_single_attachment',
                    attachment_id: id,
                    nonce: '<?php echo wp_create_nonce( 'uwb_admin_nonce' ); ?>'
                }, function(res) {
                    if (res.success) {
                        if ($msg && $msg.length) {
                            $msg.css('color', '#16a34a').text('Successfully optimized! Reloading...');
                        }
                        $btn.text('✔ Đã nén!');
                        setTimeout(function() { location.reload(); }, 600);
                    } else {
                        alert('Error: ' + (res.data ? res.data.message : 'Failed'));
                        $btn.css('pointer-events', 'auto').text(oldText);
                        if ($msg && $msg.length) {
                            $msg.css('color', '#dc2626').text('Error: ' + (res.data ? res.data.message : 'Failed'));
                        }
                    }
                }).fail(function() {
                    alert('AJAX error occurred.');
                    $btn.css('pointer-events', 'auto').text(oldText);
                });
            });

            // 2. Upload Single Attachment to S3
            $(document).on('click', '.btn-uwb-upload-s3-single', function(e) {
                e.preventDefault();
                var $btn    = $(this);
                var id      = $btn.data('id');
                var $box    = $btn.closest('.uwb-attachment-modal-status-box');
                var $msg    = $box.length ? $box.find('.uwb-modal-opt-msg') : null;
                var oldText = $btn.text();

                $btn.css('pointer-events', 'none').text('☁️ Đang đồng bộ...');
                if ($msg && $msg.length) {
                    $msg.show().css('color', '#16a34a').text('Uploading file to S3 CDN...');
                }

                $.post(ajaxurl, {
                    action: 'uwb_upload_single_attachment',
                    attachment_id: id,
                    nonce: '<?php echo wp_create_nonce( 'uwb_admin_nonce' ); ?>'
                }, function(res) {
                    if (res.success) {
                        if ($msg && $msg.length) {
                            $msg.css('color', '#16a34a').text('Successfully uploaded to S3 CDN!');
                        }
                        $btn.text('✔ Đã đồng bộ S3!');
                        setTimeout(function() { location.reload(); }, 600);
                    } else {
                        alert('Error: ' + (res.data ? res.data.message : 'Upload failed'));
                        $btn.css('pointer-events', 'auto').text(oldText);
                        if ($msg && $msg.length) {
                            $msg.css('color', '#dc2626').text('Error: ' + (res.data ? res.data.message : 'Upload failed'));
                        }
                    }
                }).fail(function() {
                    alert('AJAX error occurred.');
                    $btn.css('pointer-events', 'auto').text(oldText);
                });
            });

            // 3. Download Single Attachment to Local
            $(document).on('click', '.btn-uwb-download-local-single', function(e) {
                e.preventDefault();
                var $btn    = $(this);
                var id      = $btn.data('id');
                var $box    = $btn.closest('.uwb-attachment-modal-status-box');
                var $msg    = $box.length ? $box.find('.uwb-modal-opt-msg') : null;
                var oldText = $btn.text();

                $btn.css('pointer-events', 'none').text('📥 Đang tải về...');
                if ($msg && $msg.length) {
                    $msg.show().css('color', '#d97706').text('Downloading file from S3...');
                }

                $.post(ajaxurl, {
                    action: 'uwb_download_single_attachment',
                    attachment_id: id,
                    nonce: '<?php echo wp_create_nonce( 'uwb_admin_nonce' ); ?>'
                }, function(res) {
                    if (res.success) {
                        if ($msg && $msg.length) {
                            $msg.css('color', '#16a34a').text('Successfully downloaded to local!');
                        }
                        $btn.text('✔ Đã tải về!');
                        setTimeout(function() { location.reload(); }, 600);
                    } else {
                        alert('Error: ' + (res.data ? res.data.message : 'Download failed'));
                        $btn.css('pointer-events', 'auto').text(oldText);
                        if ($msg && $msg.length) {
                            $msg.css('color', '#dc2626').text('Error: ' + (res.data ? res.data.message : 'Download failed'));
                        }
                    }
                }).fail(function() {
                    alert('AJAX error occurred.');
                    $btn.css('pointer-events', 'auto').text(oldText);
                });
            });

            // 4. Restore Single Attachment (.bak)
            $(document).on('click', '.btn-uwb-restore-single', function(e) {
                e.preventDefault();
                var $btn    = $(this);
                var id      = $btn.data('id');
                var $box    = $btn.closest('.uwb-attachment-modal-status-box');
                var $msg    = $box.length ? $box.find('.uwb-modal-opt-msg') : null;
                var oldText = $btn.text();

                if (!confirm('Bạn có chắc chắn muốn khôi phục ảnh gốc từ file backup .bak?')) {
                    return;
                }

                $btn.css('pointer-events', 'none').text('↺ Đang khôi phục...');
                if ($msg && $msg.length) {
                    $msg.show().css('color', '#dc2626').text('Restoring original file...');
                }

                $.post(ajaxurl, {
                    action: 'uwb_restore_single_attachment',
                    attachment_id: id,
                    nonce: '<?php echo wp_create_nonce( 'uwb_admin_nonce' ); ?>'
                }, function(res) {
                    if (res.success) {
                        if ($msg && $msg.length) {
                            $msg.css('color', '#16a34a').text('Successfully restored from .bak!');
                        }
                        $btn.text('✔ Đã khôi phục!');
                        setTimeout(function() { location.reload(); }, 600);
                    }
                }).fail(function() {
                    alert('AJAX error occurred.');
                    $btn.css('pointer-events', 'auto').text(oldText);
                });
            });

            // 5. Grid View Cloud Badge Scanner & Backbone Listener
            function getCloudSvgSrc(isOffloaded) {
                return isOffloaded ?
                    'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyOSIgaGVpZ2h0PSIxOCIgdmlld0JveD0iMCAwIDI5IDE4IiBmaWxsPSJub25lIj48cGF0aCBkPSJNMjAuNSA3QzE5LjYgMy4xIDE2LjEgMCAxMiAwIDguNyAwIDYuMSAyLjMgNS4yIDUuNCAyLjIgNi42IDAgOSA2LjAgMCAxMi40IDAgMTguNmMwIDMuMSAyLjUgNS40IDUuNiA1LjRoMTUuNGMzLjEgMCA1LjYtMi41IDUuNi01LjYgMC0zLjEtMi40LTUuNC01LjUtNS40eiIgZmlsbD0iIzAyODRjNyIvPjwvc3ZnPg==' :
                    'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyOSIgaGVpZ2h0PSIxOCIgdmlld0JveD0iMCAwIDI5IDE4IiBmaWxsPSJub25lIj48cGF0aCBkPSJNMjAuNSA3QzE5LjYgMy4xIDE2LjEgMCAxMiAwIDguNyAwIDYuMSAyLjMgNS4yIDUuNCAyLjIgNi42IDAgOSA2LjAgMCAxMi40IDAgMTguNmMwIDMuMSAyLjUgNS40IDUuNiA1LjRoMTUuNGMzLjEgMCA1LjYtMi41IDUuNi01LjRoMTUuNGMzLjEgMCA1LjYtMi41IDUuNi01LjRoMTUuNGMzLjEgMCA1LjYtMi41IDUuNi01LjRoMTUuNGMzLjEgMCA1LjYtMi41IDUuNi01LjR6IiBzdHJva2U9IiM5NGEzYjgiIHN0cm9rZS13aWR0aD0iMiIgZmlsbD0ibm9uZSIvPjwvc3ZnPg==';
            }

            function scanAndInjectGridIcons() {
                $('.attachment-preview').each(function() {
                    var $prev = $(this);
                    if ($prev.find('.ilab-s3-logo').length || $prev.find('.uwb-cloud-icon-btn').length) {
                        return;
                    }

                    var $li = $prev.closest('li.attachment');
                    var id = $li.data('id') || $li.attr('data-id');
                    if (!id) return;

                    var model = null;
                    if (typeof wp !== 'undefined' && wp.media && wp.media.attachment) {
                        try {
                            var att = wp.media.attachment(id);
                            if (att) model = att.toJSON();
                        } catch(err) {}
                    }

                    var isOffloaded = model ? !!model.uwb_offloaded : false;
                    if (isOffloaded) {
                        $prev.addClass('has-s3');
                    }

                    var $img = $('<img class="ilab-s3-logo uwb-cloud-icon-btn ' + (isOffloaded ? 'is-offloaded' : 'not-offloaded') + '" data-post-id="' + id + '" data-container="grid" src="' + getCloudSvgSrc(isOffloaded) + '" width="29" height="18" title="' + (isOffloaded ? '☁️ Synced to S3 CDN' : '☁️ Not Synced') + '" />');
                    if (model) {
                        $img.data('att-data', model);
                    }
                    $prev.prepend($img);
                });
            }

            scanAndInjectGridIcons();
            setInterval(scanAndInjectGridIcons, 500);

            if (typeof wp !== 'undefined' && wp.media && wp.media.view && wp.media.view.Attachment) {
                var types = ['Library', 'EditLibrary', 'Selection', 'Attachments'];
                types.forEach(function(t) {
                    if (wp.media.view.Attachment[t]) {
                        var orig = wp.media.view.Attachment[t];
                        wp.media.view.Attachment[t] = orig.extend({
                            render: function() {
                                orig.prototype.render.apply(this, arguments);
                                var model = this.model ? this.model.toJSON() : {};
                                var $el   = this.$el;
                                var $prev = $el.find('.attachment-preview');
                                if ($prev.length && !$prev.find('.ilab-s3-logo').length && !$prev.find('.uwb-cloud-icon-btn').length) {
                                    var isOffloaded = !!model.uwb_offloaded;
                                    if (isOffloaded) {
                                        $prev.addClass('has-s3');
                                    }
                                    var $img = $('<img class="ilab-s3-logo uwb-cloud-icon-btn ' + (isOffloaded ? 'is-offloaded' : 'not-offloaded') + '" data-post-id="' + (model.id || model.ID || '') + '" data-container="grid" src="' + getCloudSvgSrc(isOffloaded) + '" width="29" height="18" title="' + (isOffloaded ? '☁️ Synced to S3 CDN' : '☁️ Not Synced') + '" />');
                                    $img.data('att-data', model);
                                    $prev.prepend($img);
                                }
                                return this;
                            }
                        });
                    }
                });
            }

            var hideTimer = null;

            function renderPopover($icon, data) {
                $('.uwb-storage-info-popover').remove();

                var type        = data.mime || data.subtype || 'image';
                var provider    = data.uwb_provider || 'cloudflare';
                var bucket      = data.uwb_bucket || 'cdn-bucket';
                var path        = data.uwb_s3_key || '';
                var cacheCtrl   = data.uwb_cache_control || 'public,max-age=2592000';
                var isOffloaded = !!data.uwb_offloaded;
                var id          = data.id || data.ID;
                var cdnDomain   = data.uwb_cdn_domain ? data.uwb_cdn_domain.replace(/\/$/, '') : '';
                var storageUrl  = isOffloaded && cdnDomain && path ? (cdnDomain + '/' + path) : (data.url || '');
                var publicUrl   = data.url || data.link || '';

                var badgesHtml = '';
                if (data.uwb_webp)       badgesHtml += '<span class="uwb-pop-badge bg-amber">⚡ WEBP</span> ';
                if (data.uwb_avif)       badgesHtml += '<span class="uwb-pop-badge bg-purple">⚡ AVIF</span> ';
                if (data.uwb_compressed) badgesHtml += '<span class="uwb-pop-badge bg-blue">⚡ COMPRESSED</span> ';
                if (isOffloaded)         badgesHtml += '<span class="uwb-pop-badge bg-green">☁️ S3 CDN</span> ';
                if (data.uwb_local_deleted) {
                    badgesHtml += '<span class="uwb-pop-badge bg-red">🗑️ Local Removed</span> ';
                } else {
                    badgesHtml += '<span class="uwb-pop-badge bg-slate">📁 Local Kept</span> ';
                }

                var actionsHtml = '<button type="button" class="button button-small btn-uwb-opt-single" data-id="' + id + '" style="font-size:11px; font-weight:700; color:#0284c7; border-color:#0284c7;">⚡ Optimize</button> ' +
                                  '<button type="button" class="button button-small btn-uwb-upload-s3-single" data-id="' + id + '" style="font-size:11px; font-weight:700; color:#16a34a; border-color:#16a34a;">☁️ Sync S3</button> ';

                if (data.uwb_local_deleted && isOffloaded) {
                    actionsHtml += '<button type="button" class="button button-small btn-uwb-download-local-single" data-id="' + id + '" style="font-size:11px; font-weight:700; color:#d97706; border-color:#d97706;">📥 Get Local</button> ';
                }
                if (data.uwb_has_bak) {
                    actionsHtml += '<button type="button" class="button button-small btn-uwb-restore-single" data-id="' + id + '" style="font-size:11px; font-weight:700; color:#dc2626; border-color:#dc2626;">↺ Restore</button> ';
                }

                var popoverHtml = '<div class="uwb-storage-info-popover">' +
                    '<div class="uwb-pop-arrow"></div>' +
                    '<div class="uwb-pop-header">STORAGE INFO</div>' +
                    '<div class="uwb-pop-body">' +
                        '<div class="uwb-pop-field"><div class="uwb-pop-label">TYPE</div><div class="uwb-pop-val">' + type + '</div></div>' +
                        '<div class="uwb-pop-field"><div class="uwb-pop-label">STORAGE SERVICE</div><div class="uwb-pop-val">' + provider + '</div></div>' +
                        '<div class="uwb-pop-field"><div class="uwb-pop-label">BUCKET</div><div class="uwb-pop-val">' + bucket + '</div></div>' +
                        '<div class="uwb-pop-field"><div class="uwb-pop-label">PATH</div><div class="uwb-pop-val">' + path + '</div></div>' +
                        '<div class="uwb-pop-field"><div class="uwb-pop-label">ACCESS</div><div class="uwb-pop-val">public-read</div></div>' +
                        '<div class="uwb-pop-field"><div class="uwb-pop-label">CACHE-CONTROL</div><div class="uwb-pop-val">' + cacheCtrl + '</div></div>' +
                        '<div class="uwb-pop-field"><div class="uwb-pop-label">EXPIRES</div><div class="uwb-pop-val">None</div></div>' +
                        '<div style="margin-top:10px; display:flex; flex-wrap:wrap; gap:4px;">' + badgesHtml + '</div>' +
                        '<div style="margin-top:10px; display:flex; flex-wrap:wrap; gap:6px;">' + actionsHtml + '</div>' +
                        '<div class="uwb-pop-footer-links">' +
                            '<a href="' + storageUrl + '" target="_blank"><span class="dashicons dashicons-external" style="font-size:13px; line-height:16px;"></span> STORAGE URL</a>' +
                            '<a href="' + publicUrl + '" target="_blank"><span class="dashicons dashicons-external" style="font-size:13px; line-height:16px;"></span> PUBLIC URL</a>' +
                        '</div>' +
                    '</div>' +
                '</div>';

                var $popover = $(popoverHtml);
                $('body').append($popover);

                var iconOffset = $icon.offset();
                var iconWidth  = $icon.outerWidth();
                var iconHeight = $icon.outerHeight();
                var popWidth   = $popover.outerWidth();
                var popHeight  = $popover.outerHeight();
                var winWidth   = $(window).width();
                var winScrollT = $(window).scrollTop();

                var top  = iconOffset.top - 18;
                var left = iconOffset.left + iconWidth + 12;
                var arrowClass = 'arrow-left';

                if (left + popWidth > winWidth - 10) {
                    left = iconOffset.left - popWidth - 12;
                    arrowClass = 'arrow-right';
                }
                if (top + popHeight > winScrollT + $(window).height() - 10) {
                    top = winScrollT + $(window).height() - popHeight - 10;
                }
                if (top < winScrollT + 10) {
                    top = winScrollT + 10;
                }

                $popover.find('.uwb-pop-arrow').addClass(arrowClass);
                $popover.css({ top: top + 'px', left: left + 'px' });
            }

            $(document).on('mouseenter', '.ilab-s3-logo, .uwb-cloud-icon-btn, .uwb-cloud-list-icon', function(e) {
                clearTimeout(hideTimer);
                var $icon = $(this);
                var rawData = $icon.data('att-data');
                var data = null;
                if (rawData) {
                    data = (typeof rawData === 'string') ? JSON.parse(rawData) : rawData;
                }

                var id = $icon.attr('data-post-id') || $icon.data('id') || ($icon.closest('li.attachment').data('id'));

                if (!data && id) {
                    $.post(ajaxurl, {
                        action: 'uwb_get_attachment_info',
                        attachment_id: id,
                        nonce: '<?php echo wp_create_nonce("uwb_admin_nonce"); ?>'
                    }, function(res) {
                        if (res.success && res.data) {
                            $icon.data('att-data', res.data);
                            renderPopover($icon, res.data);
                        }
                    });
                    return;
                }

                if (data) {
                    renderPopover($icon, data);
                }
            });

            $(document).on('mouseleave', '.ilab-s3-logo, .uwb-cloud-icon-btn, .uwb-cloud-list-icon, .uwb-storage-info-popover', function() {
                hideTimer = setTimeout(function() {
                    if (!$('.uwb-storage-info-popover:hover').length && !$('.ilab-s3-logo:hover').length && !$('.uwb-cloud-icon-btn:hover').length && !$('.uwb-cloud-list-icon:hover').length) {
                        $('.uwb-storage-info-popover').remove();
                    }
                }, 200);
            });
            $(document).on('mouseenter', '.uwb-storage-info-popover', function() {
                clearTimeout(hideTimer);
            });
        });
        </script>
        <style id="uwb-cloud-badge-css">
        .has-s3 > .ilab-s3-logo,
        .attachment-preview > .ilab-s3-logo,
        .attachment-preview > .uwb-cloud-icon-btn {
            display: block !important;
            position: absolute !important;
            right: 5px !important;
            bottom: 5px !important;
            z-index: 20 !important;
            cursor: pointer !important;
            width: 29px !important;
            height: 18px !important;
            transition: transform 0.15s ease !important;
        }
        .ilab-s3-logo:hover,
        .uwb-cloud-icon-btn:hover {
            transform: scale(1.2) !important;
        }
        .uwb-storage-info-popover {
            position: absolute;
            z-index: 999999;
            background: #ffffff;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.22);
            width: 320px;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #111827;
            animation: uwbPopIn 0.15s ease-out;
        }
        @keyframes uwbPopIn {
            from { opacity: 0; transform: scale(0.96); }
            to { opacity: 1; transform: scale(1); }
        }
        .uwb-pop-header {
            background: #e5e7eb;
            color: #374151;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.5px;
            padding: 8px 14px;
            border-top-left-radius: 7px;
            border-top-right-radius: 7px;
            border-bottom: 1px solid #d1d5db;
        }
        .uwb-pop-body {
            padding: 12px 14px 14px 14px;
        }
        .uwb-pop-field {
            margin-bottom: 8px;
        }
        .uwb-pop-label {
            font-size: 10.5px;
            font-weight: 800;
            color: #374151;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-bottom: 2px;
        }
        .uwb-pop-val {
            font-size: 12px;
            color: #4b5563;
            word-break: break-all;
            line-height: 1.3;
        }
        .uwb-pop-footer-links {
            background: #ececec;
            border-radius: 8px;
            padding: 8px;
            margin-top: 12px;
            display: flex;
            gap: 8px;
        }
        .uwb-pop-footer-links a {
            flex: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            background: #ffffff;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 6px 8px;
            font-size: 11px;
            font-weight: 800;
            color: #1f2937;
            text-decoration: none;
            text-transform: uppercase;
            transition: background 0.15s ease;
        }
        .uwb-pop-footer-links a:hover {
            background: #f9fafb;
            border-color: #9ca3af;
            color: #000000;
        }
        .uwb-pop-arrow {
            position: absolute;
            width: 0;
            height: 0;
            border-style: solid;
        }
        .uwb-pop-arrow.arrow-left {
            top: 16px;
            left: -8px;
            border-width: 7px 8px 7px 0;
            border-color: transparent #e5e7eb transparent transparent;
        }
        .uwb-pop-arrow.arrow-right {
            top: 16px;
            right: -8px;
            border-width: 7px 0 7px 8px;
            border-color: transparent transparent transparent #e5e7eb;
        }
        .uwb-pop-badge {
            padding: 2px 7px;
            border-radius: 10px;
            font-size: 10.5px;
            font-weight: 700;
            display: inline-block;
        }
        .uwb-pop-badge.bg-blue { background: #e0f2fe; color: #0369a1; border: 1px solid #7dd3fc; }
        .uwb-pop-badge.bg-amber { background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }
        .uwb-pop-badge.bg-purple { background: #f3e8ff; color: #6b21a8; border: 1px solid #d8b4fe; }
        .uwb-pop-badge.bg-green { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
        .uwb-pop-badge.bg-red { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .uwb-pop-badge.bg-slate { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }
        </style>
        <?php
    }
}
