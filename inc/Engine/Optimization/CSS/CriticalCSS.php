<?php
namespace Ultimate_WP_Booster\Engine\Optimization\CSS;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

/**
 * Server-Side Placeholder & In-Memory Client Extractor Engine for Critical CSS
 * Updates static HTML cache files directly and strips extractor JS after first real visit.
 */
class CriticalCSS {

    /**
     * Main Entry Point: Inject Critical CSS if already present, or inject Placeholder + Extractor JS
     *
     * @param string $html
     * @param string $url
     * @return string
     */
    public static function generate( $html, $url = '' ) {
        if ( empty( $html ) ) {
            return $html;
        }

        $enabled = (int) get_option( 'uwb_auto_critical_css', 1 );
        if ( ! $enabled ) {
            return $html;
        }

        // If HTML already contains filled Critical CSS inside <style id="uwb-critical-css">, do not modify
        if ( preg_match( '#<style\b[^>]*?id=[\'"]uwb-critical-css[\'"][^>]*?>\s*([^\s<].*?)\s*</style>#is', $html, $css_matches ) ) {
            if ( ! empty( $css_matches[1] ) && strlen( trim( $css_matches[1] ) ) > 10 ) {
                return $html;
            }
        }

        if ( empty( $url ) ) {
            $url = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '/';
        }

        $url_clean = strtok( $url, '?' );
        $url_hash = md5( $url_clean );

        // Inject Placeholder in <head> if not already present
        $placeholder_tag = '<style id="uwb-critical-css"></style>';
        if ( strpos( $html, 'id="uwb-critical-css"' ) === false ) {
            if ( preg_match( '/<head[^>]*>/i', $html, $matches ) ) {
                $html = str_replace( $matches[0], $matches[0] . "\n" . $placeholder_tag, $html );
            }
        }

        // Inject background Client-Side Extractor JS before </body> if not already present
        if ( strpos( $html, 'id="uwb-critical-extractor"' ) === false ) {
            $extractor_script = self::render_extractor_script( $url_hash );
            if ( stripos( $html, '</body>' ) !== false ) {
                $html = str_ireplace( '</body>', $extractor_script . "\n" . '</body>', $html );
            } else {
                $html .= "\n" . $extractor_script;
            }
        }

        return $html;
    }

