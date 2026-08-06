<?php
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
// Views/subtab_browser_cache.php
?>
                            <div id="subtab-browser_cache" class="uwb-subtab-content active">
                                <div style="background:#f8fafc; border:1px solid var(--uwb-border); border-radius:12px; padding:24px; margin-bottom:24px;">
                                    <?php $this->render_submodule_header( 'uwb_browser_cache_enabled', 'Browser Cache Settings', '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>' ); ?>
                                        <h4 style="margin-top: 24px; margin-bottom: 16px; font-size: 14px; font-weight: 700; color: var(--uwb-text);">Configure Lifespan by File Type</h4>
                                        <p class="description" style="margin-bottom: 20px;">Lifespan values are configured in minutes. Defaults are 365 days (525600 minutes).</p>
                                        
                                        <!-- Grid of Caching categories -->
                                        <div style="display: flex; flex-direction: column; gap: 16px;">
                                            <?php
                                            $categories = array(
                                                'html'  => 'HTML / XML Pages',
                                                'css'   => 'CSS Stylesheets',
                                                'js'    => 'JavaScript Files',
                                                'image' => 'Images (JPG, PNG, GIF, WebP, SVG, ICO)',
                                                'font'  => 'Fonts (TTF, OTF, WOFF, WOFF2, EOT)',
                                                'other' => 'Other Static Assets (PDF, Audio, Video, Zip)',
                                            );

                                            foreach ( $categories as $key => $label ) :
                                                $opt_enabled  = intval( get_option( "uwb_browser_cache_{$key}", 1 ) );
                                                $opt_lifespan = intval( get_option( "uwb_browser_cache_{$key}_lifespan", 525600 ) ); // 365 days
                                                ?>
                                                <div style="display: flex; align-items: center; justify-content: space-between; background: #fff; border: 1px solid var(--uwb-border); border-radius: 8px; padding: 16px; gap: 20px; flex-wrap: wrap;">
                                                    <div style="flex: 1; min-width: 200px;">
                                                        <strong style="font-size: 13.5px; color: var(--uwb-text); display: block;"><?php echo esc_html( $label ); ?></strong>
                                                    </div>
                                                    
                                                    <div style="display: flex; align-items: center; gap: 16px; flex-wrap: wrap;">
                                                        <!-- Enable/Disable Category Switch -->
                                                        <select name="uwb_browser_cache_<?php echo $key; ?>" class="uwb-bc-cat-toggle" style="width: 75px; border: 1px solid var(--uwb-border); border-radius: 6px; padding: 8px; font-size: 13px;">
                                                            <option value="0" <?php selected( $opt_enabled, 0 ); ?>>Bypass</option>
                                                            <option value="1" <?php selected( $opt_enabled, 1 ); ?>>Cache</option>
                                                        </select>
                                                        
                                                        <!-- Lifespan Input -->
                                                        <div class="uwb-bc-lifespan-wrap" style="display: flex; align-items: center; gap: 8px; <?php echo $opt_enabled ? '' : 'display:none;'; ?>">
                                                            <input type="number" min="1" name="uwb_browser_cache_<?php echo $key; ?>_lifespan" value="<?php echo esc_attr( $opt_lifespan ); ?>" style="width: 130px; padding: 8px; border: 1px solid var(--uwb-border); border-radius: 6px; font-size: 13px;" />
                                                            <span style="font-size: 12.5px; color: var(--uwb-text-muted);">Minutes</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php $this->render_module_banner_end(); ?>
                                </div>
                            </div>
