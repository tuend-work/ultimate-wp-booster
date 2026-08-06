<?php
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
// Views/subtab_opt_css.php — CSS Optimization
?>
                            <div id="subtab-opt_css" class="uwb-subtab-content">
                                <div style="background:#f8fafc; border:1px solid var(--uwb-border); border-radius:12px; padding:24px; margin-bottom:24px;">
                                    <?php $this->render_submodule_header( 'uwb_module_css_enabled', 'CSS Optimization Settings', '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/><line x1="20" y1="20" x2="4" y2="4"/></svg>' ); ?>
                                    <?php
                                    $this->render_toggle_switch( 'uwb_css_minify', 'CSS Minify', 'Minify CSS files and inline CSS code.' );
                                    $this->render_toggle_switch( 'uwb_css_combine', 'CSS Combine', 'Combine CSS stylesheets into a single cached file to reduce HTTP requests.' );
                                    $this->render_toggle_switch( 'uwb_css_combine_ext_inline', 'CSS Combine External and Inline', 'Include external CSS files and inline CSS code in the combined CSS bundle.' );
                                    $this->render_textarea_setting( 'uwb_tuning_css_excludes', 'CSS Minify & Combine Excludes', '', 'CSS files or inline keywords to exclude from minification/combination (one per line).' );
                                    $this->render_toggle_switch( 'uwb_css_load_async', 'Load CSS Asynchronously', 'Load CSS files asynchronously to eliminate render-blocking CSS and speed up page rendering.' );
                                    $this->render_toggle_switch( 'uwb_auto_critical_css', 'Automatic Server-Side Critical CSS Generation', 'Tự động trích xuất các quy tắc CSS hiển thị ở màn hình đầu tiên (Above-The-Fold) ngay khi tạo Cache trang lần đầu tiên và nhúng trực tiếp vào &lt;head&gt;.' );
                                    ?>

                                    <!-- Critical CSS Management Card -->
                                    <div style="background:#fff; border:1px solid var(--uwb-border); border-radius:12px; padding:20px; margin-top:16px; margin-bottom:24px;">
                                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; flex-wrap:wrap; gap:10px;">
                                            <h4 style="margin:0; font-size:14px; font-weight:700; color:var(--uwb-text); display:flex; align-items:center; gap:8px;">
                                                ⚡ Auto Critical CSS &amp; Custom Manual Addition
                                            </h4>
                                            <button type="button" id="btn-clear-critical-css" class="button button-secondary button-small" style="font-weight:600; padding:4px 12px; border-radius:6px; cursor:pointer;">
                                                Clear Critical CSS Cache
                                            </button>
                                        </div>
                                        <?php
                                        $this->render_textarea_setting( 'uwb_tuning_critical_css', 'Custom Above-The-Fold CSS (Manual Addition)', '', 'Nhập các quy tắc CSS tùy chỉnh để xuất ra thẻ &lt;style id="uwb-manual-critical-css"&gt; riêng biệt trong &lt;head&gt; (chèn song song với Critical CSS tự động và có độ ưu tiên cao nhất).' );
                                        ?>
                                    </div>

                                    <?php

                                    $this->render_cdn_distribution_card(
                                        'Cloudflare R2 / S3 CDN Distribution for CSS',
                                        'uwb_cdn_distribute_css',
                                        'Phân phối CSS qua S3 CDN?',
                                        'Tải các tập tin CSS đã được nén (minify) hoặc gộp (combine) lên S3/R2 CDN để tối ưu hóa tốc độ tải trang.',
                                        array(
                                            'Upload to S3 when:' => array(
                                                'uwb_cdn_auto_upload_combined_css' => 'upload (gộp file CSS)',
                                                'uwb_cdn_auto_upload_minified_css' => 'edit (nén file CSS)',
                                            ),
                                            'Delete From S3 when:' => array(
                                                'uwb_cdn_auto_purge_css_cdn' => 'Delete file (xả cache CSS)',
                                            ),
                                        )
                                     );
                                    $this->render_page_optimizer_tools_section( 'CSS Tools' );
                                    ?>
                                    <?php $this->render_module_banner_end(); ?>
                                </div>
                            </div>

