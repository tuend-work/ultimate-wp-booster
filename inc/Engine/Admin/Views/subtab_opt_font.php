<?php
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
// Views/subtab_opt_font.php — Font Optimizer
?>
                            <div id="subtab-opt_font" class="uwb-subtab-content">
                                <div style="background:#f8fafc; border:1px solid var(--uwb-border); border-radius:12px; padding:24px; margin-bottom:24px;">
                                    <?php $this->render_submodule_header( 'uwb_module_font_enabled', 'Font Optimization Settings', '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M4 7V4h16v3"/><path d="M9 20h6"/><path d="M12 4v16"/></svg>' ); ?>
                                    <?php
                                    $this->render_toggle_switch( 'uwb_css_font_display_opt', 'Font Display Swap', 'Automatically injects <code>font-display: swap</code> into all CSS <code>@font-face</code> declarations (including theme icon fonts like <code>fl-icons.woff2</code>) and Google Fonts to ensure text remains visible during font load and pass PageSpeed Insights audit.' );
                                    $this->render_textarea_setting(
                                        'uwb_preload_fonts',
                                        'Preload Key Fonts',
                                        "/wp-content/themes/flatsome/assets/css/icons/fl-icons.woff2?v=3.20.7\n/wp-content/themes/your-theme/fonts/font.woff2",
                                        'Inject <code>&lt;link rel="preload" as="font" crossorigin&gt;</code> tags to prioritize critical font loading and eliminate FOUT/FOIT. One font URL per line (.woff2 recommended).'
                                    );
                                    $this->render_textarea_setting(
                                        'uwb_preconnect_domains',
                                        'Preconnect External Font Domains',
                                        "https://fonts.googleapis.com\nhttps://fonts.gstatic.com",
                                        'Inject <code>&lt;link rel="preconnect"&gt;</code> tags into HTML <code>&lt;head&gt;</code> to preconnect external font domains (one per line).'
                                    );
                                    $this->render_toggle_switch( 'uwb_html_remove_gfonts', 'Remove Google Fonts', 'Completely remove Google Fonts requests to improve page load speed and privacy compliance.' );

                                    $this->render_cdn_distribution_card(
                                        'Cloudflare R2 / S3 CDN Distribution for Web Fonts',
                                        'uwb_cdn_distribute_font',
                                        'Phân phối Web Fonts qua S3 CDN?',
                                        'Tải và phục vụ các font chữ tùy chỉnh (.woff2, .woff, .ttf) của theme từ S3/R2 CDN.',
                                        array(
                                            'Upload to S3 when:' => array(
                                                'uwb_cdn_auto_upload_fonts'     => 'upload',
                                                'uwb_cdn_auto_rewrite_font_urls' => 'get_url',
                                            ),
                                        )
                                    );
                                    ?>
                                    <?php $this->render_module_banner_end(); ?>
                                </div>
                            </div>

                            <!-- SUB-TAB 6: CDN Offload Media -->
