<?php
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
// Views/subtab_preload_settings.php
?>
                            <div id="subtab-preload_settings_sub" class="uwb-subtab-content">
                                <div class="uwb-form-group">
                                    <label for="uwb_preload_enabled">Enable Automatic Preloading (Trình thu thập thông tin)</label>
                                    <select name="uwb_preload_enabled" id="uwb_preload_enabled" style="width:100%; border:1px solid var(--uwb-border); border-radius:8px; padding:12px;" onchange="toggleCronFields(this.value)">
                                        <option value="0" <?php selected( get_option( 'uwb_preload_enabled', 0 ), 0 ); ?>>TẮT (Disabled)</option>
                                        <option value="1" <?php selected( get_option( 'uwb_preload_enabled', 0 ), 1 ); ?>>BẬT (via WP-Cron)</option>
                                        <option value="2" <?php selected( get_option( 'uwb_preload_enabled', 0 ), 2 ); ?>>BẬT (via Custom Linux Cron)</option>
                                        <option value="3" <?php selected( get_option( 'uwb_preload_enabled', 0 ), 3 ); ?>>BẬT (via LiteSpeed Server Native Crawler)</option>
                                    </select>
                                    <p class="description">Điều này sẽ cho phép cron trình thu thập thông tin tự động cào trước bộ nhớ cache.</p>

                                    <!-- Custom Cron Instructions -->
                                    <?php
                                    $secret_key = get_option( 'uwb_preload_secret_key', '' );
                                    $http_cron_cmd = '* * * * * curl -s "' . esc_url( home_url( '/?uwb_preload_key=' . $secret_key ) ) . '" >/dev/null 2>&1';
                                    $wp_path = ABSPATH;
                                    $escaped_wp_path = function_exists( 'escapeshellarg' ) ? escapeshellarg( $wp_path ) : "'" . str_replace( "'", "'\\''", $wp_path ) . "'";
                                    $wp_cli_cron_cmd = '* * * * * wp uwb-preload run --path=' . $escaped_wp_path . ' >/dev/null 2>&1';
                                    ?>
                                    <div id="uwb-custom-cron-info" style="margin-top: 16px; padding: 20px; background: #f8fafc; border: 1px solid var(--uwb-border); border-radius: 12px; display: <?php echo ( get_option( 'uwb_preload_enabled', 0 ) == 2 ) ? 'block' : 'none'; ?>;">
                                        <h4 style="margin: 0 0 10px 0; font-size: 14px; color: var(--uwb-text); display: flex; align-items: center; gap: 6px;">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                            Custom Linux Cron Configuration
                                        </h4>
                                        <p style="font-size: 13px; color: var(--uwb-text-muted); margin: 0 0 16px 0; line-height: 1.4;">
                                            Real Linux cron jobs are more reliable than virtual WP-Cron. Use one of the options below to trigger the preloader every minute via your server's crontab (run <code>crontab -e</code> on your server):
                                        </p>
                                        
                                        <div style="margin-bottom: 16px;">
                                            <span style="font-weight: 700; font-size: 12.5px; display: block; margin-bottom: 6px; color: var(--uwb-text);">Option 1: Using curl (Recommended)</span>
                                            <div style="position: relative;">
                                                <input type="text" readonly value="<?php echo esc_attr( $http_cron_cmd ); ?>" style="width: 100%; font-family: monospace; font-size: 12px; background: #fff; padding: 10px 40px 10px 10px; border: 1px solid var(--uwb-border); border-radius: 6px; color: #1e293b;" onclick="this.select();" />
                                                <button type="button" class="uwb-copy-cron" data-clipboard-text="<?php echo esc_attr( $http_cron_cmd ); ?>" style="position: absolute; right: 6px; top: 50%; transform: translateY(-50%); border: none; background: none; cursor: pointer; padding: 4px; color: var(--uwb-text-muted);" title="Copy to clipboard">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                                </button>
                                            </div>
                                        </div>

                                        <div>
                                            <span style="font-weight: 700; font-size: 12.5px; display: block; margin-bottom: 6px; color: var(--uwb-text);">Option 2: Using WP-CLI</span>
                                            <div style="position: relative;">
                                                <input type="text" readonly value="<?php echo esc_attr( $wp_cli_cron_cmd ); ?>" style="width: 100%; font-family: monospace; font-size: 12px; background: #fff; padding: 10px 40px 10px 10px; border: 1px solid var(--uwb-border); border-radius: 6px; color: #1e293b;" onclick="this.select();" />
                                                <button type="button" class="uwb-copy-cron" data-clipboard-text="<?php echo esc_attr( $wp_cli_cron_cmd ); ?>" style="position: absolute; right: 6px; top: 50%; transform: translateY(-50%); border: none; background: none; cursor: pointer; padding: 4px; color: var(--uwb-text-muted);" title="Copy to clipboard">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- 1. Usleep Delay -->
                                <div class="uwb-form-group">
                                    <label for="uwb_preload_usleep">Độ trễ (Usleep Delay)</label>
                                    <div style="display:flex; align-items:center; gap:10px;">
                                        <input type="number" min="0" max="30000" step="50" name="uwb_preload_usleep" id="uwb_preload_usleep" value="<?php echo esc_attr( get_option( 'uwb_preload_usleep', 500 ) ); ?>" style="flex:1; max-width:320px;" />
                                        <span style="font-weight:600; font-size:13px; color:var(--uwb-text-muted);">micro giây</span>
                                    </div>
                                    <p class="description">Chỉ định thời gian tính bằng micro giây cho độ trễ giữa các yêu cầu trong khi thu thập thông tin. Giá trị mặc định: <code>500</code>. Phạm vi giá trị: nhỏ hơn <code>30000</code>.</p>
                                </div>

                                <!-- 2. Run Duration -->
                                <div class="uwb-form-group">
                                    <label for="uwb_preload_run_duration">Thời lượng chạy (Run Duration)</label>
                                    <div style="display:flex; align-items:center; gap:10px;">
                                        <input type="number" min="10" max="3600" name="uwb_preload_run_duration" id="uwb_preload_run_duration" value="<?php echo esc_attr( get_option( 'uwb_preload_run_duration', 400 ) ); ?>" style="flex:1; max-width:320px;" />
                                        <span style="font-weight:600; font-size:13px; color:var(--uwb-text-muted);">giây</span>
                                    </div>
                                    <p class="description">Chỉ định thời gian tính bằng giây trong khoảng thời gian thu thập thông tin của mỗi đợt chạy. Giá trị mặc định: <code>400</code>.</p>
                                </div>

                                <!-- 3. Interval Between Runs -->
                                <div class="uwb-form-group">
                                    <label for="uwb_preload_run_interval">Khoảng thời gian giữa các lần chạy (Interval Between Runs)</label>
                                    <div style="display:flex; align-items:center; gap:10px;">
                                        <input type="number" min="60" max="86400" name="uwb_preload_run_interval" id="uwb_preload_run_interval" value="<?php echo esc_attr( get_option( 'uwb_preload_run_interval', 600 ) ); ?>" style="flex:1; max-width:320px;" />
                                        <span style="font-weight:600; font-size:13px; color:var(--uwb-text-muted);">giây</span>
                                    </div>
                                    <p class="description">Chỉ định thời gian tính bằng giây cho thời gian giữa mỗi khoảng thời gian chạy. Giá trị mặc định: <code>600</code>. Phạm vi giá trị: Lớn hơn <code>60</code>.</p>
                                </div>

                                <!-- 4. Complete Crawl Interval -->
                                <div class="uwb-form-group">
                                    <label for="uwb_preload_crawl_interval">Khoảng thời gian thu thập thông tin toàn bộ (Complete Crawl Interval)</label>
                                    <div style="display:flex; align-items:center; gap:10px;">
                                        <input type="number" min="3600" max="2592000" name="uwb_preload_crawl_interval" id="uwb_preload_crawl_interval" value="<?php echo esc_attr( get_option( 'uwb_preload_crawl_interval', 302400 ) ); ?>" style="flex:1; max-width:320px;" />
                                        <span style="font-weight:600; font-size:13px; color:var(--uwb-text-muted);">giây</span>
                                    </div>
                                    <p class="description">Chỉ định khoảng thời gian tính bằng giây trước khi trình thu thập thông tin bắt đầu thu thập lại toàn bộ sơ đồ trang web. Giá trị mặc định: <code>302400</code> (3.5 ngày).</p>
                                </div>

                                <!-- 5. Threads / Concurrency -->
                                <div class="uwb-form-group">
                                    <label for="uwb_preload_threads">Chủ đề / Số luồng đồng thời (Concurrent Threads)</label>
                                    <input type="number" min="1" max="16" name="uwb_preload_threads" id="uwb_preload_threads" value="<?php echo esc_attr( get_option( 'uwb_preload_threads', 3 ) ); ?>" style="max-width:320px;" />
                                    <p class="description">Chỉ định số lượng chủ đề / luồng đồng thời để sử dụng trong khi thu thập dữ liệu. Giá trị mặc định: <code>3</code>. Phạm vi giá trị: <code>1 - 16</code>.</p>
                                </div>

                                <!-- 6. Request Timeout -->
                                <div class="uwb-form-group">
                                    <label for="uwb_preload_request_timeout">Hết giờ / Timeout per URL (Request Timeout)</label>
                                    <div style="display:flex; align-items:center; gap:10px;">
                                        <input type="number" min="10" max="300" name="uwb_preload_request_timeout" id="uwb_preload_request_timeout" value="<?php echo esc_attr( get_option( 'uwb_preload_request_timeout', 30 ) ); ?>" style="flex:1; max-width:320px;" />
                                        <span style="font-weight:600; font-size:13px; color:var(--uwb-text-muted);">giây</span>
                                    </div>
                                    <p class="description">Chỉ định thời gian chờ trong khi thu thập thông tin từng URL. Giá trị mặc định: <code>30</code>. Phạm vi giá trị: <code>10 - 300</code>.</p>
                                </div>

                                <!-- 7. Server Load Limit -->
                                <div class="uwb-form-group">
                                    <label for="uwb_preload_server_load_limit">Giới hạn tải máy chủ (Server Load Limit)</label>
                                    <input type="number" step="0.1" min="0.1" max="50" name="uwb_preload_server_load_limit" id="uwb_preload_server_load_limit" value="<?php echo esc_attr( get_option( 'uwb_preload_server_load_limit', 1.0 ) ); ?>" style="max-width:320px;" />
                                    <p class="description">Quá trình tải từ máy chủ trung bình tối đa được phép trong khi thu thập thông tin. Số lượng luồng thu thập thông tin đang sử dụng sẽ được giảm tích cực cho đến khi quá trình tải từ máy chủ rơi vào giới hạn này. Giá trị mặc định: <code>1</code>.</p>
                                </div>

                                <!-- 8. Preload Batch Size -->
                                <div class="uwb-form-group">
                                    <label for="uwb_preload_batch_size">Kích thước lô mỗi đợt cào (Preload Batch Size)</label>
                                    <input type="number" min="1" max="50" name="uwb_preload_batch_size" id="uwb_preload_batch_size" value="<?php echo esc_attr( get_option( 'uwb_preload_batch_size', 5 ) ); ?>" style="max-width:320px;" />
                                    <p class="description">Số lượng URL được cào trong mỗi lô để giảm thiểu tải CPU và máy chủ.</p>
                                </div>
                            </div>

                            <!-- SUB-TAB 3: Sitemap Settings & Important Sitemap Builder -->
