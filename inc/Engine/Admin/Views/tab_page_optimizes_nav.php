<?php
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
// Views/tab_page_optimizes_nav.php — Page Optimizes module banner + subtab nav
?>
                            <?php $this->render_module_banner( 'uwb_module_optimizer_enabled', 'Page Optimization', 'Optimize web source code by minifying and combining resources, lazy loading media, and tuning performance.' ); ?>

                            <!-- Horizontal Sub-tabs Nav -->
                            <div class="uwb-sub-tabs-nav">
                                <div class="uwb-sub-tab-item active" data-subtab="opt_general">WP Teak</div>
                                <div class="uwb-sub-tab-item" data-subtab="opt_html">HTML</div>
                                <div class="uwb-sub-tab-item" data-subtab="opt_css">CSS</div>
                                <div class="uwb-sub-tab-item" data-subtab="opt_js">JS</div>
                                <div class="uwb-sub-tab-item" data-subtab="opt_media">Media &amp; File</div>
                                <div class="uwb-sub-tab-item" data-subtab="opt_font">Font</div>
                                <div class="uwb-sub-tab-item" data-subtab="opt_cdn_media">S3 Storage</div>
                                <div class="uwb-sub-tab-item" data-subtab="opt_database">Database</div>
                            </div>
