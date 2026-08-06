<?php
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
// Views/subtab_advanced_debug.php — Debug Mode
?>
                            <div id="subtab-advanced_debug" class="uwb-subtab-content active">
                                <!-- Debug Mode -->
                                <div style="background:#fff8e1; border:1px solid #fcd34d; border-radius:12px; padding:24px; margin-bottom:24px;">
                                    <h3 style="margin-top:0; margin-bottom:20px; font-size:15px; display:flex; align-items:center; gap:8px; color:#92400e;">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                        Developer: Debug Mode
                                    </h3>
                                    <p class="description" style="margin-bottom:16px; color:#92400e;"><strong>Warning:</strong> When enabled, the optimizer appends a debug log as an HTML comment to every cached page. <strong>Disable on production.</strong></p>
                                    
                                    <?php $this->render_toggle_switch( 'uwb_debug_mode', 'Enable Optimizer Debug Log', 'Appends debug info as HTML comments. Use only for troubleshooting.' ); ?>
                                </div>
                            </div>
