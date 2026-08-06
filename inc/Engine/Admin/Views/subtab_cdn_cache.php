<?php
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
// Views/subtab_cdn_cache.php
?>
                            <div id="subtab-cdn_cache" class="uwb-subtab-content">
                                <!-- Section 1: Cloudflare Zone CDN Cache Integration -->
                                <div style="background:#f8fafc; border:1px solid var(--uwb-border); border-radius:12px; padding:24px; margin-bottom:24px;">
                                    <?php $this->render_submodule_header( 'uwb_cf_enabled', 'Cloudflare Zone CDN Cache Integration', '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 10h-1.26A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10z"/></svg>' ); ?>

                                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px; margin-top:20px;">
                                        <div class="uwb-form-group">
                                            <label for="uwb_cf_zone_id">Cloudflare Zone ID</label>
                                            <input type="text" name="uwb_cf_zone_id" id="uwb_cf_zone_id" value="<?php echo esc_attr( get_option( 'uwb_cf_zone_id', '' ) ); ?>" placeholder="e.g. c2547eb745079dac9320b638f5e225cf" style="width:100%; padding:12px; border:1px solid var(--uwb-border); border-radius:8px; font-size:13.5px;" />
                                            <p class="description">Found on your Cloudflare Dashboard &rarr; Domain Overview page (right sidebar).</p>
                                        </div>
                                        <div class="uwb-form-group">
                                            <label for="uwb_cf_api_token">Cloudflare API Token</label>
                                            <input type="password" name="uwb_cf_api_token" id="uwb_cf_api_token" value="<?php echo esc_attr( get_option( 'uwb_cf_api_token', '' ) ); ?>" placeholder="API Token with Cache Purge permission" style="width:100%; padding:12px; border:1px solid var(--uwb-border); border-radius:8px; font-size:13.5px;" autocomplete="new-password" />
                                            <p class="description">Create token at Cloudflare Profile &rarr; API Tokens with <code>Zone - Cache Purge - Purge</code> permission.</p>
                                        </div>
                                    </div>

                                    <div style="margin-bottom:20px; background:#fff; padding:16px; border:1px solid var(--uwb-border); border-radius:8px;">
                                        <label style="display:flex; align-items:center; gap:8px; font-size:13px; font-weight:600; cursor:pointer;">
                                            <input type="checkbox" name="uwb_cf_auto_purge_on_clear" value="1" <?php checked( get_option( 'uwb_cf_auto_purge_on_clear', 1 ), 1 ); ?> />
                                            Auto-purge Cloudflare CDN Edge Cache when clearing plugin cache
                                        </label>
                                    </div>

                                    <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
                                        <button type="button" id="btn-test-cf-connection" class="button button-secondary" style="padding:10px 18px; font-weight:600; height:auto; border-radius:8px; cursor:pointer;">
                                            Test Cloudflare API Connection
                                        </button>
                                        <button type="button" id="btn-purge-cf-cache" class="button button-secondary" style="padding:10px 18px; font-weight:600; height:auto; border-radius:8px; cursor:pointer; color:#dc2626; border-color:#fca5a5;">
                                            Purge Cloudflare Zone Cache Now
                                        </button>
                                        <button type="button" class="button button-secondary btn-trigger-clear-cdn-cache" style="padding:10px 18px; font-weight:600; height:auto; border-radius:8px; cursor:pointer; color:#0284c7; border-color:#7dd3fc; background:#f0f9ff;">
                                            Clear CDN Cache
                                        </button>
                                    </div>
                                    <div id="uwb-cf-test-result" style="margin-top:12px; display:none;"></div>
                                    <?php $this->render_module_banner_end(); ?>
                                </div>

                                <!-- Section 2: CDN Offload Media Notice Card -->
                                <div style="background:#f8fafc; border:1px solid var(--uwb-border); border-radius:12px; padding:24px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px;">
                                    <div>
                                        <h4 style="margin:0 0 6px 0; font-size:14.5px; font-weight:700; color:var(--uwb-text);">CDN Media &amp; Asset Offloading (R2 / S3)</h4>
                                        <p style="font-size:13px; color:var(--uwb-text-muted); margin:0;">
                                            Cloudflare R2 and S3 storage connection credentials, file type rules, and auto-sync settings are located under <strong>Page Optimizes &rarr; [6] CDN Offload Media</strong>.
                                        </p>
                                    </div>
                                    <button type="button" class="button button-primary" onclick="jQuery('.uwb-nav-item[data-tab=\'page_optimizes\']').trigger('click'); jQuery('.uwb-sub-tab-item[data-subtab=\'opt_cdn_media\']').trigger('click');" style="background:var(--uwb-primary); border-color:var(--uwb-primary); padding:10px 18px; height:auto; border-radius:8px; font-weight:600; cursor:pointer; white-space:nowrap;">
                                        Go to Page Optimizes &rarr; [6] CDN Offload Media
                                    </button>
                                </div>
                            </div>
