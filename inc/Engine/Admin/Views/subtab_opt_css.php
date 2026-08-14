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
                                    $this->render_toggle_switch( 'uwb_auto_critical_css', 'Automatic Server-Side Critical CSS Generation', __( 'Automatically extract above-the-fold CSS rules during first cache generation and inject them directly into the <head>.', 'ultimate-wp-booster' ) );
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
                                        $this->render_textarea_setting( 'uwb_tuning_critical_css', 'Custom Above-The-Fold CSS (Manual Addition)', '', __( 'Enter custom CSS rules to output in a separate <style id="uwb-manual-critical-css"> tag in the <head> (injected in parallel with automatic Critical CSS with highest priority).', 'ultimate-wp-booster' ) );
                                        ?>
                                    </div>

                                    <?php

                                    $this->render_cdn_distribution_card(
                                        'Cloudflare R2 / S3 CDN Distribution for CSS',
                                        'uwb_cdn_distribute_css',
                                        __( 'Distribute CSS via S3 CDN?', 'ultimate-wp-booster' ),
                                        __( 'Upload minified or combined CSS files to S3/R2 CDN to optimize page load speed.', 'ultimate-wp-booster' ),
                                        array(
                                            'Upload to S3 when:' => array(
                                                'uwb_cdn_auto_upload_combined_css' => __( 'Combined CSS file creation', 'ultimate-wp-booster' ),
                                                'uwb_cdn_auto_upload_minified_css' => __( 'Minified CSS file creation', 'ultimate-wp-booster' ),
                                            ),
                                            'Delete From S3 when:' => array(
                                                'uwb_cdn_auto_purge_css_cdn' => __( 'Purge CSS cache', 'ultimate-wp-booster' ),
                                            ),
                                        )
                                     );
                                    $this->render_page_optimizer_tools_section( 'CSS Tools' );
                                    ?>
                                    <?php $this->render_module_banner_end(); ?>
                                </div>
                            </div>
