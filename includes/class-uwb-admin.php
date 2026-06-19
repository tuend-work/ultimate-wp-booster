<?php
/**
 * Admin Panel Dashboard & Settings
 */

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class Uwb_Admin {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_plugin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );

        // Sync config JSON file when options are saved
        add_action( 'update_option_uwb_cache_lifespan', array( 'Uwb_Cache', 'write_config_file' ) );
        add_action( 'add_option_uwb_cache_lifespan', array( 'Uwb_Cache', 'write_config_file' ) );
        add_action( 'update_option_uwb_excluded_urls', array( 'Uwb_Cache', 'write_config_file' ) );
        add_action( 'add_option_uwb_excluded_urls', array( 'Uwb_Cache', 'write_config_file' ) );
        add_action( 'update_option_uwb_cache_logged_in', array( 'Uwb_Cache', 'write_config_file' ) );
        add_action( 'add_option_uwb_cache_logged_in', array( 'Uwb_Cache', 'write_config_file' ) );
    }

    public function add_plugin_menu() {
        add_options_page(
            'Ultimate WP Booster',
            'Ultimate WP Booster',
            'manage_options',
            'ultimate-wp-booster',
            array( $this, 'render_settings_page' )
        );
    }

    public function register_settings() {
        register_setting( 'uwb_settings_group', 'uwb_cache_lifespan', 'floatval' );
        register_setting( 'uwb_settings_group', 'uwb_cache_logged_in', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_excluded_urls', 'sanitize_textarea_field' );
        register_setting( 'uwb_settings_group', 'uwb_preload_enabled', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_preload_sitemap', 'esc_url_raw' );
        register_setting( 'uwb_settings_group', 'uwb_priority_urls', 'sanitize_textarea_field' );
        register_setting( 'uwb_settings_group', 'uwb_preload_batch_size', 'intval' );
    }

    /**
     * Enqueue styled assets for premium dashboard
     */
    private function render_assets() {
        ?>
        <style>
            :root {
                --uwb-primary: #6366f1;
                --uwb-primary-dark: #4f46e5;
                --uwb-success: #10b981;
                --uwb-warning: #f59e0b;
                --uwb-danger: #ef4444;
                --uwb-bg: #f8fafc;
                --uwb-card-bg: #ffffff;
                --uwb-text: #1e293b;
                --uwb-text-muted: #64748b;
                --uwb-border: #e2e8f0;
            }

            .uwb-dashboard-wrap {
                margin: 20px 20px 0 0;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
                color: var(--uwb-text);
            }

            .uwb-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
                padding: 24px 32px;
                border-radius: 16px;
                box-shadow: 0 10px 15px -3px rgba(79, 70, 229, 0.2), 0 4px 6px -2px rgba(79, 70, 229, 0.1);
                color: #ffffff;
                margin-bottom: 24px;
            }

            .uwb-header-title h1 {
                margin: 0;
                color: #ffffff;
                font-size: 24px;
                font-weight: 800;
                letter-spacing: -0.5px;
                text-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }

            .uwb-header-title p {
                margin: 6px 0 0 0;
                opacity: 0.9;
                font-size: 14px;
            }

            .uwb-header-actions {
                display: flex;
                gap: 12px;
            }

            .uwb-btn-purge {
                background: rgba(255, 255, 255, 0.2);
                border: 1px solid rgba(255, 255, 255, 0.3);
                color: #ffffff;
                padding: 10px 20px;
                font-weight: 600;
                border-radius: 8px;
                text-decoration: none;
                transition: all 0.2s ease;
                display: inline-flex;
                align-items: center;
                gap: 8px;
                font-size: 14px;
            }

            .uwb-btn-purge:hover {
                background: #ffffff;
                color: var(--uwb-primary-dark);
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            }

            .uwb-layout {
                display: grid;
                grid-template-columns: 240px 1fr;
                gap: 24px;
            }

            .uwb-sidebar-nav {
                background: var(--uwb-card-bg);
                border-radius: 12px;
                border: 1px solid var(--uwb-border);
                padding: 16px;
                display: flex;
                flex-direction: column;
                gap: 8px;
                height: fit-content;
            }

            .uwb-nav-item {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 12px 16px;
                border-radius: 8px;
                color: var(--uwb-text-muted);
                text-decoration: none;
                font-weight: 600;
                font-size: 14px;
                transition: all 0.2s ease;
                cursor: pointer;
            }

            .uwb-nav-item:hover, .uwb-nav-item.active {
                background: #f1f5f9;
                color: var(--uwb-primary);
            }

            .uwb-nav-item.active {
                background: #e0e7ff;
                color: var(--uwb-primary-dark);
            }

            .uwb-content-panel {
                background: var(--uwb-card-bg);
                border-radius: 12px;
                border: 1px solid var(--uwb-border);
                padding: 32px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            }

            .uwb-tab-content {
                display: none;
            }

            .uwb-tab-content.active {
                display: block;
            }

            .uwb-form-group {
                margin-bottom: 24px;
                max-width: 700px;
            }

            .uwb-form-group label {
                display: block;
                font-weight: 600;
                margin-bottom: 8px;
                color: var(--uwb-text);
                font-size: 14px;
            }

            .uwb-form-group input[type="text"],
            .uwb-form-group input[type="number"],
            .uwb-form-group textarea {
                width: 100%;
                border: 1px solid var(--uwb-border);
                border-radius: 8px;
                padding: 12px;
                font-size: 14px;
                color: var(--uwb-text);
                background-color: var(--uwb-bg);
                transition: border-color 0.2s ease, box-shadow 0.2s ease;
            }

            .uwb-form-group input:focus,
            .uwb-form-group textarea:focus {
                border-color: var(--uwb-primary);
                box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
                outline: none;
            }

            .uwb-form-group .description {
                margin-top: 6px;
                color: var(--uwb-text-muted);
                font-size: 12.5px;
                line-height: 1.4;
            }

            /* Preloader Progress CSS */
            .uwb-preload-status-box {
                background: var(--uwb-bg);
                border-radius: 10px;
                padding: 24px;
                border: 1px solid var(--uwb-border);
                margin-bottom: 24px;
            }

            .uwb-stats-grid {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 16px;
                margin-bottom: 24px;
            }

            .uwb-stat-card {
                background: var(--uwb-card-bg);
                border: 1px solid var(--uwb-border);
                border-radius: 8px;
                padding: 16px;
                text-align: center;
                box-shadow: 0 1px 2px rgba(0,0,0,0.02);
            }

            .uwb-stat-card .num {
                font-size: 28px;
                font-weight: 800;
                margin-bottom: 4px;
            }

            .uwb-stat-card .label {
                font-size: 12px;
                font-weight: 600;
                color: var(--uwb-text-muted);
                text-transform: uppercase;
            }

            .uwb-stat-pending .num { color: var(--uwb-text); }
            .uwb-stat-processing .num { color: var(--uwb-warning); }
            .uwb-stat-completed .num { color: var(--uwb-success); }
            .uwb-stat-failed .num { color: var(--uwb-danger); }

            .uwb-progress-bar-wrap {
                background: #e2e8f0;
                border-radius: 100px;
                height: 12px;
                width: 100%;
                overflow: hidden;
                margin-bottom: 12px;
            }

            .uwb-progress-bar-fill {
                background: linear-gradient(90deg, #10b981 0%, #059669 100%);
                height: 100%;
                width: 0%;
                transition: width 0.3s ease;
            }

            .uwb-progress-text {
                display: flex;
                justify-content: space-between;
                font-size: 13px;
                font-weight: 600;
                color: var(--uwb-text);
            }

            .uwb-preload-actions {
                display: flex;
                gap: 12px;
            }

            .uwb-btn-action {
                border-radius: 8px;
                padding: 10px 18px;
                font-size: 13.5px;
                font-weight: 600;
                cursor: pointer;
                border: 1px solid transparent;
                transition: all 0.2s ease;
                display: inline-flex;
                align-items: center;
                gap: 6px;
            }

            .uwb-btn-start { background: var(--uwb-primary); color: #ffffff; }
            .uwb-btn-start:hover { background: var(--uwb-primary-dark); }
            .uwb-btn-stop { background: var(--uwb-warning); color: #ffffff; }
            .uwb-btn-stop:hover { background: #d97706; }
            .uwb-btn-clear { background: #f1f5f9; border-color: #cbd5e1; color: var(--uwb-text); }
            .uwb-btn-clear:hover { background: #e2e8f0; }

            .uwb-nginx-instructions {
                background: #0f172a;
                color: #f8fafc;
                padding: 24px;
                border-radius: 10px;
                font-family: monospace;
                overflow-x: auto;
                font-size: 13px;
                line-height: 1.6;
                border-left: 4px solid var(--uwb-primary);
            }
        </style>
        <?php
    }

    public function render_settings_page() {
        $this->render_assets();
        
        $purge_url = wp_nonce_url( admin_url( 'admin.php?page=ultimate-wp-booster&action=uwb_purge_cache' ), 'uwb_purge_cache_action' );
        ?>
        <div class="uwb-dashboard-wrap">
            <div class="uwb-header">
                <div class="uwb-header-title">
                    <h1>Ultimate WordPress Booster v<?php echo esc_html( UWB_VERSION ); ?></h1>
                    <p>Optimize website loading speed with ultra-fast Static Page Caching.</p>
                </div>
                <div class="uwb-header-actions">
                    <a href="<?php echo esc_url( $purge_url ); ?>" class="uwb-btn-purge">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M10 11v6M14 11v6"/></svg>
                        Purge All Cache
                    </a>
                </div>
            </div>

            <div class="uwb-layout">
                <div class="uwb-sidebar-nav">
                    <div class="uwb-nav-item active" data-tab="cache_settings">
                        Cache Settings
                    </div>
                    <div class="uwb-nav-item" data-tab="preload_settings">
                        Preload Cache
                    </div>
                    <div class="uwb-nav-item" data-tab="nginx_settings">
                        Rocket-Nginx
                    </div>
                    <div class="uwb-nav-item" data-tab="updater_settings">
                        Updates
                    </div>
                </div>

                <div class="uwb-content-panel">
                    <form method="post" action="options.php">
                        <?php settings_fields( 'uwb_settings_group' ); ?>

                        <!-- TAB 1: Cache Settings -->
                        <div id="tab-cache_settings" class="uwb-tab-content active">
                            <h2 style="margin-top:0;">Cache Configuration</h2>
                            <p style="color:var(--uwb-text-muted); margin-bottom: 24px;">Configure cache lifespan, bypass conditions, and exclusions for static files.</p>

                            <div class="uwb-form-group">
                                <label for="uwb_cache_lifespan">Cache Lifespan (Hours)</label>
                                <input type="number" step="0.1" name="uwb_cache_lifespan" id="uwb_cache_lifespan" value="<?php echo esc_attr( get_option( 'uwb_cache_lifespan', 10 ) ); ?>" />
                                <p class="description">The amount of time static cache files are kept before being cleared and regenerated. Enter <code>0</code> for unlimited lifespan.</p>
                            </div>

                            <div class="uwb-form-group">
                                <label for="uwb_cache_logged_in">Cache for Logged-in Users</label>
                                <select name="uwb_cache_logged_in" id="uwb_cache_logged_in" style="width:100%; border:1px solid var(--uwb-border); border-radius:8px; padding:12px;">
                                    <option value="0" <?php selected( get_option( 'uwb_cache_logged_in', 0 ), 0 ); ?>>No (Recommended)</option>
                                    <option value="1" <?php selected( get_option( 'uwb_cache_logged_in', 0 ), 1 ); ?>>Yes</option>
                                </select>
                                <p class="description">
                                    Enable this to serve static cached pages to logged-in users. <br>
                                    <strong>Warning:</strong> Personalized content (like user profile names or WooCommerce carts) may be cached and incorrectly displayed to other users if not configured carefully.
                                </p>
                            </div>

                            <div class="uwb-form-group">
                                <label for="uwb_excluded_urls">Excluded URLs</label>
                                <textarea name="uwb_excluded_urls" id="uwb_excluded_urls" rows="6"><?php echo esc_textarea( get_option( 'uwb_excluded_urls', '' ) ); ?></textarea>
                                <p class="description">
                                    URLs or RegEx patterns that should NEVER be cached (one per line).<br>
                                    Examples:<br>
                                    <code>/cart(.*)</code> to exclude the shopping cart pages<br>
                                    <code>/checkout(.*)</code> to exclude checkout pages
                                </p>
                            </div>
                        </div>

                        <!-- TAB 2: Preload Cache -->
                        <div id="tab-preload_settings" class="uwb-tab-content">
                            <h2 style="margin-top:0;">Preload Cache (Automatic Crawler)</h2>
                            <p style="color:var(--uwb-text-muted); margin-bottom: 24px;">Automatically crawl URLs in your sitemap to pre-generate static cache files before visitors arrive.</p>

                            <div class="uwb-form-group">
                                <label for="uwb_preload_enabled">Enable Automatic Preloading</label>
                                <select name="uwb_preload_enabled" id="uwb_preload_enabled" style="width:100%; border:1px solid var(--uwb-border); border-radius:8px; padding:12px;">
                                    <option value="0" <?php selected( get_option( 'uwb_preload_enabled', 0 ), 0 ); ?>>Disabled</option>
                                    <option value="1" <?php selected( get_option( 'uwb_preload_enabled', 0 ), 1 ); ?>>Enabled (via WP-Cron)</option>
                                </select>
                                <p class="description">When enabled, the crawler runs in the background to fetch URLs in the preloading queue in small batches.</p>
                            </div>

                            <div class="uwb-form-group">
                                <label for="uwb_preload_sitemap">Sitemap XML URL</label>
                                <input type="text" name="uwb_preload_sitemap" id="uwb_preload_sitemap" placeholder="<?php echo esc_url( home_url( '/wp-sitemap.xml' ) ); ?>" value="<?php echo esc_attr( get_option( 'uwb_preload_sitemap', '' ) ); ?>" />
                                <p class="description">The preloader will extract URLs from this sitemap. If left empty, it defaults to: <code><?php echo esc_url( home_url( '/wp-sitemap.xml' ) ); ?></code>.</p>
                            </div>

                            <div class="uwb-form-group">
                                <label for="uwb_priority_urls">Priority URLs (Preloaded first)</label>
                                <textarea name="uwb_priority_urls" id="uwb_priority_urls" rows="4"><?php echo esc_textarea( get_option( 'uwb_priority_urls', '' ) ); ?></textarea>
                                <p class="description">URLs or matching keywords (one per line) that should be crawled first in the queue.</p>
                            </div>

                            <div class="uwb-form-group">
                                <label for="uwb_preload_batch_size">Preload Batch Size</label>
                                <input type="number" min="1" max="50" name="uwb_preload_batch_size" id="uwb_preload_batch_size" value="<?php echo esc_attr( get_option( 'uwb_preload_batch_size', 5 ) ); ?>" />
                                <p class="description">The number of URLs to crawl per batch to minimize CPU and server overhead.</p>
                            </div>

                            <div class="uwb-preload-status-box">
                                <h3 style="margin-top:0; color:var(--uwb-text);">Preloading Queue Status</h3>
                                
                                <div class="uwb-stats-grid">
                                    <div class="uwb-stat-card uwb-stat-pending">
                                        <div class="num" id="queue-pending">-</div>
                                        <div class="label">Pending</div>
                                    </div>
                                    <div class="uwb-stat-card uwb-stat-processing">
                                        <div class="num" id="queue-processing">-</div>
                                        <div class="label">Processing</div>
                                    </div>
                                    <div class="uwb-stat-card uwb-stat-completed">
                                        <div class="num" id="queue-completed">-</div>
                                        <div class="label">Completed</div>
                                    </div>
                                    <div class="uwb-stat-card uwb-stat-failed">
                                        <div class="num" id="queue-failed">-</div>
                                        <div class="label">Failed</div>
                                    </div>
                                </div>

                                <div class="uwb-progress-bar-wrap">
                                    <div class="uwb-progress-bar-fill" id="preload-progress-fill"></div>
                                </div>
                                
                                <div class="uwb-progress-text">
                                    <span id="preload-progress-pct">Progress: 0%</span>
                                    <span id="preload-progress-nums">0 / 0 URLs</span>
                                </div>

                                <div class="uwb-preload-actions" style="margin-top:20px;">
                                    <button type="button" id="btn-start-preload" class="uwb-btn-action uwb-btn-start">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                                        Start Preloading
                                    </button>
                                    <button type="button" id="btn-stop-preload" class="uwb-btn-action uwb-btn-stop" style="display:none;">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>
                                        Pause Preloading
                                    </button>
                                    <button type="button" id="btn-clear-preload" class="uwb-btn-action uwb-btn-clear">
                                        Clear Queue
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- TAB 3: Rocket-Nginx Compatibility -->
                        <div id="tab-nginx_settings" class="uwb-tab-content">
                            <h2 style="margin-top:0;">Rocket-Nginx Compatibility</h2>
                            <p style="color:var(--uwb-text-muted); margin-bottom: 20px;">
                                The static cache files generated by this plugin match WP Rocket's structure. This allows you to configure Nginx to serve static files directly, bypassing PHP and WordPress entirely for maximum performance.
                            </p>
                            <p style="font-size:14px; line-height:1.5;">
                                For optimal server performance, refer to the official Nginx configuration guidelines from the <a href="https://github.com/satellitewp/rocket-nginx" target="_blank">SatelliteWP Rocket-Nginx</a> project.
                            </p>
                            <p style="font-size:14px; font-weight:600; margin-bottom:8px;">System cache directory path:</p>
                            <div class="uwb-nginx-instructions">
                                <?php echo esc_html( WP_CONTENT_DIR . '/cache/wp-rocket/' ); ?>
                            </div>
                            <p style="font-size:14px; font-weight:600; margin-top:20px; margin-bottom:8px;">Cache directory structure per page:</p>
                            <div class="uwb-nginx-instructions">
                                Folder: wp-content/cache/wp-rocket/[domain]/[request_uri]/<br>
                                - index-https.html (Cache file for secure requests)<br>
                                - index-https.html_gzip (Pre-compressed version served to clients supporting gzip)<br>
                                - index.html (Cache file for HTTP requests)
                            </div>
                        </div>

                        <!-- TAB 4: GitHub Updater -->
                        <div id="tab-updater_settings" class="uwb-tab-content">
                            <?php
                            if ( class_exists( 'Uwb_Github_Updater' ) ) {
                                Uwb_Github_Updater::render_update_button();
                            } else {
                                echo '<p>Error: Uwb_Github_Updater class not found.</p>';
                            }
                            ?>
                        </div>

                        <!-- Form Submit (Floating Panel) -->
                        <div style="margin-top: 32px; padding-top: 24px; border-top: 1px solid var(--uwb-border); display: flex; gap: 12px;" id="uwb-submit-row">
                            <input type="submit" name="submit" id="submit" class="button button-primary" style="background:var(--uwb-primary); border-color:var(--uwb-primary); padding:8px 20px; height:auto; font-weight:600; border-radius:6px; box-shadow: 0 4px 6px rgba(99, 102, 241, 0.2);" value="Save Changes" />
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Tab Switcher Logic
            $('.uwb-nav-item').on('click', function() {
                var tabId = $(this).data('tab');
                
                $('.uwb-nav-item').removeClass('active');
                $(this).addClass('active');
                
                $('.uwb-tab-content').removeClass('active');
                $('#tab-' + tabId).addClass('active');

                // Hide submit row on updater tab
                if (tabId === 'updater_settings' || tabId === 'nginx_settings') {
                    $('#uwb-submit-row').hide();
                } else {
                    $('#uwb-submit-row').show();
                }
            });

            // Preloader Live Tracker
            var checkInterval;
            var triggerInterval;
            var nonce = '<?php echo esc_js( wp_create_nonce( "uwb_admin_nonce" ) ); ?>';

            function updatePreloadStatus() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'uwb_get_preload_status',
                        nonce: nonce
                    },
                    success: function(res) {
                        if (res.success) {
                            var data = res.data;
                            $('#queue-pending').text(data.pending);
                            $('#queue-processing').text(data.processing);
                            $('#queue-completed').text(data.completed);
                            $('#queue-failed').text(data.failed);

                            var total = data.total;
                            var processed = data.completed + data.failed + data.processing;
                            
                            $('#preload-progress-nums').text(processed + ' / ' + total + ' URLs');
                            
                            var pct = 0;
                            if (total > 0) {
                                pct = Math.round((processed / total) * 100);
                            }
                            
                            $('#preload-progress-pct').text('Progress: ' + pct + '%');
                            $('#preload-progress-fill').css('width', pct + '%');

                            if (data.running === 1) {
                                $('#btn-start-preload').hide();
                                $('#btn-stop-preload').show();
                                
                                // Auto trigger batch in background to speed up UI preloading
                                if (!triggerInterval) {
                                    triggerInterval = setInterval(triggerPreloadBatch, 3000);
                                }
                            } else {
                                $('#btn-start-preload').show();
                                $('#btn-stop-preload').hide();
                                if (triggerInterval) {
                                    clearInterval(triggerInterval);
                                    triggerInterval = null;
                                }
                            }

                            if (total > 0 && processed >= total && data.pending === 0 && data.processing === 0) {
                                // Done! Stop polling.
                                if (checkInterval) {
                                    clearInterval(checkInterval);
                                    checkInterval = null;
                                }
                                if (triggerInterval) {
                                    clearInterval(triggerInterval);
                                    triggerInterval = null;
                                }
                                $('#btn-start-preload').show();
                                $('#btn-stop-preload').hide();
                            }
                        }
                    }
                });
            }

            function triggerPreloadBatch() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'uwb_trigger_preload_batch',
                        nonce: nonce
                    },
                    success: function(res) {
                        updatePreloadStatus();
                    }
                });
            }

            // Start Preload Click
            $('#btn-start-preload').on('click', function() {
                var btn = $(this);
                btn.prop('disabled', true).text('Parsing Sitemap...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'uwb_start_preload',
                        nonce: nonce
                    },
                    success: function(res) {
                        btn.prop('disabled', false).html('<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg> Start Preloading');
                        if (res.success) {
                            updatePreloadStatus();
                            if (!checkInterval) {
                                checkInterval = setInterval(updatePreloadStatus, 2000);
                            }
                        } else {
                            alert(res.data.message);
                        }
                    },
                    error: function() {
                        btn.prop('disabled', false).html('<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg> Start Preloading');
                        alert('Server connection error.');
                    }
                });
            });

            // Stop Preload Click
            $('#btn-stop-preload').on('click', function() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'uwb_stop_preload',
                        nonce: nonce
                    },
                    success: function(res) {
                        updatePreloadStatus();
                        if (checkInterval) {
                            clearInterval(checkInterval);
                            checkInterval = null;
                        }
                    }
                });
            });

            // Clear Preload Click
            $('#btn-clear-preload').on('click', function() {
                if (confirm('Are you sure you want to clear the preloading queue?')) {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'uwb_clear_preload',
                            nonce: nonce
                        },
                        success: function(res) {
                            updatePreloadStatus();
                            if (checkInterval) {
                                clearInterval(checkInterval);
                                checkInterval = null;
                            }
                        }
                    });
                }
            });

            // Init status on load
            updatePreloadStatus();
            // Start polling if preloader running
            checkInterval = setInterval(updatePreloadStatus, 4000);
        });
        </script>
        <?php
    }
}
