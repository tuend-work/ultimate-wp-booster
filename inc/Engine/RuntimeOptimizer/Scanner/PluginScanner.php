<?php
namespace Ultimate_WP_Booster\Engine\RuntimeOptimizer\Scanner;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

/**
 * PluginScanner — Scans wp-content/plugins and assigns stable numeric IDs.
 *
 * IDs are stable as long as the plugin list does not change.
 * Cached to wp-content/cache/uro/plugin_list.php.
 */
class PluginScanner {

    /** @var string */
    private string $cache_file;

    public function __construct() {
        $this->cache_file = WP_CONTENT_DIR . '/cache/uro/plugin_list.php';
    }

    /**
     * Scan all installed plugins.
     *
     * @param bool $force Force re-scan even if cache exists.
     * @return array<int, array{file: string, name: string, version: string, requires_plugins: string}>
     */
    public function scan( bool $force = false ): array {
        if ( ! $force && file_exists( $this->cache_file ) ) {
            $cached = @include $this->cache_file;
            if ( is_array( $cached ) && ! empty( $cached ) ) {
                return $cached;
            }
        }

        if ( ! function_exists( 'get_plugin_data' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugins     = [];
        $plugins_dir = defined( 'WP_PLUGIN_DIR' ) ? WP_PLUGIN_DIR : WP_CONTENT_DIR . '/plugins';
        $plugin_files = glob( $plugins_dir . '/*/*.php' );

        if ( empty( $plugin_files ) ) {
            return [];
        }

        foreach ( $plugin_files as $file ) {
            $data = get_plugin_data( $file, false, false );
            if ( empty( $data['Name'] ) ) {
                continue;
            }
            $plugin_file = plugin_basename( $file );
            $plugins[]   = [
                'file'              => $plugin_file,
                'name'              => $data['Name'],
                'version'           => $data['Version'] ?? '',
                'requires_plugins'  => $data['RequiresPlugins'] ?? '',
                'author'            => $data['Author'] ?? '',
            ];
        }

        // Sort alphabetically by file path for stable IDs across requests
        usort( $plugins, static fn( $a, $b ) => strcmp( $a['file'], $b['file'] ) );

        // Re-index from 0
        $indexed = array_values( $plugins );

        $this->save_cache( $indexed );

        return $indexed;
    }

    /**
     * Build a map of plugin_file => plugin_id for the currently active plugins.
     *
     * @param array<string> $active_plugins
     * @return array<string, int>  e.g. ['woocommerce/woocommerce.php' => 3]
     */
    public function build_file_to_id_map( array $active_plugins ): array {
        $all = $this->scan();
        $map = [];
        foreach ( $all as $id => $plugin ) {
            if ( in_array( $plugin['file'], $active_plugins, true ) ) {
                $map[ $plugin['file'] ] = $id;
            }
        }
        return $map;
    }

    /**
     * Build a reverse map of plugin_id => plugin_file.
     *
     * @param array<string> $active_plugins
     * @return array<int, string>
     */
    public function build_id_to_file_map( array $active_plugins ): array {
        return array_flip( $this->build_file_to_id_map( $active_plugins ) );
    }

    /**
     * Find plugin file path by a partial slug or full file.
     *
     * @param string $slug  e.g. 'woocommerce' or 'woocommerce/woocommerce.php'
     * @return string|null
     */
    public function find_plugin_file( string $slug ): ?string {
        $all = $this->scan();
        foreach ( $all as $plugin ) {
            if ( $plugin['file'] === $slug ) {
                return $plugin['file'];
            }
            $dir = dirname( $plugin['file'] );
            if ( $dir === $slug || strpos( $plugin['file'], $slug ) !== false ) {
                return $plugin['file'];
            }
        }
        return null;
    }

    /**
     * Persist the plugin list cache.
     */
    private function save_cache( array $data ): void {
        $dir = dirname( $this->cache_file );
        if ( ! is_dir( $dir ) ) {
            @mkdir( $dir, 0755, true );
        }
        $export  = var_export( $data, true );
        $content = "<?php\n// UWB URO — Plugin List Cache | Generated: " . gmdate( 'Y-m-d H:i:s' ) . " UTC\nreturn {$export};\n";
        @file_put_contents( $this->cache_file, $content, LOCK_EX );
    }

    /**
     * Clear the cached plugin list.
     */
    public function clear_cache(): void {
        if ( file_exists( $this->cache_file ) ) {
            @unlink( $this->cache_file );
        }
    }
}
