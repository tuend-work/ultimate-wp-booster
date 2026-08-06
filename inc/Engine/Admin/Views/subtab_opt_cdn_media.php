<?php
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
// Views/subtab_opt_cdn_media.php — CDN Offload Media
?>
                            <div id="subtab-opt_cdn_media" class="uwb-subtab-content">
                                <div style="background:#f8fafc; border:1px solid var(--uwb-border); border-radius:12px; padding:24px; margin-bottom:24px;">
                                    <?php $this->render_submodule_header( 'uwb_module_cdn_enabled', 'CDN & S3 Storage Settings', '☁️' ); ?>
                                    <!-- Section 1: Provider Settings -->
                                    <div style="background:#fff; border:1px solid var(--uwb-border); border-radius:12px; padding:24px; margin-bottom:24px;">
                                        <h3 style="margin-top:0; margin-bottom:16px; font-size:15px; display:flex; align-items:center; gap:8px;">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 10h-1.26A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10z"/></svg>
                                            Provider Settings &amp; Credentials
                                        </h3>
                                        <p style="font-size:13px; color:var(--uwb-text-muted); margin-bottom:20px;">Configure your Cloudflare R2 or S3-Compatible storage connection settings.</p>

                                        <div class="uwb-form-group" style="margin-bottom:20px;">
                                            <label for="uwb_cdn_provider">CDN Storage Provider</label>
                                            <select name="uwb_cdn_provider" id="uwb_cdn_provider" style="width:100%; border:1px solid var(--uwb-border); border-radius:8px; padding:12px; font-size:14px; background:#fff;">
                                                <option value="cloudflare_r2" <?php selected( get_option( 'uwb_cdn_provider', 'cloudflare_r2' ), 'cloudflare_r2' ); ?>>Cloudflare R2 Storage (Recommended)</option>
                                                <option value="other_s3" <?php selected( get_option( 'uwb_cdn_provider', 'cloudflare_r2' ), 'other_s3' ); ?>>Other S3 Compatible Storage (AWS S3, Wasabi, DigitalOcean Spaces, MinIO, Bunny S3)</option>
                                            </select>
                                            <p class="description uwb-cdn-cf-guide" style="margin-top:8px; font-size:12.5px; <?php echo get_option( 'uwb_cdn_provider', 'cloudflare_r2' ) === 'cloudflare_r2' ? '' : 'display:none;'; ?>">
                                                📖 <strong>Hướng dẫn lấy thông số Cloudflare R2:</strong>
                                                <a href="https://developers.cloudflare.com/r2/api/s3/tokens/" target="_blank" rel="noopener noreferrer" style="color:var(--uwb-primary); font-weight:600; text-decoration:underline;">
                                                    Xem hướng dẫn tạo R2 API Tokens (Access Key &amp; Secret Key) &amp; Account ID trên Cloudflare Docs &rarr;
                                                </a>
                                            </p>
                                        </div>

                                        <!-- Cloudflare Account ID (CF R2 only) -->
                                        <div class="uwb-form-group uwb-cdn-cf-field" style="margin-bottom:20px; <?php echo get_option( 'uwb_cdn_provider', 'cloudflare_r2' ) === 'cloudflare_r2' ? '' : 'display:none;'; ?>">
                                            <label for="uwb_cdn_account_id">Cloudflare Account ID</label>
                                            <input type="text" name="uwb_cdn_account_id" id="uwb_cdn_account_id" value="<?php echo esc_attr( get_option( 'uwb_cdn_account_id', '' ) ); ?>" placeholder="e.g. 56a84f3c7e0b9d123456789abcdef012" style="width:100%; padding:12px; border:1px solid var(--uwb-border); border-radius:8px; font-size:13.5px;" />
                                            <p class="description">Your Cloudflare Account ID found in your Cloudflare Dashboard URL or R2 overview.</p>
                                        </div>

                                        <!-- Endpoint URL (Other S3 only) -->
                                        <div class="uwb-form-group uwb-cdn-s3-field" style="margin-bottom:20px; <?php echo get_option( 'uwb_cdn_provider', 'cloudflare_r2' ) === 'other_s3' ? '' : 'display:none;'; ?>">
                                            <label for="uwb_cdn_endpoint">S3 Endpoint URL</label>
                                            <input type="url" name="uwb_cdn_endpoint" id="uwb_cdn_endpoint" value="<?php echo esc_attr( get_option( 'uwb_cdn_endpoint', '' ) ); ?>" placeholder="e.g. https://s3.wasabisys.com or https://ams3.digitaloceanspaces.com" style="width:100%; padding:12px; border:1px solid var(--uwb-border); border-radius:8px; font-size:13.5px;" />
                                            <p class="description">The REST API Endpoint of your S3 compatible provider.</p>
                                        </div>

                                        <!-- Region (Other S3 only) -->
                                        <div class="uwb-form-group uwb-cdn-s3-field" style="margin-bottom:20px; <?php echo get_option( 'uwb_cdn_provider', 'cloudflare_r2' ) === 'other_s3' ? '' : 'display:none;'; ?>">
                                            <label for="uwb_cdn_region">S3 Region</label>
                                            <input type="text" name="uwb_cdn_region" id="uwb_cdn_region" value="<?php echo esc_attr( get_option( 'uwb_cdn_region', 'auto' ) ); ?>" placeholder="e.g. us-east-1, us-west-1, ams3" style="width:100%; padding:12px; border:1px solid var(--uwb-border); border-radius:8px; font-size:13.5px;" />
                                        </div>

                                        <!-- Access Key & Secret Key Grid -->
                                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px;">
                                            <div class="uwb-form-group">
                                                <label for="uwb_cdn_access_key">Access Key ID</label>
                                                <input type="text" name="uwb_cdn_access_key" id="uwb_cdn_access_key" value="<?php echo esc_attr( get_option( 'uwb_cdn_access_key', '' ) ); ?>" placeholder="Access Key ID" style="width:100%; padding:12px; border:1px solid var(--uwb-border); border-radius:8px; font-size:13.5px;" />
                                            </div>
                                            <div class="uwb-form-group">
                                                <label for="uwb_cdn_secret_key">Secret Access Key</label>
                                                <input type="password" name="uwb_cdn_secret_key" id="uwb_cdn_secret_key" value="<?php echo esc_attr( get_option( 'uwb_cdn_secret_key', '' ) ); ?>" placeholder="Secret Access Key" style="width:100%; padding:12px; border:1px solid var(--uwb-border); border-radius:8px; font-size:13.5px;" />
                                            </div>
                                        </div>

                                        <!-- Bucket Name -->
                                        <div class="uwb-form-group" style="margin-bottom:20px;">
                                            <label for="uwb_cdn_bucket">Bucket Name</label>
                                            <input type="text" name="uwb_cdn_bucket" id="uwb_cdn_bucket" value="<?php echo esc_attr( get_option( 'uwb_cdn_bucket', '' ) ); ?>" placeholder="e.g. my-website-assets" style="width:100%; padding:12px; border:1px solid var(--uwb-border); border-radius:8px; font-size:13.5px;" />
                                        </div>

                                        <!-- Custom CDN Domain / CNAME -->
                                        <div class="uwb-form-group" style="margin-bottom:20px;">
                                            <label for="uwb_cdn_custom_domain">CDN Custom Domain / CNAME URL</label>
                                            <input type="url" name="uwb_cdn_custom_domain" id="uwb_cdn_custom_domain" value="<?php echo esc_attr( get_option( 'uwb_cdn_custom_domain', '' ) ); ?>" placeholder="e.g. https://cdn.mysite.com or https://pub-xxx.r2.dev" style="width:100%; padding:12px; border:1px solid var(--uwb-border); border-radius:8px; font-size:13.5px;" />
                                            <p class="description">The public URL domain used to rewrite and serve static assets to website visitors.</p>
                                        </div>

                                        <!-- Test Connection Button -->
                                        <div style="margin-top:16px;">
                                            <button type="button" id="btn-test-cdn-connection" class="button button-secondary" style="padding:10px 18px; font-weight:600; height:auto; border-radius:8px; cursor:pointer;">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle; margin-right:4px;"><polyline points="20 6 9 17 4 12"/></svg>
                                                Test CDN Connection
                                            </button>
                                            <div id="uwb-cdn-test-result" style="margin-top:12px; display:none;"></div>
                                        </div>
                                    </div>

                                    <!-- Section 2: File Types & Offloading Rules -->
                                    <div style="background:#fff; border:1px solid var(--uwb-border); border-radius:12px; padding:24px; margin-bottom:24px;">
                                        <h3 style="margin-top:0; margin-bottom:16px; font-size:15px; display:flex; align-items:center; gap:8px;">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                            File Types &amp; URL Rewriting Rules
                                        </h3>

                                        <?php $this->render_toggle_switch( 'uwb_cdn_enabled', 'Enable CDN Static Asset Offloading & URL Rewriter', 'Automatically rewrite static asset URLs in HTML output to serve from CDN Domain.' ); ?>

                                        <div class="uwb-form-group" style="margin-bottom:0;">
                                            <label for="uwb_cdn_cache_control">Object Cache-Control Header</label>
                                            <input type="text" name="uwb_cdn_cache_control" id="uwb_cdn_cache_control" value="<?php echo esc_attr( get_option( 'uwb_cdn_cache_control', 'public, max-age=31536000, immutable' ) ); ?>" style="width:100%; padding:12px; border:1px solid var(--uwb-border); border-radius:8px; font-size:13.5px;" />
                                            <p class="description">Cache-Control header set on uploaded S3/R2 objects.</p>
                                        </div>
                                    </div>

                                    <!-- Section 3: Batch Sync Tools -->
                                    <div style="background:#fff; border:1px solid var(--uwb-border); border-radius:12px; padding:24px;">
                                        <h3 style="margin-top:0; margin-bottom:16px; font-size:15px; display:flex; align-items:center; gap:8px;">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                            Batch Sync Media Library to CDN
                                        </h3>

                                        <p style="font-size:12.5px; color:var(--uwb-text-muted); margin-bottom:16px;">Bulk upload all existing media library files and thumbnails to your configured S3/R2 bucket.</p>

                                        <button type="button" id="btn-sync-media-cdn" class="button button-primary" style="background:var(--uwb-primary); border-color:var(--uwb-primary); padding:10px 20px; height:auto; border-radius:8px; font-weight:600; cursor:pointer;">
                                            Sync Existing Media Library to CDN
                                        </button>

                                        <div id="uwb-sync-cdn-progress-wrap" style="margin-top:16px; display:none;">
                                            <div class="uwb-progress-bar-wrap" style="margin-bottom:8px;">
                                                <div class="uwb-progress-bar-fill" id="uwb-sync-cdn-progress-fill" style="width:0%;"></div>
                                            </div>
                                            <div id="uwb-sync-cdn-status-text" style="font-size:12.5px; font-weight:600; color:var(--uwb-text);">Initializing batch sync...</div>
                                        </div>
                                    </div>
                                    <?php $this->render_page_optimizer_tools_section( 'CDN Offload Tools' ); ?>
                                    <?php $this->render_module_banner_end(); ?>
                                </div>
                            </div>

