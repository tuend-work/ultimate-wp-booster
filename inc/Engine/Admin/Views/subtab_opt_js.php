<?php
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
// Views/subtab_opt_js.php — JS Optimization
?>
                            <div id="subtab-opt_js" class="uwb-subtab-content">
                                <div style="background:#f8fafc; border:1px solid var(--uwb-border); border-radius:12px; padding:24px; margin-bottom:24px;">
                                    <?php $this->render_submodule_header( 'uwb_module_js_enabled', 'JS Optimization Settings', '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>' ); ?>
                                    <h4 style="margin: 0 0 16px 0; font-size: 14px; font-weight: 700; color: var(--uwb-text); border-bottom: 1px solid var(--uwb-border); padding-bottom: 8px;">Minification &amp; Combination</h4>
                                    <?php
                                    $delay_js_on = (bool) get_option( 'uwb_delay_js', 0 );
                                    $this->render_toggle_switch( 'uwb_js_minify', 'JS Minify', 'Minify JS files and inline JS code.' );
                                    $this->render_toggle_switch( 'uwb_js_combine', 'JS Combine', 'Combine JavaScript files into a single cached file to reduce HTTP requests.', $delay_js_on );
                                    if ( $delay_js_on ) : ?>
                                    <div style="display:flex; align-items:flex-start; gap:10px; background:#fffbeb; border:1px solid #fbbf24; border-radius:8px; padding:12px 16px; margin-bottom:16px; font-size:13px; color:#92400e; margin-top:-8px;">
                                        <svg style="flex-shrink:0; margin-top:1px;" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                        <span><strong>Disabled automatically:</strong> JS Combine is not compatible with Delay JavaScript Execution. Disable Delay JS first to enable JS Combine.</span>
                                    </div>
                                    <?php endif; ?>
                                    <?php
                                    $this->render_toggle_switch( 'uwb_js_combine_ext_inline', 'JS Combine External and Inline', 'Include external JS files and inline JS code in the combined JS bundle.', $delay_js_on );
                                    $this->render_textarea_setting( 'uwb_tuning_js_excludes', 'JS Minify & Combine Excludes', '', 'JS files or inline keywords to exclude from minification/combination (one per line).' );
                                    ?>

                                    <h4 style="margin: 24px 0 16px 0; font-size: 14px; font-weight: 700; color: var(--uwb-text); border-bottom: 1px solid var(--uwb-border); padding-bottom: 8px;">Load JS Deferred</h4>
                                    <?php
                                    $js_quick_fix_notice = '<br><span style="display:block; margin-top:8px; padding:10px 14px; background:#f8fafc; border-left:3px solid #6366f1; border-radius:6px; font-size:12px; color:#334155; line-height:1.5;"><strong>💡 Quick Fix:</strong> If you have problems after activating this option, copy and paste the default exclusions to quickly resolve issues:<br><code style="display:block; margin-top:6px; padding:8px 10px; background:#0f172a; color:#f8fafc; border-radius:6px; font-family:monospace; font-size:11.5px; white-space:pre; overflow-x:auto;">\/jquery(-migrate)?-?([0-9.]+)?(.min|.slim|.slim.min)?.js(\?(.*))?( |\'|"|&gt;)
js-(before|after)
(?:/wp-content/|/wp-includes/)(.*)</code></span>';

                                    $this->render_toggle_switch( 'uwb_js_load_defer', 'Load JS Deferred', 'Load JS with defer attribute so scripts download in background without blocking DOM parsing.' . $js_quick_fix_notice );
                                    $this->render_textarea_setting( 'uwb_tuning_js_defer_excludes', 'JS Deferred Excludes', '', 'JS files or inline keywords to exclude from deferred loading (one per line).' );
                                    ?>

                                    <h4 style="margin: 24px 0 16px 0; font-size: 14px; font-weight: 700; color: var(--uwb-text); border-bottom: 1px solid var(--uwb-border); padding-bottom: 8px; display:flex; align-items:center; gap:8px;">
                                        Delay JavaScript Execution
                                        <span style="background:#7c3aed; color:#fff; font-size:10px; padding:2px 8px; border-radius:12px; font-weight:700;">NEW</span>
                                    </h4>
                                    <?php
                                    $this->render_toggle_switch( 'uwb_delay_js', 'Enable Delay JS', 'Delay execution of JavaScript until first user interaction (scroll, click, keypress). Dramatically improves <strong>LCP</strong> and <strong>TBT</strong> scores.' . $js_quick_fix_notice );
                                    $this->render_textarea_setting(
                                        'uwb_delay_js_exclusions',
                                        'Delay JS Exclusions',
                                        "jquery.js\njquery.min.js\njquery-migrate.min.js\nga.js\ngtm.js",
                                        'One pattern per line. Scripts matching these patterns will NOT be delayed.',
                                        ! intval( get_option( 'uwb_delay_js', 0 ) )
                                    );

                                    $this->render_cdn_distribution_card(
                                        'Cloudflare R2 / S3 CDN Distribution for JS',
                                        'uwb_cdn_distribute_js',
                                        'Phân phối JS qua S3 CDN?',
                                        'Tải các tập tin JavaScript đã được nén (minify) hoặc gộp (combine) lên S3/R2 CDN để phục vụ khách truy cập.',
                                        array(
                                            'Upload to S3 when:' => array(
                                                'uwb_cdn_auto_upload_combined_js' => 'upload (gộp file JS)',
                                                'uwb_cdn_auto_upload_minified_js' => 'edit (nén file JS)',
                                            ),
                                            'Delete From S3 when:' => array(
                                                'uwb_cdn_auto_purge_js_cdn' => 'Delete file (xả cache JS)',
                                            ),
                                        )
                                    );
                                    $this->render_page_optimizer_tools_section( 'JS Tools' );
                                    ?>
                                    <?php $this->render_module_banner_end(); ?>
                                </div>
                            </div>

