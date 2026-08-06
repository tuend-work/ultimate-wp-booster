<?php
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
// Views/subtab_preload_status.php
?>
                            <div id="subtab-preload_status" class="uwb-subtab-content active">
                                <!-- Cron Preloader Status Block -->
                                <div style="background:#f8fafc; border:1px solid var(--uwb-border); border-radius:12px; padding:24px; margin-bottom:24px;">
                                    <h3 style="margin-top:0; font-size:15px; display:flex; align-items:center; gap:8px;">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                        Cron Preloader Status
                                    </h3>
                                    <div style="margin-top:12px; font-size:13px; line-height:1.5;">
                                        <?php
                                        $preload_mode = intval( get_option( 'uwb_preload_enabled', 0 ) );
                                        $last_run = get_option( 'uwb_preload_last_run_time', '' );
                                        $last_urls = get_option( 'uwb_preload_last_run_urls', array() );

                                        if ( $preload_mode === 0 ) {
                                            $badge_html = '<div style="display:inline-flex; align-items:center; gap:8px; background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; padding:4px 10px; border-radius:6px; font-weight:700; font-size:11px;"><span style="width:6px;height:6px;background:#ef4444;border-radius:50%;display:inline-block;"></span> Disabled</div>';
                                            $next_run_html = '<span style="color:var(--uwb-text-muted);">None (Preload is disabled)</span>';
                                        } elseif ( $preload_mode === 1 ) {
                                            $badge_html = '<div style="display:inline-flex; align-items:center; gap:8px; background:#d1fae5; color:#065f46; border:1px solid #6ee7b7; padding:4px 10px; border-radius:6px; font-weight:700; font-size:11px;"><span style="width:6px;height:6px;background:#10b981;border-radius:50%;display:inline-block;"></span> Enabled (WP-Cron)</div>';
                                            $next_timestamp = wp_next_scheduled( 'uwb_preload_cron_job' );
                                            if ( $next_timestamp ) {
                                                $next_run_time = function_exists( 'wp_date' ) ? wp_date( 'Y-m-d H:i:s', $next_timestamp ) : date_i18n( 'Y-m-d H:i:s', $next_timestamp );
                                                $next_run_html = '<strong>' . esc_html( $next_run_time ) . '</strong>';
                                            } else {
                                                $next_run_html = '<span style="color:#b45309; font-weight:600;">Not scheduled / Waiting</span>';
                                            }
                                        } elseif ( $preload_mode === 3 ) {
                                            $badge_html = '<div style="display:inline-flex; align-items:center; gap:8px; background:#d1fae5; color:#065f46; border:1px solid #6ee7b7; padding:4px 10px; border-radius:6px; font-weight:700; font-size:11px;"><span style="width:6px;height:6px;background:#10b981;border-radius:50%;display:inline-block;"></span> Enabled (LiteSpeed Server Native Crawler)</div>';
                                            $next_run_html = '<span style="color:#047857; font-weight:600;">Managed natively by LiteSpeed Web Server Engine</span>';
                                        } else {
                                            $badge_html = '<div style="display:inline-flex; align-items:center; gap:8px; background:#e0e7ff; color:#3730a3; border:1px solid #c7d2fe; padding:4px 10px; border-radius:6px; font-weight:700; font-size:11px;"><span style="width:6px;height:6px;background:#6366f1;border-radius:50%;display:inline-block;"></span> Enabled (Custom Cron)</div>';
                                            $next_run_html = '<span style="color:#4f46e5; font-weight:600;">Managed by server crontab</span>';
                                        }
                                        ?>
                                        <p style="margin:0 0 10px 0;"><strong>Active Mode:</strong> <?php echo $badge_html; ?></p>
                                        <p style="margin:0 0 10px 0;"><strong>Last Run:</strong> <code><?php echo ! empty( $last_run ) ? esc_html( $last_run ) : 'Never'; ?></code></p>
                                        <p style="margin:0 0 10px 0;"><strong>Next Scheduled:</strong> <?php echo $next_run_html; ?></p>
                                    </div>
                                </div>

                                <!-- Preload status and last processed URLs grid -->
                                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap:20px; margin-bottom:24px;">
                                    <!-- Left Column: Preloading Queue Status -->
                                    <div class="uwb-preload-status-box" style="margin-bottom:0; display:flex; flex-direction:column; justify-content:space-between;">
                                        <h3 style="margin-top:0; color:var(--uwb-text); font-size:15px; display:flex; align-items:center; gap:8px;">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                                            Preloading Queue Status
                                        </h3>
                                        
                                        <div class="uwb-stats-grid" style="margin-top:12px; margin-bottom: 16px;">
                                            <div class="uwb-stat-card uwb-stat-pending" style="padding:12px 8px;">
                                                <div class="num" id="queue-pending" style="font-size:22px;">-</div>
                                                <div class="label" style="font-size:10px;">Pending</div>
                                            </div>
                                            <div class="uwb-stat-card uwb-stat-processing" style="padding:12px 8px;">
                                                <div class="num" id="queue-processing" style="font-size:22px;">-</div>
                                                <div class="label" style="font-size:10px;">Processing</div>
                                            </div>
                                            <div class="uwb-stat-card uwb-stat-completed" style="padding:12px 8px;">
                                                <div class="num" id="queue-completed" style="font-size:22px;">-</div>
                                                <div class="label" style="font-size:10px;">Completed</div>
                                            </div>
                                            <div class="uwb-stat-card uwb-stat-failed" style="padding:12px 8px;">
                                                <div class="num" id="queue-failed" style="font-size:22px;">-</div>
                                                <div class="label" style="font-size:10px;">Failed</div>
                                            </div>
                                        </div>

                                        <div class="uwb-progress-bar-wrap" style="margin-bottom: 8px;">
                                            <div class="uwb-progress-bar-fill" id="preload-progress-fill"></div>
                                        </div>
                                        
                                        <div class="uwb-progress-text" style="margin-bottom: 16px;">
                                            <span id="preload-progress-pct" style="font-weight:600;">Progress: 0%</span>
                                            <span id="preload-progress-nums">0 / 0 URLs</span>
                                        </div>

                                        <div class="uwb-preload-actions" style="margin-top:auto; display:flex; gap:10px;">
                                            <button type="button" id="btn-start-preload" class="uwb-btn-action uwb-btn-start" style="padding: 10px 16px; font-size:12.5px; flex:1; display:inline-flex; align-items:center; justify-content:center; gap:6px;">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                                                Start Preload
                                            </button>
                                            <button type="button" id="btn-stop-preload" class="uwb-btn-action uwb-btn-stop" style="padding: 10px 16px; font-size:12.5px; flex:1; display:none; align-items:center; justify-content:center; gap:6px;">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>
                                                Pause Preload
                                            </button>
                                            <button type="button" id="btn-clear-preload" class="uwb-btn-action uwb-btn-clear" style="padding: 10px 16px; font-size:12.5px;">
                                                Clear Queue
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Right Column: Last Processed URLs -->
                                    <div style="background:#f8fafc; border:1px solid var(--uwb-border); border-radius:12px; padding:24px; display:flex; flex-direction:column; justify-content:space-between;">
                                        <div>
                                            <h3 style="margin-top:0; font-size:15px; display:flex; align-items:center; gap:8px;">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                                Last Processed URLs
                                            </h3>
                                            
                                            <?php
                                            $last_urls = get_option( 'uwb_preload_last_run_urls', array() );
                                            if ( ! empty( $last_urls ) && is_array( $last_urls ) ) :
                                            ?>
                                                <div style="overflow-y:auto; max-height:165px; border:1px solid var(--uwb-border); border-radius:8px; background:#fff; margin-top:12px;">
                                                    <table style="width:100%; border-collapse:collapse; font-size:11.5px; text-align:left;">
                                                        <thead>
                                                            <tr style="background:#f1f5f9; border-bottom:1px solid var(--uwb-border); position:sticky; top:0; z-index:10;">
                                                                <th style="padding:8px 10px; font-weight:700; color:var(--uwb-text);">URL Path</th>
                                                                <th style="padding:8px 10px; font-weight:700; color:var(--uwb-text); text-align:center; width:70px;">Status</th>
                                                                <th style="padding:8px 10px; font-weight:700; color:var(--uwb-text); text-align:center; width:120px;">Time</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ( array_slice( $last_urls, 0, 10 ) as $url_info ) : 
                                                                $status_badge = '';
                                                                if ( $url_info['status'] === 'completed' ) {
                                                                    $status_badge = '<span style="color:#059669; font-weight:800; font-size:10px; text-transform:uppercase;">✓ Success</span>';
                                                                } else {
                                                                    $status_badge = '<span style="color:#dc2626; font-weight:800; font-size:10px; text-transform:uppercase;">✗ Failed</span>';
                                                                }
                                                                $url_path = wp_parse_url( $url_info['url'], PHP_URL_PATH );
                                                                $url_query = wp_parse_url( $url_info['url'], PHP_URL_QUERY );
                                                                $display_path = '/' . trim( $url_path, '/' ) . ( $url_query ? '?' . $url_query : '' );
                                                                if ( strlen( $display_path ) > 35 ) {
                                                                    $display_path = substr( $display_path, 0, 32 ) . '...';
                                                                }
                                                                $time_display = isset( $url_info['time'] ) ? $url_info['time'] : '';
                                                            ?>
                                                                <tr style="border-bottom:1px solid #f1f5f9;">
                                                                    <td style="padding:8px 10px; font-family:monospace; white-space:nowrap; max-width:180px; overflow:hidden; text-overflow:ellipsis;" title="<?php echo esc_attr( $url_info['url'] ); ?>">
                                                                        <a href="<?php echo esc_url( $url_info['url'] ); ?>" target="_blank" style="text-decoration:none; color:var(--uwb-primary);"><?php echo esc_html( $display_path ); ?></a>
                                                                    </td>
                                                                    <td style="padding:8px 10px; text-align:center;"><?php echo $status_badge; ?></td>
                                                                    <td style="padding:8px 10px; text-align:center; color:var(--uwb-text-muted); font-size:10.5px;"><?php echo esc_html( $time_display ); ?></td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            <?php else : ?>
                                                <div style="background:#fff; border:1px solid var(--uwb-border); border-radius:8px; padding:24px; text-align:center; color:var(--uwb-text-muted); font-style:italic; margin-top:12px;">
                                                    No URLs processed yet.
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Toolbar -->
                                <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap; margin-bottom:16px;">
                                    <input type="text" id="uwb-url-search" placeholder="Search URL..." style="border:1px solid var(--uwb-border); border-radius:8px; padding:9px 12px; font-size:13px; flex:1; min-width:180px; max-width:320px;" />
                                    <div style="display:flex; gap:6px; flex-wrap:wrap;">
                                        <button type="button" class="uwb-filter-btn active" data-status="" style="border:1px solid var(--uwb-border); background:#f1f5f9; border-radius:6px; padding:7px 14px; font-size:12.5px; font-weight:600; cursor:pointer;">All</button>
                                        <button type="button" class="uwb-filter-btn" data-status="pending" style="border:1px solid #fcd34d; background:#fef9c3; color:#92400e; border-radius:6px; padding:7px 14px; font-size:12.5px; font-weight:600; cursor:pointer;">Pending</button>
                                        <button type="button" class="uwb-filter-btn" data-status="processing" style="border:1px solid #93c5fd; background:#dbeafe; color:#1e40af; border-radius:6px; padding:7px 14px; font-size:12.5px; font-weight:600; cursor:pointer;">Processing</button>
                                        <button type="button" class="uwb-filter-btn" data-status="completed" style="border:1px solid #6ee7b7; background:#d1fae5; color:#065f46; border-radius:6px; padding:7px 14px; font-size:12.5px; font-weight:600; cursor:pointer;">Completed</button>
                                        <button type="button" class="uwb-filter-btn" data-status="failed" style="border:1px solid #fca5a5; background:#fee2e2; color:#991b1b; border-radius:6px; padding:7px 14px; font-size:12.5px; font-weight:600; cursor:pointer;">Failed</button>
                                    </div>
                                    <button type="button" id="uwb-url-refresh" style="margin-left:auto; border:1px solid var(--uwb-border); background:#fff; border-radius:6px; padding:7px 14px; font-size:12.5px; font-weight:600; cursor:pointer;">⟳ Refresh</button>
                                </div>

                                <!-- Table -->
                                <div style="overflow-x:auto; border:1px solid var(--uwb-border); border-radius:10px; width:100%;">
                                     <table id="uwb-url-table" style="width:100%; border-collapse:collapse; font-size:13px; table-layout:fixed; min-width:700px;">
                                         <thead>
                                             <tr style="background:#f8fafc; border-bottom:1px solid var(--uwb-border);">
                                                 <th class="uwb-sortable" data-col="priority" style="padding:12px 14px; text-align:center; font-weight:700; cursor:pointer; user-select:none; white-space:nowrap; width:60px;">No. <span class="uwb-sort-icon">↑</span></th>
                                                 <th class="uwb-sortable" data-col="url" style="padding:12px 14px; text-align:left; font-weight:700; cursor:pointer; user-select:none;">URL <span class="uwb-sort-icon">↕</span></th>
                                                 <th class="uwb-sortable" data-col="status" style="padding:12px 14px; text-align:center; font-weight:700; cursor:pointer; user-select:none; white-space:nowrap; width:90px;">Status <span class="uwb-sort-icon">↕</span></th>
                                                 <th class="uwb-sortable" data-col="last_attempt" style="padding:12px 14px; text-align:center; font-weight:700; cursor:pointer; user-select:none; white-space:nowrap; width:140px;">Last Attempt <span class="uwb-sort-icon">↕</span></th>
                                                 <th style="padding:12px 14px; text-align:center; font-weight:700; width:250px;">Actions</th>
                                             </tr>
                                         </thead>
                                         <tbody id="uwb-url-tbody">
                                             <tr><td colspan="5" style="text-align:center; padding:32px; color:var(--uwb-text-muted);">Loading...</td></tr>
                                         </tbody>
                                     </table>
                                </div>

                                <!-- Pagination -->
                                <div id="uwb-url-pagination" style="display:flex; justify-content:space-between; align-items:center; margin-top:14px; font-size:13px; color:var(--uwb-text-muted);"></div>

                                <!-- Toast notification -->
                                <div id="uwb-url-toast" style="display:none; position:fixed; bottom:24px; right:24px; background:#1e293b; color:#fff; padding:12px 20px; border-radius:10px; font-size:13px; font-weight:600; z-index:9999; box-shadow:0 4px 20px rgba(0,0,0,0.2);"></div>

                                <!-- Real-time Debug Logs Section -->
                                <div style="margin-top:32px; background:#f8fafc; border:1px solid var(--uwb-border); border-radius:12px; padding:24px;">
                                    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px;">
                                        <h3 style="margin:0; font-size:15px; display:flex; align-items:center; gap:8px;">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="4 17 10 11 4 5"/><line x1="12" y1="19" x2="20" y2="19"/></svg>
                                            Preloader Crawl Debug Logs
                                        </h3>
                                        <div style="display:flex; gap:8px;">
                                            <button type="button" id="btn-refresh-preload-log" class="uwb-btn-mini">⟳ Refresh Log</button>
                                            <button type="button" id="btn-clear-preload-log" class="uwb-btn-mini uwb-btn-mini-danger">Clear Log</button>
                                        </div>
                                    </div>
                                    <div id="uwb-preload-log-viewer" style="background:#0f172a; color:#38bdf8; font-family:monospace; font-size:12px; padding:16px; border-radius:8px; height:180px; overflow-y:auto; white-space:pre-wrap; line-height:1.5;">Loading logs...</div>
                                </div>
                            </div>

                            <!-- SUB-TAB 2: Preload Settings (General Crawler Settings) -->
