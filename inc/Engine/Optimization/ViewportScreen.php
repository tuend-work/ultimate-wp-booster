<?php
namespace Ultimate_WP_Booster\Engine\Optimization;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

use Ultimate_WP_Booster\Engine\Optimization\CSS\CriticalCSS;
use Ultimate_WP_Booster\Engine\Optimization\Media\Lazyload;

/**
 * Viewport Screen Optimizer Class
 * Coordinates the saving and caching of first-view optimization data (Critical CSS and Above-the-Fold Images)
 * received from client-side extractor scripts.
 */
class ViewportScreen {

    /**
     * Handle AJAX endpoint to receive Client-Side Extractor viewport payload, update Static HTML cache directly
     */
    public static function ajax_save_viewport_data() {
        $url_hash = isset( $_POST['url_hash'] ) ? (string) $_POST['url_hash'] : '';
        $token    = isset( $_POST['token'] ) ? (string) $_POST['token'] : '';
        $viewport_json = isset( $_POST['viewport_data'] ) ? wp_unslash( (string) $_POST['viewport_data'] ) : '';

        // 1. Strict MD5 Hex Validation (Prevents Path Traversal)
        if ( empty( $url_hash ) || ! preg_match( '/^[a-f0-9]{32}$/i', $url_hash ) ) {
            wp_send_json_error( array( 'message' => 'Invalid url_hash format' ) );
        }

        // 2. Strict HMAC Token / Nonce Verification
        $salt = function_exists( 'wp_salt' ) ? wp_salt( 'auth' ) : 'uwb_secret_key';
        $expected_token = hash_hmac( 'sha256', 'uwb_crit_' . $url_hash, $salt );

        if ( ! hash_equals( $expected_token, $token ) && ! wp_verify_nonce( $token, 'uwb_crit_nonce_' . $url_hash ) ) {
            wp_send_json_error( array( 'message' => 'Security token verification failed' ) );
        }

        $data = json_decode( $viewport_json, true );
        if ( ! is_array( $data ) ) {
            wp_send_json_error( array( 'message' => 'Invalid viewport data JSON payload' ) );
        }

        $css_raw = isset( $data['critical_css'] ) ? (string) $data['critical_css'] : '';
        $images  = isset( $data['images'] ) && is_array( $data['images'] ) ? $data['images'] : array();

        $optimize_viewport_images = (int) get_option( 'uwb_media_optimize_viewport_images', 1 );
        if ( ! $optimize_viewport_images ) {
            $images = array();
        }

        if ( empty( trim( $css_raw ) ) && empty( $images ) ) {
            wp_send_json_error( array( 'message' => 'Empty viewport data payload' ) );
        }

        // 3. CSS Size Limit (Max 300KB)
        if ( strlen( $css_raw ) > 307200 ) {
            $css_raw = substr( $css_raw, 0, 307200 );
        }

        // 4. Strict CSS Sanitization & Minification (Delegate to CriticalCSS helper methods)
        $minified_css = '';
        if ( ! empty( $css_raw ) ) {
            $sanitized_css = CriticalCSS::sanitize_critical_css( $css_raw );
            if ( ! empty( $sanitized_css ) ) {
                $minified_css = CriticalCSS::minify_css( $sanitized_css );
            }
        }

        // 5. Directly update static HTML cache files
        $updated_count = self::update_static_html_cache_files( $url_hash, $minified_css, $images );

        wp_send_json_success( array(
            'message' => 'Viewport data processed, static HTML cache updated.',
            'bytes'   => strlen( $minified_css ),
            'images'  => count( $images ),
            'updated' => $updated_count,
        ) );
    }

