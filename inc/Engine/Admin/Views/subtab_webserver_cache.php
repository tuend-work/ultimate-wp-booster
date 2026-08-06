<?php
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
// Views/subtab_webserver_cache.php
?>
                            <div id="subtab-webserver_cache" class="uwb-subtab-content">
                                <div style="background:#f8fafc; border:1px solid var(--uwb-border); border-radius:12px; padding:24px; margin-bottom:24px;">
                                    <h3 style="margin-top:0; margin-bottom:20px; font-size:15px; display:flex; align-items:center; gap:8px;">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="8" rx="2" ry="2"/><rect x="2" y="14" width="20" height="8" rx="2" ry="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>
                                        Webserver Cache Status & Information
                                    </h3>
                                    <p style="font-size:13.5px; line-height:1.6; color:var(--uwb-text); margin-bottom:20px;">
                                        Webserver-level caching compiles HTML files dynamically and serves them with zero execution overhead. Static files preloaded by Ultimate WP Booster are fully compatible. Below is the status of supported webserver caching technologies detected on your host:
                                    </p>
                                    
                                    <div class="uwb-webserver-cards" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 24px;">
                                        <!-- CARD 1: NGINX -->
                                        <?php 
                                        $is_nginx = ($detected_server === 'nginx');
                                        $nginx_opacity = $is_nginx ? '1' : '0.45';
                                        $nginx_border = $is_nginx ? 'border: 2px solid #10b981;' : 'border: 1px solid var(--uwb-border);';
                                        $nginx_shadow = $is_nginx ? 'box-shadow: 0 4px 12px rgba(16,185,129,0.15);' : '';
                                        $nginx_bg = $is_nginx ? 'background: #ffffff;' : 'background: #f8fafc;';
                                        ?>
                                        <div class="uwb-webserver-card" style="opacity: <?php echo $nginx_opacity; ?>; <?php echo $nginx_border; ?> <?php echo $nginx_shadow; ?> <?php echo $nginx_bg; ?> border-radius: 12px; padding: 20px; transition: all 0.3s ease;">
                                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                                                <h4 style="margin: 0; font-size: 15px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 8px;">
                                                    <span style="display:inline-block; width: 10px; height: 10px; border-radius: 50%; background: <?php echo $is_nginx ? '#10b981;' : '#94a3b8;'; ?>"></span>
                                                    NGINX FastCGI Cache
                                                </h4>
                                                <span style="font-size: 11px; font-weight: 700; padding: 4px 8px; border-radius: 6px; <?php echo $is_nginx ? 'background: #d1fae5; color: #065f46;' : 'background: #e2e8f0; color: #64748b;'; ?>">
                                                    <?php echo $is_nginx ? 'ACTIVE' : 'INACTIVE'; ?>
                                                </span>
                                            </div>
                                            <p style="font-size: 13px; line-height: 1.5; color: #475569; margin: 0 0 16px 0;">
                                                Uses Nginx daemon level static file delivery or FastCGI microcaching. Extremely high performance.
                                            </p>
                                            <?php if ( $is_nginx ) : ?>
                                                <div style="background: #f8fafc; border-radius: 8px; padding: 12px; font-size: 12px; border: 1px solid #e2e8f0;">
                                                    <div style="margin-bottom: 6px; display: flex; justify-content: space-between;">
                                                        <strong style="color: #64748b;">Software:</strong>
                                                        <span style="color: #334155; font-family: monospace;"><?php echo esc_html( $server_software ); ?></span>
                                                    </div>
                                                    <div style="margin-bottom: 6px; display: flex; justify-content: space-between;">
                                                        <strong style="color: #64748b;">Cache Engine:</strong>
                                                        <span style="color: #334155;">Rocket-Nginx Rules Compatible</span>
                                                    </div>
                                                    <div style="display: flex; justify-content: space-between;">
                                                        <strong style="color: #64748b;">Static Directory:</strong>
                                                        <span style="color: #334155; font-family: monospace;">wp-content/cache</span>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- CARD 2: APACHE -->
                                        <?php 
                                        $is_apache = ($detected_server === 'apache');
                                        $apache_opacity = $is_apache ? '1' : '0.45';
                                        $apache_border = $is_apache ? 'border: 2px solid #10b981;' : 'border: 1px solid var(--uwb-border);';
                                        $apache_shadow = $is_apache ? 'box-shadow: 0 4px 12px rgba(16,185,129,0.15);' : '';
                                        $apache_bg = $is_apache ? 'background: #ffffff;' : 'background: #f8fafc;';
                                        
                                        $htaccess_file = ABSPATH . '.htaccess';
                                        $htaccess_writable = file_exists( $htaccess_file ) && is_writable( $htaccess_file );
                                        $rules_active = false;
                                        if ( file_exists( $htaccess_file ) ) {
                                            $htaccess_content = @file_get_contents( $htaccess_file );
                                            if ( $htaccess_content && strpos( $htaccess_content, 'Ultimate WP Booster Browser Cache' ) !== false ) {
                                                $rules_active = true;
                                            }
                                        }
                                        ?>
                                        <div class="uwb-webserver-card" style="opacity: <?php echo $apache_opacity; ?>; <?php echo $apache_border; ?> <?php echo $apache_shadow; ?> <?php echo $apache_bg; ?> border-radius: 12px; padding: 20px; transition: all 0.3s ease;">
                                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                                                <h4 style="margin: 0; font-size: 15px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 8px;">
                                                    <span style="display:inline-block; width: 10px; height: 10px; border-radius: 50%; background: <?php echo $is_apache ? '#10b981;' : '#94a3b8;'; ?>"></span>
                                                    Apache mod_expires & Cache
                                                </h4>
                                                <span style="font-size: 11px; font-weight: 700; padding: 4px 8px; border-radius: 6px; <?php echo $is_apache ? 'background: #d1fae5; color: #065f46;' : 'background: #e2e8f0; color: #64748b;'; ?>">
                                                    <?php echo $is_apache ? 'ACTIVE' : 'INACTIVE'; ?>
                                                </span>
                                            </div>
                                            <p style="font-size: 13px; line-height: 1.5; color: #475569; margin: 0 0 16px 0;">
                                                Uses .htaccess rewriting rules and Apache caching modules to expire and deliver static assets directly.
                                            </p>
                                            <?php if ( $is_apache ) : ?>
                                                <div style="background: #f8fafc; border-radius: 8px; padding: 12px; font-size: 12px; border: 1px solid #e2e8f0;">
                                                    <div style="margin-bottom: 6px; display: flex; justify-content: space-between;">
                                                        <strong style="color: #64748b;">Software:</strong>
                                                        <span style="color: #334155; font-family: monospace;"><?php echo esc_html( $server_software ); ?></span>
                                                    </div>
                                                    <div style="margin-bottom: 6px; display: flex; justify-content: space-between;">
                                                        <strong style="color: #64748b;">.htaccess Status:</strong>
                                                        <span style="color: <?php echo $htaccess_writable ? '#10b981;' : '#ef4444;'; ?>"><?php echo $htaccess_writable ? 'Writable' : 'Not Writable'; ?></span>
                                                    </div>
                                                    <div style="display: flex; justify-content: space-between;">
                                                        <strong style="color: #64748b;">Expiration Rules:</strong>
                                                        <span style="color: <?php echo $rules_active ? '#10b981;' : '#f59e0b;'; ?>"><?php echo $rules_active ? 'Active (.htaccess)' : 'Inactive'; ?></span>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- CARD 3: LITESPEED -->
                                        <?php 
                                        $is_litespeed = ($detected_server === 'litespeed');
                                        $litespeed_opacity = $is_litespeed ? '1' : '0.45';
                                        $litespeed_border = $is_litespeed ? 'border: 2px solid #10b981;' : 'border: 1px solid var(--uwb-border);';
                                        $litespeed_shadow = $is_litespeed ? 'box-shadow: 0 4px 12px rgba(16,185,129,0.15);' : '';
                                        $litespeed_bg = $is_litespeed ? 'background: #ffffff;' : 'background: #f8fafc;';
                                        ?>
                                        <div class="uwb-webserver-card" style="opacity: <?php echo $litespeed_opacity; ?>; <?php echo $litespeed_border; ?> <?php echo $litespeed_shadow; ?> <?php echo $litespeed_bg; ?> border-radius: 12px; padding: 20px; transition: all 0.3s ease;">
                                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                                                <h4 style="margin: 0; font-size: 15px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 8px;">
                                                    <span style="display:inline-block; width: 10px; height: 10px; border-radius: 50%; background: <?php echo $is_litespeed ? '#10b981;' : '#94a3b8;'; ?>"></span>
                                                    LiteSpeed LSCache
                                                </h4>
                                                <span style="font-size: 11px; font-weight: 700; padding: 4px 8px; border-radius: 6px; <?php echo $is_litespeed ? 'background: #d1fae5; color: #065f46;' : 'background: #e2e8f0; color: #64748b;'; ?>">
                                                    <?php echo $is_litespeed ? 'ACTIVE' : 'INACTIVE'; ?>
                                                </span>
                                            </div>
                                            <p style="font-size: 13px; line-height: 1.5; color: #475569; margin: 0 0 16px 0;">
                                                Leverages LiteSpeed Server built-in high performance page cache module. Configured via .htaccess headers.
                                            </p>
                                            <?php if ( $is_litespeed ) : ?>
                                                <div style="background: #f8fafc; border-radius: 8px; padding: 12px; font-size: 12px; border: 1px solid #e2e8f0;">
                                                    <div style="margin-bottom: 6px; display: flex; justify-content: space-between;">
                                                        <strong style="color: #64748b;">Software:</strong>
                                                        <span style="color: #334155; font-family: monospace;"><?php echo esc_html( ! empty( $server_software ) ? $server_software : 'LiteSpeed Server' ); ?></span>
                                                    </div>
                                                    <div style="margin-bottom: 6px; display: flex; justify-content: space-between;">
                                                        <strong style="color: #64748b;">LSCache Module:</strong>
                                                        <span style="color: #10b981; font-weight:700;">Active (RAM Engine &amp; Tagging)</span>
                                                    </div>
                                                    <div style="margin-bottom: 6px; display: flex; justify-content: space-between;">
                                                        <strong style="color: #64748b;">Header Caching:</strong>
                                                        <span style="color: #3b82f6; font-weight:600;">Dynamic (No Server Restart Needed)</span>
                                                    </div>
                                                    <div style="display: flex; justify-content: space-between;">
                                                        <strong style="color: #64748b;">Htaccess Status:</strong>
                                                        <span style="color: <?php echo $htaccess_writable ? '#10b981;' : '#ef4444;'; ?>"><?php echo $htaccess_writable ? 'Writable (CacheLookup On)' : 'Not Writable'; ?></span>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div style="padding:12px 16px; background:#e0e7ff; color:var(--uwb-primary-dark); border-radius:8px; font-size:13px; font-weight:600; display:inline-block;">
                                        Detected Server: <?php echo esc_html($webserver_details); ?>
                                    </div>
                                </div>
                            </div>

                            <!-- SUB-TAB 4: Object Cache -->
