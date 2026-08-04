<?php
namespace Ultimate_WP_Booster\Engine\RuntimeOptimizer\Runtime;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

use Ultimate_WP_Booster\Engine\RuntimeOptimizer\Compiler\Compiler;

/**
 * RuntimeManager — Manages the MU Plugin lifecycle and compile triggers.
 *
 * Installs/uninstalls uro-runtime.php into wp-content/mu-plugins/.
 * Registers all compile trigger hooks.
 */
class RuntimeManager {

    private const MU_PLUGIN_FILE  = 'uro-runtime.php';
    private const MU_PLUGIN_DIR   = '/mu-plugins/';
    private const SOURCE_TEMPLATE = 'uro-runtime.php'; // in plugin templates/

    /** @var string  Absolute path to mu-plugins directory */
    private string $mu_dir;

    /** @var string  Source template path */
    private string $source;

    /** @var Compiler */
    private Compiler $compiler;

    public function __construct() {
        $this->mu_dir   = WP_CONTENT_DIR . self::MU_PLUGIN_DIR;
        $this->source   = UWB_PLUGIN_DIR . 'templates/' . self::SOURCE_TEMPLATE;
        $this->compiler = new Compiler();
    }

    /**
     * Register all WordPress hooks for compile triggers.
     */
    public function register_hooks(): void {
        // Compile triggers
        add_action( 'activated_plugin',         [ $this, 'on_plugin_change' ] );
        add_action( 'deactivated_plugin',       [ $this, 'on_plugin_change' ] );
        add_action( 'upgrader_process_complete', [ $this, 'on_upgrade' ], 10, 2 );
        add_action( 'deleted_plugin',           [ $this, 'on_plugin_change' ] );
        add_action( 'switch_theme',             [ $this, 'on_theme_change' ] );
        add_action( 'update_option_permalink_structure', [ $this, 'on_permalink_change' ] );

        // AJAX handlers
        add_action( 'wp_ajax_uwb_uro_save_rules',        [ $this, 'ajax_save_rules' ] );
        add_action( 'wp_ajax_uwb_uro_rebuild',           [ $this, 'ajax_rebuild' ] );
        add_action( 'wp_ajax_uwb_uro_scan_plugins',      [ $this, 'ajax_scan_plugins' ] );
        add_action( 'wp_ajax_uwb_uro_get_status',        [ $this, 'ajax_get_status' ] );
        add_action( 'wp_ajax_uwb_uro_toggle_analyzer',   [ $this, 'ajax_toggle_analyzer' ] );
        add_action( 'wp_ajax_uwb_uro_get_analyzer_log',  [ $this, 'ajax_get_analyzer_log' ] );
        add_action( 'wp_ajax_uwb_uro_clear_analyzer',    [ $this, 'ajax_clear_analyzer' ] );
        add_action( 'wp_ajax_uwb_uro_toggle_runtime',    [ $this, 'ajax_toggle_runtime' ] );
        add_action( 'wp_ajax_uwb_uro_quick_save_rule',   [ $this, 'ajax_quick_save_rule' ] );
    }

    // =========================================================================
    // Compile Triggers
    // =========================================================================

    public function on_plugin_change( $plugin = null ): void {
        // Clear plugin list cache so scanner gets fresh data
        ( new \Ultimate_WP_Booster\Engine\RuntimeOptimizer\Scanner\PluginScanner() )->clear_cache();
        $this->schedule_recompile();
    }

    public function on_upgrade( $upgrader, array $options ): void {
        if ( in_array( $options['type'] ?? '', [ 'plugin', 'theme' ], true ) ) {
            $this->schedule_recompile();
        }
    }

    public function on_theme_change(): void {
        $this->schedule_recompile();
    }

    public function on_permalink_change(): void {
        $this->schedule_recompile();
    }

    /**
     * Immediate recompile (synchronous).
     *
     * @return array{success: bool, message: string, errors: array}
     */
    public function recompile(): array {
        return $this->compiler->compile();
    }

