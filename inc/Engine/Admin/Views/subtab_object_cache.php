<?php
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
// Views/subtab_object_cache.php
?>
                            <div id="subtab-object_cache" class="uwb-subtab-content">
                                <div style="background:#f8fafc; border:1px solid var(--uwb-border); border-radius:12px; padding:24px; margin-bottom:24px;">
                                    <?php $this->render_submodule_header( 'uwb_module_object_cache_enabled', 'Object Cache Settings', '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="8" rx="2" ry="2"/><rect x="2" y="14" width="20" height="8" rx="2" ry="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>' ); ?>
                                <?php
                                $oc_active = wp_using_ext_object_cache();
                                $oc_dropin = file_exists( WP_CONTENT_DIR . '/object-cache.php' );
                                $oc_type = intval( get_option( 'uwb_redis_enabled', 0 ) );

                                // Fetch stats if active
                                $stats_error = '';
                                $stats_data = array();

                                if ( $oc_active ) {
                                    $curr_conn_type = get_option('uwb_redis_conn_type', 'tcp');
                                    $curr_host = get_option('uwb_redis_host', '127.0.0.1');
                                    $curr_port = get_option('uwb_redis_port', 6379);
                                    $curr_socket = get_option('uwb_redis_socket', '');
                                    $curr_password = get_option('uwb_redis_password', '');
                                    $curr_db = get_option('uwb_redis_db', 0);

                                    if ( $oc_type === 2 ) {
                                        // Memcached
                                        if ( class_exists( 'Memcached' ) ) {
                                            if ( intval( $curr_port ) === 6379 ) {
                                                $curr_port = 11211;
                                            }
                                            $m = new \Memcached();
                                            $m->addServer( $curr_host, $curr_port );
                                            $m_stats = $m->getStats();

                                            if ( is_array( $m_stats ) && ! empty( $m_stats ) ) {
                                                $stats = reset( $m_stats );
                                                if ( is_array( $stats ) && isset( $stats['pid'] ) && $stats['pid'] > 0 ) {
                                                    $stats_data['type'] = 'memcached';
                                                    $stats_data['version'] = isset( $stats['version'] ) ? $stats['version'] : 'Unknown';
                                                    $stats_data['uptime'] = isset( $stats['uptime'] ) ? intval( $stats['uptime'] ) : 0;
                                                    $stats_data['curr_items'] = isset( $stats['curr_items'] ) ? intval( $stats['curr_items'] ) : 0;

                                                    $bytes = isset( $stats['bytes'] ) ? floatval( $stats['bytes'] ) : 0;
                                                    $limit_maxbytes = isset( $stats['limit_maxbytes'] ) ? floatval( $stats['limit_maxbytes'] ) : 0;
                                                    $stats_data['memory_used'] = size_format( $bytes );
                                                    $stats_data['memory_total'] = size_format( $limit_maxbytes );
                                                    $stats_data['memory_pct'] = $limit_maxbytes > 0 ? round( ( $bytes / $limit_maxbytes ) * 100, 1 ) : 0;

                                                    $hits = isset( $stats['get_hits'] ) ? floatval( $stats['get_hits'] ) : 0;
                                                    $misses = isset( $stats['get_misses'] ) ? floatval( $stats['get_misses'] ) : 0;
                                                    $total_req = $hits + $misses;
                                                    $stats_data['hits'] = $hits;
                                                    $stats_data['misses'] = $misses;
                                                    $stats_data['hit_ratio'] = $total_req > 0 ? round( ( $hits / $total_req ) * 100, 1 ) : 0;
                                                } else {
                                                    $stats_error = 'Could not retrieve stats from Memcached server.';
                                                }
                                            } else {
                                                $stats_error = 'Could not connect to Memcached server to fetch stats.';
                                            }
                                        } else {
                                            $stats_error = 'PHP Memcached extension is not loaded.';
                                        }
                                    } else {
                                        // Redis
                                        if ( class_exists( 'Redis' ) ) {
                                            if ( intval( $curr_port ) === 11211 ) {
                                                $curr_port = 6379;
                                            }
                                            $redis = new \Redis();
                                            try {
                                                if ( $curr_conn_type === 'socket' && ! empty( $curr_socket ) ) {
                                                    $connected = @$redis->connect( $curr_socket );
                                                } else {
                                                    $connected = @$redis->connect( $curr_host, $curr_port, 1.0 );
                                                }

                                                if ( $connected ) {
                                                    if ( ! empty( $curr_password ) ) {
                                                        @$redis->auth( $curr_password );
                                                    }
                                                    $info = @$redis->info();
                                                    if ( is_array( $info ) ) {
                                                        $stats_data['type'] = 'redis';
                                                        $stats_data['version'] = isset( $info['redis_version'] ) ? $info['redis_version'] : 'Unknown';
                                                        $stats_data['uptime'] = isset( $info['uptime_in_seconds'] ) ? intval( $info['uptime_in_seconds'] ) : 0;
                                                        $stats_data['connected_clients'] = isset( $info['connected_clients'] ) ? intval( $info['connected_clients'] ) : 0;

                                                        // Memory usage
                                                        $used_memory = isset( $info['used_memory'] ) ? floatval( $info['used_memory'] ) : 0;
                                                        $total_system_memory = isset( $info['total_system_memory'] ) ? floatval( $info['total_system_memory'] ) : 0;
                                                        $maxmemory = isset( $info['maxmemory'] ) ? floatval( $info['maxmemory'] ) : 0;

                                                        $stats_data['memory_used'] = size_format( $used_memory );
                                                        if ( $maxmemory > 0 ) {
                                                            $stats_data['memory_total'] = size_format( $maxmemory ) . ' (maxmemory)';
                                                            $stats_data['memory_pct'] = round( ( $used_memory / $maxmemory ) * 100, 1 );
                                                        } elseif ( $total_system_memory > 0 ) {
                                                            $stats_data['memory_total'] = size_format( $total_system_memory ) . ' (system)';
                                                            $stats_data['memory_pct'] = round( ( $used_memory / $total_system_memory ) * 100, 1 );
                                                        } else {
                                                            $stats_data['memory_total'] = 'N/A';
                                                            $stats_data['memory_pct'] = 0;
                                                        }

                                                        // Keys space stats
                                                        $db_key = 'db' . $curr_db;
                                                        $keys_count = 0;
                                                        if ( isset( $info[ $db_key ] ) ) {
                                                            preg_match( '/keys=(\d+)/', $info[ $db_key ], $matches );
                                                            if ( isset( $matches[1] ) ) {
                                                                $keys_count = intval( $matches[1] );
                                                            }
                                                        }
                                                        $stats_data['keys'] = $keys_count;

                                                        // Hit Ratio from Redis Keyspace
                                                        $hits = isset( $info['keyspace_hits'] ) ? floatval( $info['keyspace_hits'] ) : 0;
                                                        $misses = isset( $info['keyspace_misses'] ) ? floatval( $info['keyspace_misses'] ) : 0;
                                                        $total_req = $hits + $misses;
                                                        $stats_data['hits'] = $hits;
                                                        $stats_data['misses'] = $misses;
                                                        $stats_data['hit_ratio'] = $total_req > 0 ? round( ( $hits / $total_req ) * 100, 1 ) : 0;
                                                    } else {
                                                        $stats_error = 'Could not fetch INFO from Redis server.';
                                                    }
                                                } else {
                                                    $stats_error = 'Could not connect to Redis server.';
                                                }
                                            } catch ( \Exception $e ) {
                                                $stats_error = 'Redis client error: ' . $e->getMessage();
                                            }
                                        } else {
                                            $stats_error = 'PHP Redis class does not exist.';
                                        }
                                    }
                                }
                                ?>
                                
                                <!-- Object Cache Status Panel: Full Width -->
                                <div style="margin-bottom:20px; width:100%;">
                                    <!-- Cache Status & Connection Test Block -->
                                    <div style="background:#f8fafc; border:1px solid var(--uwb-border); border-radius:12px; padding:24px; display:flex; flex-direction:column; justify-content:space-between; margin-bottom:0;">
                                        <div>
                                            <h3 style="margin-top:0; font-size:15px; display:flex; align-items:center; gap:8px;">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="8" rx="2" ry="2"/><rect x="2" y="14" width="20" height="8" rx="2" ry="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>
                                                Object Cache Status
                                            </h3>
                                            
                                            <div style="margin-top:16px; display:flex; flex-direction:column; gap:16px;">
                                                <!-- Status Info -->
                                                <div style="display:flex; align-items:center; justify-content:space-between;">
                                                    <span style="font-weight:600; font-size:13.5px; color:var(--uwb-text);">Status:</span>
                                                    <?php
                                                    if ( $oc_active ) {
                                                        if ( $oc_type === 2 ) {
                                                            echo '<div style="display:inline-flex; align-items:center; gap:6px; background:#d1fae5; color:#065f46; border:1px solid #6ee7b7; padding:6px 12px; border-radius:6px; font-weight:700; font-size:12px;"><span style="width:6px;height:6px;background:#10b981;border-radius:50%;display:inline-block;"></span> Active (Memcached)</div>';
                                                        } else {
                                                            echo '<div style="display:inline-flex; align-items:center; gap:6px; background:#d1fae5; color:#065f46; border:1px solid #6ee7b7; padding:6px 12px; border-radius:6px; font-weight:700; font-size:12px;"><span style="width:6px;height:6px;background:#10b981;border-radius:50%;display:inline-block;"></span> Active (Redis)</div>';
                                                        }
                                                    } else {
                                                        echo '<div style="display:inline-flex; align-items:center; gap:6px; background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; padding:6px 12px; border-radius:6px; font-weight:700; font-size:12px;"><span style="width:6px;height:6px;background:#ef4444;border-radius:50%;display:inline-block;"></span> Inactive</div>';
                                                    }
                                                    ?>
                                                </div>

                                                <!-- Drop-in Info -->
                                                <div style="border-top:1px solid var(--uwb-border); padding-top:12px; display:flex; align-items:center; justify-content:space-between; font-size:13px;">
                                                    <span style="font-weight:600; color:var(--uwb-text);">Drop-in File:</span>
                                                    <?php if ( $oc_dropin ) : ?>
                                                        <span style="color:#059669; font-weight:600;">✓ Installed</span>
                                                    <?php else : ?>
                                                        <span style="color:#d97706; font-weight:600;">✗ Not Found</span>
                                                    <?php endif; ?>
                                                </div>

                                                <!-- Connection Info -->
                                                <div style="border-top:1px solid var(--uwb-border); padding-top:12px; display:flex; flex-direction:column; gap:6px; font-size:13px;">
                                                    <?php
                                                    $curr_conn_type = get_option('uwb_redis_conn_type', 'tcp');
                                                    $curr_host = get_option('uwb_redis_host', '127.0.0.1');
                                                    $curr_port = get_option('uwb_redis_port', 6379);
                                                    $curr_socket = get_option('uwb_redis_socket', '');
                                                    $curr_db = get_option('uwb_redis_db', 0);
                                                    
                                                    if ( $oc_type === 0 ) {
                                                        $redis_available = extension_loaded('redis') || class_exists('Redis');
                                                        $mc_available = extension_loaded('memcached');
                                                        ?>
                                                        <div style="display:flex; justify-content:space-between;">
                                                            <span style="font-weight:600; color:var(--uwb-text);">Connection:</span>
                                                            <span style="color:var(--uwb-text-muted);">Disabled</span>
                                                        </div>
                                                        <div style="display:flex; justify-content:space-between;">
                                                            <span style="font-weight:600; color:var(--uwb-text);">Redis Extension:</span>
                                                            <span><?php echo $redis_available ? 'Available ✓' : 'Not Installed ✗'; ?></span>
                                                        </div>
                                                        <div style="display:flex; justify-content:space-between;">
                                                            <span style="font-weight:600; color:var(--uwb-text);">Memcached Extension:</span>
                                                            <span><?php echo $mc_available ? 'Available ✓' : 'Not Installed ✗'; ?></span>
                                                        </div>
                                                        <?php
                                                    } else {
                                                        if ( $oc_type === 2 ) {
                                                            if ( intval( $curr_port ) === 6379 ) {
                                                                $curr_port = 11211;
                                                            }
                                                            $conn_str = esc_html( $curr_host . ':' . $curr_port );
                                                            $ext_available = extension_loaded('memcached');
                                                            $ext_label = 'Memcached';
                                                        } else {
                                                            if ( intval( $curr_port ) === 11211 ) {
                                                                $curr_port = 6379;
                                                            }
                                                            if ( $curr_conn_type === 'socket' ) {
                                                                $conn_str = esc_html( $curr_socket );
                                                            } else {
                                                                $conn_str = esc_html( $curr_host . ':' . $curr_port );
                                                            }
                                                            $ext_available = extension_loaded('redis') || class_exists('Redis');
                                                            $ext_label = 'Redis';
                                                        }
                                                        ?>
                                                        <div style="display:flex; justify-content:space-between;">
                                                            <span style="font-weight:600; color:var(--uwb-text);">Connection:</span>
                                                            <code><?php echo $conn_str; ?><?php if ($oc_type !== 2) { echo ' (DB ' . intval( $curr_db ) . ')'; } ?></code>
                                                        </div>
                                                        <div style="display:flex; justify-content:space-between;">
                                                            <span style="font-weight:600; color:var(--uwb-text);">PHP Extension:</span>
                                                            <span><?php echo $ext_available ? $ext_label . ' Available ✓' : $ext_label . ' Not Installed ✗'; ?></span>
                                                        </div>
                                                        <?php
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                            <div id="redis-test-result" style="display:none; padding:10px 14px; border-radius:8px; font-size:12.5px; font-weight:600; margin-top:12px;"></div>
                                        </div>
                                        <div style="display:flex; gap:12px; margin-top:16px; border-top:1px solid var(--uwb-border); padding-top:12px;">
                                            <button type="button" id="btn-test-redis" class="button" style="border:1px solid var(--uwb-border); padding:8px 16px; border-radius:6px; font-weight:600; font-size:12.5px; background:#fff; cursor:pointer; color:var(--uwb-text); transition:all 0.2s; flex:1;">Test Connection</button>
                                            <button type="button" id="btn-flush-redis" class="button" style="border:1px solid #fca5a5; background:#fee2e2; color:#991b1b; padding:8px 16px; border-radius:6px; font-weight:600; font-size:12.5px; cursor:pointer; transition:all 0.2s; flex:1;">Flush Cache</button>
                                        </div>
                                    </div>
                                </div>

                                <?php if ( $oc_active && ! empty( $stats_data ) ) : ?>
                                    <!-- Gauges Grid: 2 Columns side-by-side -->
                                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:20px; margin-bottom:24px; align-items:stretch;">
                                        <!-- Gauge 1: Hit Rate -->
                                        <div style="background:#fff; border:1px solid var(--uwb-border); border-radius:12px; padding:24px; text-align:center; display:flex; flex-direction:column; align-items:center; justify-content:center;">
                                            <strong style="font-size:14px; color:var(--uwb-text); margin-bottom:12px; display:block;">Hit Rate (Keyspace)</strong>
                                            <div style="position:relative; width:120px; height:120px; display:flex; align-items:center; justify-content:center;">
                                                <svg width="100%" height="100%" viewBox="0 0 36 36">
                                                    <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="#e2e8f0" stroke-width="3" />
                                                    <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="var(--uwb-primary)" stroke-dasharray="<?php echo round($stats_data['hit_ratio']); ?>, 100" stroke-width="3" stroke-linecap="round" />
                                                </svg>
                                                <span style="position:absolute; font-size:20px; font-weight:700; color:var(--uwb-text);"><?php echo $stats_data['hit_ratio']; ?>%</span>
                                            </div>
                                        </div>

                                        <!-- Gauge 2: Memory -->
                                        <div style="background:#fff; border:1px solid var(--uwb-border); border-radius:12px; padding:24px; text-align:center; display:flex; flex-direction:column; align-items:center; justify-content:center;">
                                            <strong style="font-size:14px; color:var(--uwb-text); margin-bottom:12px; display:block;">Memory Usage</strong>
                                            <div style="position:relative; width:120px; height:120px; display:flex; align-items:center; justify-content:center;">
                                                <svg width="100%" height="100%" viewBox="0 0 36 36">
                                                    <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="#e2e8f0" stroke-width="3" />
                                                    <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="var(--uwb-primary)" stroke-dasharray="<?php echo $stats_data['memory_pct']; ?>, 100" stroke-width="3" stroke-linecap="round" />
                                                </svg>
                                                <span style="position:absolute; font-size:20px; font-weight:700; color:var(--uwb-text);"><?php echo $stats_data['memory_pct']; ?>%</span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if ( $oc_active && ! empty( $stats_data ) ) : ?>
                                    <!-- Stats details row: 2 Cards -->
                                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:20px; margin-bottom:24px;">
                                        <!-- Card 1: Server Stats -->
                                        <div style="background:#fff; border:1px solid var(--uwb-border); border-radius:10px; padding:20px;">
                                            <strong style="font-size:14px; color:var(--uwb-text); margin-bottom:12px; display:block; border-bottom:1px solid var(--uwb-border); padding-bottom:8px;">Server Information</strong>
                                            <table style="width:100%; font-size:12.5px; border-collapse:collapse;">
                                                <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:6px 0; color:var(--uwb-text-muted);">Cache Type:</td><td style="padding:6px 0; text-align:right; font-weight:600; color:var(--uwb-text);"><?php echo esc_html(ucfirst($stats_data['type'])); ?></td></tr>
                                                <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:6px 0; color:var(--uwb-text-muted);">Version:</td><td style="padding:6px 0; text-align:right; font-weight:600; color:var(--uwb-text);"><?php echo esc_html($stats_data['version']); ?></td></tr>
                                                <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:6px 0; color:var(--uwb-text-muted);">Uptime:</td><td style="padding:6px 0; text-align:right; font-weight:600; color:var(--uwb-text);"><?php echo human_time_diff(0, $stats_data['uptime']); ?></td></tr>
                                                <?php if ( isset( $stats_data['connected_clients'] ) ) : ?>
                                                    <tr><td style="padding:6px 0; color:var(--uwb-text-muted);">Connected Clients:</td><td style="padding:6px 0; text-align:right; font-weight:600; color:var(--uwb-text);"><?php echo number_format($stats_data['connected_clients']); ?></td></tr>
                                                <?php endif; ?>
                                            </table>
                                        </div>

                                        <!-- Card 2: Cache Usage Stats -->
                                        <div style="background:#fff; border:1px solid var(--uwb-border); border-radius:10px; padding:20px;">
                                            <strong style="font-size:14px; color:var(--uwb-text); margin-bottom:12px; display:block; border-bottom:1px solid var(--uwb-border); padding-bottom:8px;">Cache Statistics</strong>
                                            <table style="width:100%; font-size:12.5px; border-collapse:collapse;">
                                                <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:6px 0; color:var(--uwb-text-muted);">Memory Used:</td><td style="padding:6px 0; text-align:right; font-weight:600; color:var(--uwb-text);"><?php echo esc_html($stats_data['memory_used']); ?></td></tr>
                                                <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:6px 0; color:var(--uwb-text-muted);">Memory Limit:</td><td style="padding:6px 0; text-align:right; font-weight:600; color:var(--uwb-text);"><?php echo esc_html($stats_data['memory_total']); ?></td></tr>
                                                <?php if ( isset( $stats_data['keys'] ) ) : ?>
                                                    <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:6px 0; color:var(--uwb-text-muted);">Total Keys (Current DB):</td><td style="padding:6px 0; text-align:right; font-weight:600; color:var(--uwb-text);"><?php echo number_format($stats_data['keys']); ?></td></tr>
                                                <?php elseif ( isset( $stats_data['curr_items'] ) ) : ?>
                                                    <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:6px 0; color:var(--uwb-text-muted);">Total Items:</td><td style="padding:6px 0; text-align:right; font-weight:600; color:var(--uwb-text);"><?php echo number_format($stats_data['curr_items']); ?></td></tr>
                                                <?php endif; ?>
                                                <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:6px 0; color:var(--uwb-text-muted);">Hits:</td><td style="padding:6px 0; text-align:right; font-weight:600; color:var(--uwb-text);"><?php echo number_format($stats_data['hits']); ?></td></tr>
                                                <tr><td style="padding:6px 0; color:var(--uwb-text-muted);">Misses:</td><td style="padding:6px 0; text-align:right; font-weight:600; color:var(--uwb-text);"><?php echo number_format($stats_data['misses']); ?></td></tr>
                                            </table>
                                        </div>
                                    </div>
                                <?php elseif ( $oc_active && ! empty( $stats_error ) ) : ?>
                                    <div style="background:#fff; border:1px solid var(--uwb-border); border-radius:12px; padding:20px; margin-bottom:24px; border-left: 4px solid var(--uwb-danger);">
                                        <p style="margin:0; font-size:13.5px; font-weight:600; color:var(--uwb-danger);">Không thể lấy thông tin chi tiết Object Cache:</p>
                                        <p style="margin:8px 0 0 0; font-size:13px; color:var(--uwb-text-muted);"><?php echo esc_html($stats_error); ?></p>
                                    </div>
                                <?php endif; ?>

                                <!-- Group 4: Object Cache Settings -->
                                <div style="background:#f8fafc; border:1px solid var(--uwb-border); border-radius:12px; padding:24px; margin-bottom:24px;">
                                    <h3 style="margin-top:0; margin-bottom:20px; font-size:15px; display:flex; align-items:center; gap:8px;">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.1a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>
                                        Object Cache Settings
                                    </h3>

                                    <div class="uwb-form-group">
                                        <label for="uwb_redis_enabled">Enable Object Cache?</label>
                                        <select name="uwb_redis_enabled" id="uwb_redis_enabled" style="width:100%; border:1px solid var(--uwb-border); border-radius:8px; padding:12px;">
                                            <option value="0" <?php selected( get_option( 'uwb_redis_enabled', 0 ), 0 ); ?>>None</option>
                                            <option value="1" <?php selected( get_option( 'uwb_redis_enabled', 0 ), 1 ); ?>>Redis / Valkey</option>
                                            <option value="2" <?php selected( get_option( 'uwb_redis_enabled', 0 ), 2 ); ?>>Memcached</option>
                                        </select>
                                        <p class="description" style="margin-bottom:0;">When enabled, database query results will be stored persistently in the selected cache backend. Our custom drop-in file will be automatically copied to <code>wp-content/object-cache.php</code>.</p>
                                    </div>

                                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                                        <div id="uwb-oc-conn-type-group" class="uwb-form-group">
                                            <label for="uwb_redis_conn_type">Connection Type</label>
                                            <select name="uwb_redis_conn_type" id="uwb_redis_conn_type" style="width:100%; border:1px solid var(--uwb-border); border-radius:8px; padding:12px;">
                                                <option value="tcp" <?php selected( get_option( 'uwb_redis_conn_type', 'tcp' ), 'tcp' ); ?>>TCP/IP (Host/Port)</option>
                                                <option value="socket" <?php selected( get_option( 'uwb_redis_conn_type', 'tcp' ), 'socket' ); ?>>Unix Socket</option>
                                            </select>
                                        </div>

                                        <div id="uwb-oc-db-group" class="uwb-form-group">
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
                                    <div id="uwb-oc-password-group" class="uwb-form-group" style="margin-bottom:20px;">
                                        <label for="uwb_redis_password">Redis Password (Optional)</label>
                                        <input type="password" name="uwb_redis_password" id="uwb_redis_password" placeholder="Leave blank if no password" value="<?php echo esc_attr( get_option( 'uwb_redis_password', '' ) ); ?>" style="width:100%; border:1px solid var(--uwb-border); border-radius:8px; padding:12px; font-size:14px;" autocomplete="new-password" />
                                    </div>

                                    <!-- Key Prefix / Salt Setting -->
                                    <div id="uwb-oc-prefix-group" class="uwb-form-group">
                                        <label for="uwb_redis_prefix">Redis Key Prefix / Salt</label>
                                        <input type="text" name="uwb_redis_prefix" id="uwb_redis_prefix" placeholder="uwb_oc:" value="<?php echo esc_attr( get_option( 'uwb_redis_prefix', 'uwb_oc:' ) ); ?>" />
                                        <p class="description">Prefix to avoid conflicts with other sites sharing the same Redis database. Default is <code>uwb_oc:</code>.</p>
                                    </div>

                                    <!-- Redis Connection Timeouts and retry interval -->
                                    <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px; margin-bottom:20px;">
                                        <div class="uwb-form-group">
                                            <label for="uwb_redis_timeout">Timeout (seconds)</label>
                                            <input type="number" step="0.1" min="0.1" name="uwb_redis_timeout" id="uwb_redis_timeout" value="<?php echo esc_attr( get_option( 'uwb_redis_timeout', 1.0 ) ); ?>" />
                                        </div>
                                        <div class="uwb-form-group">
                                            <label for="uwb_redis_read_timeout">Read Timeout (seconds)</label>
                                            <input type="number" step="0.1" min="0.1" name="uwb_redis_read_timeout" id="uwb_redis_read_timeout" value="<?php echo esc_attr( get_option( 'uwb_redis_read_timeout', 1.0 ) ); ?>" />
                                        </div>
                                        <div class="uwb-form-group">
                                            <label for="uwb_redis_retry_interval">Retry Interval (ms)</label>
                                            <input type="number" name="uwb_redis_retry_interval" id="uwb_redis_retry_interval" placeholder="e.g. 100" value="<?php echo esc_attr( get_option( 'uwb_redis_retry_interval', '' ) ); ?>" />
                                        </div>
                                    </div>

                                    <div class="uwb-form-group" style="margin-top:20px; padding-top:20px; border-top:1px solid var(--uwb-border);">
                                        <button type="button" id="btn-test-redis-settings" class="button button-secondary" style="font-weight:600; padding:10px 20px; height:auto; border-radius:8px; display:inline-flex; align-items:center; gap:8px;">
                                            Test Connection Settings
                                        </button>
                                        <div id="redis-test-result-settings" style="display:none; margin-top:12px; padding:12px; border-radius:8px; font-size:13px; font-weight:600;"></div>
                                    </div>
                                    <?php $this->render_module_banner_end(); ?>
                                </div>
                            </div>
