<?php
namespace Ultimate_WP_Booster\Engine\RuntimeOptimizer\Compiler;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

/**
 * TrieBuilder — Builds a compressed URL Trie for fast O(k) path matching.
 *
 * The Trie maps URL segments to arrays of rule IDs.
 * Wildcards (*) match any single path segment.
 *
 * Example Trie structure:
 * [
 *   'shop' => [
 *     '_rules' => [0],            // /shop/ matches rule 0
 *     'cart'   => ['_rules' => [2]],
 *     '*'      => ['_rules' => [1]], // /shop/anything
 *   ],
 *   'blog' => ['_rules' => [3]],
 *   '*'    => ['_rules' => [4]], // any root-level segment
 * ]
 */
class TrieBuilder {

    /**
     * Build a Trie from a list of (url_pattern, rule_id) pairs.
     *
     * @param array<array{pattern: string, rule_id: int}> $url_rules
     * @return array  Trie array suitable for compiled.php
     */
    public function build( array $url_rules ): array {
        $trie = [];

        foreach ( $url_rules as $entry ) {
            $pattern = $entry['pattern'];
            $rule_id = $entry['rule_id'];
            $this->insert( $trie, $pattern, $rule_id );
        }

        // Compress the trie (merge single-child nodes where possible)
        $trie = $this->compress( $trie );

        return $trie;
    }

    /**
     * Insert a URL pattern into the trie.
     *
     * Supports:
     * - Exact: /shop/cart/
     * - Wildcard segment: /shop/*
     * - Root wildcard: /*
     */
    private function insert( array &$node, string $pattern, int $rule_id ): void {
        // Normalise: strip query string, trim slashes
        $path     = strtolower( parse_url( $pattern, PHP_URL_PATH ) ?? $pattern );
        $path     = trim( $path, '/' );
        $segments = $path !== '' ? explode( '/', $path ) : [];

        if ( empty( $segments ) ) {
            // Root URL "/"
            $node['_root_rules'][] = $rule_id;
            return;
        }

        $current = &$node;
        foreach ( $segments as $idx => $seg ) {
            $is_last = ( $idx === count( $segments ) - 1 );
            $key     = ( $seg === '*' ) ? '*' : $seg;

            if ( ! isset( $current[ $key ] ) ) {
                $current[ $key ] = [];
            }
            $current = &$current[ $key ];

            if ( $is_last ) {
                $current['_rules'][] = $rule_id;
            }
        }
    }

    /**
     * Compress trie by removing redundant single-child nodes.
     * (Radix/Patricia trie optimisation)
     */
    private function compress( array $node ): array {
        $result = [];

        foreach ( $node as $key => $child ) {
            if ( strpos( $key, '_' ) === 0 ) {
                // Metadata keys (_rules, _root_rules) — keep as-is
                $result[ $key ] = array_unique( (array) $child );
                continue;
            }

            // Recurse
            $compressed_child = $this->compress( $child );
            $result[ $key ]   = $compressed_child;
        }

        return $result;
    }

    /**
     * Walk the trie against a request path and collect matching rule IDs.
     * Useful for testing/debugging.
     *
     * @param array  $trie
     * @param string $request_path  e.g. "/shop/product-name/"
     * @return int[]  matched rule IDs
     */
    public function match( array $trie, string $request_path ): array {
        $path     = strtolower( trim( parse_url( $request_path, PHP_URL_PATH ) ?? $request_path, '/' ) );
        $segments = $path !== '' ? explode( '/', $path ) : [];
        $matched  = [];

        // Root rules always apply
        if ( isset( $trie['_root_rules'] ) ) {
            $matched = array_merge( $matched, $trie['_root_rules'] );
        }

        $node = $trie;
        foreach ( $segments as $seg ) {
            $seg = strtolower( $seg );

            // Wildcard at this level matches any segment
            if ( isset( $node['*']['_rules'] ) ) {
                $matched = array_merge( $matched, $node['*']['_rules'] );
            }

            // Exact match
            if ( isset( $node[ $seg ] ) ) {
                $node = $node[ $seg ];
                if ( isset( $node['_rules'] ) ) {
                    $matched = array_merge( $matched, $node['_rules'] );
                }
            } elseif ( isset( $node['*'] ) ) {
                // Wildcard fallback
                $node = $node['*'];
                if ( isset( $node['_rules'] ) ) {
                    $matched = array_merge( $matched, $node['_rules'] );
                }
            } else {
                break;
            }
        }

        return array_values( array_unique( $matched ) );
    }

    /**
     * Convert post type or taxonomy permalink patterns to URL patterns.
     *
     * Uses WordPress permalink structure to derive URL patterns at compile time.
     *
     * @param string $type   'post_type' or 'taxonomy'
     * @param string $name   e.g. 'product' or 'product_cat'
     * @return string[]  URL patterns  e.g. ['/product/*']
     */
    public static function derive_url_patterns( string $type, string $name ): array {
        $patterns = [];

        if ( $type === 'post_type' ) {
            $post_type_obj = get_post_type_object( $name );
            if ( $post_type_obj ) {
                $slug = $post_type_obj->rewrite['slug'] ?? $name;
                $patterns[] = "/{$slug}/";
                $patterns[] = "/{$slug}/*";
            }
        } elseif ( $type === 'taxonomy' ) {
            $tax_obj = get_taxonomy( $name );
            if ( $tax_obj ) {
                $slug = $tax_obj->rewrite['slug'] ?? $name;
                $patterns[] = "/{$slug}/";
                $patterns[] = "/{$slug}/*";
            }
        }

        return $patterns;
    }
}
