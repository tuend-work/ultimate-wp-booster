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
        $options_to_sync = array(
            'uwb_cache_lifespan',
            'uwb_excluded_urls',
            'uwb_cache_logged_in',
            'uwb_redis_enabled',
            'uwb_redis_conn_type',
            'uwb_redis_host',
            'uwb_redis_port',
            'uwb_redis_socket',
            'uwb_redis_password',
            'uwb_redis_db'
        );
        foreach ( $options_to_sync as $opt ) {
            add_action( "update_option_{$opt}", array( 'Uwb_Cache', 'write_config_file' ) );
            add_action( "add_option_{$opt}", array( 'Uwb_Cache', 'write_config_file' ) );
        }

        // Redis AJAX hooks
        add_action( 'wp_ajax_uwb_test_redis_connection', array( $this, 'ajax_test_redis_connection' ) );
        add_action( 'wp_ajax_uwb_flush_redis_cache', array( $this, 'ajax_flush_redis_cache' ) );
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

        // Redis Object Cache Settings
        register_setting( 'uwb_settings_group', 'uwb_redis_enabled', 'intval' );
        register_setting( 'uwb_settings_group', 'uwb_redis_conn_type', 'sanitize_text_field' );
        register_setting( 'uwb_settings_group', 'uwb_redis_host', 'sanitize_text_field' );
        register_setting( 'uwb_settings_group', 'uwb_redis_port', 'intval' );
        register_setting( 'uwb_redis_socket', 'uwb_redis_socket', 'sanitize_text_field' );
        register_setting( 'uwb_settings_group', 'uwb_redis_password', 'sanitize_text_field' );
        register_setting( 'uwb_settings_group', 'uwb_redis_db', 'intval' );
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
                    <div class="uwb-nav-item active" data-tab="url_status">
                        Dashboard
                    </div>
                    <div class="uwb-nav-item" data-tab="cache_settings">
                        Cache Settings
                    </div>
                    <div class="uwb-nav-item" data-tab="preload_settings">
                        Preload Cache
                    </div>
                    <div class="uwb-nav-item" data-tab="object_cache">
                        Redis Object Cache
                    </div>
                    <div class="uwb-nav-item" data-tab="updater_settings">
                        Updates
                    </div>
                </div>

                <div class="uwb-content-panel">
                    <form method="post" action="options.php">
                        <?php settings_fields( 'uwb_settings_group' ); ?>

                        <!-- TAB 1: Cache Settings -->
                        <div id="tab-cache_settings" class="uwb-tab-content">
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

                        <!-- TAB 3: Redis Object Cache -->
                        <div id="tab-object_cache" class="uwb-tab-content">
                            <h2 style="margin-top:0;">Redis Object Cache</h2>
                            <p style="color:var(--uwb-text-muted); margin-bottom:24px;">Persistent object caching stores database query results in memory (like Redis), reducing redundant queries and speeding up WordPress.</p>

                            <!-- Configuration Fields -->
                            <div style="background:#f8fafc; border:1px solid var(--uwb-border); border-radius:12px; padding:24px; margin-bottom:24px;">
                                <h3 style="margin-top:0; font-size:15px; display:flex; align-items:center; gap:8px;">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.1a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>
                                    Redis Configuration
                                </h3>

                                <div class="uwb-form-group" style="margin-top:16px;">
                                    <label for="uwb_redis_enabled">Enable Redis Object Cache</label>
                                    <select name="uwb_redis_enabled" id="uwb_redis_enabled" style="width:100%; border:1px solid var(--uwb-border); border-radius:8px; padding:12px;">
                                        <option value="0" <?php selected( get_option( 'uwb_redis_enabled', 0 ), 0 ); ?>>Disabled</option>
                                        <option value="1" <?php selected( get_option( 'uwb_redis_enabled', 0 ), 1 ); ?>>Enabled</option>
                                    </select>
                                    <p class="description">When enabled, WordPress database query results will be stored persistently in Redis. Our custom drop-in file will be automatically copied to <code>wp-content/object-cache.php</code>.</p>
                                </div>

                                <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                                    <div class="uwb-form-group">
                                        <label for="uwb_redis_conn_type">Connection Type</label>
                                        <select name="uwb_redis_conn_type" id="uwb_redis_conn_type" style="width:100%; border:1px solid var(--uwb-border); border-radius:8px; padding:12px;">
                                            <option value="tcp" <?php selected( get_option( 'uwb_redis_conn_type', 'tcp' ), 'tcp' ); ?>>TCP/IP (Host/Port)</option>
                                            <option value="socket" <?php selected( get_option( 'uwb_redis_conn_type', 'tcp' ), 'socket' ); ?>>Unix Socket</option>
                                        </select>
                                    </div>

                                    <div class="uwb-form-group">
                                        <label for="uwb_redis_db">Database Index</label>
                                        <input type="number" min="0" max="15" name="uwb_redis_db" id="uwb_redis_db" value="<?php echo esc_attr( get_option( 'uwb_redis_db', 0 ) ); ?>" />
                                    </div>
                                </div>

                                <!-- TCP Settings -->
                                <div id="redis-tcp-settings" style="display:grid; grid-template-columns: 2fr 1fr; gap:16px;">
                                    <div class="uwb-form-group">
                                        <label for="uwb_redis_host">Redis Host</label>
                                        <input type="text" name="uwb_redis_host" id="uwb_redis_host" value="<?php echo esc_attr( get_option( 'uwb_redis_host', '127.0.0.1' ) ); ?>" />
                                    </div>
                                    <div class="uwb-form-group">
                                        <label for="uwb_redis_port">Redis Port</label>
                                        <input type="number" name="uwb_redis_port" id="uwb_redis_port" value="<?php echo esc_attr( get_option( 'uwb_redis_port', 6379 ) ); ?>" />
                                    </div>
                                </div>

                                <!-- Socket Settings -->
                                <div id="redis-socket-settings" style="display:none;">
                                    <div class="uwb-form-group">
                                        <label for="uwb_redis_socket">Unix Socket Path</label>
                                        <input type="text" name="uwb_redis_socket" id="uwb_redis_socket" placeholder="/var/run/redis/redis.sock" value="<?php echo esc_attr( get_option( 'uwb_redis_socket', '' ) ); ?>" />
                                    </div>
                                </div>

                                <!-- Password Setting -->
                                <div class="uwb-form-group">
                                    <label for="uwb_redis_password">Redis Password (Optional)</label>
                                    <input type="password" name="uwb_redis_password" id="uwb_redis_password" placeholder="Leave blank if no password" value="<?php echo esc_attr( get_option( 'uwb_redis_password', '' ) ); ?>" style="width:100%; border:1px solid var(--uwb-border); border-radius:8px; padding:12px; font-size:14px;" />
                                </div>
                            </div>

                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom:24px;">
                                <!-- Status Block -->
                                <div style="background:#f8fafc; border:1px solid var(--uwb-border); border-radius:12px; padding:24px;">
                                    <h3 style="margin-top:0; font-size:15px; display:flex; align-items:center; gap:8px;">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="8" rx="2" ry="2"/><rect x="2" y="14" width="20" height="8" rx="2" ry="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>
                                        Cache Status
                                    </h3>
                                    <div style="margin-top:12px;">
                                        <?php
                                        $oc_active = wp_using_ext_object_cache();
                                        $oc_dropin = file_exists( WP_CONTENT_DIR . '/object-cache.php' );
                                        if ( $oc_active ) {
                                            echo '<div style="display:inline-flex; align-items:center; gap:8px; background:#d1fae5; color:#065f46; border:1px solid #6ee7b7; padding:8px 16px; border-radius:8px; font-weight:700; font-size:13px;"><span style="width:8px;height:8px;background:#10b981;border-radius:50%;display:inline-block;"></span> Active</div>';
                                            // Detect backend
                                            $backend = 'Unknown';
                                            if ( class_exists('Redis') || class_exists('Predis\Client') || defined('WP_REDIS_HOST') || get_option('uwb_redis_enabled') ) {
                                                $backend = 'Redis';
                                            } elseif ( class_exists('Memcached') || class_exists('Memcache') ) {
                                                $backend = 'Memcached';
                                            } elseif ( function_exists('apcu_fetch') ) {
                                                $backend = 'APCu';
                                            }
                                            echo '<p style="margin-top:12px; font-size:13px; color:var(--uwb-text-muted);">Backend: <strong>' . esc_html( $backend ) . '</strong></p>';
                                        } else {
                                            echo '<div style="display:inline-flex; align-items:center; gap:8px; background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; padding:8px 16px; border-radius:8px; font-weight:700; font-size:13px;"><span style="width:8px;height:8px;background:#ef4444;border-radius:50%;display:inline-block;"></span> Inactive</div>';
                                        }
                                        ?>
                                    </div>
                                    <div style="margin-top:16px; border-top:1px solid var(--uwb-border); padding-top:16px;">
                                        <h4 style="margin:0 0 8px 0; font-size:13.5px; color:var(--uwb-text);">Drop-in File</h4>
                                        <?php if ( $oc_dropin ) : ?>
                                            <p style="font-size:13px; color:#065f46; margin:0;"><strong>✓</strong> <code>wp-content/object-cache.php</code> is installed.</p>
                                        <?php else : ?>
                                            <p style="font-size:13px; color:#92400e; margin:0;"><strong>✗</strong> <code>wp-content/object-cache.php</code> not found.</p>
                                            <p style="font-size:12px; color:var(--uwb-text-muted); margin-top:6px; line-height:1.4;">To enable persistent object caching, configure connection above, select Enabled, and click Save Changes.</p>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Connection & Tools Block -->
                                <div style="background:#f8fafc; border:1px solid var(--uwb-border); border-radius:12px; padding:24px; display:flex; flex-direction:column; justify-content:space-between;">
                                    <div>
                                        <h3 style="margin-top:0; font-size:15px; display:flex; align-items:center; gap:8px;">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                                            Redis Connection Test
                                        </h3>
                                        <div style="margin-top:12px; font-size:13px; line-height:1.5;">
                                            <?php
                                            $curr_conn_type = get_option('uwb_redis_conn_type', 'tcp');
                                            $curr_host = get_option('uwb_redis_host', '127.0.0.1');
                                            $curr_port = get_option('uwb_redis_port', 6379);
                                            $curr_socket = get_option('uwb_redis_socket', '');
                                            $curr_db = get_option('uwb_redis_db', 0);
                                            if ( $curr_conn_type === 'socket' ) {
                                                $conn_str = esc_html( $curr_socket );
                                            } else {
                                                $conn_str = esc_html( $curr_host . ':' . $curr_port );
                                            }
                                            ?>
                                            <p style="margin:0 0 8px 0;"><strong>Saved Connection:</strong> <code><?php echo $conn_str; ?></code> (DB: <?php echo intval( $curr_db ); ?>)</p>
                                            <p style="margin:0 0 12px 0;"><strong>PHP Redis Extension:</strong> <code><?php echo class_exists('Redis') ? 'Available ✓' : 'Not Installed ✗'; ?></code></p>
                                        </div>
                                        <div id="redis-test-result" style="display:none; padding:10px 14px; border-radius:8px; font-size:12.5px; font-weight:600; margin-bottom:12px;"></div>
                                    </div>

                                    <div style="display:flex; gap:12px;">
                                        <button type="button" id="btn-test-redis" class="button" style="border:1px solid var(--uwb-border); padding:8px 16px; border-radius:6px; font-weight:600; font-size:12.5px; background:#fff; cursor:pointer; color:var(--uwb-text); transition:all 0.2s;">Test Connection</button>
                                        <button type="button" id="btn-flush-redis" class="button" style="border:1px solid #fca5a5; background:#fee2e2; color:#991b1b; padding:8px 16px; border-radius:6px; font-weight:600; font-size:12.5px; cursor:pointer; transition:all 0.2s;">Flush Cache</button>
                                    </div>
                                </div>
                            </div>

                            <!-- Cache Stats Block -->
                            <div style="background:#f8fafc; border:1px solid var(--uwb-border); border-radius:12px; padding:24px;">
                                <h3 style="margin-top:0; font-size:15px; display:flex; align-items:center; gap:8px;">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                                    Global Cache Statistics
                                </h3>
                                <?php
                                global $wp_object_cache;
                                $hits = 0; $misses = 0;
                                if ( isset( $wp_object_cache->cache_hits ) ) $hits = intval( $wp_object_cache->cache_hits );
                                if ( isset( $wp_object_cache->cache_misses ) ) $misses = intval( $wp_object_cache->cache_misses );
                                $total_req = $hits + $misses;
                                $hit_ratio = $total_req > 0 ? round( ( $hits / $total_req ) * 100, 1 ) : 0;
                                ?>
                                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(120px,1fr)); gap:14px; margin-top:12px;">
                                    <div style="background:#fff; border:1px solid var(--uwb-border); border-radius:10px; padding:16px; text-align:center;">
                                        <div style="font-size:22px; font-weight:800; color:var(--uwb-primary);"><?php echo number_format( $hits ); ?></div>
                                        <div style="font-size:12px; color:var(--uwb-text-muted); margin-top:4px;">Cache Hits</div>
                                    </div>
                                    <div style="background:#fff; border:1px solid var(--uwb-border); border-radius:10px; padding:16px; text-align:center;">
                                        <div style="font-size:22px; font-weight:800; color:#f59e0b;"><?php echo number_format( $misses ); ?></div>
                                        <div style="font-size:12px; color:var(--uwb-text-muted); margin-top:4px;">Cache Misses</div>
                                    </div>
                                    <div style="background:#fff; border:1px solid var(--uwb-border); border-radius:10px; padding:16px; text-align:center;">
                                        <div style="font-size:22px; font-weight:800; color:#10b981;"><?php echo $hit_ratio; ?>%</div>
                                        <div style="font-size:12px; color:var(--uwb-text-muted); margin-top:4px;">Hit Ratio</div>
                                    </div>
                                    <div style="background:#fff; border:1px solid var(--uwb-border); border-radius:10px; padding:16px; text-align:center;">
                                        <div style="font-size:22px; font-weight:800; color:#64748b;"><?php echo number_format( $total_req ); ?></div>
                                        <div style="font-size:12px; color:var(--uwb-text-muted); margin-top:4px;">Total Requests</div>
                                    </div>
                                </div>
                                <p style="font-size:12px; color:var(--uwb-text-muted); margin-top:14px;">Statistics are for the current page load only. Persistent stats require a backend like Redis with monitoring enabled.</p>
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

                        <!-- TAB 5: Dashboard -->
                        <div id="tab-url_status" class="uwb-tab-content active">
                            <h2 style="margin-top:0;">Dashboard</h2>
                            <p style="color:var(--uwb-text-muted); margin-bottom:20px;">View and manage all URLs in the preload queue. Filter by status, search, sort columns, and take actions on individual URLs.</p>

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
                            <div style="overflow-x:auto; border:1px solid var(--uwb-border); border-radius:10px;">
                                <table id="uwb-url-table" style="width:100%; border-collapse:collapse; font-size:13px;">
                                    <thead>
                                        <tr style="background:#f8fafc; border-bottom:1px solid var(--uwb-border);">
                                            <th class="uwb-sortable" data-col="url" style="padding:12px 14px; text-align:left; font-weight:700; cursor:pointer; user-select:none;">URL <span class="uwb-sort-icon">↕</span></th>
                                            <th class="uwb-sortable" data-col="status" style="padding:12px 14px; text-align:center; font-weight:700; cursor:pointer; user-select:none; white-space:nowrap;">Status <span class="uwb-sort-icon">↕</span></th>
                                            <th class="uwb-sortable" data-col="priority" style="padding:12px 14px; text-align:center; font-weight:700; cursor:pointer; user-select:none;">Priority <span class="uwb-sort-icon">↕</span></th>
                                            <th class="uwb-sortable" data-col="attempts" style="padding:12px 14px; text-align:center; font-weight:700; cursor:pointer; user-select:none;">Tries <span class="uwb-sort-icon">↕</span></th>
                                            <th class="uwb-sortable" data-col="last_attempt" style="padding:12px 14px; text-align:center; font-weight:700; cursor:pointer; user-select:none; white-space:nowrap;">Last Attempt <span class="uwb-sort-icon">↕</span></th>
                                            <th style="padding:12px 14px; text-align:center; font-weight:700;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="uwb-url-tbody">
                                        <tr><td colspan="6" style="text-align:center; padding:32px; color:var(--uwb-text-muted);">Loading...</td></tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <div id="uwb-url-pagination" style="display:flex; justify-content:space-between; align-items:center; margin-top:14px; font-size:13px; color:var(--uwb-text-muted);"></div>

                            <!-- Toast notification -->
                            <div id="uwb-url-toast" style="display:none; position:fixed; bottom:24px; right:24px; background:#1e293b; color:#fff; padding:12px 20px; border-radius:10px; font-size:13px; font-weight:600; z-index:9999; box-shadow:0 4px 20px rgba(0,0,0,0.2);"></div>
                        </div>

                        <!-- Form Submit (Floating Panel) -->
                        <div style="margin-top: 32px; padding-top: 24px; border-top: 1px solid var(--uwb-border); display: none; gap: 12px;" id="uwb-submit-row">
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

                // Hide submit row on non-settings tabs
                if (['updater_settings', 'url_status'].indexOf(tabId) !== -1) {
                    $('#uwb-submit-row').hide();
                } else {
                    $('#uwb-submit-row').show();
                }

                // Load URL table on first visit
                if (tabId === 'url_status' && !uwbUrlTableLoaded) {
                    uwbUrlTableLoaded = true;
                    loadUrlTable();
                }
            });

            // Preloader Live Tracker
            var checkInterval;
            var triggerInterval;
            var nonce = '<?php echo esc_js( wp_create_nonce( "uwb_admin_nonce" ) ); ?>';
            var uwbUrlTableLoaded = false;
            var uwbUrlPage = 1;
            var uwbUrlStatus = '';
            var uwbUrlSearch = '';
            var uwbUrlOrderby = 'id';
            var uwbUrlOrder = 'ASC';

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
            $('#btn-start-preload').on('click', function(e) {
                e.preventDefault();
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
            $('#btn-stop-preload').on('click', function(e) {
                e.preventDefault();
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
            $('#btn-clear-preload').on('click', function(e) {
                e.preventDefault();
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

            // Toggle Redis configuration fields
            function toggleRedisFields() {
                var connType = $('#uwb_redis_conn_type').val();
                if (connType === 'socket') {
                    $('#redis-tcp-settings').hide();
                    $('#redis-socket-settings').show();
                } else {
                    $('#redis-tcp-settings').show();
                    $('#redis-socket-settings').hide();
                }
            }
            $('#uwb_redis_conn_type').on('change', toggleRedisFields);
            toggleRedisFields();

            // Test Redis Connection
            $('#btn-test-redis').on('click', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var $result = $('#redis-test-result');
                
                $btn.prop('disabled', true).text('Testing...');
                $result.hide().removeClass('notice-success notice-error').css({'background': '', 'color': '', 'border': ''});
                
                var connType = $('#uwb_redis_conn_type').val();
                var host = $('#uwb_redis_host').val();
                var port = $('#uwb_redis_port').val();
                var socket = $('#uwb_redis_socket').val();
                var password = $('#uwb_redis_password').val();
                var db = $('#uwb_redis_db').val();

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'uwb_test_redis_connection',
                        nonce: nonce,
                        conn_type: connType,
                        host: host,
                        port: port,
                        socket: socket,
                        password: password,
                        db: db
                    },
                    success: function(res) {
                        $btn.prop('disabled', false).text('Test Connection');
                        $result.show();
                        if (res.success) {
                            $result.css({
                                'background': '#d1fae5',
                                'color': '#065f46',
                                'border': '1px solid #6ee7b7'
                            }).text(res.data.message);
                        } else {
                            $result.css({
                                'background': '#fee2e2',
                                'color': '#991b1b',
                                'border': '1px solid #fca5a5'
                            }).text(res.data.message);
                        }
                    },
                    error: function() {
                        $btn.prop('disabled', false).text('Test Connection');
                        $result.show().css({
                            'background': '#fee2e2',
                            'color': '#991b1b',
                            'border': '1px solid #fca5a5'
                        }).text('Server error occurred during the test.');
                    }
                });
            });

            // Flush Redis Cache
            $('#btn-flush-redis').on('click', function(e) {
                e.preventDefault();
                if (!confirm('Are you sure you want to flush the persistent object cache?')) {
                    return;
                }
                var $btn = $(this);
                $btn.prop('disabled', true).text('Flushing...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'uwb_flush_redis_cache',
                        nonce: nonce
                    },
                    success: function(res) {
                        $btn.prop('disabled', false).text('Flush Cache');
                        if (res.success) {
                            showToast(res.data.message);
                            // Reload page after a delay to update stats
                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        } else {
                            showToast(res.data.message, true);
                        }
                    },
                    error: function() {
                        $btn.prop('disabled', false).text('Flush Cache');
                        showToast('Server error flushing cache.', true);
                    }
                });
            });

            /* =====================================================
               URL STATUS TABLE
            ===================================================== */

            var uwbSearchTimer = null;

            function showToast(msg, isError) {
                var $t = $('#uwb-url-toast');
                $t.text(msg).css('background', isError ? '#dc2626' : '#1e293b').fadeIn(200);
                setTimeout(function() { $t.fadeOut(400); }, 3000);
            }

            function statusBadge(s) {
                var colors = {
                    pending:    'background:#fef9c3; color:#92400e; border:1px solid #fcd34d;',
                    processing: 'background:#dbeafe; color:#1e40af; border:1px solid #93c5fd;',
                    completed:  'background:#d1fae5; color:#065f46; border:1px solid #6ee7b7;',
                    failed:     'background:#fee2e2; color:#991b1b; border:1px solid #fca5a5;'
                };
                var style = colors[s] || '';
                return '<span style="' + style + ' padding:3px 10px; border-radius:20px; font-size:11.5px; font-weight:700; white-space:nowrap;">' + s + '</span>';
            }

            function loadUrlTable() {
                $('#uwb-url-tbody').html('<tr><td colspan="6" style="text-align:center; padding:32px; color:var(--uwb-text-muted);">Loading...</td></tr>');
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action:  'uwb_get_url_table',
                        nonce:   nonce,
                        status:  uwbUrlStatus,
                        search:  uwbUrlSearch,
                        orderby: uwbUrlOrderby,
                        order:   uwbUrlOrder,
                        page:    uwbUrlPage
                    },
                    success: function(res) {
                        if (!res.success) { showToast('Failed to load table.', true); return; }
                        renderUrlTable(res.data);
                    },
                    error: function() { showToast('Server error loading table.', true); }
                });
            }

            function renderUrlTable(data) {
                var rows = data.rows;
                var $tbody = $('#uwb-url-tbody');
                if (!rows || rows.length === 0) {
                    $tbody.html('<tr><td colspan="6" style="text-align:center; padding:32px; color:var(--uwb-text-muted);">No URLs found.</td></tr>');
                } else {
                    var html = '';
                    $.each(rows, function(i, r) {
                        var rowBg = (i % 2 === 0) ? '#ffffff' : '#f8fafc';
                        var priorityLabel = r.priority == 1 ? '<span style="color:#f59e0b; font-weight:700;">★ High</span>' : '<span style="color:#cbd5e1;">Normal</span>';
                        var lastAttempt = r.last_attempt ? r.last_attempt : '—';
                        html += '<tr style="background:' + rowBg + '; border-bottom:1px solid #f1f5f9; transition:background 0.15s;" onmouseover="this.style.background=\'#eef2ff\'" onmouseout="this.style.background=\'' + rowBg + '\'">';
                        html += '<td style="padding:10px 14px; max-width:380px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="' + $('<div>').text(r.url).html() + '"><a href="' + $('<div>').text(r.url).html() + '" target="_blank" style="color:var(--uwb-primary); text-decoration:none; font-size:12.5px;">' + $('<div>').text(r.url).html() + '</a></td>';
                        html += '<td style="padding:10px 14px; text-align:center;">' + statusBadge(r.status) + '</td>';
                        html += '<td style="padding:10px 14px; text-align:center;">' + priorityLabel + '</td>';
                        html += '<td style="padding:10px 14px; text-align:center; color:var(--uwb-text-muted);">' + r.attempts + '</td>';
                        html += '<td style="padding:10px 14px; text-align:center; color:var(--uwb-text-muted); font-size:12px;">' + lastAttempt + '</td>';
                        html += '<td style="padding:10px 14px; text-align:center; white-space:nowrap;">';
                        html += '<button class="uwb-act-process" data-id="' + r.id + '" style="background:#6366f1; color:#fff; border:none; border-radius:5px; padding:5px 10px; font-size:11.5px; font-weight:600; cursor:pointer; margin:2px;" title="Process this URL now">▶ Now</button>';
                        html += '<button class="uwb-act-exclude" data-id="' + r.id + '" style="background:#f1f5f9; border:1px solid #cbd5e1; color:#475569; border-radius:5px; padding:5px 10px; font-size:11.5px; font-weight:600; cursor:pointer; margin:2px;" title="Add to Exclude list">✕ Exclude</button>';
                        html += '<button class="uwb-act-priority" data-id="' + r.id + '" style="background:#fef9c3; border:1px solid #fcd34d; color:#92400e; border-radius:5px; padding:5px 10px; font-size:11.5px; font-weight:600; cursor:pointer; margin:2px;" title="Add to Priority URLs">★ Priority</button>';
                        html += '</td></tr>';
                    });
                    $tbody.html(html);
                }

                // Pagination
                var totalPages = data.total_pages;
                var currentPage = data.page;
                var from = (currentPage - 1) * data.per_page + 1;
                var to = Math.min(currentPage * data.per_page, data.total);
                var paginHtml = '<span>Showing ' + from + '–' + to + ' of ' + data.total + ' URLs</span>';
                paginHtml += '<div style="display:flex; gap:6px;">';
                if (currentPage > 1) {
                    paginHtml += '<button id="uwb-page-prev" style="border:1px solid var(--uwb-border); background:#fff; border-radius:6px; padding:5px 12px; cursor:pointer; font-weight:600;">‹ Prev</button>';
                }
                for (var p = Math.max(1, currentPage - 2); p <= Math.min(totalPages, currentPage + 2); p++) {
                    var active = (p === currentPage) ? 'background:var(--uwb-primary);color:#fff;' : 'background:#fff;';
                    paginHtml += '<button class="uwb-page-btn" data-page="' + p + '" style="border:1px solid var(--uwb-border);' + active + 'border-radius:6px; padding:5px 12px; cursor:pointer; font-weight:600;">' + p + '</button>';
                }
                if (currentPage < totalPages) {
                    paginHtml += '<button id="uwb-page-next" style="border:1px solid var(--uwb-border); background:#fff; border-radius:6px; padding:5px 12px; cursor:pointer; font-weight:600;">Next ›</button>';
                }
                paginHtml += '</div>';
                $('#uwb-url-pagination').html(paginHtml);
            }

            // Sort headers
            $(document).on('click', '.uwb-sortable', function(e) {
                e.preventDefault();
                var col = $(this).data('col');
                if (uwbUrlOrderby === col) {
                    uwbUrlOrder = (uwbUrlOrder === 'ASC') ? 'DESC' : 'ASC';
                } else {
                    uwbUrlOrderby = col;
                    uwbUrlOrder = 'ASC';
                }
                $('.uwb-sort-icon').text('↕');
                $(this).find('.uwb-sort-icon').text(uwbUrlOrder === 'ASC' ? '↑' : '↓');
                uwbUrlPage = 1;
                loadUrlTable();
            });

            // Filter buttons
            $(document).on('click', '.uwb-filter-btn', function(e) {
                e.preventDefault();
                $('.uwb-filter-btn').css('outline', '').removeClass('active');
                $(this).css('outline', '2px solid var(--uwb-primary)').addClass('active');
                uwbUrlStatus = $(this).data('status');
                uwbUrlPage = 1;
                loadUrlTable();
            });

            // Search
            $('#uwb-url-search').on('input', function() {
                clearTimeout(uwbSearchTimer);
                var val = $(this).val();
                uwbSearchTimer = setTimeout(function() {
                    uwbUrlSearch = val;
                    uwbUrlPage = 1;
                    loadUrlTable();
                }, 400);
            });

            // Refresh button
            $('#uwb-url-refresh').on('click', function(e) { e.preventDefault(); loadUrlTable(); });

            // Pagination
            $(document).on('click', '#uwb-page-prev', function(e) { e.preventDefault(); uwbUrlPage--; loadUrlTable(); });
            $(document).on('click', '#uwb-page-next', function(e) { e.preventDefault(); uwbUrlPage++; loadUrlTable(); });
            $(document).on('click', '.uwb-page-btn', function(e) { e.preventDefault(); uwbUrlPage = parseInt($(this).data('page')); loadUrlTable(); });

            // Row action: Process Now
            $(document).on('click', '.uwb-act-process', function(e) {
                e.preventDefault();
                var id = $(this).data('id');
                var $btn = $(this).prop('disabled', true).text('...');
                $.ajax({
                    url: ajaxurl, type: 'POST',
                    data: { action: 'uwb_process_url_now', nonce: nonce, id: id },
                    success: function(res) {
                        $btn.prop('disabled', false).text('▶ Now');
                        if (res.success) {
                            showToast('Done! Status: ' + res.data.status);
                            loadUrlTable();
                        } else {
                            showToast(res.data.message, true);
                        }
                    },
                    error: function() { $btn.prop('disabled', false).text('▶ Now'); showToast('Error.', true); }
                });
            });

            // Row action: Add to Exclude
            $(document).on('click', '.uwb-act-exclude', function(e) {
                e.preventDefault();
                var id = $(this).data('id');
                var $btn = $(this).prop('disabled', true).text('...');
                $.ajax({
                    url: ajaxurl, type: 'POST',
                    data: { action: 'uwb_add_to_exclude', nonce: nonce, id: id },
                    success: function(res) {
                        $btn.prop('disabled', false).text('✕ Exclude');
                        if (res.success) { showToast(res.data.message); }
                        else { showToast(res.data.message, true); }
                    },
                    error: function() { $btn.prop('disabled', false).text('✕ Exclude'); showToast('Error.', true); }
                });
            });

            // Row action: Add to Priority
            $(document).on('click', '.uwb-act-priority', function(e) {
                e.preventDefault();
                var id = $(this).data('id');
                var $btn = $(this).prop('disabled', true).text('...');
                $.ajax({
                    url: ajaxurl, type: 'POST',
                    data: { action: 'uwb_add_to_priority', nonce: nonce, id: id },
                    success: function(res) {
                        $btn.prop('disabled', false).text('★ Priority');
                        if (res.success) { showToast(res.data.message); loadUrlTable(); }
                        else { showToast(res.data.message, true); }
                    },
                    error: function() { $btn.prop('disabled', false).text('★ Priority'); showToast('Error.', true); }
                });
            });

            // Load URL table on load since it is the default tab (Dashboard)
            uwbUrlTableLoaded = true;
            loadUrlTable();

        });
        </script>
        <?php

    }

    public function ajax_test_redis_connection() {
        check_ajax_referer( 'uwb_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ) );
        }

        $redis_host = defined( 'WP_REDIS_HOST' ) ? WP_REDIS_HOST : '127.0.0.1';
        $redis_port = defined( 'WP_REDIS_PORT' ) ? WP_REDIS_PORT : 6379;
        $redis_password = defined( 'WP_REDIS_PASSWORD' ) ? WP_REDIS_PASSWORD : '';

        if ( ! class_exists( 'Redis' ) ) {
            // Check if socket is open as a fallback indicator
            $fp = @fsockopen( $redis_host, $redis_port, $errno, $errstr, 1.0 );
            if ( $fp ) {
                fclose( $fp );
                wp_send_json_success( array(
                    'message' => sprintf( 'Redis extension is not installed, but port %d is open on %s.', $redis_port, $redis_host )
                ) );
            } else {
                wp_send_json_error( array(
                    'message' => 'Redis PHP extension is not installed, and could not connect to port ' . $redis_port . ' on ' . $redis_host
                ) );
            }
            exit;
        }

        $redis = new Redis();
        try {
            $connected = @$redis->connect( $redis_host, $redis_port, 1.0 );
            if ( ! $connected ) {
                wp_send_json_error( array(
                    'message' => sprintf( 'Could not connect to Redis server at %s:%d.', $redis_host, $redis_port )
                ) );
            }

            if ( ! empty( $redis_password ) ) {
                $authenticated = @$redis->auth( $redis_password );
                if ( ! $authenticated ) {
                    wp_send_json_error( array(
                        'message' => 'Authentication failed. Please verify WP_REDIS_PASSWORD.'
                    ) );
                }
            }

            $ping = $redis->ping();
            $ping_str = is_bool( $ping ) ? ( $ping ? 'PONG' : 'FAIL' ) : (string) $ping;

            wp_send_json_success( array(
                'message' => sprintf( 'Connection successful! Host: %s:%d | Ping: %s', $redis_host, $redis_port, $ping_str )
            ) );

        } catch ( Exception $e ) {
            wp_send_json_error( array(
                'message' => 'Redis error: ' . $e->getMessage()
            ) );
        }
        exit;
    }

    public function ajax_flush_redis_cache() {
        check_ajax_referer( 'uwb_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ) );
        }

        $flushed = wp_cache_flush();

        if ( $flushed ) {
            wp_send_json_success( array( 'message' => 'Redis Object Cache flushed successfully!' ) );
        } else {
            // Try direct flushing via Redis extension
            if ( class_exists( 'Redis' ) ) {
                $redis_host = defined( 'WP_REDIS_HOST' ) ? WP_REDIS_HOST : '127.0.0.1';
                $redis_port = defined( 'WP_REDIS_PORT' ) ? WP_REDIS_PORT : 6379;
                $redis_password = defined( 'WP_REDIS_PASSWORD' ) ? WP_REDIS_PASSWORD : '';

                $redis = new Redis();
                try {
                    if ( @$redis->connect( $redis_host, $redis_port, 1.0 ) ) {
                        if ( ! empty( $redis_password ) ) {
                            @$redis->auth( $redis_password );
                        }
                        $redis->flushDB();
                        wp_send_json_success( array( 'message' => 'Redis DB flushed successfully via direct connection!' ) );
                    }
                } catch ( Exception $e ) {
                    // fall through
                }
            }
            wp_send_json_error( array( 'message' => 'Failed to flush object cache. Make sure Redis Object Cache is active and configured.' ) );
        }
        exit;
    }
}
