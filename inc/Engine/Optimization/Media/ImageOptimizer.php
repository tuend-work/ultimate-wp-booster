<?php
namespace Ultimate_WP_Booster\Engine\Optimization\Media;

use Ultimate_WP_Booster\Engine\CDN\CDNManager;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class ImageOptimizer {

    /**
     * Check if WebP conversion is supported by PHP GD or Imagick.
     */
    public static function is_webp_supported() {
        if ( function_exists( 'imagewebp' ) ) {
            return true;
        }
        if ( class_exists( '\Imagick' ) && in_array( 'WEBP', \Imagick::queryFormats(), true ) ) {
            return true;
        }
        return false;
    }

    /**
     * Check if AVIF conversion is supported by PHP GD or Imagick.
     */
    public static function is_avif_supported() {
        if ( function_exists( 'imageavif' ) ) {
            return true;
        }
        if ( class_exists( '\Imagick' ) && in_array( 'AVIF', \Imagick::queryFormats(), true ) ) {
            return true;
        }
        return false;
    }

    /**
     * Optimize a single attachment image.
     *
     * @param int   $attachment_id Attachment Post ID.
     * @param array $override_config Optional runtime settings override.
     * @param bool  $force_reoptimize Force re-optimizing even if meta flags exist.
     * @return bool True on success, false on failure or skipped.
     */
    public static function optimize_attachment( $attachment_id, $override_config = array(), $force_reoptimize = false ) {
        if ( ! wp_attachment_is_image( $attachment_id ) ) {
            return false;
        }

        $file = get_attached_file( $attachment_id );
        if ( ! $file || ! file_exists( $file ) ) {
            return false;
        }

        // Config settings
        $quality = isset( $override_config['quality'] ) ? intval( $override_config['quality'] ) : intval( get_option( 'uwb_media_opt_quality', 80 ) );
        $quality = max( 1, min( 100, $quality ) );

        $format = isset( $override_config['format'] ) ? $override_config['format'] : get_option( 'uwb_media_opt_format', 'original' ); // original, webp, avif

        // Conversion mode checkboxes & migration fallback
        $db_sidecar     = get_option( 'uwb_media_opt_mode_sidecar', null );
        $db_overwrite   = get_option( 'uwb_media_opt_mode_overwrite', null );
        $db_replace_ext = get_option( 'uwb_media_opt_mode_replace_ext', null );

        if ( null === $db_sidecar && null === $db_overwrite && null === $db_replace_ext ) {
            $old_mode       = get_option( 'uwb_media_opt_mode', 'new_file' );
            $do_sidecar     = ( 'new_file' === $old_mode );
            $do_overwrite   = ( 'overwrite' === $old_mode );
            $do_replace_ext = ( 'change_extension' === $old_mode );
        } else {
            $do_sidecar     = ! empty( $db_sidecar );
            $do_overwrite   = ! empty( $db_overwrite );
            $do_replace_ext = ! empty( $db_replace_ext );
        }

        if ( isset( $override_config['mode_sidecar'] ) ) {
            $do_sidecar = (bool) $override_config['mode_sidecar'];
        }
        if ( isset( $override_config['mode_overwrite'] ) ) {
            $do_overwrite = (bool) $override_config['mode_overwrite'];
        }
        if ( isset( $override_config['mode_replace_ext'] ) ) {
            $do_replace_ext = (bool) $override_config['mode_replace_ext'];
        }

        // Fallback: If no checkbox selected, default to sidecar
        if ( ! $do_sidecar && ! $do_overwrite && ! $do_replace_ext ) {
            $do_sidecar = true;
        }

        // Check if already optimized with same parameters
        if ( ! $force_reoptimize ) {
            $comp_status = get_post_meta( $attachment_id, '_uwb_img_compress_status', true );
            $webp_status = get_post_meta( $attachment_id, '_uwb_img_convert_webp_status', true );
            $avif_status = get_post_meta( $attachment_id, '_uwb_img_convert_avif_status', true );
            $prev_q      = get_post_meta( $attachment_id, '_uwb_img_opt_quality', true );

            $is_comp_ok = ( 'compressed' === $comp_status && intval( $prev_q ) === $quality );
            if ( 'webp' === $format && $is_comp_ok && 'converted' === $webp_status ) {
                return true;
            }
            if ( 'avif' === $format && $is_comp_ok && 'converted' === $avif_status ) {
                return true;
            }
            if ( 'original' === $format && $is_comp_ok ) {
                return true;
            }
        }

        // Determine effective target mime type & extension
        $target_mime = null;
        $target_ext  = null;

        $original_ext  = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
        $original_mime = wp_check_filetype( $file )['type'];
        if ( empty( $original_mime ) ) {
            if ( in_array( $original_ext, array( 'jpg', 'jpeg' ), true ) ) {
                $original_mime = 'image/jpeg';
            } elseif ( 'png' === $original_ext ) {
                $original_mime = 'image/png';
            } elseif ( 'gif' === $original_ext ) {
                $original_mime = 'image/gif';
            }
        }

        if ( 'webp' === $format && self::is_webp_supported() ) {
            $target_mime = 'image/webp';
            $target_ext  = 'webp';
        } elseif ( 'avif' === $format && self::is_avif_supported() ) {
            $target_mime = 'image/avif';
            $target_ext  = 'avif';
        } elseif ( 'original' === $format ) {
            $target_mime = $original_mime;
            $target_ext  = $original_ext;

            // Remove any legacy WebP/AVIF status flags and sidecar files
            delete_post_meta( $attachment_id, '_uwb_img_convert_webp_status' );
            delete_post_meta( $attachment_id, '_uwb_img_convert_avif_status' );
            if ( file_exists( $file . '.webp' ) ) {
                @unlink( $file . '.webp' );
            }
            if ( file_exists( $file . '.avif' ) ) {
                @unlink( $file . '.avif' );
            }
        }

        // 1. Optional backup of original image file to .bak or restore if switching back to original format
        $bak_file = $file . '.bak';
        if ( get_option( 'uwb_media_opt_backup_bak', 0 ) && ! file_exists( $bak_file ) ) {
            @copy( $file, $bak_file );
        }

        if ( 'original' === $format && file_exists( $bak_file ) ) {
            @copy( $bak_file, $file );
        }

        // 2. Optimize main image
        $editor = wp_get_image_editor( $file );
        if ( is_wp_error( $editor ) ) {
            return false;
        }

        $editor->set_quality( $quality );
        $dir = dirname( $file );
        $filename_no_ext = pathinfo( $file, PATHINFO_FILENAME );

        if ( $target_mime && $target_ext && 'original' !== $format ) {
            // 1. Generate converted WebP/AVIF file first (e.g. image.webp)
            $target_converted_file = $dir . '/' . $filename_no_ext . '.' . $target_ext;
            $saved_converted       = $editor->save( $target_converted_file, $target_mime );

            // 2. Mode: Overwrite in-place (copy converted .webp / .avif binary over original file path)
            if ( $do_overwrite && file_exists( $target_converted_file ) && filesize( $target_converted_file ) > 0 ) {
                @copy( $target_converted_file, $file );
            }

            // 3. Mode: Create Sidecar Files
            if ( $do_sidecar ) {
                $sidecar2 = $dir . '/' . $filename_no_ext . '.' . $original_ext . '.' . $target_ext;
                if ( $sidecar2 !== $file && $sidecar2 !== $target_converted_file ) {
                    @copy( $target_converted_file, $sidecar2 );
                }
                if ( ! $do_overwrite && $original_mime ) {
                    $editor->save( $file, $original_mime );
                }
            } else {
                // If Sidecar mode is disabled, delete the generated .webp / .avif file if it's not the attached file
                if ( $target_converted_file !== $file && file_exists( $target_converted_file ) && ! $do_replace_ext ) {
                    @unlink( $target_converted_file );
                }
                $sidecar2 = $dir . '/' . $filename_no_ext . '.' . $original_ext . '.' . $target_ext;
                if ( $sidecar2 !== $file && file_exists( $sidecar2 ) ) {
                    @unlink( $sidecar2 );
                }
            }

            // 4. Mode: Replace & Change File Extension
            if ( $do_replace_ext && file_exists( $target_converted_file ) ) {
                if ( $target_converted_file !== $file && file_exists( $file ) && ! $do_overwrite && ! $do_sidecar ) {
                    @unlink( $file );
                }
                $file = $target_converted_file;
                update_attached_file( $attachment_id, $file );
            }
        } else {
            // Keep original format (JPEG/PNG/GIF): strictly save with original mime
            if ( $original_mime ) {
                $editor->save( $file, $original_mime );
            } else {
                $editor->save( $file );
            }
        }

        // 3. Optimize thumbnails / intermediate sizes
        $meta = wp_get_attachment_metadata( $attachment_id );
        if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
            foreach ( $meta['sizes'] as $size_key => $size_info ) {
                if ( empty( $size_info['file'] ) ) {
                    continue;
                }
                $thumb_path = $dir . '/' . $size_info['file'];

                if ( 'original' === $format && file_exists( $thumb_path . '.bak' ) ) {
                    @copy( $thumb_path . '.bak', $thumb_path );
                }

                if ( file_exists( $thumb_path ) ) {
                    $thumb_editor = wp_get_image_editor( $thumb_path );
                    if ( ! is_wp_error( $thumb_editor ) ) {
                        $thumb_editor->set_quality( $quality );
                        if ( $target_mime && $target_ext && 'original' !== $format ) {
                            $thumb_no_ext   = pathinfo( $thumb_path, PATHINFO_FILENAME );
                            $thumb_orig_ext = pathinfo( $thumb_path, PATHINFO_EXTENSION );
                            $thumb_target   = $dir . '/' . $thumb_no_ext . '.' . $target_ext;

                            $thumb_editor->save( $thumb_target, $target_mime );

                            if ( $do_overwrite && file_exists( $thumb_target ) && filesize( $thumb_target ) > 0 ) {
                                @copy( $thumb_target, $thumb_path );
                            }

                            if ( $do_sidecar ) {
                                $thumb_side2 = $dir . '/' . $thumb_no_ext . '.' . $thumb_orig_ext . '.' . $target_ext;
                                if ( $thumb_side2 !== $thumb_path && $thumb_side2 !== $thumb_target ) {
                                    @copy( $thumb_target, $thumb_side2 );
                                }
                                if ( ! $do_overwrite ) {
                                    $thumb_orig_mime = wp_check_filetype( $thumb_path )['type'];
                                    if ( $thumb_orig_mime ) {
                                        $thumb_editor->save( $thumb_path, $thumb_orig_mime );
                                    }
                                }
                            } else {
                                if ( $thumb_target !== $thumb_path && file_exists( $thumb_target ) && ! $do_replace_ext ) {
                                    @unlink( $thumb_target );
                                }
                                $thumb_side2 = $dir . '/' . $thumb_no_ext . '.' . $thumb_orig_ext . '.' . $target_ext;
                                if ( $thumb_side2 !== $thumb_path && file_exists( $thumb_side2 ) ) {
                                    @unlink( $thumb_side2 );
                                }
                            }

                            if ( $do_replace_ext && file_exists( $thumb_target ) ) {
                                if ( $thumb_target !== $thumb_path && file_exists( $thumb_path ) && ! $do_overwrite && ! $do_sidecar ) {
                                    @unlink( $thumb_path );
                                }
                                $meta['sizes'][ $size_key ]['file']      = pathinfo( $thumb_target, PATHINFO_BASENAME );
                                $meta['sizes'][ $size_key ]['mime-type'] = $target_mime;
                            }
                        } else {
                            $thumb_orig_mime = wp_check_filetype( $thumb_path )['type'];
                            if ( $thumb_orig_mime ) {
                                $thumb_editor->save( $thumb_path, $thumb_orig_mime );
                            } else {
                                $thumb_editor->save( $thumb_path );
                            }
                            if ( file_exists( $thumb_path . '.webp' ) ) {
                                @unlink( $thumb_path . '.webp' );
                            }
                            if ( file_exists( $thumb_path . '.avif' ) ) {
                                @unlink( $thumb_path . '.avif' );
                            }
                        }
                    }
                }
            }
            wp_update_attachment_metadata( $attachment_id, $meta );
        }

        // 4. Record Meta Flags as requested
        update_post_meta( $attachment_id, '_uwb_img_compress_status', 'compressed' );
        if ( 'webp' === $format ) {
            update_post_meta( $attachment_id, '_uwb_img_convert_webp_status', 'converted' );
        } elseif ( 'avif' === $format ) {
            update_post_meta( $attachment_id, '_uwb_img_convert_avif_status', 'converted' );
        } else {
            delete_post_meta( $attachment_id, '_uwb_img_convert_webp_status' );
            delete_post_meta( $attachment_id, '_uwb_img_convert_avif_status' );
        }

        // Backwards compatibility meta flags
        update_post_meta( $attachment_id, '_uwb_img_optimize_status', 'optimized' );
        update_post_meta( $attachment_id, '_uwb_img_convert_status', $format );
        update_post_meta( $attachment_id, '_uwb_img_optimize_timestamp', time() );
        update_post_meta( $attachment_id, '_uwb_img_opt_quality', $quality );
        update_post_meta( $attachment_id, '_uwb_img_opt_mode', $mode );

        return true;
    }

    /**
     * Restore original image from .bak backup file if available.
     *
     * @param int $attachment_id Attachment Post ID.
     * @return bool True on success, false on failure.
     */
    public static function restore_attachment( $attachment_id ) {
        $file = get_attached_file( $attachment_id );
        if ( ! $file ) {
            return false;
        }

        $bak_file = $file . '.bak';
        if ( ! file_exists( $bak_file ) ) {
            return false;
        }

        // Restore main image file
        @copy( $bak_file, $file );

        // Clear optimization status flags
        delete_post_meta( $attachment_id, '_uwb_img_compress_status' );
        delete_post_meta( $attachment_id, '_uwb_img_convert_webp_status' );
        delete_post_meta( $attachment_id, '_uwb_img_convert_avif_status' );
        delete_post_meta( $attachment_id, '_uwb_img_optimize_status' );
        delete_post_meta( $attachment_id, '_uwb_img_convert_status' );
        delete_post_meta( $attachment_id, '_uwb_img_optimize_timestamp' );
        delete_post_meta( $attachment_id, '_uwb_img_opt_quality' );

        // Restore intermediate thumbnail sizes if .bak exists
        $meta = wp_get_attachment_metadata( $attachment_id );
        if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
            $dir = dirname( $file );
            foreach ( $meta['sizes'] as $size_info ) {
                if ( ! empty( $size_info['file'] ) ) {
                    $thumb_file = $dir . '/' . $size_info['file'];
                    $thumb_bak  = $thumb_file . '.bak';
                    if ( file_exists( $thumb_bak ) ) {
                        @copy( $thumb_bak, $thumb_file );
                    }
                }
            }
        }

        // If CDN offloading is active, re-upload restored original to S3
        if ( get_option( 'uwb_cdn_distribute_media', 0 ) ) {
            $s3_client = CDNManager::get_s3_client();
            if ( $s3_client->is_configured() ) {
                $uploads     = wp_upload_dir();
                $base_dir    = rtrim( str_replace( '\\', '/', $uploads['basedir'] ), '/' );
                $file_norm   = str_replace( '\\', '/', $file );
                if ( strpos( $file_norm, $base_dir ) === 0 ) {
                    $rel = ltrim( substr( $file_norm, strlen( $base_dir ) ), '/' );
                    $s3_key = 'wp-content/uploads/' . $rel;
                    $cache_control = get_option( 'uwb_cdn_cache_control', 'public, max-age=31536000, immutable' );
                    $res = $s3_client->put_object( $file, $s3_key, '', $cache_control );
                    if ( $res ) {
                        CDNManager::mark_attachment_offloaded( $attachment_id, $s3_key );
                    }
                }
            }
        }

        return true;
    }
}
