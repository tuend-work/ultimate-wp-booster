<?php
namespace Ultimate_WP_Booster\Engine\RuntimeOptimizer\Analyzer;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

/**
 * Analyzer — Profiles which plugins load on which pages.
 *
 * Hooks into WordPress after plugins are loaded to collect:
 * - Memory usage per plugin
 * - Execution time delta
 * - Registered hooks, REST routes, shortcodes, widgets
 *
 * Data is saved to wp-content/cache/uro/analyzer/
 * and presented in the Admin UI to help craft deny rules.
 */
class Analyzer {

    private const CACHE_DIR = '/cache/uro/analyzer/';
    private const MAX_LOG   = 100; // Maximum page samples to retain

    /** @var string */
    private string $cache_dir;

    /** @var float */
    private float $start_time;

    /** @var int */
    private int $start_memory;

    /** @var bool */
    private bool $enabled = false;

    /** @var array */
    private static array $wrapped_callbacks = [];

    public function __construct() {
        $this->cache_dir = WP_CONTENT_DIR . self::CACHE_DIR;
    }

    public function init(): void {
        $cookie_profile = isset( $_COOKIE['uwb_uro_profile'] ) && $_COOKIE['uwb_uro_profile'] === '1';

        // Debug HTML comments in head
        add_action( 'wp_head', function() use ( $cookie_profile ) {
            echo "\n<!-- UWB URO Profiler: Initialized, Cookie Profile=" . ( $cookie_profile ? '1' : '0' ) . " -->\n";
        } );
        add_action( 'admin_head', function() use ( $cookie_profile ) {
            echo "\n<!-- UWB URO Profiler: Initialized, Cookie Profile=" . ( $cookie_profile ? '1' : '0' ) . " -->\n";
        } );

        $this->enabled = (bool) get_option( 'uwb_uro_analyzer_enabled', false ) || $cookie_profile;
        if ( ! $this->enabled ) {
            return;
        }

        $this->start_time   = microtime( true );
        $this->start_memory = memory_get_usage( true );

        // Wrap existing hooks and hook into dynamic registrations
        $this->wrap_all_existing_hooks();
        add_action( 'all', [ $this, 'wrap_hook_callbacks_dynamically' ] );

        // Record request log to file (only when global Analyzer is enabled in Settings)
        if ( (bool) get_option( 'uwb_uro_analyzer_enabled', false ) ) {
            add_action( 'wp_loaded', [ $this, 'record' ], 9999 );
        }
        add_action( 'wp_footer', [ $this, 'output_profile_data' ], 9999 );
        add_action( 'admin_footer', [ $this, 'output_profile_data' ], 9999 );
    }

    /**
     * Wrap callbacks dynamically in-place right before the hook runs.
     */
    public function wrap_hook_callbacks_dynamically( string $hook_name ): void {
        global $wp_filter;
        if ( empty( $wp_filter[ $hook_name ] ) || ! ( $wp_filter[ $hook_name ] instanceof \WP_Hook ) ) {
            return;
        }

        $hook = $wp_filter[ $hook_name ];
        if ( empty( $hook->callbacks ) ) {
            return;
        }

        foreach ( $hook->callbacks as $priority => $callbacks ) {
            foreach ( $callbacks as $idx => $the_ ) {
                if ( empty( $the_['function'] ) ) {
                    continue;
                }

                $original_func = $the_['function'];
                if ( $original_func instanceof UWB_Callback_Wrapper ) {
                    continue;
                }

                $wrap_key = $hook_name . '_' . $priority . '_' . $idx;
                if ( isset( self::$wrapped_callbacks[ $wrap_key ] ) ) {
                    continue;
                }

                $the_['function'] = new UWB_Callback_Wrapper( $original_func, $hook_name );

                $hook->callbacks[ $priority ][ $idx ] = $the_;
                self::$wrapped_callbacks[ $wrap_key ] = true;
            }
        }
    }

    /**
     * Wrap all already-registered action/filter hooks.
     */
    private function wrap_all_existing_hooks(): void {
        global $wp_filter;
        if ( ! is_array( $wp_filter ) ) {
            return;
        }
        foreach ( $wp_filter as $name => $hook ) {
            if ( $hook instanceof \WP_Hook ) {
                $this->wrap_hook_callbacks_dynamically( $name );
            }
        }
    }

