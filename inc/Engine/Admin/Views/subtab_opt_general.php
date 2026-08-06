<?php
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
// Views/subtab_opt_general.php — WP Tweaks
?>
                            <div id="subtab-opt_general" class="uwb-subtab-content active">
                                <div style="background:#f8fafc; border:1px solid var(--uwb-border); border-radius:12px; padding:24px; margin-bottom:24px;">
                                    <?php $this->render_submodule_header( 'uwb_module_general_enabled', 'General WP Tweaks Settings', '⚙️' ); ?>
                                    <?php
                                    $this->render_toggle_switch( 'uwb_preload_links', 'Preload Links (Hover Instant Page)', 'Link preloading improves perceived load time by downloading HTML when a user hovers over a link on the frontend. Powered by <a href="https://instant.page" target="_blank" rel="noopener noreferrer">instant.page</a>.' );
                                    $this->render_toggle_switch( 'uwb_optimize_logged_in', 'Optimize for Logged-in Users', 'Run page optimization (minify HTML/CSS/JS, lazy load, etc.) for logged-in users. By default, optimization is skipped for admin/logged-in sessions to avoid conflicts.' );
                                    ?>
                                    <?php
                                    $this->render_select_setting( 'uwb_general_autosave_interval', 'Autosave Interval', array(
                                        'default' => 'Default',
                                        '60'      => '1 Minute',
                                        '120'     => '2 Minutes',
                                        '300'     => '5 Minutes',
                                        '600'     => '10 Minutes',
                                    ), 'Select the interval for WordPress autosave feature.' );

                                    $this->render_select_setting( 'uwb_general_limit_post_revisions', 'Limit Post Revisions', array(
                                        'default' => 'Default',
                                        'disable' => 'Disable Revisions',
                                        '1'       => '1',
                                        '2'       => '2',
                                        '3'       => '3',
                                        '4'       => '4',
                                        '5'       => '5',
                                        '10'      => '10',
                                    ), 'Limit the number of post revisions stored in the database.' );

                                    $this->render_select_setting( 'uwb_general_heartbeat_frequency', 'Heartbeat Frequency', array(
                                        'default' => 'Default',
                                        '15'      => '15 Seconds',
                                        '30'      => '30 Seconds',
                                        '60'      => '60 Seconds',
                                        '120'     => '120 Seconds',
                                    ), 'Set the heartbeat API communication frequency.' );

                                    $this->render_select_setting( 'uwb_general_disable_heartbeat', 'Disable Heartbeat', array(
                                        'default'     => 'Default',
                                        'disable_all' => 'Disable Everywhere',
                                        'only_edit'   => 'Only Allow When Editing Posts/Pages',
                                    ), 'Control or disable the WordPress Heartbeat API.' );

                                    $this->render_toggle_switch( 'uwb_general_disable_comments', 'Disable Comments', 'Close comments, pings, and hide comments on the front-end completely.' );
                                    $this->render_toggle_switch( 'uwb_general_disable_password_strength_meter', 'Disable Password Strength Meter', 'Disable password strength meter scripts in WooCommerce or checkout pages.' );
                                    $this->render_toggle_switch( 'uwb_general_disable_google_maps', 'Disable Google Maps', 'De-enqueue Google Maps API scripts and styles from the front-end.' );

                                    $this->render_select_setting( 'uwb_general_disable_rest_api', 'Disable REST API', array(
                                        'default'           => 'Default',
                                        'disable_non_admin' => 'Disable for Non-Admins',
                                        'disable_all'       => 'Disable Completely',
                                    ), 'Block REST API requests to secure your site\'s endpoints.' );

                                    $this->render_toggle_switch( 'uwb_general_disable_self_pingbacks', 'Disable Self Pingbacks', 'Stop WordPress from pingbacking your own posts when you link to them.' );
                                    $this->render_toggle_switch( 'uwb_general_disable_rss_feeds', 'Disable RSS Feeds', 'Disable RSS feed generation and redirect feed URLs to display error page.' );
                                    $this->render_toggle_switch( 'uwb_general_remove_jquery_migrate', 'Remove jQuery Migrate', 'Remove jQuery Migrate script dependency from frontend scripts.' );
                                    $this->render_toggle_switch( 'uwb_general_disable_xmlrpc', 'Disable XML-RPC', 'Disable XML-RPC requests and remove RSD links to block brute-force attacks.' );
                                    $this->render_toggle_switch( 'uwb_general_disable_dashicons', 'Disable Dashicons', 'De-enqueue Dashicons stylesheet on frontend for non-logged-in users.' );
                                    $this->render_toggle_switch( 'uwb_general_disable_embeds', 'Disable Embeds', 'De-enqueue oEmbed javascript and disable oEmbed-related discovery links.' );
                                    ?>
                                    <?php $this->render_module_banner_end(); ?>
                                </div>
                            </div>

