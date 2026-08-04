<?php
namespace Ultimate_WP_Booster\Engine\RuntimeOptimizer\Compiler;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

use Ultimate_WP_Booster\Engine\RuntimeOptimizer\Scanner\PluginScanner;
use Ultimate_WP_Booster\Engine\RuntimeOptimizer\Scanner\DependencyScanner;

/**
 * Compiler — Orchestrates the full compile pipeline.
 *
 * Pipeline:
 *   Raw Rules (JSON option)
 *     → RuleParser        (parse & validate)
 *     → DependencyScanner (validate deny lists)
 *     → Dead Rule Elim    (remove disabled / empty rules)
 *     → Duplicate Merge   (merge rules with same plugin set + action)
 *     → TrieBuilder       (build URL trie from all URL / PostType / Taxonomy / WooCommerce conditions)
 *     → PHP Generator     (emit compiled.php with version + checksum)
 */
class Compiler {

    private const CACHE_DIR      = '/cache/uro/';
    private const COMPILED_FILE  = 'compiled.php';
    private const METADATA_FILE  = 'metadata.php';

    /** @var string  Absolute path to cache directory */
    private string $cache_dir;

    /** @var RuleParser */
    private RuleParser $parser;

    /** @var TrieBuilder */
    private TrieBuilder $trie_builder;

    /** @var PluginScanner */
    private PluginScanner $plugin_scanner;

    /** @var DependencyScanner */
    private DependencyScanner $dep_scanner;

    public function __construct() {
        $this->cache_dir      = WP_CONTENT_DIR . self::CACHE_DIR;
        $this->parser         = new RuleParser();
        $this->trie_builder   = new TrieBuilder();
        $this->plugin_scanner = new PluginScanner();
        $this->dep_scanner    = new DependencyScanner();
    }

    /**
     * Run the full compile pipeline.
     *
     * @return array{success: bool, message: string, errors: array}
     */
    public function compile(): array {
        // 1. Load raw rules from option
        $raw_rules = get_option( 'uwb_uro_rules', '[]' );

        // 2. Parse & validate
        $parse_result = $this->parser->parse( $raw_rules );
        if ( ! empty( $parse_result['errors'] ) ) {
            return [
                'success' => false,
                'message' => 'Rule parse errors found.',
                'errors'  => $parse_result['errors'],
            ];
        }
        $rules = $parse_result['rules'];

        // 3. Filter to enabled rules only (Dead Rule Elimination — disabled)
        $rules = array_values( array_filter( $rules, static fn( $r ) => $r['enabled'] ) );

        // 4. Dead Rule Elimination — constant folding (e.g. guest AND administrator is impossible)
        $rules = $this->eliminate_impossible_rules( $rules );

        // 5. Sort by priority
        $rules = $this->parser->sort_by_priority( $rules );

        // 6. Validate dependencies
        $active_plugins = get_option( 'active_plugins', [] );
        foreach ( $rules as $rule ) {
            if ( $rule['action'] === 'deny' ) {
                $dep_errors = $this->dep_scanner->validate_deny_list( $rule['plugins'], $active_plugins );
                if ( ! empty( $dep_errors ) ) {
                    return [
                        'success' => false,
                        'message' => 'Dependency validation failed.',
                        'errors'  => $dep_errors,
                    ];
                }
            }
        }

        // 7. Expand PostType / Taxonomy / WooCommerce conditions → URL patterns
        $rules = $this->expand_to_url_patterns( $rules );

        // 8. Merge duplicate plugin sets (Bitmask Reuse optimisation)
        [$rules, $mask_table] = $this->build_mask_table( $rules, $active_plugins );

        // 9. Build URL Trie from rules that have URL conditions
        $trie = $this->build_url_trie( $rules );

        // 10. Separate global rules (no URL condition)
        $global_rules = $this->extract_global_rules( $rules );

        // 11. Generate & write compiled.php
        $write_result = $this->write_compiled( $rules, $trie, $global_rules, $mask_table, $active_plugins );

        return $write_result;
    }

    /**
     * Eliminate logically impossible rules (e.g. role = guest AND administrator).
     */
    private function eliminate_impossible_rules( array $rules ): array {
        return array_values( array_filter( $rules, static function ( $rule ) {
            $roles = $rule['conditions']['roles'] ?? [];
            // guest AND administrator cannot both be true
            if ( in_array( 'guest', $roles, true ) && in_array( 'administrator', $roles, true ) ) {
                return false;
            }
            // Logged-in AND guest
            if ( in_array( 'guest', $roles, true ) && in_array( 'logged_in', $roles, true ) ) {
                return false;
            }
            return true;
        } ) );
    }