    /**
     * Record current request profile.
     */
    public function record(): void {
        if ( ! $this->enabled ) {
            return;
        }

        global $wp;

        $duration = round( ( microtime( true ) - $this->start_time ) * 1000, 2 ); // ms
        $memory   = memory_get_peak_usage( true );

        // Collect registered hooks (count per plugin)
        $hook_counts = $this->count_hooks_per_plugin();

        // REST routes
        $rest_routes = [];
        if ( class_exists( 'WP_REST_Server' ) ) {
            $server = rest_get_server();
            $routes = $server->get_routes();
            foreach ( $routes as $route => $handlers ) {
                $rest_routes[] = $route;
            }
        }

        // Shortcodes
        global $shortcode_tags;
        $shortcodes = array_keys( $shortcode_tags ?? [] );

        // Active plugins
        $active_plugins = get_option( 'active_plugins', [] );

        $entry = [
            'url'               => ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http' )
                . '://' . ( $_SERVER['HTTP_HOST'] ?? '' ) . ( $_SERVER['REQUEST_URI'] ?? '/' ),
            'time'              => gmdate( 'Y-m-d H:i:s' ),
            'duration_ms'       => $duration,
            'peak_memory'       => $memory,
            'plugins_loaded'    => count( $active_plugins ),
            'plugin_list'       => $active_plugins,
            'plugin_load_times' => $GLOBALS['_uro_plugin_times'] ?? [],
            'hook_counts'       => $hook_counts,
            'rest_routes'       => $rest_routes,
            'shortcodes'        => $shortcodes,
            'post_type'         => ( function_exists( 'get_post_type' ) ) ? get_post_type() ?: '' : '',
            'is_archive'        => function_exists( 'is_archive' ) && is_archive(),
            'is_singular'       => function_exists( 'is_singular' ) && is_singular(),
            'is_front_page'     => function_exists( 'is_front_page' ) && is_front_page(),
        ];

        $this->append_log( $entry );
    }

    /**
     * Count how many hooks (actions + filters) each plugin callback is registered for.
     *
     * @return array<string, int>  plugin_file => hook count
     */
    private function count_hooks_per_plugin(): array {
        global $wp_filter;
        $counts      = [];
        $plugins_dir = defined( 'WP_PLUGIN_DIR' ) ? WP_PLUGIN_DIR : WP_CONTENT_DIR . '/plugins';

        if ( ! is_array( $wp_filter ) ) {
            return $counts;
        }

        foreach ( $wp_filter as $hook_name => $hook_obj ) {
            if ( ! is_object( $hook_obj ) || ! isset( $hook_obj->callbacks ) ) {
                continue;
            }
            foreach ( $hook_obj->callbacks as $priority => $callbacks ) {
                foreach ( $callbacks as $cb ) {
                    $func = $cb['function'] ?? null;
                    $file = null;

                    try {
                        if ( is_array( $func ) && isset( $func[0] ) ) {
                            $ref  = is_object( $func[0] )
                                ? new \ReflectionClass( $func[0] )
                                : new \ReflectionClass( $func[0] );
                            $file = $ref->getFileName();
                        } elseif ( is_string( $func ) && function_exists( $func ) ) {
                            $ref  = new \ReflectionFunction( $func );
                            $file = $ref->getFileName();
                        } elseif ( $func instanceof \Closure ) {
                            $ref  = new \ReflectionFunction( $func );
                            $file = $ref->getFileName();
                        }
                    } catch ( \Throwable $e ) {
                        continue;
                    }

                    if ( ! $file ) {
                        continue;
                    }

                    // Map file path to plugin slug
                    $rel = str_replace( $plugins_dir . DIRECTORY_SEPARATOR, '', $file );
                    $rel = str_replace( $plugins_dir . '/', '', $rel );
                    $parts = explode( DIRECTORY_SEPARATOR, $rel );
                    if ( count( $parts ) < 2 ) {
                        $parts = explode( '/', $rel );
                    }
                    if ( ! empty( $parts[0] ) ) {
                        $slug               = $parts[0];
                        $counts[ $slug ]    = ( $counts[ $slug ] ?? 0 ) + 1;
                    }
                }
            }
        }

        return $counts;
    }

