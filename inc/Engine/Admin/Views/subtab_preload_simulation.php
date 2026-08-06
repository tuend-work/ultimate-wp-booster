<?php
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
// Views/subtab_preload_simulation.php
?>
                            <div id="subtab-preload_simulation_sub" class="uwb-subtab-content">
                                <div style="background:#f8fafc; border:1px solid var(--uwb-border); border-radius:12px; padding:24px; margin-bottom:24px;">
                                    <h3 style="margin-top:0; font-size:15px; color:var(--uwb-text); display:flex; align-items:center; gap:8px;">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
                                        Crawler Simulation &amp; Custom Headers/Cookies
                                    </h3>
                                    <p style="font-size:12.5px; color:var(--uwb-text-muted); margin:4px 0 0 0;">
                                        Customize the User-Agent, HTTP Cookies, and Headers sent during preloader requests to emulate specific devices, currencies, or user contexts.
                                    </p>
                                </div>

                                <div class="uwb-form-group">
                                    <label for="uwb_preload_user_agent">Crawler User-Agent String</label>
                                    <input type="text" name="uwb_preload_user_agent" id="uwb_preload_user_agent" value="<?php echo esc_attr( get_option( 'uwb_preload_user_agent', 'Ultimate-WP-Booster-Preloader' ) ); ?>" placeholder="Ultimate-WP-Booster-Preloader" style="width:100%; border:1px solid var(--uwb-border); border-radius:8px; padding:12px;" />
                                    <p class="description">HTTP User-Agent header sent when warming up pages.</p>
                                    <div style="display:flex; gap:8px; margin-top:8px;">
                                        <button type="button" class="uwb-btn-mini" onclick="document.getElementById('uwb_preload_user_agent').value='Ultimate-WP-Booster-Preloader';">Default</button>
                                        <button type="button" class="uwb-btn-mini" onclick="document.getElementById('uwb_preload_user_agent').value='lscache_runner';">LiteSpeed Native (lscache_runner)</button>
                                        <button type="button" class="uwb-btn-mini" onclick="document.getElementById('uwb_preload_user_agent').value='Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';">Chrome Desktop</button>
                                    </div>
                                </div>

                                <div class="uwb-form-group">
                                    <label for="uwb_preload_custom_cookies">Custom HTTP Cookies</label>
                                    <textarea name="uwb_preload_custom_cookies" id="uwb_preload_custom_cookies" rows="4" placeholder="currency=USD&#10;location=VN&#10;theme=dark"><?php echo esc_textarea( get_option( 'uwb_preload_custom_cookies', '' ) ); ?></textarea>
                                    <p class="description">Specify HTTP cookies to attach to every preloading HTTP request (one per line in <code>cookie_name=cookie_value</code> format). Useful for multi-currency or multi-language sites.</p>
                                </div>

                                <div class="uwb-form-group">
                                    <label for="uwb_preload_custom_headers">Custom HTTP Headers</label>
                                    <textarea name="uwb_preload_custom_headers" id="uwb_preload_custom_headers" rows="4" placeholder="Accept-Language: vi-VN,vi;q=0.9&#10;X-Preload-Simulation: 1"><?php echo esc_textarea( get_option( 'uwb_preload_custom_headers', '' ) ); ?></textarea>
                                    <p class="description">Specify custom HTTP request headers to include during preloading (one per line in <code>Header-Name: Header-Value</code> format).</p>
                                </div>
                            </div>