    /**
     * Expand post_type / taxonomy / woocommerce conditions into URL patterns
     * using WordPress permalink structure.
     */
    private function expand_to_url_patterns( array $rules ): array {
        foreach ( $rules as &$rule ) {
            $extra_urls = [];

            // PostType → URL
            foreach ( $rule['conditions']['post_type'] ?? [] as $pt ) {
                $patterns   = TrieBuilder::derive_url_patterns( 'post_type', $pt );
                $extra_urls = array_merge( $extra_urls, $patterns );
            }

            // Taxonomy → URL
            foreach ( $rule['conditions']['taxonomy'] ?? [] as $tax ) {
                $patterns   = TrieBuilder::derive_url_patterns( 'taxonomy', $tax );
                $extra_urls = array_merge( $extra_urls, $patterns );
            }

            // WooCommerce → URL
            $woo_url_map = [
                'shop'     => '/shop/',
                'cart'     => '/cart/',
                'checkout' => '/checkout/',
                'account'  => '/my-account/',
                'product'  => '/product/*',
            ];
            foreach ( $rule['conditions']['woocommerce'] ?? [] as $ctx ) {
                if ( $ctx === 'any' ) {
                    // Add all WooCommerce URLs
                    $extra_urls = array_merge( $extra_urls, array_values( $woo_url_map ) );
                } elseif ( isset( $woo_url_map[ $ctx ] ) ) {
                    $extra_urls[] = $woo_url_map[ $ctx ];
                }
            }

            // Check if WooCommerce has custom page slugs via options
            if ( function_exists( 'wc_get_page_id' ) ) {
                $woo_ctx_keys = $rule['conditions']['woocommerce'] ?? [];
                if ( ! empty( $woo_ctx_keys ) ) {
                    $wc_pages = [
                        'shop'     => wc_get_page_id( 'shop' ),
                        'cart'     => wc_get_page_id( 'cart' ),
                        'checkout' => wc_get_page_id( 'checkout' ),
                        'account'  => wc_get_page_id( 'myaccount' ),
                    ];
                    foreach ( $woo_ctx_keys as $ctx ) {
                        if ( isset( $wc_pages[ $ctx ] ) && $wc_pages[ $ctx ] > 0 ) {
                            $slug = get_post_field( 'post_name', $wc_pages[ $ctx ] );
                            if ( $slug ) {
                                $extra_urls[] = "/{$slug}/";
                            }
                        }
                    }
                }
            }

            // Merge expanded URLs into the rule's URL condition list
            if ( ! empty( $extra_urls ) ) {
                $rule['conditions']['url'] = array_values( array_unique(
                    array_merge( $rule['conditions']['url'] ?? [], $extra_urls )
                ) );
            }
        }
        unset( $rule );

        return $rules;
    }

    /**
     * Build a mask table deduplicating identical plugin sets.
     *
     * Returns: [enriched_rules_with_mask_id, mask_table]
     * mask_table: [ mask_id => ['action' => 'deny', 'plugin_files' => [...]] ]
     */
    private function build_mask_table( array $rules, array $active_plugins ): array {
        $mask_table    = [];
        $signature_map = [];

        foreach ( $rules as &$rule ) {
            $sig = $rule['action'] . '|' . implode( ',', $rule['plugins'] );
            if ( ! isset( $signature_map[ $sig ] ) ) {
                $mask_id                = count( $mask_table );
                $mask_table[ $mask_id ] = [
                    'action'       => $rule['action'],
                    'plugin_files' => $rule['plugins'],
                ];
                $signature_map[ $sig ]  = $mask_id;
            }
            $rule['mask_id'] = $signature_map[ $sig ];
        }
        unset( $rule );

        return [ $rules, $mask_table ];
    }

    /**
     * Build the URL Trie from rules that have URL conditions.
     */
    private function build_url_trie( array $rules ): array {
        $url_entries = [];
        foreach ( $rules as $rule_id => $rule ) {
            foreach ( $rule['conditions']['url'] ?? [] as $pattern ) {
                $url_entries[] = [ 'pattern' => $pattern, 'rule_id' => $rule_id ];
            }
        }
        return $this->trie_builder->build( $url_entries );
    }

    /**
     * Extract rules that have NO URL condition (global rules).
     * These must be checked on every request regardless of URL.
     */
    private function extract_global_rules( array $rules ): array {
        $global = [];
        foreach ( $rules as $rule_id => $rule ) {
            $has_url = ! empty( $rule['conditions']['url'] );
            if ( ! $has_url ) {
                $global[ $rule_id ] = $this->build_runtime_rule( $rule );
            }
        }
        return $global;
    }

