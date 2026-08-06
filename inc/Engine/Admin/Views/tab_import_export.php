<?php
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
// Views/tab_import_export.php — Import/Export Settings
?>
                    <div id="tab-import_export" class="uwb-tab-content">
                        <h2 style="margin-top:0;">Import &amp; Export Settings</h2>
                        <p style="color:var(--uwb-text-muted); margin-bottom: 24px;">Export current settings to a JSON file or import settings from a previously saved JSON file.</p>
                        
                        <form method="post" action="" enctype="multipart/form-data">
                            <?php wp_nonce_field( 'uwb_import_export_action', 'uwb_import_export_nonce' ); ?>
                            
                            <div style="background:#f8fafc; border:1px solid var(--uwb-border); border-radius:12px; padding:24px; margin-bottom:24px;">
                                <h3 style="margin-top:0; margin-bottom:12px; font-size:15px; display:flex; align-items:center; gap:8px;">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                    Export Settings
                                </h3>
                                <p class="description" style="margin-bottom:16px;">Download all your current plugin settings as a <code>.json</code> file.</p>
                                <button type="submit" name="uwb_export_settings" class="button button-primary" style="background:var(--uwb-primary); border-color:var(--uwb-primary); padding:10px 20px; height:auto; border-radius:8px; font-weight:600; cursor:pointer;">Export Settings (JSON)</button>
                            </div>

                            <div style="background:#f8fafc; border:1px solid var(--uwb-border); border-radius:12px; padding:24px;">
                                <h3 style="margin-top:0; margin-bottom:12px; font-size:15px; display:flex; align-items:center; gap:8px;">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                    Import Settings
                                </h3>
                                <p class="description" style="margin-bottom:16px;">Choose a valid plugin settings <code>.json</code> file to import.</p>
                                
                                <input type="file" name="uwb_import_file" id="uwb_import_file" accept=".json" style="margin-bottom:16px; display:block;" />
                                <button type="submit" name="uwb_import_settings" class="button" style="padding:10px 20px; height:auto; border-radius:8px; font-weight:600; border:1px solid var(--uwb-border); background:#fff; cursor:pointer; color:var(--uwb-text);">Import Settings (JSON)</button>
                            </div>
                        </form>
                    </div>
