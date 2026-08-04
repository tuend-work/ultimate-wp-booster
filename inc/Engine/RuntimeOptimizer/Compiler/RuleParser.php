<?php
namespace Ultimate_WP_Booster\Engine\RuntimeOptimizer\Compiler;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

/**
 * RuleParser — Parses and validates raw URO rule arrays.
 *
 * Rule format (stored as JSON in option `uwb_uro_rules`):
 * [
 *   {
 *     "id":         "uuid-string",
 *     "name":       "Human label",
 *     "enabled":    true,
 *     "priority":   10,
 *     "conditions": {
 *       "url":          ["/shop/*", "/blog/"],
 *       "post_type":    ["product", "post"],
 *       "taxonomy":     ["product_cat", "category"],
 *       "woocommerce":  ["shop", "cart", "checkout", "account", "product"],
 *       "user_role":    ["guest", "logged_in", "administrator"],
 *       "device":       ["desktop", "tablet", "mobile"],
 *       "is_ajax":      null,
 *       "is_rest":      null,
 *       "callback":     null
 *     },
 *     "action":  "deny",   // "allow" | "deny"
 *     "plugins": ["elementor/elementor.php", "contact-form-7/wp-contact-form-7.php"]
 *   }
 * ]
 */
class RuleParser {

    /** Valid condition keys */
    private const VALID_CONDITIONS = [
        'url', 'post_type', 'taxonomy', 'woocommerce',
        'user_role', 'device', 'is_ajax', 'is_rest', 'callback',
    ];

    /** Valid user roles */
    private const VALID_ROLES = [ 'any', 'guest', 'logged_in', 'administrator', 'editor', 'author', 'subscriber' ];

    /** Valid devices */
    private const VALID_DEVICES = [ 'any', 'desktop', 'tablet', 'mobile' ];

    /** Valid WooCommerce contexts */
    private const VALID_WOO_CTX = [ 'any', 'shop', 'cart', 'checkout', 'account', 'product' ];

    /** Valid rule actions */
    private const VALID_ACTIONS = [ 'allow', 'deny' ];

    /**
     * Parse raw rules from option storage.
     *
     * @param mixed $raw  JSON string or decoded array.
     * @return array{rules: array, errors: array}
     */
    public function parse( $raw ): array {
        if ( is_string( $raw ) ) {
            $decoded = json_decode( $raw, true );
            if ( json_last_error() !== JSON_ERROR_NONE ) {
                return [ 'rules' => [], 'errors' => [ 'JSON decode error: ' . json_last_error_msg() ] ];
            }
            $raw = $decoded;
        }

        if ( ! is_array( $raw ) ) {
            return [ 'rules' => [], 'errors' => [ 'Rules must be a JSON array.' ] ];
        }

        $rules  = [];
        $errors = [];

        foreach ( $raw as $idx => $raw_rule ) {
            $result = $this->parse_single( $raw_rule, $idx );
            if ( ! empty( $result['errors'] ) ) {
                $errors = array_merge( $errors, $result['errors'] );
            } else {
                $rules[] = $result['rule'];
            }
        }

        return [ 'rules' => $rules, 'errors' => $errors ];
    }