    /**
     * Render lightweight Client-Side Extractor JS
     *
     * @param string $url_hash
     * @return string
     */
    private static function render_extractor_script( $url_hash ) {
        $ajax_url = function_exists( 'admin_url' ) ? admin_url( 'admin-ajax.php' ) : '/wp-admin/admin-ajax.php';
        $salt = function_exists( 'wp_salt' ) ? wp_salt( 'auth' ) : 'uwb_secret_key';
        $token = hash_hmac( 'sha256', 'uwb_crit_' . $url_hash, $salt );

        ob_start();
        ?>
<script id="uwb-critical-extractor">
(function(){
    if (window.__uwb_crit_ran) return;
    window.__uwb_crit_ran = true;
    function extractCritical() {
        try {
            var maxVh = window.innerHeight || 900;
            var elements = document.querySelectorAll('header, nav, section, div, h1, h2, h3, a, img, form, p, span, ul, li');
            var topSelectors = new Set(['html', 'body', '*', 'header', 'nav', 'h1', 'h2', 'h3', 'a', 'img', 'p', 'div', 'section', 'ul', 'li']);
            for (var i = 0; i < elements.length; i++) {
                var el = elements[i];
                var rect = el.getBoundingClientRect();
                if (rect.top <= maxVh + 100) {
                    if (el.tagName) topSelectors.add(el.tagName.toLowerCase());
                    if (el.id) topSelectors.add('#' + el.id);
                    if (el.className && typeof el.className === 'string') {
                        var classes = el.className.split(/\s+/);
                        for (var c = 0; c < classes.length; c++) {
                            if (classes[c]) topSelectors.add('.' + classes[c]);
                        }
                    }
                }
            }
            var rulesExtracted = [];
            var sheets = document.styleSheets;
            for (var s = 0; s < sheets.length; s++) {
                try {
                    var rules = sheets[s].cssRules || sheets[s].rules;
                    if (!rules) continue;
                    for (var r = 0; r < rules.length; r++) {
                        var rule = rules[r];
                        if (rule.type === CSSRule.STYLE_RULE) {
                            var sel = rule.selectorText;
                            if (!sel) continue;
                            if (sel.indexOf(':root') !== -1 || sel === '*' || sel === 'body' || sel === 'html') {
                                rulesExtracted.push(rule.cssText);
                                continue;
                            }
                            var parts = sel.split(',');
                            var matched = false;
                            for (var p = 0; p < parts.length; p++) {
                                var part = parts[p].trim();
                                var matchCls = part.match(/\.([a-z0-9_-]+)/i);
                                var matchId = part.match(/\#([a-z0-9_-]+)/i);
                                var matchTag = part.match(/^([a-z0-9]+)/i);
                                if ((matchCls && topSelectors.has('.' + matchCls[1])) ||
                                    (matchId && topSelectors.has('#' + matchId[1])) ||
                                    (matchTag && topSelectors.has(matchTag[1].toLowerCase()))) {
                                    matched = true;
                                    break;
                                }
                            }
                            if (matched) rulesExtracted.push(rule.cssText);
                        } else if (rule.type === CSSRule.MEDIA_RULE || rule.type === 3 || rule.type === 7) {
                            rulesExtracted.push(rule.cssText);
                        }
                    }
                } catch(e){}
            }
            var finalCss = rulesExtracted.join('\n');
            if (!finalCss || finalCss.length < 20) return;
            var styleEl = document.getElementById('uwb-critical-css');
            if (styleEl) styleEl.textContent = finalCss;
            var payload = new FormData();
            payload.append('action', 'uwb_save_critical_css');
            payload.append('url_hash', '<?php echo esc_js( $url_hash ); ?>');
            payload.append('token', '<?php echo esc_js( $token ); ?>');
            payload.append('critical_css', finalCss);
            if (navigator.sendBeacon) {
                navigator.sendBeacon('<?php echo esc_url( $ajax_url ); ?>', payload);
            } else if (window.fetch) {
                fetch('<?php echo esc_url( $ajax_url ); ?>', { method: 'POST', body: payload });
            }
        } catch(e){}
    }
    if (document.readyState === 'complete') {
        setTimeout(extractCritical, 600);
    } else {
        window.addEventListener('load', function(){ setTimeout(extractCritical, 600); });
    }
})();
</script>
        <?php
        return ob_get_clean();
    }

    /**
     * Handle AJAX endpoint to receive Client-Side Extractor payload, update Static HTML cache directly, and strip extractor JS
     */
    public static function ajax_save_critical_css() {
        $url_hash = isset( $_POST['url_hash'] ) ? (string) $_POST['url_hash'] : '';
        $token    = isset( $_POST['token'] ) ? (string) $_POST['token'] : ( isset( $_POST['nonce'] ) ? (string) $_POST['nonce'] : '' );
        $css_raw  = isset( $_POST['critical_css'] ) ? (string) $_POST['critical_css'] : '';

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

        if ( empty( trim( $css_raw ) ) ) {
            wp_send_json_error( array( 'message' => 'Empty CSS payload' ) );
        }

        // 3. Payload Size Limit (Max 300KB)
        if ( strlen( $css_raw ) > 307200 ) {
            $css_raw = substr( $css_raw, 0, 307200 );
        }

        // 4. Strict CSS Sanitization
        $sanitized_css = self::sanitize_critical_css( $css_raw );
        if ( empty( $sanitized_css ) ) {
            wp_send_json_error( array( 'message' => 'Invalid CSS content' ) );
        }

        // 5. Minify CSS payload
        $minified_css = self::minify_css( $sanitized_css );

        // 6. Directly update static HTML cache files and strip extractor script
        $updated_count = self::update_static_html_cache_files( $minified_css );

        wp_send_json_success( array(
            'message' => 'Critical CSS embedded directly into static HTML cache files, extractor script stripped.',
            'bytes'   => strlen( $minified_css ),
            'updated' => $updated_count,
        ) );
    }

    /**
     * Sanitize Critical CSS payload to prevent Stored XSS, HTML Injection, or PHP execution
     *
     * @param string $css
     * @return string
     */
    public static function sanitize_critical_css( $css ) {
        if ( empty( $css ) ) {
            return '';
        }

        $css = wp_strip_all_tags( $css );
        $css = preg_replace( '/<\?php|<\?|\?>/i', '', $css );

        $dangerous_patterns = array(
            '/javascript\s*:/i',
            '/expression\s*\(/i',
            '/behavior\s*:/i',
            '/-moz-binding\s*:/i',
            '/@import\b/i',
            '/url\s*\(\s*["\']?\s*data\s*:\s*text\/html/i',
        );
        $css = preg_replace( $dangerous_patterns, '', $css );

        return trim( $css );
    }

    /**
     * Directly update static HTML cache files on disk to fill Critical CSS and strip extractor script
     *
     * @param string $critical_css
     * @return int Number of updated cache files
     */
    private static function update_static_html_cache_files( $critical_css ) {
        $wp_rocket_dir = WP_CONTENT_DIR . '/cache/wp-rocket';
        if ( ! is_dir( $wp_rocket_dir ) ) {
            return 0;
        }

        $style_replacement = '<style id="uwb-critical-css">' . trim( $critical_css ) . '</style>';
        $empty_placeholder = '<style id="uwb-critical-css"></style>';
        $updated_count     = 0;

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator( $wp_rocket_dir, \RecursiveDirectoryIterator::SKIP_DOTS ),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ( $iterator as $item ) {
                if ( $item->isFile() && strpos( $item->getFilename(), '.html' ) !== false ) {
                    $f = $item->getPathname();
                    if ( filesize( $f ) > 100 ) {
                        $content = @file_get_contents( $f );
                        if ( strpos( $content, $empty_placeholder ) !== false ) {
                            // 1. Fill Critical CSS into placeholder
                            $content = str_replace( $empty_placeholder, $style_replacement, $content );

                            // 2. Completely REMOVE extractor script tag from static HTML cache
                            $content = preg_replace( '#<script\b[^>]*?id=[\'"]uwb-critical-extractor[\'"][^>]*?>.*?</script>#is', '', $content );

                            @file_put_contents( $f, $content );
                            $updated_count++;

                            // 3. Purge matching .gzip file if present so webserver serves updated HTML
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
                    if ( strpos( $content, $empty_placeholder ) !== false ) {
                        $content = str_replace( $empty_placeholder, $style_replacement, $content );
                        $content = preg_replace( '#<script\b[^>]*?id=[\'"]uwb-critical-extractor[\'"][^>]*?>.*?</script>#is', '', $content );
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

    /**
     * Minify CSS string
     *
     * @param string $css
     * @return string
     */
    public static function minify_css( $css ) {
        if ( empty( $css ) ) {
            return '';
        }
        $css = preg_replace( '!/\*.*?\*/!s', '', $css );
        $css = preg_replace( '/\s+/', ' ', $css );
        $css = str_replace( array( " {", "{ ", " }", "} ", " :", ": ", " ;", "; ", " ,", ", " ), array( "{", "{", "}", "}", ":", ":", ";", ";", ",", "," ), $css );
        return trim( $css );
    }

    /**
     * Purge cache placeholder
     */
    public static function purge_cache() {
        // No-op: Static HTML cache files are purged by CacheManager
    }
}
