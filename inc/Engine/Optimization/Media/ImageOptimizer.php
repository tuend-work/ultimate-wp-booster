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
        $mode   = isset( $override_config['mode'] ) ? $override_config['mode'] : get_option( 'uwb_media_opt_mode', 'overwrite' ); // overwrite, new_file

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

        if ( 'webp' === $format && self::is_webp_supported() ) {
            $target_mime = 'image/webp';
            $target_ext  = 'webp';
        } elseif ( 'avif' === $format && self::is_avif_supported() ) {
            $target_mime = 'image/avif';
            $target_ext  = 'avif';
        }

        // 1. Optional backup of original image file to .bak
        if ( get_option( 'uwb_media_opt_backup_bak', 0 ) ) {
            $bak_file = $file . '.bak';
            if ( ! file_exists( $bak_file ) ) {
                @copy( $file, $bak_file );
            }
        }

        // 2. Optimize main image
        $editor = wp_get_image_editor( $file );
        if ( is_wp_error( $editor ) ) {
            return false;
        }

        $editor->set_quality( $quality );
        $dir = dirname( $file );
        $filename_no_ext = pathinfo( $file, PATHINFO_FILENAME );
        $original_ext = pathinfo( $file, PATHINFO_EXTENSION );

        $new_main_file = $file;

        if ( $target_mime && $target_ext ) {
            if ( 'overwrite' === $mode ) {
                // In overwrite mode: save WebP/AVIF binary data DIRECTLY into original file path (keeping original extension e.g. .jpg, .png)
                $editor->save( $file, $target_mime );
            } else {
                // New file mode (generate .webp / .avif next to original file, e.g. banner.jpg.webp)
                $dest_file = $dir . '/' . $filename_no_ext . '.' . $original_ext . '.' . $target_ext;
                $editor->save( $dest_file, $target_mime );
            }
        } else {
            // Keep original format, just re-compress quality
            $editor->save( $file );
        }

        // 3. Optimize thumbnails / intermediate sizes
        $meta = wp_get_attachment_metadata( $attachment_id );
        if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
            foreach ( $meta['sizes'] as $size_key => $size_info ) {
                if ( empty( $size_info['file'] ) ) {
                    continue;
                }
                $thumb_path = $dir . '/' . $size_info['file'];
                if ( file_exists( $thumb_path ) ) {
                    $thumb_editor = wp_get_image_editor( $thumb_path );
                    if ( ! is_wp_error( $thumb_editor ) ) {
                        $thumb_editor->set_quality( $quality );
                        if ( $target_mime && $target_ext ) {
                            if ( 'overwrite' === $mode ) {
                                // Save WebP/AVIF binary data DIRECTLY into thumbnail file path
                                $thumb_editor->save( $thumb_path, $target_mime );
                            } else {
                                $thumb_no_ext   = pathinfo( $thumb_path, PATHINFO_FILENAME );
                                $thumb_orig_ext = pathinfo( $thumb_path, PATHINFO_EXTENSION );
                                $dest_thumb     = $dir . '/' . $thumb_no_ext . '.' . $thumb_orig_ext . '.' . $target_ext;
                                $thumb_editor->save( $dest_thumb, $target_mime );
                            }
                        } else {
                            $thumb_editor->save( $thumb_path );
                        }
                    }
                }
            }
            wp_update_attachment_metadata( $attachment_id, $meta );
        }

        // 3. Record Meta Flags as requested
        $effective_format = $target_ext ? $target_ext : 'original';
        update_post_meta( $attachment_id, '_uwb_img_compress_status', 'compressed' );
        if ( 'webp' === $effective_format ) {
            update_post_meta( $attachment_id, '_uwb_img_convert_webp_status', 'converted' );
        } elseif ( 'avif' === $effective_format ) {
            update_post_meta( $attachment_id, '_uwb_img_convert_avif_status', 'converted' );
        }

        // Backwards compatibility meta flags
        update_post_meta( $attachment_id, '_uwb_img_optimize_status', 'optimized' );
        update_post_meta( $attachment_id, '_uwb_img_convert_status', $effective_format );
        update_post_meta( $attachment_id, '_uwb_img_optimize_timestamp', time() );
        update_post_meta( $attachment_id, '_uwb_img_opt_quality', $quality );
        update_post_meta( $attachment_id, '_uwb_img_opt_mode', $mode );

        return true;
    }
}
