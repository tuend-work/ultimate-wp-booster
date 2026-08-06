<?php
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
// Views/tab_preload_nav.php — Preload module banner + subtab nav
?>
                            <?php $this->render_module_banner( 'uwb_module_preload_enabled', 'Preload Cache (Automatic Crawler)', 'Automatically crawl URLs in your sitemap to pre-generate static cache files before visitors arrive.' ); ?>

                            <!-- Horizontal Sub-tabs Nav -->
                            <div class="uwb-sub-tabs-nav" style="margin-bottom: 24px;">
                                <div class="uwb-sub-tab-item active" data-subtab="preload_status">Status</div>
                                <div class="uwb-sub-tab-item" data-subtab="preload_settings_sub">Settings</div>
                                <div class="uwb-sub-tab-item" data-subtab="preload_sitemap_sub">Sitemap</div>
                                <div class="uwb-sub-tab-item" data-subtab="preload_simulation_sub">Simulation</div>
                            </div>