    /**
     * Schedule a recompile via WP Cron (deferred, non-blocking).
     */
    public function schedule_recompile(): void {
        if ( ! wp_next_scheduled( 'uwb_uro_recompile_event' ) ) {
            wp_schedule_single_event( time() + 5, 'uwb_uro_recompile_event' );
        }
    }

    // =========================================================================
    // MU Plugin Install / Uninstall
    // =========================================================================

    /**
     * Install (copy) the runtime MU plugin into wp-content/mu-plugins/.
     *
     * @return bool|WP_Error
     */
    public function install_mu_plugin() {
        if ( ! is_dir( $this->mu_dir ) ) {
            if ( ! @mkdir( $this->mu_dir, 0755, true ) ) {
                return new \WP_Error( 'mu_mkdir', 'Cannot create mu-plugins directory.' );
            }
        }

        $dest = $this->mu_dir . self::MU_PLUGIN_FILE;

        if ( ! file_exists( $this->source ) ) {
            return new \WP_Error( 'mu_source', 'Runtime template not found: ' . $this->source );
        }

        if ( ! @copy( $this->source, $dest ) ) {
            return new \WP_Error( 'mu_copy', 'Cannot copy runtime to mu-plugins. Check file permissions.' );
        }

        return true;
    }

    /**
     * Remove the runtime MU plugin from wp-content/mu-plugins/.
     */
    public function uninstall_mu_plugin(): bool {
        $dest = $this->mu_dir . self::MU_PLUGIN_FILE;
        if ( file_exists( $dest ) ) {
            return @unlink( $dest );
        }
        return true;
    }

    /**
     * Check if the MU plugin is installed and up to date.
     *
     * @return array{installed: bool, version_match: bool}
     */
    public function mu_plugin_status(): array {
        $dest = $this->mu_dir . self::MU_PLUGIN_FILE;
        if ( ! file_exists( $dest ) ) {
            return [ 'installed' => false, 'version_match' => false ];
        }
        // Check the version comment in the file
        $content = file_get_contents( $dest );
        $src     = file_exists( $this->source ) ? file_get_contents( $this->source ) : '';
        return [
            'installed'     => true,
            'version_match' => ( md5( $content ) === md5( $src ) ),
        ];
    }

    // =========================================================================
    // AJAX Handlers
    // =========================================================================

