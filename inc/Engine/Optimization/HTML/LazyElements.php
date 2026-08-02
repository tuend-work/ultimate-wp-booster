<?php
namespace Ultimate_WP_Booster\Engine\Optimization\HTML;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

/**
 * Lazy Load HTML Elements (Perfmatters technique with <noscript> SEO Fallback)
 * Supports standard selectors (#comments, .footer-widgets) and parent > child combinators (.product_list_widget > li).
 */
class LazyElements {

    /**
     * Process HTML markup to lazy-load targeted elements.
     *
     * @param string     $html
     * @param string     $selectors_text
     * @param array|null $debug_logs
     * @return string
     */
    public static function process( $html, $selectors_text, &$debug_logs = null ) {
        if ( empty( $html ) || empty( $selectors_text ) ) {
            return $html;
        }

        $lines = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", '', (string) $selectors_text ) ) ) );
        if ( empty( $lines ) ) {
            return $html;
        }

        $count = 0;
        $elem_index = 0;

        foreach ( $lines as $selector ) {
            if ( empty( $selector ) ) continue;

            // Check if selector contains child combinators e.g. ".product_list_widget > li" or ".widget-area .widget"
            if ( strpos( $selector, '>' ) !== false || strpos( $selector, ' ' ) !== false ) {
                $html = self::replace_combinator_selector( $html, $selector, $elem_index, $count );
            } else {
                $selector_type = '';
                $selector_name = '';

                if ( strpos( $selector, '#' ) === 0 ) {
                    $selector_type = 'id';
                    $selector_name = substr( $selector, 1 );
                } elseif ( strpos( $selector, '.' ) === 0 ) {
                    $selector_type = 'class';
                    $selector_name = substr( $selector, 1 );
                } else {
                    $selector_type = 'tag';
                    $selector_name = $selector;
                }

                if ( empty( $selector_name ) ) continue;

                $html = self::replace_target_element( $html, $selector_type, $selector_name, $elem_index, $count );
            }
        }

        if ( $count > 0 ) {
            $html = self::inject_observer_js( $html );
            if ( is_array( $debug_logs ) ) {
                $debug_logs[] = "Lazy Load Elements: Processed {$count} elements with <noscript> SEO fallback";
            }
        }

        return $html;
    }

    /**
     * Handle parent > child combinator selectors e.g. ".product_list_widget > li" or "#my-list li"
     *
     * @param string $html
     * @param string $selector
     * @param int    $elem_index
     * @param int    $count
     * @return string
     */
    private static function replace_combinator_selector( $html, $selector, &$elem_index, &$count ) {
        $parts = preg_split( '/\s*(?:>|\s)\s*/', trim( $selector ) );
        if ( count( $parts ) < 2 ) {
            return $html;
        }

        $parent_sel = array_shift( $parts );
        $child_sel  = implode( ' ', $parts );

        $parent_type = 'tag';
        $parent_name = $parent_sel;
        if ( strpos( $parent_sel, '#' ) === 0 ) {
            $parent_type = 'id';
            $parent_name = substr( $parent_sel, 1 );
        } elseif ( strpos( $parent_sel, '.' ) === 0 ) {
            $parent_type = 'class';
            $parent_name = substr( $parent_sel, 1 );
        }

        $child_type = 'tag';
        $child_name = $child_sel;
        if ( strpos( $child_sel, '#' ) === 0 ) {
            $child_type = 'id';
            $child_name = substr( $child_sel, 1 );
        } elseif ( strpos( $child_sel, '.' ) === 0 ) {
            $child_type = 'class';
            $child_name = substr( $child_sel, 1 );
        }

        $escaped_parent = preg_quote( $parent_name, '#' );

        if ( 'id' === $parent_type ) {
            $parent_pattern = '#(<([a-z0-9]+)\b[^>]*?\bid=["\']' . $escaped_parent . '["\'][^>]*>)(.*?)(</\2>)#is';
        } elseif ( 'class' === $parent_type ) {
            $parent_pattern = '#(<([a-z0-9]+)\b[^>]*?\bclass=["\'][^"\']*?\b' . $escaped_parent . '\b[^"\']*?["\'][^>]*>)(.*?)(</\2>)#is';
        } else {
            $parent_pattern = '#(<(' . $escaped_parent . ')\b[^>]*>)(.*?)(</\2>)#is';
        }

        return preg_replace_callback( $parent_pattern, function( $p_matches ) use ( $child_type, $child_name, &$elem_index, &$count ) {
            $parent_open  = $p_matches[1];
            $parent_inner = $p_matches[3];
            $parent_close = $p_matches[4];

            $parent_inner = self::replace_target_element( $parent_inner, $child_type, $child_name, $elem_index, $count );

            return $parent_open . $parent_inner . $parent_close;
        }, $html );
    }

