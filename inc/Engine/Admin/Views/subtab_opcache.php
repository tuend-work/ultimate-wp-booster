<?php
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
// Views/subtab_opcache.php
?>
                            <div id="subtab-opcache" class="uwb-subtab-content">
                                <div style="background:#f8fafc; border:1px solid var(--uwb-border); border-radius:12px; padding:24px; margin-bottom:24px;">
                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:16px;">
                                        <h3 style="margin:0; font-size:15px; display:flex; align-items:center; gap:8px;">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
                                            OPcache Status & Settings
                                        </h3>
                                        <a href="<?php echo wp_nonce_url( admin_url( 'admin-post.php?action=uwb_flush_opcache' ), 'uwb_flush_opcache_action' ); ?>" class="button button-secondary" style="border:1px solid var(--uwb-btn-danger-border); color:var(--uwb-btn-danger-text); background:#fff; font-weight:600; padding:6px 12px; font-size:12.5px; border-radius:6px; cursor:pointer;">Flush OPcache</a>
                                    </div>
                                    
                                    <?php
                                    $opcache_status = function_exists( 'opcache_get_status' ) ? @opcache_get_status( true ) : false;
                                    $opcache_config = function_exists( 'opcache_get_configuration' ) ? @opcache_get_configuration() : false;
                                    $opcache_enabled = ! empty( $opcache_status['opcache_enabled'] );

                                    if ( ! function_exists( 'uwb_format_bytes' ) ) {
                                        function uwb_format_bytes( $bytes, $precision = 1 ) {
                                            $units = array( 'B', 'KB', 'MB', 'GB' );
                                            $bytes = max( $bytes, 0 );
                                            $pow = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
                                            $pow = min( $pow, count( $units ) - 1 );
                                            $bytes /= pow( 1024, $pow );
                                            return round( $bytes, $precision ) . ' ' . $units[$pow];
                                        }
                                    }

                                    if ( $opcache_enabled ) :
                                        // Memory
                                        $mem_used = isset( $opcache_status['memory_usage']['used_memory'] ) ? $opcache_status['memory_usage']['used_memory'] : 0;
                                        $mem_free = isset( $opcache_status['memory_usage']['free_memory'] ) ? $opcache_status['memory_usage']['free_memory'] : 0;
                                        $mem_wasted = isset( $opcache_status['memory_usage']['wasted_memory'] ) ? $opcache_status['memory_usage']['wasted_memory'] : 0;
                                        $mem_wasted_pct = isset( $opcache_status['memory_usage']['current_wasted_percentage'] ) ? round( $opcache_status['memory_usage']['current_wasted_percentage'], 1 ) : 0;
                                        $mem_total = $mem_used + $mem_free + $mem_wasted;
                                        $mem_used_pct = $mem_total > 0 ? round( ( $mem_used / $mem_total ) * 100 ) : 0;
                                        
                                        // Hit rate
                                        $hits = isset( $opcache_status['opcache_statistics']['hits'] ) ? $opcache_status['opcache_statistics']['hits'] : 0;
                                        $misses = isset( $opcache_status['opcache_statistics']['misses'] ) ? $opcache_status['opcache_statistics']['misses'] : 0;
                                        $hit_rate = isset( $opcache_status['opcache_statistics']['opcache_hit_rate'] ) ? round( $opcache_status['opcache_statistics']['opcache_hit_rate'], 1 ) : ( $hits + $misses > 0 ? round( ( $hits / ($hits + $misses) ) * 100, 1 ) : 0 );
                                        
                                        // Keys
                                        $keys_used = isset( $opcache_status['opcache_statistics']['num_cached_keys'] ) ? $opcache_status['opcache_statistics']['num_cached_keys'] : 0;
                                        $keys_max = isset( $opcache_status['opcache_statistics']['max_cached_keys'] ) ? $opcache_status['opcache_statistics']['max_cached_keys'] : 0;
                                        $keys_pct = $keys_max > 0 ? round( ( $keys_used / $keys_max ) * 100 ) : 0;
                                        
                                        // Interned strings
                                        $is_size = isset( $opcache_status['interned_strings_usage']['buffer_size'] ) ? $opcache_status['interned_strings_usage']['buffer_size'] : 0;
                                        $is_used = isset( $opcache_status['interned_strings_usage']['used_memory'] ) ? $opcache_status['interned_strings_usage']['used_memory'] : 0;
                                        $is_free = isset( $opcache_status['interned_strings_usage']['free_memory'] ) ? $opcache_status['interned_strings_usage']['free_memory'] : 0;
                                        $is_strings = isset( $opcache_status['interned_strings_usage']['number_of_strings'] ) ? $opcache_status['interned_strings_usage']['number_of_strings'] : 0;
                                        
                                        // Statistics
                                        $num_scripts = isset( $opcache_status['opcache_statistics']['num_cached_scripts'] ) ? $opcache_status['opcache_statistics']['num_cached_scripts'] : 0;
                                        $blacklist_misses = isset( $opcache_status['opcache_statistics']['blacklist_misses'] ) ? $opcache_status['opcache_statistics']['blacklist_misses'] : 0;
                                        $start_time = isset( $opcache_status['opcache_statistics']['start_time'] ) ? date( 'Y/m/d h:i:s A', $opcache_status['opcache_statistics']['start_time'] ) : 'Unknown';
                                        $last_restart = isset( $opcache_status['opcache_statistics']['last_restart_time'] ) && $opcache_status['opcache_statistics']['last_restart_time'] > 0 ? date( 'Y/m/d h:i:s A', $opcache_status['opcache_statistics']['last_restart_time'] ) : 'Never';
                                        
                                        // General info
                                        $opcache_version = isset( $opcache_config['version']['version'] ) ? $opcache_config['version']['version'] : 'PHP OPcache';
                                        $php_version = PHP_VERSION;
                                        $host = isset( $_SERVER['SERVER_NAME'] ) ? $_SERVER['SERVER_NAME'] : ( isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : 'localhost' );
                                        $server_software = isset( $_SERVER['SERVER_SOFTWARE'] ) ? $_SERVER['SERVER_SOFTWARE'] : 'Unknown';
                                        ?>
                                        
                                        <!-- Gauges row: 3 Gauges -->
                                        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:20px; margin-bottom:24px;">
                                            <!-- Gauge 1: Memory -->
                                            <div style="background:#fff; border:1px solid var(--uwb-border); border-radius:10px; padding:20px; text-align:center; display:flex; flex-direction:column; align-items:center; justify-content:center;">
                                                <strong style="font-size:14px; color:var(--uwb-text); margin-bottom:12px; display:block;">Memory</strong>
                                                <div style="position:relative; width:120px; height:120px; display:flex; align-items:center; justify-content:center;">
                                                    <svg width="100%" height="100%" viewBox="0 0 36 36">
                                                        <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="#e2e8f0" stroke-width="3" />
                                                        <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="var(--uwb-primary)" stroke-dasharray="<?php echo $mem_used_pct; ?>, 100" stroke-width="3" stroke-linecap="round" />
                                                    </svg>
                                                    <span style="position:absolute; font-size:20px; font-weight:700; color:var(--uwb-text);"><?php echo $mem_used_pct; ?>%</span>
                                                </div>
                                            </div>

                                            <!-- Gauge 2: Hit Rate -->
                                            <div style="background:#fff; border:1px solid var(--uwb-border); border-radius:10px; padding:20px; text-align:center; display:flex; flex-direction:column; align-items:center; justify-content:center;">
                                                <strong style="font-size:14px; color:var(--uwb-text); margin-bottom:12px; display:block;">Hit rate</strong>
                                                <div style="position:relative; width:120px; height:120px; display:flex; align-items:center; justify-content:center;">
                                                    <svg width="100%" height="100%" viewBox="0 0 36 36">
                                                        <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="#e2e8f0" stroke-width="3" />
                                                        <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="var(--uwb-primary)" stroke-dasharray="<?php echo round($hit_rate); ?>, 100" stroke-width="3" stroke-linecap="round" />
                                                    </svg>
                                                    <span style="position:absolute; font-size:20px; font-weight:700; color:var(--uwb-text);"><?php echo $hit_rate; ?>%</span>
                                                </div>
                                            </div>

                                            <!-- Gauge 3: Keys -->
                                            <div style="background:#fff; border:1px solid var(--uwb-border); border-radius:10px; padding:20px; text-align:center; display:flex; flex-direction:column; align-items:center; justify-content:center;">
                                                <strong style="font-size:14px; color:var(--uwb-text); margin-bottom:12px; display:block;">Keys</strong>
                                                <div style="position:relative; width:120px; height:120px; display:flex; align-items:center; justify-content:center;">
                                                    <svg width="100%" height="100%" viewBox="0 0 36 36">
                                                        <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="#e2e8f0" stroke-width="3" />
                                                        <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="var(--uwb-primary)" stroke-dasharray="<?php echo $keys_pct; ?>, 100" stroke-width="3" stroke-linecap="round" />
                                                    </svg>
                                                    <span style="position:absolute; font-size:20px; font-weight:700; color:var(--uwb-text);"><?php echo $keys_pct; ?>%</span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Stats details row: 3 Cards -->
                                        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:20px; margin-bottom:24px;">
                                            <!-- Card 1: Memory usage -->
                                            <div style="background:#fff; border:1px solid var(--uwb-border); border-radius:10px; padding:20px;">
                                                <strong style="font-size:14px; color:var(--uwb-text); margin-bottom:12px; display:block; border-bottom:1px solid var(--uwb-border); padding-bottom:8px;">Memory usage</strong>
                                                <table style="width:100%; font-size:12.5px; border-collapse:collapse;">
                                                    <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:6px 0; color:var(--uwb-text-muted);">total memory:</td><td style="padding:6px 0; text-align:right; font-weight:600; color:var(--uwb-text);"><?php echo uwb_format_bytes($mem_total); ?></td></tr>
                                                    <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:6px 0; color:var(--uwb-text-muted);">used memory:</td><td style="padding:6px 0; text-align:right; font-weight:600; color:var(--uwb-text);"><?php echo uwb_format_bytes($mem_used); ?></td></tr>
                                                    <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:6px 0; color:var(--uwb-text-muted);">free memory:</td><td style="padding:6px 0; text-align:right; font-weight:600; color:var(--uwb-text);"><?php echo uwb_format_bytes($mem_free); ?></td></tr>
                                                    <tr><td style="padding:6px 0; color:var(--uwb-text-muted);">wasted memory:</td><td style="padding:6px 0; text-align:right; font-weight:600; color:var(--uwb-text);"><?php echo uwb_format_bytes($mem_wasted); ?> (<?php echo $mem_wasted_pct; ?>%)</td></tr>
                                                </table>
                                            </div>

                                            <!-- Card 2: OPcache statistics -->
                                            <div style="background:#fff; border:1px solid var(--uwb-border); border-radius:10px; padding:20px;">
                                                <strong style="font-size:14px; color:var(--uwb-text); margin-bottom:12px; display:block; border-bottom:1px solid var(--uwb-border); padding-bottom:8px;">OPcache statistics</strong>
                                                <table style="width:100%; font-size:12.5px; border-collapse:collapse;">
                                                    <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:6px 0; color:var(--uwb-text-muted);">number of cached files:</td><td style="padding:6px 0; text-align:right; font-weight:600; color:var(--uwb-text);"><?php echo number_format($num_scripts); ?></td></tr>
                                                    <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:6px 0; color:var(--uwb-text-muted);">number of hits:</td><td style="padding:6px 0; text-align:right; font-weight:600; color:var(--uwb-text);"><?php echo number_format($hits); ?></td></tr>
                                                    <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:6px 0; color:var(--uwb-text-muted);">number of misses:</td><td style="padding:6px 0; text-align:right; font-weight:600; color:var(--uwb-text);"><?php echo number_format($misses); ?></td></tr>
                                                    <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:6px 0; color:var(--uwb-text-muted);">blacklist misses:</td><td style="padding:6px 0; text-align:right; font-weight:600; color:var(--uwb-text);"><?php echo number_format($blacklist_misses); ?></td></tr>
                                                    <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:6px 0; color:var(--uwb-text-muted);">number of cached keys:</td><td style="padding:6px 0; text-align:right; font-weight:600; color:var(--uwb-text);"><?php echo number_format($keys_used); ?></td></tr>
                                                    <tr><td style="padding:6px 0; color:var(--uwb-text-muted);">max cached keys:</td><td style="padding:6px 0; text-align:right; font-weight:600; color:var(--uwb-text);"><?php echo number_format($keys_max); ?></td></tr>
                                                </table>
                                            </div>

                                            <!-- Card 3: Interned strings usage -->
                                            <div style="background:#fff; border:1px solid var(--uwb-border); border-radius:10px; padding:20px;">
                                                <strong style="font-size:14px; color:var(--uwb-text); margin-bottom:12px; display:block; border-bottom:1px solid var(--uwb-border); padding-bottom:8px;">Interned strings usage</strong>
                                                <table style="width:100%; font-size:12.5px; border-collapse:collapse;">
                                                    <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:6px 0; color:var(--uwb-text-muted);">buffer size:</td><td style="padding:6px 0; text-align:right; font-weight:600; color:var(--uwb-text);"><?php echo uwb_format_bytes($is_size); ?></td></tr>
                                                    <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:6px 0; color:var(--uwb-text-muted);">used memory:</td><td style="padding:6px 0; text-align:right; font-weight:600; color:var(--uwb-text);"><?php echo uwb_format_bytes($is_used); ?></td></tr>
                                                    <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:6px 0; color:var(--uwb-text-muted);">free memory:</td><td style="padding:6px 0; text-align:right; font-weight:600; color:var(--uwb-text);"><?php echo uwb_format_bytes($is_free); ?></td></tr>
                                                    <tr><td style="padding:6px 0; color:var(--uwb-text-muted);">number of strings:</td><td style="padding:6px 0; text-align:right; font-weight:600; color:var(--uwb-text);"><?php echo number_format($is_strings); ?></td></tr>
                                                </table>
                                            </div>
                                        </div>

                                        <!-- General Info & Available functions row: 2 Cards -->
                                        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(300px, 1fr)); gap:20px;">
                                            <!-- Card 1: General info -->
                                            <div style="background:#fff; border:1px solid var(--uwb-border); border-radius:10px; padding:20px;">
                                                <strong style="font-size:14px; color:var(--uwb-text); margin-bottom:12px; display:block; border-bottom:1px solid var(--uwb-border); padding-bottom:8px;">General info</strong>
                                                <table style="width:100%; font-size:12.5px; border-collapse:collapse;">
                                                    <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:6px 0; color:var(--uwb-text-muted);">OPcache version:</td><td style="padding:6px 0; text-align:right; font-weight:600; color:var(--uwb-text);"><?php echo esc_html($opcache_version); ?></td></tr>
                                                    <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:6px 0; color:var(--uwb-text-muted);">PHP version:</td><td style="padding:6px 0; text-align:right; font-weight:600; color:var(--uwb-text);"><?php echo esc_html($php_version); ?></td></tr>
                                                    <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:6px 0; color:var(--uwb-text-muted);">host:</td><td style="padding:6px 0; text-align:right; font-weight:600; color:var(--uwb-text);"><?php echo esc_html($host); ?></td></tr>
                                                    <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:6px 0; color:var(--uwb-text-muted);">server software:</td><td style="padding:6px 0; text-align:right; font-weight:600; color:var(--uwb-text);"><?php echo esc_html($server_software); ?></td></tr>
                                                    <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:6px 0; color:var(--uwb-text-muted);">start time:</td><td style="padding:6px 0; text-align:right; font-weight:600; color:var(--uwb-text);"><?php echo esc_html($start_time); ?></td></tr>
                                                    <tr><td style="padding:6px 0; color:var(--uwb-text-muted);">last reset:</td><td style="padding:6px 0; text-align:right; font-weight:600; color:var(--uwb-text);"><?php echo esc_html($last_restart); ?></td></tr>
                                                </table>
                                            </div>

                                            <!-- Card 2: Available functions -->
                                            <div style="background:#fff; border:1px solid var(--uwb-border); border-radius:10px; padding:20px;">
                                                <strong style="font-size:14px; color:var(--uwb-text); margin-bottom:12px; display:block; border-bottom:1px solid var(--uwb-border); padding-bottom:8px;">Available functions</strong>
                                                <div style="display:flex; flex-direction:column; gap:6px; font-size:12.5px;">
                                                    <?php
                                                    $funcs = array(
                                                        'opcache_reset',
                                                        'opcache_get_status',
                                                        'opcache_compile_file',
                                                        'opcache_invalidate',
                                                        'opcache_get_configuration',
                                                        'opcache_is_script_cached',
                                                    );
                                                    foreach ( $funcs as $f ) :
                                                        $avail = function_exists( $f );
                                                        ?>
                                                        <div style="display:flex; justify-content:space-between; align-items:center; padding:4px 0; border-bottom:1px solid #f1f5f9;">
                                                            <code style="color:var(--uwb-primary); background:none; padding:0;"><?php echo $f; ?></code>
                                                            <span style="font-weight:600; color:<?php echo $avail ? '#10b981' : '#ef4444'; ?>;"><?php echo $avail ? 'available' : 'unavailable'; ?></span>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php else : ?>
                                        <div style="padding:16px; background:#fee2e2; border:1px solid #fca5a5; color:#991b1b; border-radius:8px; font-size:13.5px; font-weight:600;">
                                            OPcache is not active or enabled on this server. Check your PHP configuration (<code>opcache.enable</code> directive).
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
