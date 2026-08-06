<?php
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
// Views/tab_cache_nav.php — Cache module banner + subtab nav
?>
                            <?php $this->render_module_banner( 'uwb_module_cache_enabled', 'Cache Module', 'Configure cache lifespan, bypass conditions, and exclusions for static files.' ); ?>

                            <!-- Horizontal Sub-tabs Nav -->
                            <div class="uwb-sub-tabs-nav">
                                <div class="uwb-sub-tab-item active" data-subtab="browser_cache">Browser Cache</div>
                                <div class="uwb-sub-tab-item" data-subtab="page_cache">Cache Page</div>
                                <div class="uwb-sub-tab-item" data-subtab="cdn_cache">CDN Cache</div>
                                <div class="uwb-sub-tab-item" data-subtab="webserver_cache">Webserver Cache</div>
                                <div class="uwb-sub-tab-item" data-subtab="object_cache">Object Cache</div>
                                <div class="uwb-sub-tab-item" data-subtab="opcache">OPCache</div>
                            </div>
