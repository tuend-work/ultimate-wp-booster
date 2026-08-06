<?php
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
// Views/tab_dashboard.php — Dashboard / URL Status tab
?>
                        <div id="tab-url_status" class="uwb-tab-content active">
                            <h2 style="margin-top:0;">Dashboard</h2>
                            <!-- Horizontal Cache Pipeline Widget -->
                            <?php
                            // 1. OPCache
                            $opcode_active = false;
                            $opcode_details = 'PHP OPcache extension not enabled.';
                            if ( function_exists( 'opcache_get_status' ) ) {
                                $opcache_status = @opcache_get_status( false );
                                if ( ! empty( $opcache_status['opcache_enabled'] ) ) {
                                    $opcode_active = true;
                                    $mem = $opcache_status['memory_usage'] ?? [];
                                    $stats = $opcache_status['opcache_statistics'] ?? [];

                                    $used_mb  = isset( $mem['used_memory'] )  ? round( $mem['used_memory'] / 1048576, 1 )  : 0;
                                    $total_mem = isset( $mem['used_memory'], $mem['free_memory'], $mem['wasted_memory'] )
                                        ? round( ( $mem['used_memory'] + $mem['free_memory'] + $mem['wasted_memory'] ) / 1048576, 0 )
                                        : 0;

                                    $op_hits   = isset( $stats['hits'] )   ? intval( $stats['hits'] )   : 0;
                                    $op_misses = isset( $stats['misses'] ) ? intval( $stats['misses'] ) : 0;
                                    $op_total  = $op_hits + $op_misses;
                                    $op_hit_pct = $op_total > 0 ? round( ( $op_hits / $op_total ) * 100, 1 ) : 0;

                                    $cached_keys  = isset( $stats['num_cached_scripts'] ) ? intval( $stats['num_cached_scripts'] ) : 0;
                                    $max_keys     = isset( $stats['max_cached_keys'] )    ? intval( $stats['max_cached_keys'] )    : 0;
                                    $keys_pct = $max_keys > 0 ? round( ( $cached_keys / $max_keys ) * 100, 1 ) : 0;

                                    $opcode_details = "Mem: {$used_mb}MB / {$total_mem}MB | Hit: {$op_hit_pct}% | Keys: {$cached_keys}/{$max_keys} ({$keys_pct}%)";
                                }
                            }

                            // 2. Object Cache — hit rate from WP's in-memory counters (no TCP)
                            $obj_active = wp_using_ext_object_cache();
                            $obj_details = 'No external persistent cache detected.';
                            if ( $obj_active ) {
                                $oc_type  = intval( get_option( 'uwb_redis_enabled', 0 ) );
                                $oc_label = $oc_type === 2 ? 'Memcached' : 'Redis';

                                global $wp_object_cache;
                                $oc_hits   = isset( $wp_object_cache->cache_hits )   ? intval( $wp_object_cache->cache_hits )   : 0;
                                $oc_misses = isset( $wp_object_cache->cache_misses ) ? intval( $wp_object_cache->cache_misses ) : 0;
                                $oc_total  = $oc_hits + $oc_misses;
                                $oc_hit_pct = $oc_total > 0 ? round( ( $oc_hits / $oc_total ) * 100, 1 ) : 0;

                                // Memory info fetched via AJAX to avoid blocking TCP connection here
                                $obj_details = "{$oc_label} Active | Hit rate: {$oc_hit_pct}% <span id='uwb-oc-mem-inline' style='color:#64748b;'>(loading memory…)</span>";
                            }

                            // 3. Page Cache Full
                            $page_cache_active = defined( 'WP_CACHE' ) && WP_CACHE;
                            $page_cache_details = 'WP_CACHE constant is not enabled.';
                            if ( $page_cache_active ) {
                                $active_opts = [];

                                // HTML
                                if ( intval( get_option( 'uwb_minify_html', 0 ) ) ) $active_opts[] = 'Minify HTML';
                                if ( intval( get_option( 'uwb_gzip_enabled', 0 ) ) ) $active_opts[] = 'Gzip/Brotli';

                                // CSS
                                if ( intval( get_option( 'uwb_minify_css', 0 ) ) ) $active_opts[] = 'Minify CSS';
                                if ( intval( get_option( 'uwb_combine_css', 0 ) ) ) $active_opts[] = 'Combine CSS';
                                if ( intval( get_option( 'uwb_defer_css', 0 ) ) )   $active_opts[] = 'Defer CSS';

                                // JS
                                if ( intval( get_option( 'uwb_minify_js', 0 ) ) )  $active_opts[] = 'Minify JS';
                                if ( intval( get_option( 'uwb_combine_js', 0 ) ) )  $active_opts[] = 'Combine JS';
                                if ( intval( get_option( 'uwb_defer_js', 0 ) ) )    $active_opts[] = 'Defer JS';

                                // Media & Files
                                if ( intval( get_option( 'uwb_lazyload_enabled', 0 ) ) ) $active_opts[] = 'Lazy Load Images';
                                if ( intval( get_option( 'uwb_webp_enabled', 0 ) ) )     $active_opts[] = 'WebP';

                                // Fonts
                                if ( intval( get_option( 'uwb_preload_fonts', 0 ) ) )    $active_opts[] = 'Font Preload';
                                if ( intval( get_option( 'uwb_font_display_swap', 0 ) ) ) $active_opts[] = 'Font Display Swap';

                                // Count cached files — use transient to avoid recursive scan on every page load
                                $file_count = get_transient( 'uwb_dashboard_cache_file_count' );
                                if ( $file_count === false ) {
                                    $file_count = 0;
                                    $cache_dirs = [ WP_CONTENT_DIR . '/cache/uwb', WP_CONTENT_DIR . '/cache/wp-rocket' ];
                                    foreach ( $cache_dirs as $cache_dir ) {
                                        if ( is_dir( $cache_dir ) ) {
                                            $di = new \RecursiveDirectoryIterator( $cache_dir, \RecursiveDirectoryIterator::SKIP_DOTS );
                                            $it = new \RecursiveIteratorIterator( $di );
                                            foreach ( $it as $f ) {
                                                if ( $f->isFile() && in_array( $f->getExtension(), ['html', 'html_gzip'], true ) ) {
                                                    $file_count++;
                                                }
                                            }
                                        }
                                    }
                                    set_transient( 'uwb_dashboard_cache_file_count', $file_count, MINUTE_IN_SECONDS );
                                }

                                $opts_str = ! empty( $active_opts ) ? implode( ', ', $active_opts ) : 'No extra optimizations enabled';
                                $page_cache_details = "Active ({$file_count} files) — {$opts_str}";
                            }

                            // 4. CDN Cache
                            $cdn_active = ! empty( $_SERVER['HTTP_CF_RAY'] ) || ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) || ! empty( $_SERVER['HTTP_X_CDN_FORWARD'] );
                            if ( $cdn_active ) {
                                $cdn_details = 'Cloudflare / CDN Proxy Detected';
                            } else {
                                $cdn_details = 'No active CDN proxy header detected.';
                            }

                            // 5. Browser Cache
                            $browser_active = intval( get_option( 'uwb_browser_cache_enabled', 1 ) ) === 1;
                            if ( $browser_active ) {
                                $browser_lifespan = intval( get_option( 'uwb_browser_cache_lifespan', 10 ) );
                                if ( $browser_lifespan >= 1440 ) {
                                    $browser_display = round( $browser_lifespan / 1440, 1 ) . ' day(s)';
                                } elseif ( $browser_lifespan >= 60 ) {
                                    $browser_display = round( $browser_lifespan / 60, 1 ) . ' hour(s)';
                                } else {
                                    $browser_display = $browser_lifespan . ' minute(s)';
                                }
                                $browser_details = "Browser cache enabled — {$browser_display}";
                            } else {
                                $browser_details = 'Local browser caching is disabled.';
                            }

                            // 6. DNS Cache
                            $dns_active = true;
                            $dns_details = 'DNS resolution is cached at OS/Browser/ISP level.';
                            ?>
                            
                            <div class="uwb-pipeline-container">
                                <h3 style="margin-top:0; font-size:15px; display:flex; align-items:center; gap:8px; margin-bottom: 20px;">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
                                    Cấu Hình Chuỗi Cache Xử Lý (Cache Pipeline)
                                </h3>
                                
                                <div class="uwb-pipeline-tree">
                                    <!-- Node 1: DNS Cache -->
                                    <div class="uwb-tree-node active">
                                        <div class="node-status-left"></div>
                                        <div class="node-info-mid">
                                            <div class="node-icon-wrap">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                                            </div>
                                            <div class="node-text-wrap">
                                                <span class="node-title">1. DNS Cache</span>
                                                <span class="node-desc"><?php echo esc_html($dns_details); ?></span>
                                            </div>
                                        </div>
                                        <div class="node-action-right">
                                            <button type="button" onclick="window.location.reload();" class="uwb-btn-mini">Retest</button>
                                        </div>
                                    </div>
                                    
                                    <!-- Node 2: Trình duyệt cache -->
                                    <div class="uwb-tree-node <?php echo $browser_active ? 'active' : 'inactive'; ?>">
                                        <div class="node-status-left"></div>
                                        <div class="node-info-mid">
                                            <div class="node-icon-wrap">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                                            </div>
                                            <div class="node-text-wrap">
                                                <span class="node-title">2. Trình duyệt cache (Browser Cache)</span>
                                                <span class="node-desc"><?php echo esc_html($browser_details); ?></span>
                                            </div>
                                        </div>
                                        <div class="node-action-right">
                                            <button type="button" onclick="jQuery('.uwb-nav-item[data-tab=\'cache_settings\']').trigger('click'); jQuery('.uwb-sub-tab-item[data-subtab=\'browser_cache\']').trigger('click');" class="uwb-btn-mini">Settings</button>
                                        </div>
                                    </div>
                                    
                                    <!-- Node 3: CDN Cache -->
                                    <div class="uwb-tree-node <?php echo $cdn_active ? 'active' : 'inactive'; ?>">
                                        <div class="node-status-left"></div>
                                        <div class="node-info-mid">
                                            <div class="node-icon-wrap">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 10h-1.26A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10z"/></svg>
                                            </div>
                                            <div class="node-text-wrap">
                                                <span class="node-title">3. CDN Cache</span>
                                                <span class="node-desc"><?php echo esc_html($cdn_details); ?></span>
                                            </div>
                                        </div>
                                        <div class="node-action-right" style="display:flex; gap:6px;">
                                            <button type="button" onclick="jQuery('.uwb-nav-item[data-tab=\'cache_settings\']').trigger('click'); jQuery('.uwb-sub-tab-item[data-subtab=\'cdn_cache\']').trigger('click');" class="uwb-btn-mini">Settings</button>
                                            <button type="button" class="uwb-btn-mini uwb-btn-mini-danger btn-trigger-clear-cdn-cache">Clear CDN Cache</button>
                                        </div>
                                    </div>
                                    
                                    <!-- Node 4: Webserver Cache -->
                                    <div class="uwb-tree-node <?php echo $webserver_active ? 'active' : 'inactive'; ?>">
                                        <div class="node-status-left"></div>
                                        <div class="node-info-mid">
                                            <div class="node-icon-wrap">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="2" y="2" width="20" height="8" rx="2" ry="2"/><rect x="2" y="14" width="20" height="8" rx="2" ry="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>
                                            </div>
                                            <div class="node-text-wrap">
                                                <span class="node-title">4. Webserver Cache</span>
                                                <span class="node-desc"><?php echo esc_html($webserver_details); ?></span>
                                            </div>
                                        </div>
                                        <div class="node-action-right" style="display:flex; gap:6px;">
                                            <button type="button" onclick="jQuery('.uwb-nav-item[data-tab=\'cache_settings\']').trigger('click'); jQuery('.uwb-sub-tab-item[data-subtab=\'webserver_cache\']').trigger('click');" class="uwb-btn-mini">Settings</button>
                                            <button type="button" onclick="window.location.reload();" class="uwb-btn-mini">Retest</button>
                                        </div>
                                    </div>
 
                                    <!-- Node 5: Page Cache Full -->
                                    <div class="uwb-tree-node <?php echo $page_cache_active ? 'active' : 'inactive'; ?>">
                                        <div class="node-status-left"></div>
                                        <div class="node-info-mid">
                                            <div class="node-icon-wrap">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                            </div>
                                            <div class="node-text-wrap">
                                                <span class="node-title">5. Page Cache Full (Static HTML)</span>
                                                <span class="node-desc"><?php echo esc_html($page_cache_details); ?></span>
                                            </div>
                                        </div>
                                        <div class="node-action-right" style="display:flex; gap:6px;">
                                            <button type="button" onclick="jQuery('.uwb-nav-item[data-tab=\'cache_settings\']').trigger('click'); jQuery('.uwb-sub-tab-item[data-subtab=\'page_cache\']').trigger('click');" class="uwb-btn-mini">Settings</button>
                                            <a href="<?php echo $purge_url; ?>" class="uwb-btn-mini uwb-btn-mini-danger" style="text-decoration:none;">Purge Cache</a>
                                        </div>
                                    </div>
 
                                    <!-- Node 6: Object Cache -->
                                    <div class="uwb-tree-node <?php echo $obj_active ? 'active' : 'inactive'; ?>">
                                        <div class="node-status-left"></div>
                                        <div class="node-info-mid">
                                            <div class="node-icon-wrap">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v6c0 1.66 4 3 9 3s9-1.34 9-3V5"/><path d="M3 11v6c0 1.66 4 3 9 3s9-1.34 9-3v-6"/></svg>
                                            </div>
                                            <div class="node-text-wrap">
                                                <span class="node-title">6. Object Cache (Redis/Memcached)</span>
                                                <span class="node-desc"><?php echo wp_kses_post($obj_details); ?></span>
                                            </div>
                                        </div>
                                        <div class="node-action-right" style="display:flex; gap:6px;">
                                            <button type="button" onclick="jQuery('.uwb-nav-item[data-tab=\'cache_settings\']').trigger('click'); jQuery('.uwb-sub-tab-item[data-subtab=\'object_cache\']').trigger('click');" class="uwb-btn-mini">Settings</button>
                                            <button type="button" id="btn-flush-redis-tree" class="uwb-btn-mini uwb-btn-mini-danger">Flush Cache</button>
                                        </div>
                                    </div>
 
                                    <!-- Node 7: Opcode Cache -->
                                    <div class="uwb-tree-node <?php echo $opcode_active ? 'active' : 'inactive'; ?>">
                                        <div class="node-status-left"></div>
                                        <div class="node-info-mid">
                                            <div class="node-icon-wrap">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
                                            </div>
                                            <div class="node-text-wrap">
                                                <span class="node-title">7. Opcode Cache (PHP OPcache)</span>
                                                <span class="node-desc"><?php echo esc_html($opcode_details); ?></span>
                                            </div>
                                        </div>
                                        <div class="node-action-right" style="display:flex; gap:6px;">
                                            <button type="button" onclick="jQuery('.uwb-nav-item[data-tab=\'cache_settings\']').trigger('click'); jQuery('.uwb-sub-tab-item[data-subtab=\'opcache\']').trigger('click');" class="uwb-btn-mini">Settings</button>
                                            <button type="button" onclick="window.location.reload();" class="uwb-btn-mini">Retest</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                                <!-- Card pointing to Preload Module Status -->
                                <div style="background:#f8fafc; border:1px solid var(--uwb-border); border-radius:12px; padding:20px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px;">
                                    <div>
                                        <h4 style="margin:0 0 4px 0; font-size:14px; font-weight:700; color:var(--uwb-text); display:flex; align-items:center; gap:6px;">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                            Preload Engine &amp; Queue Management
                                        </h4>
                                        <p style="font-size:12.5px; color:var(--uwb-text-muted); margin:0;">
                                            Real-time preloading progress, URL queue filters, manual controls (Start/Pause), and crawl logs are available under <strong>Preload Module &rarr; Status</strong>.
                                        </p>
                                    </div>
                                    <button type="button" class="button button-primary" onclick="jQuery('.uwb-nav-item[data-tab=\'preload_settings\']').trigger('click'); jQuery('.uwb-sub-tab-item[data-subtab=\'preload_status\']').trigger('click');" style="background:var(--uwb-primary); border-color:var(--uwb-primary); padding:8px 16px; height:auto; border-radius:8px; font-size:12.5px; font-weight:600; cursor:pointer; white-space:nowrap;">
                                        Open Preload Module &rarr; Status
                                    </button>
                                </div>
                        </div>