    /**
     * Locate and replace targeted HTML element content with <template> + <noscript>.
     *
     * @param string $html
     * @param string $type
     * @param string $name
     * @param int    $elem_index
     * @param int    $count
     * @return string
     */
    private static function replace_target_element( $html, $type, $name, &$elem_index, &$count ) {
        $escaped_name = preg_quote( $name, '#' );

        if ( 'id' === $type ) {
            $pattern = '#(<([a-z0-9]+)\b[^>]*?\bid=["\']' . $escaped_name . '["\'][^>]*>)(.*?)(</\2>)#is';
        } elseif ( 'class' === $type ) {
            $pattern = '#(<([a-z0-9]+)\b[^>]*?\bclass=["\'][^"\']*?\b' . $escaped_name . '\b[^"\']*?["\'][^>]*>)(.*?)(</\2>)#is';
        } else {
            $pattern = '#(<(' . $escaped_name . ')\b[^>]*>)(.*?)(</\2>)#is';
        }

        $html = preg_replace_callback( $pattern, function( $matches ) use ( &$elem_index, &$count ) {
            $elem_index++;
            $count++;

            $open_tag   = $matches[1];
            $inner_html = $matches[3];
            $close_tag  = $matches[4];

            // If element is already lazy loaded or contains no inner HTML, skip
            if ( strpos( $open_tag, 'uwb-lazy-element' ) !== false || empty( trim( $inner_html ) ) ) {
                return $matches[0];
            }

            $template_id = 'uwb-lazy-elem-' . $elem_index;

            // Inject class and data attribute into opening tag
            if ( strpos( $open_tag, 'class="' ) !== false ) {
                $open_tag = preg_replace( '#class="(.*?)"#i', 'class="$1 uwb-lazy-element" data-uwb-target="' . $template_id . '"', $open_tag );
            } elseif ( strpos( $open_tag, "class='" ) !== false ) {
                $open_tag = preg_replace( "#class='(.*?)'#i", "class='$1 uwb-lazy-element' data-uwb-target='" . $template_id . "'", $open_tag );
            } else {
                $open_tag = rtrim( rtrim( $open_tag, '>' ), '/' ) . ' class="uwb-lazy-element" data-uwb-target="' . $template_id . '">';
            }

            // Wrap inner HTML inside <noscript> fallback and <template> tag
            $replacement  = $open_tag;
            $replacement .= '<noscript class="uwb-lazy-noscript">' . $inner_html . '</noscript>';
            $replacement .= $close_tag;
            $replacement .= '<template id="' . $template_id . '">' . $inner_html . '</template>';

            return $replacement;
        }, $html );

        return $html;
    }

    /**
     * Inject lightweight client-side IntersectionObserver JS before </body>.
     *
     * @param string $html
     * @return string
     */
    private static function inject_observer_js( $html ) {
        if ( strpos( $html, 'uwb-lazy-elements-js' ) !== false ) {
            return $html;
        }

        $js = '<script id="uwb-lazy-elements-js">
(function(){
    if (!("IntersectionObserver" in window)) return;
    var observer = new IntersectionObserver(function(entries, obs) {
        entries.forEach(function(entry) {
            if (entry.isIntersecting) {
                var container = entry.target;
                var targetId = container.getAttribute("data-uwb-target");
                var template = document.getElementById(targetId);
                if (template && container) {
                    var content = template.content ? template.content.cloneNode(true) : null;
                    if (!content) {
                        var div = document.createElement("div");
                        div.innerHTML = template.innerHTML;
                        content = div;
                    }
                    container.innerHTML = "";
                    container.appendChild(content);
                    container.classList.remove("uwb-lazy-element");
                    container.classList.add("uwb-lazy-loaded");
                    obs.unobserve(container);

                    window.dispatchEvent(new CustomEvent("uwb_element_lazy_loaded", { detail: { element: container } }));
                    if (window.uwbLazyObserver && typeof window.uwbLazyObserver.observe === "function") {
                        container.querySelectorAll("img.uwb-lazy, iframe.uwb-lazy").forEach(function(el) {
                            window.uwbLazyObserver.observe(el);
                        });
                    }
                }
            }
        });
    }, { rootMargin: "200px 0px" });

    function initLazyElements() {
        document.querySelectorAll(".uwb-lazy-element").forEach(function(el) {
            observer.observe(el);
        });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initLazyElements);
    } else {
        initLazyElements();
    }
})();
</script>';

        if ( stripos( $html, '</body>' ) !== false ) {
            return str_ireplace( '</body>', $js . "\n</body>", $html );
        }

        return $html . $js;
    }
}
