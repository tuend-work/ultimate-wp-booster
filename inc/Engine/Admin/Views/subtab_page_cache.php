<?php
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
// Views/subtab_page_cache.php
?>
                            <div id="subtab-page_cache" class="uwb-subtab-content">
                                <!-- Group 1: Page Cache Settings -->
                                <div style="background:#f8fafc; border:1px solid var(--uwb-border); border-radius:12px; padding:24px; margin-bottom:24px;">
                                    <?php $this->render_submodule_header( 'uwb_cache_page_enabled', 'Page Cache Settings', '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>' ); ?>
                                        <div class="uwb-form-group">
                                            <label for="uwb_cache_lifespan">Cache Lifespan (Minutes)</label>
                                            <input type="number" name="uwb_cache_lifespan" id="uwb_cache_lifespan" value="<?php echo esc_attr( get_option( 'uwb_cache_lifespan', 0 ) ); ?>" />
                                            <p class="description">
                                                The amount of time static cache files are kept before being cleared and regenerated. Enter <code>0</code> for unlimited lifespan.<br>
                                                <strong>Quick conversion (click to copy):</strong> <code class="uwb-copy-val" style="cursor:pointer; background:#e2e8f0; padding:2px 6px; border-radius:4px;" title="Click to copy">60</code> (1h) | <code class="uwb-copy-val" style="cursor:pointer; background:#e2e8f0; padding:2px 6px; border-radius:4px;" title="Click to copy">360</code> (6h) | <code class="uwb-copy-val" style="cursor:pointer; background:#e2e8f0; padding:2px 6px; border-radius:4px;" title="Click to copy">720</code> (12h) | <code class="uwb-copy-val" style="cursor:pointer; background:#e2e8f0; padding:2px 6px; border-radius:4px;" title="Click to copy">1440</code> (24h) | <code class="uwb-copy-val" style="cursor:pointer; background:#e2e8f0; padding:2px 6px; border-radius:4px;" title="Click to copy">4320</code> (3d) | <code class="uwb-copy-val" style="cursor:pointer; background:#e2e8f0; padding:2px 6px; border-radius:4px;" title="Click to copy">10080</code> (7d)
                                            </p>
                                        </div>

                                        <div class="uwb-form-group" style="max-width: 700px; margin-bottom: 20px;">
                                            <label style="font-weight: 600; margin-bottom: 8px; color: var(--uwb-text); font-size: 14px;">Cache for Logged-in Users</label>
                                            <div style="display: flex; align-items: stretch; border: 1px solid var(--uwb-border); border-radius: 8px; overflow: hidden; background: #fff;">
                                                <select name="uwb_cache_logged_in" id="uwb_cache_logged_in" style="flex: 1; border: none; border-radius: 0; padding: 12px; font-size: 14px; background: transparent; color: var(--uwb-text); outline: none; box-shadow: none; height: auto;">
                                                    <option value="0" <?php selected( get_option( 'uwb_cache_logged_in', 0 ), 0 ); ?>>None</option>
                                                    <option value="2" <?php selected( get_option( 'uwb_cache_logged_in', 0 ), 2 ); ?>>Enable</option>
                                                </select>
                                                <div id="uwb-logged-in-divider" style="width: 1px; background: var(--uwb-border); <?php echo get_option( 'uwb_cache_logged_in', 0 ) == 2 ? '' : 'display:none;'; ?>"></div>
                                                <input type="number" name="uwb_cache_logged_in_lifespan" id="uwb-logged-in-lifespan-group" value="<?php echo esc_attr( get_option( 'uwb_cache_logged_in_lifespan', 10 ) ); ?>" min="1" placeholder="Lifespan (Minutes)" style="flex: 1; border: none; border-radius: 0; padding: 12px; font-size: 14px; background: transparent; color: var(--uwb-text); outline: none; box-shadow: none; height: auto; <?php echo get_option( 'uwb_cache_logged_in', 0 ) == 2 ? '' : 'display:none;'; ?>" />
                                            </div>
                                            <p class="description">
                                                Serve static cached pages to logged-in users. When enabled, enter lifespan in minutes (default is 10).<br>
                                                <strong>Warning:</strong> Personalized content may be cached if not configured carefully.<br>
                                                <strong>Quick conversion (click to copy):</strong> <code class="uwb-copy-val" style="cursor:pointer; background:#e2e8f0; padding:2px 6px; border-radius:4px;" title="Click to copy">5</code> (5m) | <code class="uwb-copy-val" style="cursor:pointer; background:#e2e8f0; padding:2px 6px; border-radius:4px;" title="Click to copy">10</code> (10m) | <code class="uwb-copy-val" style="cursor:pointer; background:#e2e8f0; padding:2px 6px; border-radius:4px;" title="Click to copy">15</code> (15m)
                                            </p>
                                        </div>

                                        <div class="uwb-form-group">
                                            <label for="uwb_cache_404">Cache 404 Pages</label>
                                            <select name="uwb_cache_404" id="uwb_cache_404" style="width:100%; border:1px solid var(--uwb-border); border-radius:8px; padding:12px;">
                                                <option value="0" <?php selected( get_option( 'uwb_cache_404', 0 ), 0 ); ?>>Disabled</option>
                                                <option value="1" <?php selected( get_option( 'uwb_cache_404', 0 ), 1 ); ?>>Enabled</option>
                                            </select>
                                            <p class="description">Enable this to generate static cache files for 404 Not Found error pages.</p>
                                        </div>

                                        <div class="uwb-form-group" style="max-width: 700px; margin-bottom: 20px;">
                                            <label style="font-weight: 600; margin-bottom: 8px; color: var(--uwb-text); font-size: 14px;">Cache XML Sitemaps</label>
                                            <div style="display: flex; align-items: stretch; border: 1px solid var(--uwb-border); border-radius: 8px; overflow: hidden; background: #fff;">
                                                <select name="uwb_cache_xml_sitemaps" id="uwb_cache_xml_sitemaps" style="flex: 1; border: none; border-radius: 0; padding: 12px; font-size: 14px; background: transparent; color: var(--uwb-text); outline: none; box-shadow: none; height: auto;">
                                                    <option value="0" <?php selected( get_option( 'uwb_cache_xml_sitemaps', 0 ), 0 ); ?>>Disabled</option>
                                                    <option value="1" <?php selected( get_option( 'uwb_cache_xml_sitemaps', 0 ), 1 ); ?>>Enabled</option>
                                                </select>
                                                <div id="uwb-xml-sitemaps-divider" style="width: 1px; background: var(--uwb-border); <?php echo get_option( 'uwb_cache_xml_sitemaps', 0 ) ? '' : 'display:none;'; ?>"></div>
                                                <input type="number" name="uwb_cache_xml_sitemaps_lifespan" id="uwb-xml-sitemaps-lifespan-group" value="<?php echo esc_attr( get_option( 'uwb_cache_xml_sitemaps_lifespan', 10 ) ); ?>" min="1" placeholder="Lifespan (Minutes)" style="flex: 1; border: none; border-radius: 0; padding: 12px; font-size: 14px; background: transparent; color: var(--uwb-text); outline: none; box-shadow: none; height: auto; <?php echo get_option( 'uwb_cache_xml_sitemaps', 0 ) ? '' : 'display:none;'; ?>" />
                                            </div>
                                            <p class="description">
                                                Generate static cache files for XML sitemaps (e.g. <code>/sitemap.xml</code>). Served as <code>text/xml</code>.<br>
                                                <strong>Quick conversion (click to copy):</strong> <code class="uwb-copy-val" style="cursor:pointer; background:#e2e8f0; padding:2px 6px; border-radius:4px;" title="Click to copy">60</code> (1h) | <code class="uwb-copy-val" style="cursor:pointer; background:#e2e8f0; padding:2px 6px; border-radius:4px;" title="Click to copy">600</code> (10h) | <code class="uwb-copy-val" style="cursor:pointer; background:#e2e8f0; padding:2px 6px; border-radius:4px;" title="Click to copy">1440</code> (24h) | <code class="uwb-copy-val" style="cursor:pointer; background:#e2e8f0; padding:2px 6px; border-radius:4px;" title="Click to copy">10080</code> (7d)
                                            </p>
                                        </div>

                                        <div class="uwb-form-group" style="max-width: 700px; margin-bottom: 0;">
                                            <label style="font-weight: 600; margin-bottom: 8px; color: var(--uwb-text); font-size: 14px;">Cache PHP Pages</label>
                                            <div style="display: flex; align-items: stretch; border: 1px solid var(--uwb-border); border-radius: 8px; overflow: hidden; background: #fff;">
                                                <select name="uwb_cache_php" id="uwb_cache_php" style="flex: 1; border: none; border-radius: 0; padding: 12px; font-size: 14px; background: transparent; color: var(--uwb-text); outline: none; box-shadow: none; height: auto;">
                                                    <option value="0" <?php selected( get_option( 'uwb_cache_php', 0 ), 0 ); ?>>Disabled</option>
                                                    <option value="1" <?php selected( get_option( 'uwb_cache_php', 0 ), 1 ); ?>>Enabled</option>
                                                </select>
                                                <div id="uwb-php-divider" style="width: 1px; background: var(--uwb-border); <?php echo get_option( 'uwb_cache_php', 0 ) ? '' : 'display:none;'; ?>"></div>
                                                <input type="number" name="uwb_cache_php_lifespan" id="uwb-php-lifespan-group" value="<?php echo esc_attr( get_option( 'uwb_cache_php_lifespan', 10 ) ); ?>" min="1" placeholder="Lifespan (Minutes)" style="flex: 1; border: none; border-radius: 0; padding: 12px; font-size: 14px; background: transparent; color: var(--uwb-text); outline: none; box-shadow: none; height: auto; <?php echo get_option( 'uwb_cache_php', 0 ) ? '' : 'display:none;'; ?>" />
                                            </div>
                                            <p class="description">
                                                Generate static cache files for requests ending with <code>.php</code> extension (except <code>index.php</code>).<br>
                                                <strong>Quick conversion (click to copy):</strong> <code class="uwb-copy-val" style="cursor:pointer; background:#e2e8f0; padding:2px 6px; border-radius:4px;" title="Click to copy">60</code> (1h) | <code class="uwb-copy-val" style="cursor:pointer; background:#e2e8f0; padding:2px 6px; border-radius:4px;" title="Click to copy">600</code> (10h) | <code class="uwb-copy-val" style="cursor:pointer; background:#e2e8f0; padding:2px 6px; border-radius:4px;" title="Click to copy">1440</code> (24h) | <code class="uwb-copy-val" style="cursor:pointer; background:#e2e8f0; padding:2px 6px; border-radius:4px;" title="Click to copy">10080</code> (7d)
                                            </p>
                                        </div>
                                        <?php $this->render_module_banner_end(); ?>
                                    </div>
                                    <!-- End of Page Cache Settings Card -->
                                    <div style="background:#f8fafc; border:1px solid var(--uwb-border); border-radius:12px; padding:24px; margin-bottom:24px;">
                                        <h3 style="margin-top:0; margin-bottom:20px; font-size:15px; display:flex; align-items:center; gap:8px;">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                            Force & Exclusion Rules
                                        </h3>

                                        <div class="uwb-form-group">
                                            <label for="uwb_excluded_urls">Excluded URLs</label>
                                            <textarea name="uwb_excluded_urls" id="uwb_excluded_urls" rows="6"><?php echo esc_textarea( $this->get_excluded_urls_setting_value() ); ?></textarea>
                                            <p class="description">
                                                URLs or RegEx patterns that should NEVER be cached (one per line).<br>
                                                Examples:<br>
                                                <code>/cart(.*)</code> to exclude the shopping cart pages<br>
                                                <code>/checkout(.*)</code> to exclude checkout pages
                                            </p>
                                        </div>

                                        <div class="uwb-form-group">
                                            <label for="uwb_ignore_all_query_strings">Serve Cache for Strange Query Strings</label>
                                            <select name="uwb_ignore_all_query_strings" id="uwb_ignore_all_query_strings" style="width:100%; border:1px solid var(--uwb-border); border-radius:8px; padding:12px;">
                                                <option value="1" <?php selected( get_option( 'uwb_ignore_all_query_strings', 1 ), 1 ); ?>>Enabled (Ignore unrecognized parameters and serve main cache)</option>
                                                <option value="0" <?php selected( get_option( 'uwb_ignore_all_query_strings', 1 ), 0 ); ?>>Disabled (Bypass cache completely for unrecognized parameters)</option>
                                            </select>
                                            <p class="description">When enabled, strange URL queries like <code>?c=123</code> or <code>?xyz=999</code> will serve the cached main page instead of hitting PHP/database. (Recommended)</p>
                                        </div>

                                        <div class="uwb-form-group" style="margin-top: 16px;">
                                            <label style="display: flex; align-items: center; gap: 8px; font-weight: 600; cursor: pointer;">
                                                <input type="checkbox" name="uwb_auto_collect_params" id="uwb_auto_collect_params" value="1" <?php checked( get_option( 'uwb_auto_collect_params', 0 ), 1 ); ?> />
                                                Auto Collect GET Parameters.
                                            </label>
                                            <p class="description" style="margin-top: 4px;">
                                                Auto Collect GET Parameters when the page is loaded successfully (HTTP Status 200).
                                            </p>
                                        </div>

                                        <div class="uwb-form-group" id="uwb-collected-params-group" style="margin-top: 16px; <?php echo get_option( 'uwb_auto_collect_params', 0 ) ? '' : 'display:none;'; ?>">
                                            <label for="uwb_collected_params">Danh sách Parameter đã thu thập (Discovered GET Parameters)</label>
                                            <textarea name="uwb_collected_params" id="uwb_collected_params" rows="5" placeholder="Các parameter thu thập tự động sẽ hiển thị ở đây (mỗi tham số 1 dòng)..."><?php echo esc_textarea( get_option( 'uwb_collected_params', '' ) ); ?></textarea>
                                            <p class="description">
                                                Toàn bộ tham số URL (GET parameters) đã tìm thấy trên trang web (mỗi tham số 1 dòng). Bạn có thể thêm, sửa hoặc xóa các tham số tại đây.
                                            </p>
                                        </div>

                                        <div class="uwb-form-group">
                                            <label for="uwb_ignored_query">Ignored Query Parameters</label>
                                            <textarea name="uwb_ignored_query" id="uwb_ignored_query" rows="5"><?php 
                                                $ignored_query_val = get_option( 'uwb_ignored_query', "utm_source\nutm_medium\nutm_campaign\nfbclid\ngclid\nage-verified" );
                                                echo esc_textarea( $ignored_query_val ); 
                                            ?></textarea>
                                            <p class="description">
                                                Query parameters to ignore when deciding whether to serve the static cache (one per line).<br>
                                                Marketing parameters like <code>utm_source</code>, <code>fbclid</code>, and <code>gclid</code> are ignored by default to ensure ad campaign clicks still get fast static pages.
                                            </p>
                                        </div>

                                        <div class="uwb-form-group">
                                            <label for="uwb_exclude_cookies">Never Cache Cookies</label>
                                            <textarea name="uwb_exclude_cookies" id="uwb_exclude_cookies" rows="4" placeholder="wordpress_no_cache_&#10;custom_cookie_*"><?php echo esc_textarea( get_option( 'uwb_exclude_cookies', '' ) ); ?></textarea>
                                            <p class="description">
                                                Specify cookie names or patterns that should bypass cache when present in the request (one per line).<br>
                                                Supports wildcards, e.g. <code>woocommerce_items_in_cart_*</code>
                                            </p>
                                        </div>

                                        <div class="uwb-form-group">
                                            <label for="uwb_exclude_user_agents">Never Cache User Agent(s)</label>
                                            <textarea name="uwb_exclude_user_agents" id="uwb_exclude_user_agents" rows="4" placeholder="GTmetrix&#10;PingdomLinkCheck"><?php echo esc_textarea( get_option( 'uwb_exclude_user_agents', '' ) ); ?></textarea>
                                            <p class="description">
                                                Specify user agent substrings that should bypass cache (one per line). Case-insensitive.<br>
                                                Examples: <code>GTmetrix</code>, <code>Pingdom</code>, etc.
                                            </p>
                                        </div>

                                        <div class="uwb-form-group">
                                            <label for="uwb_always_purge_urls">Always Purge URL</label>
                                            <textarea name="uwb_always_purge_urls" id="uwb_always_purge_urls" rows="4" placeholder="/some-page/&#10;https://example.com/another-page/"><?php echo esc_textarea( $this->get_always_purge_urls_setting_value() ); ?></textarea>
                                            <p class="description">
                                                Specify URLs you always want purged from cache whenever you update any post or page (one per line).<br>
                                                Supports absolute URLs or relative paths starting with <code>/</code>.
                                            </p>
                                        </div>

                                        <div class="uwb-form-group" style="margin-bottom:0;">
                                            <label for="uwb_cache_query_strings">Cache Query String</label>
                                            <textarea name="uwb_cache_query_strings" id="uwb_cache_query_strings" rows="4" placeholder="paged&#10;sort"><?php echo esc_textarea( get_option( 'uwb_cache_query_strings', '' ) ); ?></textarea>
                                            <p class="description" style="margin-bottom:0;">
                                                Cache for query strings enables you to force caching for specific GET parameters (one per line).<br>
                                                Example: <code>paged</code> or <code>sort</code>.
                                            </p>
                                        </div>
                                    </div>
                                </div>