    /**
     * Directly update static HTML cache files on disk to fill Critical CSS, optimize viewport images, and strip extractor script
     *
     * @param string $url_hash
     * @param string $critical_css
     * @param array $images
     * @return int Number of updated cache files
     */
    private static function update_static_html_cache_files( $url_hash, $critical_css, $images = array() ) {
        $wp_rocket_dir = WP_CONTENT_DIR . '/cache/wp-rocket';
        if ( ! is_dir( $wp_rocket_dir ) ) {
            return 0;
        }

        $host = isset( $_SERVER['HTTP_HOST'] ) ? strtolower( explode( ':', $_SERVER['HTTP_HOST'] )[0] ) : '';
        $target_dir = ( ! empty( $host ) && is_dir( $wp_rocket_dir . '/' . $host ) ) ? $wp_rocket_dir . '/' . $host : $wp_rocket_dir;

        // Output Auto-Generated Critical CSS and Custom Manual Critical CSS into separate style tags
        $manual_raw   = get_option( 'uwb_tuning_critical_css', '' );
        $manual_clean = ! empty( $manual_raw ) ? CriticalCSS::minify_css( CriticalCSS::sanitize_critical_css( $manual_raw ) ) : '';

        $style_replacement = '';
        if ( ! empty( $critical_css ) ) {
            $style_replacement .= '<style id="uwb-critical-css" data-hash="' . $url_hash . '">' . trim( $critical_css ) . '</style>';
        }
        if ( ! empty( $manual_clean ) ) {
            if ( ! empty( $style_replacement ) ) {
                $style_replacement .= "\n";
            }
            $style_replacement .= '<style id="uwb-manual-critical-css">' . trim( $manual_clean ) . '</style>';
        }

        $placeholder_pattern = '#<style\b[^>]*?id=[\'"]uwb-critical-css[\'"][^>]*?data-hash=[\'"]' . preg_quote( $url_hash, '#' ) . '[\'"][^>]*?>.*?</style>#is';
        $fallback_pattern    = '#<style\b[^>]*?id=[\'"]uwb-critical-css[\'"][^>]*?>.*?</style>#is';
        $updated_count       = 0;

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator( $target_dir, \RecursiveDirectoryIterator::SKIP_DOTS ),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ( $iterator as $item ) {
                if ( $item->isFile() && strpos( $item->getFilename(), '.html' ) !== false ) {
                    $f = $item->getPathname();
                    if ( filesize( $f ) > 100 ) {
                        $content = @file_get_contents( $f );
                        if ( preg_match( $placeholder_pattern, $content ) || ( strpos( $content, 'data-hash="' . $url_hash . '"' ) !== false && strpos( $content, 'uwb-critical-extractor' ) !== false ) ) {
                            // 1. Fill Critical CSS into matching placeholder
                            if ( ! empty( $style_replacement ) ) {
                                $content = preg_replace( $placeholder_pattern, $style_replacement, $content );
                                $content = preg_replace( $fallback_pattern, $style_replacement, $content );
                            }

                            // 2. Optimize first-view images using Lazyload class
                            if ( ! empty( $images ) ) {
                                $content = Lazyload::optimize_first_view_images( $content, $images );
                            }

                            // 3. Multi-layer REMOVAL of extractor script tag from static HTML cache
                            $content = preg_replace( '#<!--UWB_CRIT_START-->.*?<!--UWB_CRIT_END-->#is', '', $content );
                            $content = preg_replace( '#<script\b[^>]*?id=[\'"]uwb-critical-extractor[\'"][^>]*?>.*?</script>#is', '', $content );
                            $content = preg_replace( '#<script\b[^>]*?>[^<]*?__uwb_crit_ran[^<]*?</script>#is', '', $content );
                            $content = preg_replace( '#<script\b[^>]*?>[^<]*?uwb_save_viewport_data[^<]*?</script>#is', '', $content );
                            
                            $start_pos = strpos( $content, '<!--UWB_CRIT_START-->' );
                            $end_pos   = strpos( $content, '<!--UWB_CRIT_END-->' );
                            if ( $start_pos !== false && $end_pos !== false && $end_pos > $start_pos ) {
                                $content = substr( $content, 0, $start_pos ) . substr( $content, $end_pos + 20 );
                            }

                            @file_put_contents( $f, $content );
                            $updated_count++;

                            // 4. Purge matching .gzip file if present
                            $gzip_file = $f . '_gzip';
                            if ( file_exists( $gzip_file ) ) {
                                @unlink( $gzip_file );
                            }
                        }
                    }
                }
            }
        } catch ( \Exception $e ) {
            $html_files = array_merge(
                glob( $wp_rocket_dir . '/*/index*.html' ) ?: array(),
                glob( $wp_rocket_dir . '/*/*/index*.html' ) ?: array(),
                glob( $wp_rocket_dir . '/*/*/*/index*.html' ) ?: array()
            );
            foreach ( $html_files as $f ) {
                if ( file_exists( $f ) && filesize( $f ) > 100 ) {
                    $content = @file_get_contents( $f );
                    if ( preg_match( $placeholder_pattern, $content ) || ( strpos( $content, 'data-hash="' . $url_hash . '"' ) !== false && strpos( $content, 'uwb-critical-extractor' ) !== false ) ) {
                        if ( ! empty( $style_replacement ) ) {
                            $content = preg_replace( $placeholder_pattern, $style_replacement, $content );
                            $content = preg_replace( $fallback_pattern, $style_replacement, $content );
                        }
                        if ( ! empty( $images ) ) {
                            $content = Lazyload::optimize_first_view_images( $content, $images );
                        }
                        $content = preg_replace( '#<!--UWB_CRIT_START-->.*?<!--UWB_CRIT_END-->#is', '', $content );
                        $content = preg_replace( '#<script\b[^>]*?id=[\'"]uwb-critical-extractor[\'"][^>]*?>.*.*?<\/script>#is', '', $content );
                        $start_pos = strpos( $content, '<!--UWB_CRIT_START-->' );
                        $end_pos   = strpos( $content, '<!--UWB_CRIT_END-->' );
                        if ( $start_pos !== false && $end_pos !== false && $end_pos > $start_pos ) {
                            $content = substr( $content, 0, $start_pos ) . substr( $content, $end_pos + 20 );
                        }
                        @file_put_contents( $f, $content );
                        $updated_count++;
                        $gzip_file = $f . '_gzip';
                        if ( file_exists( $gzip_file ) ) {
                            @unlink( $gzip_file );
                        }
                    }
                }
            }
        }

        return $updated_count;
    }
}
