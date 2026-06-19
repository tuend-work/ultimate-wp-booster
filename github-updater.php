<?php
/**
 * GitHub Updater for Ultimate WordPress Booster
 * Handles native WordPress update notifications and provides a manual update button.
 */

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

if ( ! class_exists( 'Uwb_Github_Updater' ) ) {
    class Uwb_Github_Updater {
        private $file;
        private $plugin_data;
        private $basename;
        private $username = 'tuend-work';
        private $repository = 'ultimate-wp-booster';
        private $github_response = null;

        public function __construct( $file ) {
            $this->file = $file;
            $this->basename = 'ultimate-wp-booster/ultimate-wp-booster.php';

            // Hook to admin_init to load plugin properties
            add_action( 'admin_init', array( $this, 'init_properties' ) );

            // AJAX action for manual update
            add_action( 'wp_ajax_uwb_github_manual_update', array( $this, 'ajax_manual_update' ) );

            return $this;
        }

        public function init_properties() {
            $this->plugin_data = get_plugin_data( $this->file );

            // Native WordPress plugin update hooks
            add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_updates' ) );
            add_filter( 'plugins_api', array( $this, 'plugin_popup_info' ), 10, 3 );
            add_filter( 'upgrader_post_install', array( $this, 'post_install_rename' ), 10, 3 );
        }

        /**
         * Fetch latest release details from GitHub API
         */
        private function get_latest_github_release() {
            if ( ! is_null( $this->github_response ) ) {
                return $this->github_response;
            }

            // Fetch the raw main file from the main branch to check the version (append timestamp to bypass CDN caching)
            $url = "https://raw.githubusercontent.com/{$this->username}/{$this->repository}/main/ultimate-wp-booster.php?t=" . time();
            $args = array(
                'timeout' => 10
            );

            $request = wp_remote_get( $url, $args );

            if ( is_wp_error( $request ) ) {
                return $request;
            }

            $code = wp_remote_retrieve_response_code( $request );
            $message = wp_remote_retrieve_response_message( $request );

            if ( $code !== 200 ) {
                return new WP_Error( 'github_error', 'Cannot connect to GitHub Raw (Error: ' . $code . ' - ' . $message . '). Please verify the repository is public.' );
            }

            $body = wp_remote_retrieve_body( $request );
            
            // Extract version from plugin headers
            if ( preg_match( '/Version:\s*([0-9.-]+)/i', $body, $matches ) ) {
                $version = trim( $matches[1] );
                
                // Construct mock release object pointing to the main branch ZIP
                $response = new stdClass();
                $response->tag_name = $version;
                $response->zipball_url = "https://github.com/{$this->username}/{$this->repository}/archive/refs/heads/main.zip";
                $response->body = "Direct update from GitHub main branch.";
                
                $this->github_response = $response;
                return $response;
            }

            return new WP_Error( 'github_parse_error', 'Cannot parse plugin version from GitHub source file.' );
        }

        /**
         * Inject GitHub update data into WordPress update transient
         */
        public function check_for_updates( $transient ) {
            if ( empty( $transient->checked ) ) {
                return $transient;
            }

            $release = $this->get_latest_github_release();

            if ( $release && ! is_wp_error( $release ) ) {
                $github_version = ltrim( $release->tag_name, 'v' );
                $local_version  = $this->plugin_data['Version'];

                if ( version_compare( $local_version, $github_version, '<' ) ) {
                    $obj = new stdClass();
                    $obj->slug        = $this->basename;
                    $obj->plugin      = $this->basename;
                    $obj->new_version = $github_version;
                    $obj->url         = "https://github.com/{$this->username}/{$this->repository}";
                    $obj->package     = $release->zipball_url;

                    $transient->response[ $this->basename ] = $obj;
                }
            }

            return $transient;
        }

        /**
         * Provide information for the WordPress "View details" popup modal
         */
        public function plugin_popup_info( $result, $action, $args ) {
            if ( $action !== 'plugin_information' ) {
                return $result;
            }

            if ( ! isset( $args->slug ) || $args->slug !== $this->basename ) {
                return $result;
            }

            $release = $this->get_latest_github_release();

            if ( $release && ! is_wp_error( $release ) ) {
                $github_version = ltrim( $release->tag_name, 'v' );

                $api_response = new stdClass();
                $api_response->name          = $this->plugin_data['Name'];
                $api_response->slug          = $this->basename;
                $api_response->version       = $github_version;
                $api_response->author        = $this->plugin_data['AuthorName'];
                $api_response->homepage      = $this->plugin_data['PluginURI'];
                $api_response->download_link = $release->zipball_url;
                $api_response->sections      = array(
                    'description' => $this->plugin_data['Description'],
                    'changelog'   => wp_kses_post( nl2br( $release->body ) )
                );

                return $api_response;
            }

            return $result;
        }

        /**
         * Rename the GitHub zip extraction folder to the proper plugin folder name
         */
        public function post_install_rename( $response, $hook_extra, $result ) {
            global $wp_filesystem;

            // Check if this upgrade is for our plugin
            if ( empty( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->basename ) {
                return $response;
            }

            // If there's an error already, do not proceed
            if ( is_wp_error( $response ) ) {
                return $response;
            }

            // Ensure $wp_filesystem is initialized
            if ( ! $wp_filesystem ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                WP_Filesystem();
            }

            $plugin_folder = dirname( $this->basename ); // 'ultimate-wp-booster'
            $proper_destination = WP_PLUGIN_DIR . '/' . $plugin_folder;
            $source = $result['destination'];

            if ( $source !== $proper_destination ) {
                // Delete destination if it exists
                if ( $wp_filesystem->exists( $proper_destination ) ) {
                    $wp_filesystem->delete( $proper_destination, true );
                }

                // Perform moves
                $moved = $wp_filesystem->move( $source, $proper_destination );
                if ( ! $moved ) {
                    return new WP_Error( 'rename_failed', 'Failed to rename the plugin folder to ' . $plugin_folder );
                }
            }

            // Reactivate if it was active prior to upgrade
            if ( is_plugin_active( $this->basename ) ) {
                activate_plugin( $this->basename );
            }

            return true;
        }

        /**
         * AJAX Action: Check for updates and return native upgrade URL
         */
        public function ajax_manual_update() {
            // Check permission
            if ( ! current_user_can( 'update_plugins' ) ) {
                wp_send_json_error( array( 'message' => 'You do not have permission to perform this action.' ) );
            }

            // Verify nonce
            check_ajax_referer( 'uwb_github_update_nonce', 'nonce' );

            $release = $this->get_latest_github_release();

            if ( is_wp_error( $release ) ) {
                wp_send_json_error( array( 'message' => 'GitHub connection error: ' . $release->get_error_message() ) );
            }

            $github_version = ltrim( $release->tag_name, 'v' );

            // Temporarily force transient update to ensure updater recognizes the source package
            $transient = get_site_transient( 'update_plugins' );
            if ( ! is_object( $transient ) ) {
                $transient = new stdClass();
            }
            
            $obj = new stdClass();
            $obj->slug        = 'ultimate-wp-booster';
            $obj->plugin      = $this->basename;
            $obj->new_version = $github_version;
            $obj->url         = "https://github.com/{$this->username}/{$this->repository}";
            $obj->package     = $release->zipball_url;
            $transient->response[ $this->basename ] = $obj;
            set_site_transient( 'update_plugins', $transient );

            // Generate native WordPress update URL with nonce
            $upgrade_url = wp_nonce_url( self_admin_url( 'update.php?action=upgrade-plugin&plugin=' . urlencode( $this->basename ) ), 'upgrade-plugin_' . $this->basename );

            wp_send_json_success( array(
                'update_available' => true,
                'upgrade_url'      => $upgrade_url,
                'message'          => 'Latest version ' . $github_version . ' found. Redirecting to native installer...'
            ) );
        }

        /**
         * Render Update Button HTML in Settings Page
         */
        public static function render_update_button() {
            $plugin_file = __DIR__ . '/ultimate-wp-booster.php';
            $version = 'N/A';
            if ( file_exists( $plugin_file ) ) {
                $data = get_plugin_data( $plugin_file );
                $version = $data['Version'];
            }
            
            $nonce = wp_create_nonce( 'uwb_github_update_nonce' );
            ?>
            <div class="uwb-updater-card">
                <div class="uwb-updater-header">
                    <svg class="uwb-git-icon" viewBox="0 0 16 16" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z"></path></svg>
                    <h4>GitHub Updates</h4>
                </div>
                <div class="uwb-updater-body">
                    <p>Current Version: <span class="uwb-badge">v<?php echo esc_html( $version ); ?></span></p>
                    <p class="uwb-help-text">Click the button below to check and update directly to the latest version from the official GitHub repository (<code>main</code> branch).</p>
                    <div class="uwb-action-row">
                        <button type="button" id="uwb-github-update-btn" class="uwb-btn uwb-btn-primary">
                            <span class="uwb-btn-text">Check & Update</span>
                            <span class="uwb-spinner" style="display: none;"></span>
                        </button>
                        <span id="uwb-github-update-status"></span>
                    </div>
                </div>
            </div>
            
            <style>
            .uwb-updater-card {
                background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
                padding: 24px;
                border-radius: 12px;
                border: 1px solid #e2e8f0;
                margin-top: 20px;
                max-width: 650px;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            }
            .uwb-updater-header {
                display: flex;
                align-items: center;
                gap: 10px;
                margin-bottom: 16px;
                border-bottom: 1px solid #edf2f7;
                padding-bottom: 12px;
            }
            .uwb-updater-header h4 {
                margin: 0;
                color: #0f172a;
                font-size: 1.15rem;
                font-weight: 700;
            }
            .uwb-git-icon {
                color: #334155;
            }
            .uwb-badge {
                background-color: #e2e8f0;
                color: #334155;
                padding: 3px 8px;
                border-radius: 6px;
                font-family: monospace;
                font-size: 0.9rem;
                font-weight: 600;
            }
            .uwb-help-text {
                color: #64748b;
                font-size: 0.95rem;
                line-height: 1.5;
                margin: 12px 0 20px 0;
            }
            .uwb-action-row {
                display: flex;
                align-items: center;
                gap: 16px;
            }
            .uwb-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                cursor: pointer;
                border: 1px solid transparent;
                border-radius: 8px;
                padding: 10px 20px;
                font-size: 0.95rem;
                font-weight: 600;
                transition: all 0.2s ease;
                height: 42px;
            }
            .uwb-btn-primary {
                background: #2563eb;
                color: #ffffff;
                box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.2);
            }
            .uwb-btn-primary:hover {
                background: #1d4ed8;
                box-shadow: 0 4px 12px -1px rgba(37, 99, 235, 0.3);
            }
            .uwb-btn-primary:disabled {
                background: #93c5fd;
                cursor: not-allowed;
                box-shadow: none;
            }
            #uwb-github-update-status {
                font-weight: 600;
                font-size: 0.95rem;
                color: #475569;
                transition: all 0.2s ease;
            }
            .uwb-spinner {
                width: 16px;
                height: 16px;
                border: 2px solid #ffffff;
                border-bottom-color: transparent;
                border-radius: 50%;
                display: inline-block;
                box-sizing: border-box;
                animation: uwb-rotation 1s linear infinite;
            }
            @keyframes uwb-rotation {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            </style>
            
            <script>
            jQuery(document).ready(function($) {
                $('#uwb-github-update-btn').on('click', function(e) {
                    e.preventDefault();
                    var btn = $(this);
                    var status = $('#uwb-github-update-status');
                    var spinner = btn.find('.uwb-spinner');
                    var btnText = btn.find('.uwb-btn-text');
                    
                    btn.prop('disabled', true);
                    btnText.text('Checking...');
                    spinner.show();
                    status.css('color', '#475569').text('Checking GitHub...');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'uwb_github_manual_update',
                            nonce: '<?php echo esc_js( $nonce ); ?>'
                        },
                        success: function(res) {
                            spinner.hide();
                            if (res.success) {
                                status.css('color', '#16a34a').text(res.data.message);
                                if (res.data.update_available) {
                                    setTimeout(function() {
                                        window.location.href = res.data.upgrade_url;
                                    }, 1500);
                                } else {
                                    btn.prop('disabled', false);
                                    btnText.text('Check & Update');
                                }
                            } else {
                                status.css('color', '#ef4444').text(res.data.message || 'Unknown error.');
                                btn.prop('disabled', false);
                                btnText.text('Check & Update');
                            }
                        },
                        error: function() {
                            spinner.hide();
                            status.css('color', '#ef4444').text('Server connection error.');
                            btn.prop('disabled', false);
                            btnText.text('Check & Update');
                        }
                    });
                });
            });
            </script>
            <?php
        }
    }
}
