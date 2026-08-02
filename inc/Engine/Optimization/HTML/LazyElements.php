<?php
namespace Ultimate_WP_Booster\Engine\Optimization\HTML;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

/**
 * Lazy Load HTML Elements (Perfmatters technique with <noscript> SEO Fallback)
 * Supports standard CSS selectors (.footer-wrapper, footer.footer-wrapper, #comments, .product_list_widget > li).
 */
class LazyElements {

    /**
     * Process HTML markup to lazy-load targeted elements using simple_html_dom.
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

        if ( ! function_exists( 'str_get_html' ) ) {
            $dep_path = defined( 'UWB_PLUGIN_DIR' ) ? UWB_PLUGIN_DIR . 'inc/Dependencies/simple_html_dom.php' : dirname( __DIR__, 3 ) . '/Dependencies/simple_html_dom.php';
            if ( file_exists( $dep_path ) ) {
                require_once $dep_path;
            }
        }

        if ( ! function_exists( 'str_get_html' ) ) {
            return $html;
        }

        $lines = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", '', (string) $selectors_text ) ) ) );
        if ( empty( $lines ) ) {
            return $html;
        }

        $dom = str_get_html( $html );
        if ( ! $dom ) {
            return $html;
        }

        $count = 0;
        $elem_index = 0;

        foreach ( $lines as $selector ) {
            $selector = trim( $selector );
            if ( empty( $selector ) ) continue;

            $elements = $dom->find( $selector );
            if ( empty( $elements ) ) continue;

            foreach ( $elements as $element ) {
                $class_attr = $element->getAttribute( 'class' );
                if ( is_string( $class_attr ) && strpos( $class_attr, 'uwb-lazy-element' ) !== false ) {
                    continue;
                }

                $inner_html = $element->innertext();
                if ( empty( trim( $inner_html ) ) ) {
                    continue;
                }

                $elem_index++;
                $count++;

                $template_id = 'uwb-lazy-elem-' . $elem_index;

                // Add uwb-lazy-element class and data-uwb-target attribute to element node
                $current_classes = ! empty( $class_attr ) ? trim( $class_attr ) : '';
                $new_classes = ! empty( $current_classes ) ? $current_classes . ' uwb-lazy-element' : 'uwb-lazy-element';

                $element->setAttribute( 'class', $new_classes );
                $element->setAttribute( 'data-uwb-target', $template_id );

                // Place <noscript> fallback inside container and append <template> sibling right after element
                $element->innertext = '<noscript class="uwb-lazy-noscript">' . $inner_html . '</noscript>';
                $element->outertext = $element->outertext() . '<template id="' . $template_id . '">' . $inner_html . '</template>';
            }
        }

        if ( $count > 0 ) {
            $html = $dom->save();
            $html = self::inject_observer_js( $html );
            if ( is_array( $debug_logs ) ) {
                $debug_logs[] = "Lazy Load Elements: Processed {$count} elements with <noscript> SEO fallback";
            }
        } else {
            $html = $dom->save();
        }

        $dom->clear();
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

                    console.log("⚡ [UWB Lazy Elements] Successfully lazy-loaded element:", container, "Target ID:", targetId);

                    window.dispatchEvent(new CustomEvent("uwb_element_lazy_loaded", { detail: { element: container } }));
                    if (window.uwbLazyObserver && typeof window.uwbLazyObserver.observe === "function") {
                        container.querySelectorAll("img.uwb-lazy, iframe.uwb-lazy").forEach(function(el) {
                            window.uwbLazyObserver.observe(el);
                        });
                    }
                } else {
                    console.warn("⚠️ [UWB Lazy Elements] Template missing or container error for:", container, "Target ID:", targetId);
                }
            }
        });
    }, { rootMargin: "200px 0px" });

    function initLazyElements() {
        var elements = document.querySelectorAll(".uwb-lazy-element");
        if (elements.length > 0) {
            console.log("🚀 [UWB Lazy Elements] Initialized " + elements.length + " lazy element(s) waiting for scroll:", elements);
            elements.forEach(function(el) {
                observer.observe(el);
            });
        }
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