    /**
     * Parse and validate a single rule.
     *
     * @return array{rule: array|null, errors: array}
     */
    private function parse_single( mixed $raw, int $idx ): array {
        $errors = [];
        $prefix = "Rule #{$idx}";

        if ( ! is_array( $raw ) ) {
            return [ 'rule' => null, 'errors' => [ "{$prefix}: must be an object." ] ];
        }

        // Required fields
        $id      = $raw['id'] ?? wp_generate_uuid4();
        $name    = sanitize_text_field( $raw['name'] ?? "Rule #{$idx}" );
        $enabled = isset( $raw['enabled'] ) ? (bool) $raw['enabled'] : true;
        $priority = (int) ( $raw['priority'] ?? 10 );

        // Action
        $action = strtolower( trim( $raw['action'] ?? 'deny' ) );
        if ( ! in_array( $action, self::VALID_ACTIONS, true ) ) {
            $errors[] = "{$prefix} [{$name}]: invalid action '{$action}'. Must be 'allow' or 'deny'.";
            $action   = 'deny';
        }

        // Plugins
        $plugins = [];
        foreach ( (array) ( $raw['plugins'] ?? [] ) as $pf ) {
            $plugins[] = sanitize_text_field( $pf );
        }
        if ( empty( $plugins ) ) {
            $errors[] = "{$prefix} [{$name}]: 'plugins' list cannot be empty.";
        }

        // Conditions
        $conditions = $this->parse_conditions( $raw['conditions'] ?? [], $prefix . " [{$name}]", $errors );

        $rule = [
            'id'         => $id,
            'name'       => $name,
            'enabled'    => $enabled,
            'priority'   => $priority,
            'action'     => $action,
            'plugins'    => $plugins,
            'conditions' => $conditions,
        ];

        return [ 'rule' => empty( $errors ) ? $rule : null, 'errors' => $errors ];
    }

    /**
     * Parse conditions array.
     */
    private function parse_conditions( array $raw, string $prefix, array &$errors ): array {
        $cond = [];

        // URL patterns
        $cond['url'] = array_map( 'sanitize_text_field', (array) ( $raw['url'] ?? [] ) );

        // Post Types
        $cond['post_type'] = array_map( 'sanitize_key', (array) ( $raw['post_type'] ?? [] ) );

        // Taxonomies
        $cond['taxonomy'] = array_map( 'sanitize_key', (array) ( $raw['taxonomy'] ?? [] ) );

        // WooCommerce context
        $raw_woo = (array) ( $raw['woocommerce'] ?? [] );
        $cond['woocommerce'] = [];
        foreach ( $raw_woo as $ctx ) {
            $ctx = strtolower( trim( $ctx ) );
            if ( in_array( $ctx, self::VALID_WOO_CTX, true ) ) {
                $cond['woocommerce'][] = $ctx;
            } else {
                $errors[] = "{$prefix}: invalid woocommerce context '{$ctx}'.";
            }
        }

        // User roles
        $raw_roles = (array) ( $raw['user_role'] ?? [] );
        $cond['roles'] = [];
        foreach ( $raw_roles as $role ) {
            $role = strtolower( trim( $role ) );
            if ( in_array( $role, self::VALID_ROLES, true ) ) {
                $cond['roles'][] = $role;
            } else {
                // Allow custom role slugs (sanitized)
                $cond['roles'][] = sanitize_key( $role );
            }
        }

        // Devices
        $raw_devices = (array) ( $raw['device'] ?? [] );
        $cond['devices'] = [];
        foreach ( $raw_devices as $dev ) {
            $dev = strtolower( trim( $dev ) );
            if ( in_array( $dev, self::VALID_DEVICES, true ) ) {
                $cond['devices'][] = $dev;
            } else {
                $errors[] = "{$prefix}: invalid device '{$dev}'.";
            }
        }

        // is_ajax — null means "don't care"
        $cond['is_ajax'] = isset( $raw['is_ajax'] ) && $raw['is_ajax'] !== null
            ? (bool) $raw['is_ajax']
            : null;

        // is_rest
        $cond['is_rest'] = isset( $raw['is_rest'] ) && $raw['is_rest'] !== null
            ? (bool) $raw['is_rest']
            : null;

        // Callback — must be a valid PHP function name string
        $callback = $raw['callback'] ?? null;
        if ( $callback !== null ) {
            $callback = sanitize_text_field( $callback );
            if ( ! preg_match( '/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/', $callback ) ) {
                $errors[] = "{$prefix}: callback '{$callback}' is not a valid PHP function name.";
                $callback = null;
            }
        }
        $cond['callback'] = $callback;

        return $cond;
    }

    /**
     * Sort rules by priority (ascending = higher priority first).
     *
     * @param array $rules  Parsed rules.
     * @return array
     */
    public function sort_by_priority( array $rules ): array {
        usort( $rules, static fn( $a, $b ) => $a['priority'] <=> $b['priority'] );
        return $rules;
    }
}
