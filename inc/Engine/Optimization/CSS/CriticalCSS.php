<?php
namespace Ultimate_WP_Booster\Engine\Optimization\CSS;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

/**
 * Server-Side Placeholder & Client-Side Passive Auto-Extraction Engine for Critical CSS
 */
class CriticalCSS {

    /**
     * Cache directory for Critical CSS files
     */
    public static function get_cache_dir() {
        return WP_CONTENT_DIR . '/cache/ultimate-wp-booster/critical-css/';
    }

    /**
     * Main Entry Point: Inject Critical CSS if cached, or inject Placeholder + Extractor JS
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

        if ( empty( $url ) ) {
            $url = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '/';
        }

        $url_clean = strtok( $url, '?' );
        $url_hash = md5( $url_clean );
        $cache_dir = self::get_cache_dir();
        $cache_file = $cache_dir . $url_hash . '.css';

        // 1. If cached Critical CSS file exists on server: Inject directly into <head>
        if ( file_exists( $cache_file ) && filesize( $cache_file ) > 20 ) {
            $cached_css = @file_get_contents( $cache_file );
            if ( ! empty( $cached_css ) ) {
                $style_tag = '<style id="uwb-critical-css">' . trim( $cached_css ) . '</style>';
                if ( preg_match( '/<head[^>]*>/i', $html, $matches ) ) {
                    return str_replace( $matches[0], $matches[0] . "\n" . $style_tag, $html );
                }
                return $html;
            }
        }

        // 2. Fallback for Preloader / First Visit: Inject Placeholder in <head> & Extractor JS in <body>
        $placeholder_tag = '<style id="uwb-critical-css"></style>';

        if ( preg_match( '/<head[^>]*>/i', $html, $matches ) ) {
            $html = str_replace( $matches[0], $matches[0] . "\n" . $placeholder_tag, $html );
        }

        // Inject background Client-Side Extractor JS before </body>
        $extractor_script = self::render_extractor_script( $url_hash );
        if ( stripos( $html, '</body>' ) !== false ) {
            $html = str_ireplace( '</body>', $extractor_script . "\n" . '</body>', $html );
        } else {
            $html .= "\n" . $extractor_script;
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
        $nonce = function_exists( 'wp_create_nonce' ) ? wp_create_nonce( 'uwb_crit_nonce_' . $url_hash ) : '';

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
            payload.append('nonce', '<?php echo esc_js( $nonce ); ?>');
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
     * Handle AJAX endpoint to save Client-Side Extractor payload and update Static HTML cache file
     */
    public static function ajax_save_critical_css() {
        $url_hash = isset( $_POST['url_hash'] ) ? (string) $_POST['url_hash'] : '';
        $nonce    = isset( $_POST['nonce'] ) ? (string) $_POST['nonce'] : '';
        $css_raw  = isset( $_POST['critical_css'] ) ? (string) $_POST['critical_css'] : '';

        // 1. Strict MD5 Hex Validation (Prevents Path Traversal)
        if ( empty( $url_hash ) || ! preg_match( '/^[a-f0-9]{32}$/i', $url_hash ) ) {
            wp_send_json_error( array( 'message' => 'Invalid url_hash format' ) );
        }

        // 2. Strict Nonce Verification
        if ( ! wp_verify_nonce( $nonce, 'uwb_crit_nonce_' . $url_hash ) ) {
            wp_send_json_error( array( 'message' => 'Nonce verification failed' ) );
        }

        if ( empty( trim( $css_raw ) ) ) {
            wp_send_json_error( array( 'message' => 'Empty CSS payload' ) );
        }

        // 3. Payload Size Limit (Max 300KB)
        if ( strlen( $css_raw ) > 307200 ) {
            $css_raw = substr( $css_raw, 0, 307200 );
        }

        // 4. Strict CSS Sanitization & Anti-XSS / Anti-PHP Injection
        $sanitized_css = self::sanitize_critical_css( $css_raw );
        if ( empty( $sanitized_css ) ) {
            wp_send_json_error( array( 'message' => 'Invalid CSS content' ) );
        }

        // 5. Minify CSS payload
        $minified_css = self::minify_css( $sanitized_css );

        // 6. Save to cache directory securely
        $cache_dir = self::get_cache_dir();
        if ( ! is_dir( $cache_dir ) ) {
            @wp_mkdir_p( $cache_dir );
        }

        $target_file = $cache_dir . $url_hash . '.css';
        @file_put_contents( $target_file, $minified_css );

        // 7. Update static HTML cache files safely
        self::update_static_html_cache_files( $minified_css );

        wp_send_json_success( array( 'message' => 'Critical CSS saved securely', 'bytes' => strlen( $minified_css ) ) );
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

        // Strip any HTML tags (<script>, <iframe>, <style>, etc.)
        $css = wp_strip_all_tags( $css );

        // Remove PHP tags
        $css = preg_replace( '/<\?php|<\?|\?>/i', '', $css );

        // Disallow dangerous CSS functions & expressions
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
     * Update static HTML cache files on disk to fill placeholder <style id="uwb-critical-css"></style>
     *
     * @param string $critical_css
     */
    private static function update_static_html_cache_files( $critical_css ) {
        $wp_rocket_dir = WP_CONTENT_DIR . '/cache/wp-rocket';
        if ( ! is_dir( $wp_rocket_dir ) ) {
            return;
        }

        $style_replacement = '<style id="uwb-critical-css">' . trim( $critical_css ) . '</style>';
        $empty_placeholder = '<style id="uwb-critical-css"></style>';

        $html_files = glob( $wp_rocket_dir . '/*/*/index*.html' );
        if ( is_array( $html_files ) ) {
            foreach ( $html_files as $f ) {
                if ( file_exists( $f ) && filesize( $f ) > 100 ) {
                    $content = @file_get_contents( $f );
                    if ( strpos( $content, $empty_placeholder ) !== false ) {
                        $content = str_replace( $empty_placeholder, $style_replacement, $content );
                        @file_put_contents( $f, $content );
                    }
                }
            }
        }
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
     * Purge all generated Critical CSS files
     */
    public static function purge_cache() {
        $dir = self::get_cache_dir();
        if ( is_dir( $dir ) ) {
            $files = glob( $dir . '*.css' );
            if ( ! empty( $files ) ) {
                foreach ( $files as $f ) {
                    @unlink( $f );
                }
            }
        }
    }
}
