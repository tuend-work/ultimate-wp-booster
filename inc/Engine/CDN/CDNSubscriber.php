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
                    } else {
                        alert('Error: ' + (res.data ? res.data.message : 'Restore failed'));
                        $btn.css('pointer-events', 'auto').text(oldText);
                        if ($msg && $msg.length) {
                            $msg.css('color', '#dc2626').text('Error: ' + (res.data ? res.data.message : 'Restore failed'));
                        }
                    }
                }).fail(function() {
                    alert('AJAX error occurred.');
                    $btn.css('pointer-events', 'auto').text(oldText);
                });
            });
        });
        </script>
        <?php
    }
}
