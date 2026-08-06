<?php
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
// Views/subtab_preload_sitemap.php
?>
                            <div id="subtab-preload_sitemap_sub" class="uwb-subtab-content">
                                <div class="uwb-form-group">
                                    <label for="uwb_preload_sitemap">Sitemap XML URLs</label>
                                    <textarea name="uwb_preload_sitemap" id="uwb_preload_sitemap" rows="5" placeholder="<?php echo esc_attr( home_url( '/important-sitemap.xml' ) . "\n" . home_url( '/wp-sitemap.xml' ) ); ?>"><?php echo esc_textarea( $this->get_preload_sitemap_setting_value() ); ?></textarea>
                                    <p class="description">Enter full URLs or relative paths of your XML sitemaps to crawl (one per line). Example: <code><?php echo esc_url( home_url( '/wp-sitemap.xml' ) ); ?></code></p>
                                </div>

                                <!-- Important Sitemap Section -->
                                <div style="background:#f8fafc; border:1px solid var(--uwb-border); border-radius:12px; padding:24px; margin-top:24px; margin-bottom:24px;">
                                    <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:16px;">
                                        <div>
                                            <h3 style="margin:0; font-size:15px; color:var(--uwb-text); display:flex; align-items:center; gap:8px;">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                                Important Sitemap Builder (<code>/important-sitemap.xml</code>)
                                            </h3>
                                            <p style="font-size:12.5px; color:var(--uwb-text-muted); margin:4px 0 0 0;">
                                                Generates a high-priority XML feed at <code><?php echo esc_url( home_url( '/important-sitemap.xml' ) ); ?></code> containing essential URLs.
                                            </p>
                                        </div>
                                        
                                        <!-- Toggle Switch for Important Sitemap Section -->
                                        <label class="uwb-switch" style="position:relative; display:inline-block; width:44px; height:24px;">
                                            <input type="checkbox" name="uwb_important_sitemap_enabled" id="uwb_important_sitemap_enabled" value="1" <?php checked( get_option( 'uwb_important_sitemap_enabled', 1 ), 1 ); ?> style="opacity:0; width:0; height:0;" onchange="jQuery('#uwb-important-sitemap-wrap').toggle(this.checked);" />
                                            <span class="uwb-slider round" style="position:absolute; cursor:pointer; top:0; left:0; right:0; bottom:0; background-color:#cbd5e1; transition:.4s; border-radius:24px;"></span>
                                        </label>
                                    </div>

                                    <div id="uwb-important-sitemap-wrap" style="display: <?php echo get_option( 'uwb_important_sitemap_enabled', 1 ) ? 'block' : 'none'; ?>; border-top:1px solid var(--uwb-border); padding-top:20px; margin-top:16px;">
                                        <!-- Checkbox: Homepage links -->
                                        <div class="uwb-form-group" style="margin-bottom:20px;">
                                            <label style="display:flex; align-items:center; gap:8px; font-weight:600; cursor:pointer;">
                                                <input type="checkbox" name="uwb_imp_homepage_links" value="1" <?php checked( get_option( 'uwb_imp_homepage_links', 1 ), 1 ); ?> />
                                                🌐 Toàn bộ link trên trang chủ (Scrape &amp; prioritize all internal links found on Home page)
                                            </label>
                                        </div>

                                        <!-- Checkbox: Taxonomies -->
                                        <div class="uwb-form-group" style="margin-bottom:20px;">
                                            <label style="display:flex; align-items:center; gap:8px; font-weight:600; cursor:pointer;">
                                                <input type="checkbox" name="uwb_imp_taxonomies_enabled" id="uwb_imp_taxonomies_enabled" value="1" <?php checked( get_option( 'uwb_imp_taxonomies_enabled', 1 ), 1 ); ?> onchange="jQuery('#uwb-imp-tax-options-wrap').toggle(this.checked);" />
                                                📂 Danh sách các loại Taxonomy (Categories, Tags, Product Categories, Product Tags)
                                            </label>

                                            <div id="uwb-imp-tax-options-wrap" style="display: <?php echo get_option( 'uwb_imp_taxonomies_enabled', 1 ) ? 'block' : 'none'; ?>; margin-left:26px; margin-top:14px; padding-left:16px; border-left:3px solid var(--uwb-primary);">
                                                <div style="display:flex; gap:20px; margin-bottom:14px;">
                                                    <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-weight:600; font-size:13px;">
                                                        <input type="radio" name="uwb_imp_taxonomy_mode" value="all" <?php checked( get_option( 'uwb_imp_taxonomy_mode', 'all' ), 'all' ); ?> onchange="jQuery('#uwb-imp-tax-terms-list').hide();" />
                                                        All terms (Tất cả taxonomy terms công khai)
                                                    </label>
                                                    <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-weight:600; font-size:13px;">
                                                        <input type="radio" name="uwb_imp_taxonomy_mode" value="specific" <?php checked( get_option( 'uwb_imp_taxonomy_mode', 'all' ), 'specific' ); ?> onchange="jQuery('#uwb-imp-tax-terms-list').show();" />
                                                        Chọn từng term cụ thể (HOT, NEW, ...)
                                                    </label>
                                                </div>

                                                <div id="uwb-imp-tax-terms-list" style="display: <?php echo get_option( 'uwb_imp_taxonomy_mode', 'all' ) === 'specific' ? 'block' : 'none'; ?>;">
                                                    <?php $this->render_taxonomy_terms_checklist(); ?>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Manual Important URLs -->
                                        <div class="uwb-form-group" style="margin-bottom:0;">
                                            <label for="uwb_priority_urls">Custom Important URLs (Hand-picked priority links)</label>
                                            <textarea name="uwb_priority_urls" id="uwb_priority_urls" rows="4"><?php echo esc_textarea( $this->get_priority_urls_setting_value() ); ?></textarea>
                                            <p class="description">Additional custom URLs or matching keywords to include in the important sitemap (one per line).</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- SUB-TAB 4: Simulation & Custom Headers -->