    public function ajax_save_rules(): void {
        check_ajax_referer( 'uwb_uro_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }

        $rules_json = isset( $_POST['rules'] ) ? wp_unslash( $_POST['rules'] ) : '[]';
        update_option( 'uwb_uro_rules', $rules_json, false );

        // Trigger recompile
        $result = $this->recompile();
        wp_send_json( $result );
    }

    public function ajax_rebuild(): void {
        check_ajax_referer( 'uwb_uro_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }

        $result = $this->recompile();
        wp_send_json( $result );
    }

    public function ajax_scan_plugins(): void {
        check_ajax_referer( 'uwb_uro_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }

        $scanner = new \Ultimate_WP_Booster\Engine\RuntimeOptimizer\Scanner\PluginScanner();
        $plugins = $scanner->scan( true ); // force re-scan
        wp_send_json_success( $plugins );
    }

    public function ajax_get_status(): void {
        check_ajax_referer( 'uwb_uro_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }

        $meta = $this->compiler->get_metadata();
        $mu   = $this->mu_plugin_status();

        wp_send_json_success( [
            'compiled'      => $this->compiler->is_compiled(),
            'metadata'      => $meta,
            'mu_plugin'     => $mu,
            'rules_json'    => get_option( 'uwb_uro_rules', '[]' ),
            'analyzer_on'   => (bool) get_option( 'uwb_uro_analyzer_enabled', false ),
        ] );
    }

    public function ajax_toggle_runtime(): void {
        check_ajax_referer( 'uwb_uro_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }

        $enable = (bool) ( $_POST['enable'] ?? false );

        if ( $enable ) {
            $install = $this->install_mu_plugin();
            if ( is_wp_error( $install ) ) {
                wp_send_json_error( $install->get_error_message() );
            }
            $result = $this->recompile();
            wp_send_json( array_merge( $result, [ 'runtime_enabled' => true ] ) );
        } else {
            $this->uninstall_mu_plugin();
            $this->compiler->clear();
            wp_send_json_success( [ 'runtime_enabled' => false ] );
        }
    }

    public function ajax_toggle_analyzer(): void {
        check_ajax_referer( 'uwb_uro_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }

        $enable = (bool) ( $_POST['enable'] ?? false );
        update_option( 'uwb_uro_analyzer_enabled', $enable );
        wp_send_json_success( [ 'analyzer_enabled' => $enable ] );
    }

    public function ajax_get_analyzer_log(): void {
        check_ajax_referer( 'uwb_uro_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }

        $analyzer = new \Ultimate_WP_Booster\Engine\RuntimeOptimizer\Analyzer\Analyzer();
        $log      = $analyzer->get_log();
        $recs     = $analyzer->generate_recommendations();
        wp_send_json_success( [ 'log' => $log, 'recommendations' => $recs ] );
    }

    public function ajax_clear_analyzer(): void {
        check_ajax_referer( 'uwb_uro_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }

        $analyzer = new \Ultimate_WP_Booster\Engine\RuntimeOptimizer\Analyzer\Analyzer();
        $analyzer->clear_log();
        wp_send_json_success();
    }

    public function ajax_quick_save_rule(): void {
        check_ajax_referer( 'uwb_uro_quick_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }

        $url_path = isset( $_POST['url_path'] ) ? sanitize_text_field( wp_unslash( $_POST['url_path'] ) ) : '';
        if ( empty( $url_path ) ) {
            wp_send_json_error( 'Invalid URL path.' );
        }

        $blocked_plugins = isset( $_POST['blocked_plugins'] ) ? (array) $_POST['blocked_plugins'] : [];
        $rules_json = get_option( 'uwb_uro_rules', '[]' );
        $rules = json_decode( $rules_json, true );
        if ( ! is_array( $rules ) ) {
            $rules = [];
        }

        // Find if there is an existing rule for this exact URL path
        $found_key = null;
        foreach ( $rules as $key => $rule ) {
            if ( isset( $rule['conditions']['url'] ) && count( $rule['conditions']['url'] ) === 1 && $rule['conditions']['url'][0] === $url_path && $rule['action'] === 'deny' ) {
                $found_key = $key;
                break;
            }
        }

        if ( empty( $blocked_plugins ) ) {
            // If no plugins are blocked, remove the rule if it exists
            if ( $found_key !== null ) {
                unset( $rules[ $found_key ] );
                $rules = array_values( $rules );
            }
        } else {
            // Update or create rule
            $rule_data = [
                'id' => ( $found_key !== null && isset( $rules[ $found_key ]['id'] ) ) ? $rules[ $found_key ]['id'] : 'rule-' . time(),
                'name' => 'Quick block on ' . $url_path,
                'enabled' => true,
                'priority' => 10,
                'action' => 'deny',
                'plugins' => $blocked_plugins,
                'conditions' => [
                    'url' => [ $url_path ],
                    'post_type' => [],
                    'taxonomy' => [],
                    'woocommerce' => [],
                    'user_role' => [],
                    'device' => [],
                    'is_ajax' => null,
                    'is_rest' => null,
                    'callback' => null,
                ]
            ];

            if ( $found_key !== null ) {
                $rules[ $found_key ] = $rule_data;
            } else {
                $rules[] = $rule_data;
            }
        }

        update_option( 'uwb_uro_rules', json_encode( $rules ), false );

        // Trigger recompile
        $result = $this->recompile();
        wp_send_json( $result );
    }
}