    /**
     * Append a log entry and trim to MAX_LOG.
     */
    private function append_log( array $entry ): void {
        if ( ! is_dir( $this->cache_dir ) ) {
            @mkdir( $this->cache_dir, 0755, true );
        }

        $log_file = $this->cache_dir . 'log.php';
        $log      = [];

        if ( file_exists( $log_file ) ) {
            $existing = @include $log_file;
            if ( is_array( $existing ) ) {
                $log = $existing;
            }
        }

        array_unshift( $log, $entry );

        if ( count( $log ) > self::MAX_LOG ) {
            $log = array_slice( $log, 0, self::MAX_LOG );
        }

        $export = var_export( $log, true );
        @file_put_contents( $log_file, "<?php\n// UWB URO Analyzer Log\nreturn {$export};\n", LOCK_EX );
    }

    /**
     * Read analyzer log for Admin UI.
     *
     * @return array
     */
    public function get_log(): array {
        $log_file = $this->cache_dir . 'log.php';
        if ( ! file_exists( $log_file ) ) {
            return [];
        }
        $data = @include $log_file;
        return is_array( $data ) ? $data : [];
    }

    /**
     * Generate rule recommendations based on log data.
     *
     * Compares plugin lists across URLs and suggests which plugins
     * appear unnecessary on high-traffic non-ecommerce pages.
     *
     * @return array  List of recommendation strings.
     */
    public function generate_recommendations(): array {
        $log   = $this->get_log();
        $recs  = [];

        if ( empty( $log ) ) {
            return [ 'No analyzer data yet. Enable the Analyzer and browse your site.' ];
        }

        // Find plugins that appear in every request (potentially cuttable per-page)
        $plugin_counts  = [];
        $total_requests = count( $log );

        foreach ( $log as $entry ) {
            foreach ( $entry['plugin_list'] ?? [] as $pf ) {
                $plugin_counts[ $pf ] = ( $plugin_counts[ $pf ] ?? 0 ) + 1;
            }
        }

        // Plugins loaded on every single page — candidates for per-context removal
        foreach ( $plugin_counts as $pf => $cnt ) {
            $dir = dirname( $pf );
            if ( $cnt === $total_requests && $total_requests > 5 ) {
                $recs[] = "Plugin '{$dir}' loads on all {$total_requests} sampled pages. Consider a deny rule for specific contexts.";
            }
        }

        // Plugins with very high hook counts
        $hook_totals = [];
        foreach ( $log as $entry ) {
            foreach ( $entry['hook_counts'] ?? [] as $slug => $count ) {
                $hook_totals[ $slug ] = ( $hook_totals[ $slug ] ?? 0 ) + $count;
            }
        }
        arsort( $hook_totals );
        $top = array_slice( $hook_totals, 0, 5, true );
        foreach ( $top as $slug => $total ) {
            $recs[] = "Plugin '{$slug}' registered ~" . round( $total / $total_requests ) . " hooks/page on average.";
        }

        return $recs;
    }

    /**
     * Clear all analyzer logs.
     */
    public function clear_log(): void {
        $log_file = $this->cache_dir . 'log.php';
        @unlink( $log_file );
    }

