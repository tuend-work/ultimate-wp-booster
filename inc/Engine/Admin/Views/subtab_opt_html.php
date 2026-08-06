<?php
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
// Views/subtab_opt_html.php — HTML Optimization
?>
                            <div id="subtab-opt_html" class="uwb-subtab-content">
                                <div style="background:#f8fafc; border:1px solid var(--uwb-border); border-radius:12px; padding:24px; margin-bottom:24px;">
                                    <?php $this->render_submodule_header( 'uwb_module_html_enabled', 'HTML Optimization Settings', '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>' ); ?>
                                <?php
                                $this->render_toggle_switch( 'uwb_html_minify', 'HTML Minify', 'Minify HTML source code.' );

                                $is_lazy_elem_on = (bool) get_option( 'uwb_html_lazy_load_elements_enabled', 0 );
                                $this->render_toggle_switch( 'uwb_html_lazy_load_elements_enabled', 'Lazy Load Elements', 'Lazy load heavy HTML elements (e.g. <code>#comments</code>, <code>.footer-widgets</code>, <code>#related-products</code>) using <code>IntersectionObserver</code> to reduce initial DOM size and improve FCP, TBT, and LCP.' );
                                ?>

                                <div id="uwb-lazy-elements-textarea-wrap" style="margin-bottom:24px; <?php echo $is_lazy_elem_on ? '' : 'display:none;'; ?>">
                                    <?php
                                    $this->render_textarea_setting(
                                        'uwb_html_lazy_load_elements',
                                        'Lazy Load Element Selectors',
                                        "#comments\n.product_list_widget > li\n.footer-widgets\n#related-products\n.widget-area",
                                        'Specify CSS selectors (IDs, class names, or parent > child rules) of heavy HTML elements to lazy load (one per line).<br>Example: <code>#comments</code>, <code>.product_list_widget > li</code>, <code>.site-footer</code>, <code>#related-products</code>, <code>.widget-area</code>'
                                    );
                                    $this->render_textarea_setting(
                                        'uwb_html_lazy_load_elements_excludes',
                                        'Exclude Lazy Load Element Selectors',
                                        "#header\n.no-lazy-element\nfooter.no-lazy",
                                        'Specify CSS selectors (IDs, class names, or element tags) that should NEVER be lazy loaded (one per line).<br>Example: <code>#header</code>, <code>.no-lazy-widget</code>, <code>#flatsome_recent_posts-2</code>'
                                    );
                                    ?>
                                    <div class="uwb-warning-box" style="margin-top: -8px; background: #eff6ff; border-left: 4px solid #3b82f6; color: #1e40af; padding: 16px; border-radius: 8px;">
                                        <strong style="display:block; font-size:13.5px; margin-bottom:6px; color:#1e3a8a;">💡 SEO &amp; Troubleshooting Guidelines for Lazy Loading Elements (Perfmatters Standard):</strong>
                                        <ul style="margin: 0; padding-left: 20px; font-size: 12.5px; line-height: 1.6; color: #1e40af;">
                                            <li><strong>100% Indexable via <code>&lt;noscript&gt;</code> Fallback:</strong> Content inside lazy-loaded elements is preserved inside <code>&lt;noscript&gt;</code> tags. Search engine crawlers (Googlebot) read <code>&lt;noscript&gt;</code> tags, making all text, links, and schema 100% crawlable &amp; indexable (verifiable via Google Search Console URL Inspection &amp; Rich Results Test).</li>
                                            <li><strong>DOM Monitoring:</strong> When a lazy element is rendered upon user scroll, DOM Monitoring automatically re-triggers image/iframe lazyloading for all nested assets inside the loaded element tree.</li>
                                            <li><strong>Avoid Above-the-fold &amp; Lightbox:</strong> Do NOT lazy load above-the-fold / LCP elements (header, hero banners) or elements containing images that initiate a lightbox popup.</li>
                                        </ul>
                                    </div>
                                </div>
                                <?php
                                $this->render_toggle_switch( 'uwb_html_remove_qs', 'Remove Query Strings', 'Remove query strings from static resources.' );
                                $this->render_toggle_switch( 'uwb_general_disable_emojis', 'Disable Emojis', 'Remove default WordPress emoji styling and detection script.' );
                                $this->render_toggle_switch( 'uwb_html_remove_noscript', 'Remove Noscript Tags', 'Remove all noscript tags from HTML.' );
                                $this->render_toggle_switch( 'uwb_general_remove_global_styles', 'Remove Global Styles', 'Remove default global inline styles and Gutenberg block library CSS.' );
                                $this->render_toggle_switch( 'uwb_general_add_blank_favicon', 'Add Blank Favicon', 'Inject a blank base64 favicon to stop browsers requesting a favicon if not set.' );
                                $this->render_toggle_switch( 'uwb_general_remove_comment_urls', 'Remove Comment URLs', 'Remove the website URL field from the default comment form.' );
                                $this->render_toggle_switch( 'uwb_general_remove_rest_api_links', 'Remove REST API Links', 'Remove REST API discovery links from page headers and headers responses.' );
                                $this->render_toggle_switch( 'uwb_general_remove_rss_feed_links', 'Remove RSS Feed Links', 'Remove RSS feed and comment feed links from page head.' );
                                $this->render_toggle_switch( 'uwb_general_remove_shortlink', 'Remove Shortlink', 'Remove shortlinks from page head and HTTP headers response.' );
                                $this->render_toggle_switch( 'uwb_general_remove_rsd', 'Remove RSD Link', 'Remove Real Simple Discovery (RSD) link tag from page head.' );
                                $this->render_toggle_switch( 'uwb_general_remove_wlwmanifest', 'Remove wlwmanifest Link', 'Remove Windows Live Writer manifest XML link tag from page head.' );
                                $this->render_toggle_switch( 'uwb_general_hide_wp_version', 'Hide WP Version', 'Hide WordPress version generator meta tag and query args from scripts/styles.' );

                                $this->render_page_optimizer_tools_section( 'HTML Tools' );
                                ?>
                                <?php $this->render_module_banner_end(); ?>
                                </div>
                            </div>

                            <!-- SUB-TAB 4: Media Settings & Excludes -->
