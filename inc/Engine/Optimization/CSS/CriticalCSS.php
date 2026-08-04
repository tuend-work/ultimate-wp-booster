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
    public static function generate( $html, $url = '', $manual_css = '' ) {
        if ( empty( $html ) ) {
            return $html;
        }

        $enabled = (int) get_option( 'uwb_auto_critical_css', 1 );
        if ( ! $enabled && empty( $manual_css ) ) {
            return $html;
        }

        $manual_clean = ! empty( $manual_css ) ? self::minify_css( self::sanitize_critical_css( $manual_css ) ) : '';

        // Inject Custom Manual Critical CSS in a separate tag with highest priority
        if ( ! empty( $manual_clean ) && strpos( $html, 'id="uwb-manual-critical-css"' ) === false ) {
            $manual_tag = '<style id="uwb-manual-critical-css">' . $manual_clean . '</style>';
            if ( preg_match( '/<head[^>]*>/i', $html, $matches ) ) {
                $html = str_replace( $matches[0], $matches[0] . "\n" . $manual_tag, $html );
            }
        }

        if ( ! $enabled ) {
            return $html;
        }

        // If HTML already contains filled Auto Critical CSS inside <style id="uwb-critical-css">
        if ( preg_match( '#<style\b[^>]*?id=[\'"]uwb-critical-css[\'"][^>]*?>(.*?)</style>#is', $html, $css_matches ) ) {
            $existing_css = isset( $css_matches[1] ) ? trim( $css_matches[1] ) : '';
            if ( ! empty( $existing_css ) && strlen( $existing_css ) > 10 ) {
                return $html;
            }
        }

        if ( empty( $url ) ) {
            $url = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '/';
        }

        $url_clean = strtok( $url, '?' );
        $url_hash = md5( $url_clean );

        // Inject Auto Critical CSS Placeholder in <head> if not present
        $placeholder_tag = '<style id="uwb-critical-css" data-hash="' . $url_hash . '"></style>';
        if ( strpos( $html, 'id="uwb-critical-css"' ) === false ) {
            if ( preg_match( '/<head[^>]*>/i', $html, $matches ) ) {
                $html = str_replace( $matches[0], $matches[0] . "\n" . $placeholder_tag, $html );
            }
        }

        // Inject background Client-Side Extractor JS before </body> if not present
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
<!--UWB_CRIT_START-->
<script id="uwb-critical-extractor">
(function(){
    if (window.__uwb_crit_ran) return;
    window.__uwb_crit_ran = true;
    function extractCritical() {
        try {
            var styleEl = document.getElementById('uwb-critical-css');
            if (styleEl && styleEl.textContent && styleEl.textContent.trim().length > 10) {
                return;
            }
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
            if (styleEl) styleEl.textContent = finalCss;

            // Extract first-screen images
            function isSliderHidden(img) {
                var p = img.parentElement;
                while (p) {
                    if (p.classList) {
                        if (p.classList.contains('slick-slide') && !p.classList.contains('slick-active')) return true;
                        if (p.classList.contains('swiper-slide') && !p.classList.contains('swiper-slide-active') && !p.classList.contains('swiper-slide-duplicate-active')) return true;
                        if (p.classList.contains('owl-item') && !p.classList.contains('active')) return true;
                        if (p.classList.contains('carousel-item') && !p.classList.contains('active')) return true;
                        if (p.classList.contains('gallery-cell') && !p.classList.contains('is-selected')) return true;
                        if (p.classList.contains('slick-cloned')) return true;
                    }
                    p = p.parentElement;
                }
                return false;
            }

            var firstViewImages = [];
            var imgs = document.querySelectorAll('img');
            for (var j = 0; j < imgs.length; j++) {
                var img = imgs[j];
                var imgRect = img.getBoundingClientRect();
                if (imgRect.width > 2 && imgRect.height > 2 && !isSliderHidden(img)) {
                    var style = window.getComputedStyle(img);
                    if (style.display !== 'none' && style.visibility !== 'hidden' && style.opacity !== '0') {
                        var inVertical = imgRect.top <= maxVh && imgRect.bottom >= 0;
                        var inHorizontal = imgRect.left < (window.innerWidth || 1200) && imgRect.right > 0;
                        if (inVertical && inHorizontal) {
                            var src = img.getAttribute('data-src') || img.src;
                            if (src && src.indexOf('data:image') !== 0) {
                                firstViewImages.push(src);
                            }
                        }
                    }
                }
            }

            var viewportData = {
                critical_css: finalCss,
                images: firstViewImages
            };

            var payload = new FormData();
            payload.append('action', 'uwb_save_viewport_data');
            payload.append('url_hash', '<?php echo esc_js( $url_hash ); ?>');
            payload.append('token', '<?php echo esc_js( $token ); ?>');
            payload.append('viewport_data', JSON.stringify(viewportData));

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
<!--UWB_CRIT_END-->
        <?php
        return ob_get_clean();
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

        // Remove any PHP code
        $css = preg_replace( '/<\?php|<\?|\?>/i', '', $css );

        // Crucial: Prevent HTML tag breakout by stripping '</style>'
        $css = preg_replace( '#</style\b[^>]*>#i', '', $css );

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