    /**
     * Strip compile-time data, keep only what Runtime needs.
     */
    private function build_runtime_rule( array $rule ): array {
        $queryParams = [];
        foreach ( $rule['conditions']['url'] ?? [] as $pattern ) {
            $path  = strtolower( trim( parse_url( $pattern, PHP_URL_PATH ) ?? $pattern, '/' ) );
            $query = parse_url( $pattern, PHP_URL_QUERY );
            if ( $query ) {
                parse_str( strtolower( $query ), $args );
                if ( ! empty( $args ) ) {
                    $queryParams[ $path ][] = $args;
                }
            }
        }

        return [
            'action'       => $rule['action'],
            'mask_id'      => $rule['mask_id'],
            'roles'        => $rule['conditions']['roles'] ?? [],
            'devices'      => $rule['conditions']['devices'] ?? [],
            'woocommerce'  => $rule['conditions']['woocommerce'] ?? [],
            'is_ajax'      => $rule['conditions']['is_ajax'],
            'is_rest'      => $rule['conditions']['is_rest'],
            'callback'     => $rule['conditions']['callback'],
            'plugin_files' => $rule['plugins'],
            'query_params' => $queryParams,
        ];
    }

    /**
     * Build slim runtime rules array for every rule (URL-based and global).
     */
    private function build_all_runtime_rules( array $rules ): array {
        $rt = [];
        foreach ( $rules as $rule_id => $rule ) {
            $rt[ $rule_id ] = $this->build_runtime_rule( $rule );
        }
        return $rt;
    }

    /**
     * Write compiled data directly inside the MU plugin template.
     */
    private function write_compiled(
        array $rules,
        array $trie,
        array $global_rules,
        array $mask_table,
        array $active_plugins
    ): array {
        $mu_plugin_file = WP_CONTENT_DIR . '/mu-plugins/uro-runtime.php';

        $rt_rules  = $this->build_all_runtime_rules( $rules );
        $timestamp = time();

        $compiled_data = [
            'version'      => 1,
            'timestamp'    => $timestamp,
            'url_trie'     => $trie,
            'rules'        => $rt_rules,
            'global_rules' => $global_rules,
            'masks'        => $mask_table,
        ];

        // Save metadata to DB option instead of file to prevent cache deletion issues
        $meta = [
            'uwb_version'  => UWB_VERSION,
            'compile_time' => $timestamp,
            'rule_count'   => count( $rules ),
            'active_count' => count( $active_plugins ),
        ];
        update_option( 'uwb_uro_metadata', $meta, false );

        // Check if MU plugin is actually installed
        $status = ( new \Ultimate_WP_Booster\Engine\RuntimeOptimizer\Runtime\RuntimeManager() )->mu_plugin_status();
        if ( ! $status['installed'] ) {
            return [
                'success' => true,
                'message' => 'Rules saved. Enable Runtime to apply them.',
                'errors'  => [],
            ];
        }

        // Read source template
        $template_path = UWB_PLUGIN_DIR . 'templates/uro-runtime.php';
        if ( ! file_exists( $template_path ) ) {
            return [
                'success' => false,
                'message' => 'Runtime template not found.',
                'errors'  => [ 'Template missing: ' . $template_path ],
            ];
        }

        $template = file_get_contents( $template_path );
        $export   = var_export( $compiled_data, true );
        
        // Replace {{COMPILED_DATA}} placeholder
        $placeholder = '// {{COMPILED_DATA}}';
        $replacement = 'return ' . $export . ';';
        $compiled_content = str_replace( $placeholder, $replacement, $template );

        // Write directly to mu-plugins/uro-runtime.php
        $tmp_path = $mu_plugin_file . '.tmp';
        if ( @file_put_contents( $tmp_path, $compiled_content, LOCK_EX ) === false ) {
            return [
                'success' => false,
                'message' => 'Cannot write compiled data to MU plugin.',
                'errors'  => [ 'File write failed: ' . $tmp_path ],
            ];
        }

        // Verify the file was written and is not empty
        if ( ! file_exists( $tmp_path ) || filesize( $tmp_path ) === 0 ) {
            return [
                'success' => false,
                'message' => 'Failed to write temporary MU plugin file.',
                'errors'  => [ 'File missing or empty: ' . $tmp_path ],
            ];
        }
        @rename( $tmp_path, $mu_plugin_file );

        // Clean up legacy files if they exist
        @unlink( $this->cache_dir . self::COMPILED_FILE );
        @unlink( $this->cache_dir . self::METADATA_FILE );

        return [
            'success' => true,
            'message' => 'Compiled & written directly to MU plugin. Rules: ' . count( $rules ),
            'errors'  => [],
        ];
    }

    /**
     * Delete metadata and remove inline data.
     */
    public function clear(): void {
        delete_option( 'uwb_uro_metadata' );
        @unlink( $this->cache_dir . self::COMPILED_FILE );
        @unlink( $this->cache_dir . self::METADATA_FILE );
    }

    /**
     * Check if a valid compiled metadata exists.
     */
    public function is_compiled(): bool {
        $meta = get_option( 'uwb_uro_metadata' );
        return is_array( $meta ) && ! empty( $meta['compile_time'] );
    }

    /**
     * Get metadata from option.
     *
     * @return array|null
     */
    public function get_metadata(): ?array {
        $meta = get_option( 'uwb_uro_metadata' );
        return is_array( $meta ) ? $meta : null;
    }
}