    /**
     * Accumulates execution time of a hook callback under its parent plugin and hook name.
     */
    public static function record_callback_time_with_hook( $callback, string $hook_name, float $duration_seconds ): void {
        static $plugin_cache = [];

        $cb_key = '';
        if ( is_string( $callback ) ) {
            $cb_key = $callback;
        } elseif ( is_array( $callback ) ) {
            $cb_key = ( is_object( $callback[0] ) ? get_class( $callback[0] ) : $callback[0] ) . '::' . $callback[1];
        } elseif ( $callback instanceof \Closure ) {
            $cb_key = 'closure_' . spl_object_hash( $callback );
        } else {
            return;
        }

        if ( ! isset( $plugin_cache[ $cb_key ] ) ) {
            $plugin_name = null;
            try {
                if ( is_string( $callback ) && function_exists( $callback ) ) {
                    $ref = new \ReflectionFunction( $callback );
                    $plugin_name = self::get_plugin_from_filepath( $ref->getFileName() );
                } elseif ( is_array( $callback ) && isset( $callback[0] ) ) {
                    $class = is_object( $callback[0] ) ? get_class( $callback[0] ) : $callback[0];
                    if ( class_exists( $class ) && method_exists( $class, $callback[1] ) ) {
                        $ref = new \ReflectionMethod( $class, $callback[1] );
                        $plugin_name = self::get_plugin_from_filepath( $ref->getFileName() );
                    }
                } elseif ( $callback instanceof \Closure ) {
                    $ref = new \ReflectionFunction( $callback );
                    $plugin_name = self::get_plugin_from_filepath( $ref->getFileName() );
                }
            } catch ( \Throwable $e ) {
                // Silence reflection exceptions
            }
            $plugin_cache[ $cb_key ] = $plugin_name ?: 'core_or_other';
        }

        $plugin = $plugin_cache[ $cb_key ];
        if ( $plugin !== 'core_or_other' ) {
            $ms = round( $duration_seconds * 1000, 2 );
            if ( ! isset( $GLOBALS['_uro_plugin_times'][ $plugin ] ) ) {
                $GLOBALS['_uro_plugin_times'][ $plugin ] = 0.0;
            }
            $GLOBALS['_uro_plugin_times'][ $plugin ] += $ms;

            if ( ! isset( $GLOBALS['_uro_plugin_hook_details'][ $plugin ][ $hook_name ] ) ) {
                $GLOBALS['_uro_plugin_hook_details'][ $plugin ][ $hook_name ] = 0.0;
            }
            $GLOBALS['_uro_plugin_hook_details'][ $plugin ][ $hook_name ] += $ms;
        }
    }

    /**
     * Outputs the gathered profiling data to the page footer as a secure JSON script tag.
     */
    public function output_profile_data(): void {
        if ( ! isset( $_COOKIE['uwb_uro_profile'] ) || $_COOKIE['uwb_uro_profile'] !== '1' ) {
            return;
        }

        $profile_data = [
            'plugins' => []
        ];

        $times = $GLOBALS['_uro_plugin_times'] ?? [];
        $hook_details = $GLOBALS['_uro_plugin_hook_details'] ?? [];

        foreach ( $times as $plugin => $total_time ) {
            $hooks = $hook_details[ $plugin ] ?? [];
            arsort( $hooks );

            $filtered_hooks = [];
            foreach ( $hooks as $h => $t ) {
                if ( $t >= 0.05 ) {
                    $filtered_hooks[ $h ] = $t;
                }
            }

            $profile_data['plugins'][ $plugin ] = [
                'total_time' => $total_time,
                'hooks'      => $filtered_hooks,
            ];
        }

        echo "\n<!-- UWB Profiler Data -->\n";
        echo "<script id='uwb-profiler-data-json' type='application/json'>";
        echo json_encode( $profile_data );
        echo "</script>\n";
    }

    /**
     * Determines which active plugin file path owns the given file path.
     */
    public static function get_plugin_from_filepath( ?string $file ): ?string {
        if ( ! $file ) {
            return null;
        }
        $file = wp_normalize_path( $file );
        $plugins_dir = wp_normalize_path( WP_PLUGIN_DIR );

        if ( strpos( $file, $plugins_dir ) !== false ) {
            $relative = str_replace( $plugins_dir . '/', '', $file );
            $sub = explode( '/', $relative );
            $dir = $sub[0];
            
            static $active_plugins_cache = null;
            if ( $active_plugins_cache === null ) {
                global $wpdb;
                $active_plugins_serialized = $wpdb->get_var( "SELECT option_value FROM $wpdb->options WHERE option_name = 'active_plugins'" );
                $active_plugins_cache = maybe_unserialize( $active_plugins_serialized );
                if ( ! is_array( $active_plugins_cache ) ) {
                    $active_plugins_cache = [];
                }
            }

            foreach ( $active_plugins_cache as $ap ) {
                if ( strpos( $ap, $dir . '/' ) === 0 || $ap === $dir ) {
                    return $ap;
                }
            }
        }
        return null;
    }
}

/**
 * Invokable callback wrapper to measure hook callback execution times safely.
 */
class UWB_Callback_Wrapper {
    public $original_func;
    public $hook_name;

    public function __construct( $original_func, $hook_name ) {
        $this->original_func = $original_func;
        $this->hook_name     = $hook_name;
    }

    public function __invoke( ...$args ) {
        $start = microtime( true );
        $res = call_user_func_array( $this->original_func, $args );
        $duration = microtime( true ) - $start;
        Analyzer::record_callback_time_with_hook( $this->original_func, $this->hook_name, $duration );
        return $res;
    }
}


