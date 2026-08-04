<?php
namespace Ultimate_WP_Booster\Engine\RuntimeOptimizer\Scanner;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

/**
 * DependencyScanner — Reads inter-plugin dependencies and validates Deny rules.
 *
 * Reads both the 'Requires Plugins' header (WP 6.5+) and a manual
 * map stored in the option `uwb_uro_dependency_map`.
 */
class DependencyScanner {

    /** @var array<string, string[]>  plugin_file => list of required plugin_files */
    private array $dependency_map = [];

    /** @var bool */
    private bool $loaded = false;

    public function __construct() {}

    /**
     * Build the full dependency map from all active plugins.
     *
     * @param array<string> $active_plugins  List of active plugin files.
     * @return array<string, string[]>
     */
    public function build_map( array $active_plugins ): array {
        if ( ! function_exists( 'get_plugin_data' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $map = [];
        $plugins_dir = defined( 'WP_PLUGIN_DIR' ) ? WP_PLUGIN_DIR : WP_CONTENT_DIR . '/plugins';

        foreach ( $active_plugins as $plugin_file ) {
            $full_path = $plugins_dir . '/' . $plugin_file;
            if ( ! file_exists( $full_path ) ) {
                continue;
            }
            $data = get_plugin_data( $full_path, false, false );

            // WP 6.5+ header: "Requires Plugins: woocommerce, elementor"
            $requires_raw = $data['RequiresPlugins'] ?? '';
            if ( ! empty( $requires_raw ) ) {
                $slugs = array_filter( array_map( 'trim', explode( ',', $requires_raw ) ) );
                $map[ $plugin_file ] = $slugs; // Store as slugs; resolved later
            }
        }

        // Merge with manually configured map (Admin can add custom deps)
        $manual = get_option( 'uwb_uro_dependency_map', [] );
        if ( is_array( $manual ) ) {
            foreach ( $manual as $plugin => $deps ) {
                $map[ $plugin ] = array_merge( $map[ $plugin ] ?? [], (array) $deps );
            }
        }

        $this->dependency_map = $map;
        $this->loaded         = true;

        return $map;
    }

    /**
     * Given a plugin file to disable, find all active plugins that depend on it.
     *
     * @param string        $plugin_to_disable
     * @param array<string> $active_plugins
     * @return array<string>  list of plugins that depend on $plugin_to_disable
     */
    public function find_dependents( string $plugin_to_disable, array $active_plugins ): array {
        if ( ! $this->loaded ) {
            $this->build_map( $active_plugins );
        }

        $dependents   = [];
        $disable_slug = dirname( $plugin_to_disable ); // e.g. "woocommerce"

        foreach ( $this->dependency_map as $plugin => $deps ) {
            foreach ( $deps as $dep ) {
                // Match by slug or full file path
                if ( $dep === $plugin_to_disable || $dep === $disable_slug ) {
                    $dependents[] = $plugin;
                    break;
                }
            }
        }

        return $dependents;
    }

    /**
     * Validate that a list of plugins to deny will not break other active plugins.
     *
     * @param array<string> $plugins_to_deny
     * @param array<string> $active_plugins
     * @return array<string>  Error messages (empty = valid)
     */
    public function validate_deny_list( array $plugins_to_deny, array $active_plugins ): array {
        if ( ! $this->loaded ) {
            $this->build_map( $active_plugins );
        }

        $errors = [];
        foreach ( $plugins_to_deny as $plugin ) {
            $dependents = $this->find_dependents( $plugin, $active_plugins );
            // Remove from dependents any plugin that is also being denied
            $dependents = array_diff( $dependents, $plugins_to_deny );
            if ( ! empty( $dependents ) ) {
                $errors[] = sprintf(
                    'Cannot disable "%s": the following plugins depend on it: %s',
                    $plugin,
                    implode( ', ', $dependents )
                );
            }
        }

        return $errors;
    }

    /**
     * Get the full dependency map (build if not already done).
     *
     * @param array<string> $active_plugins
     * @return array<string, string[]>
     */
    public function get_map( array $active_plugins ): array {
        if ( ! $this->loaded ) {
            $this->build_map( $active_plugins );
        }
        return $this->dependency_map;
    }
}
