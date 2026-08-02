<?php
namespace Ultimate_WP_Booster\Engine\Optimization;

use Ultimate_WP_Booster\EventManagement\Subscriber_Interface;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

/**
 * General Optimization Subscriber
 * Handles all general WordPress performance improvements, cleaning up headers,
 * disabling unused features, and optimizing server requests.
 */
class GeneralOptimizationSubscriber implements Subscriber_Interface {

    public static function get_subscribed_events() {
        return array(
            'init' => array( 'run_general_optimizations', 10 ),
        );
    }

    public function run_general_optimizations() {
        // 1. Disable Emojis
        if ( (int) get_option( 'uwb_general_disable_emojis', 0 ) === 1 ) {
            remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
            remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
            remove_action( 'wp_print_styles', 'print_emoji_styles' );
            remove_action( 'admin_print_styles', 'print_emoji_styles' );
            remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
            remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
            remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
            add_filter( 'tiny_mce_plugins', function( $plugins ) {
                return is_array( $plugins ) ? array_diff( $plugins, array( 'wpemoji' ) ) : array();
            } );
            add_filter( 'wp_resource_hints', function( $urls, $relation_type ) {
                if ( 'dns-prefetch' === $relation_type ) {
                    $emoji_svg_url = apply_filters( 'emoji_svg_url', 'https://s.w.org/images/core/emoji/14.0.0/svg/' );
                    $urls = array_diff( $urls, array( $emoji_svg_url ) );
                }
                return $urls;
            }, 10, 2 );
        }

        // 2. Disable Dashicons
        if ( (int) get_option( 'uwb_general_disable_dashicons', 0 ) === 1 ) {
            add_action( 'wp_enqueue_scripts', function() {
                if ( ! is_user_logged_in() ) {
                    wp_dequeue_style( 'dashicons' );
                    wp_deregister_style( 'dashicons' );
                }
            }, 100 );
        }

        // 3. Disable Embeds
        if ( (int) get_option( 'uwb_general_disable_embeds', 0 ) === 1 ) {
            global $wp;
            if ( isset( $wp->public_query_vars ) && is_array( $wp->public_query_vars ) ) {
                $wp->public_query_vars = array_diff( $wp->public_query_vars, array( 'embed' ) );
            }
            add_filter( 'embed_oembed_discover', '__return_false' );
            remove_filter( 'oembed_dataparse', 'wp_filter_oembed_result', 10 );
            remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
            remove_action( 'wp_head', 'wp_oembed_add_host_js' );
            add_filter( 'tiny_mce_plugins', function( $plugins ) {
                return is_array( $plugins ) ? array_diff( $plugins, array( 'wpembed' ) ) : array();
            } );
            add_filter( 'rewrite_rules_array', function( $rules ) {
                if ( is_array( $rules ) ) {
                    foreach ( array_keys( $rules ) as $rule ) {
                        if ( false !== strpos( $rule, 'embed=' ) ) {
                            unset( $rules[ $rule ] );
                        }
                    }
                }
                return $rules;
            } );
        }

        // 4. Disable XML-RPC
        if ( (int) get_option( 'uwb_general_disable_xmlrpc', 0 ) === 1 ) {
            add_filter( 'xmlrpc_enabled', '__return_false' );
            add_filter( 'xmlrpc_methods', '__return_empty_array' );
            remove_action( 'wp_head', 'rsd_link' );
        }

        // 5. Remove jQuery Migrate
        if ( (int) get_option( 'uwb_general_remove_jquery_migrate', 0 ) === 1 ) {
            add_action( 'wp_default_scripts', function( $scripts ) {
                if ( ! is_admin() && isset( $scripts->registered['jquery'] ) ) {
                    $jquery_dependencies = $scripts->registered['jquery']->deps;
                    $scripts->registered['jquery']->deps = array_diff( $jquery_dependencies, array( 'jquery-migrate' ) );
                }
            } );
        }

        // 6. Hide WP Version
        if ( (int) get_option( 'uwb_general_hide_wp_version', 0 ) === 1 ) {
            add_filter( 'the_generator', '__return_empty_string' );
            $remove_ver = function( $src ) {
                if ( strpos( $src, 'ver=' . get_bloginfo( 'version' ) ) ) {
                    $src = remove_query_arg( 'ver', $src );
                }
                return $src;
            };
            add_filter( 'script_loader_src', $remove_ver );
            add_filter( 'style_loader_src', $remove_ver );
        }

        // 7. Remove wlwmanifest Link
        if ( (int) get_option( 'uwb_general_remove_wlwmanifest', 0 ) === 1 ) {
            remove_action( 'wp_head', 'wlwmanifest_link' );
        }

        // 8. Remove RSD Link
        if ( (int) get_option( 'uwb_general_remove_rsd', 0 ) === 1 ) {
            remove_action( 'wp_head', 'rsd_link' );
        }

        // 9. Remove Shortlink
        if ( (int) get_option( 'uwb_general_remove_shortlink', 0 ) === 1 ) {
            remove_action( 'wp_head', 'wp_shortlink_wp_head', 10 );
            remove_action( 'template_redirect', 'wp_shortlink_header', 11 );
        }

        // 10. Disable RSS Feeds
        if ( (int) get_option( 'uwb_general_disable_rss_feeds', 0 ) === 1 ) {
            $feed_callback = function() {
                wp_die( __( 'Feeds have been disabled.' ) );
            };
            add_action( 'do_feed', $feed_callback, 1 );
            add_action( 'do_feed_rdf', $feed_callback, 1 );
            add_action( 'do_feed_rss', $feed_callback, 1 );
            add_action( 'do_feed_rss2', $feed_callback, 1 );
            add_action( 'do_feed_atom', $feed_callback, 1 );
            add_action( 'do_feed_rss2_comments', $feed_callback, 1 );
            add_action( 'do_feed_atom_comments', $feed_callback, 1 );
        }

        // 11. Remove RSS Feed Links
        if ( (int) get_option( 'uwb_general_remove_rss_feed_links', 0 ) === 1 ) {
            remove_action( 'wp_head', 'feed_links', 2 );
            remove_action( 'wp_head', 'feed_links_extra', 3 );
        }

        // 12. Disable Self Pingbacks
        if ( (int) get_option( 'uwb_general_disable_self_pingbacks', 0 ) === 1 ) {
            add_action( 'pre_ping', function( &$links ) {
                $home = get_option( 'home' );
                if ( is_array( $links ) ) {
                    foreach ( $links as $l => $link ) {
                        if ( 0 === strpos( $link, $home ) ) {
                            unset( $links[ $l ] );
                        }
                    }
                }
            } );
        }

        // 13. Disable REST API
        $rest_mode = get_option( 'uwb_general_disable_rest_api', 'default' );
        if ( $rest_mode !== 'default' ) {
            add_filter( 'rest_authentication_errors', function( $errors ) use ( $rest_mode ) {
                if ( ! empty( $errors ) ) {
                    return $errors;
                }
                if ( $rest_mode === 'disable_all' ) {
                    return new \WP_Error( 'rest_disabled', __( 'The REST API is disabled.' ), array( 'status' => 403 ) );
                }
                if ( $rest_mode === 'disable_non_admin' && ! current_user_can( 'manage_options' ) ) {
                    return new \WP_Error( 'rest_disabled', __( 'The REST API is disabled for non-admins.' ), array( 'status' => 403 ) );
                }
                return $errors;
            } );
        }

        // 14. Remove REST API Links
        if ( (int) get_option( 'uwb_general_remove_rest_api_links', 0 ) === 1 ) {
            remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );
            remove_action( 'template_redirect', 'rest_output_link_header', 11 );
        }

        // 15. Disable Google Maps
        if ( (int) get_option( 'uwb_general_disable_google_maps', 0 ) === 1 ) {
            add_action( 'wp_enqueue_scripts', function() {
                wp_dequeue_script( 'google-maps' );
                wp_dequeue_script( 'google-maps-api' );
            }, 999 );
        }

        // 16. Disable Password Strength Meter
        if ( (int) get_option( 'uwb_general_disable_password_strength_meter', 0 ) === 1 ) {
            add_action( 'wp_print_scripts', function() {
                wp_dequeue_script( 'wc-password-strength-meter' );
            }, 100 );
        }

        // 17. Disable Comments
        if ( (int) get_option( 'uwb_general_disable_comments', 0 ) === 1 ) {
            add_filter( 'comments_open', '__return_false', 20, 2 );
            add_filter( 'pings_open', '__return_false', 20, 2 );
            add_filter( 'comments_array', '__return_empty_array', 10, 2 );
        }

        // 18. Remove Comment URLs
        if ( (int) get_option( 'uwb_general_remove_comment_urls', 0 ) === 1 ) {
            add_filter( 'comment_form_default_fields', function( $fields ) {
                if ( isset( $fields['url'] ) ) {
                    unset( $fields['url'] );
                }
                return $fields;
            } );
        }

        // 19. Add Blank Favicon
        if ( (int) get_option( 'uwb_general_add_blank_favicon', 0 ) === 1 ) {
            add_action( 'wp_head', function() {
                echo '<link rel="icon" href="data:;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==">';
            }, 1 );
        }

        // 20. Remove Global Styles
        if ( (int) get_option( 'uwb_general_remove_global_styles', 0 ) === 1 ) {
            add_action( 'wp_enqueue_scripts', function() {
                wp_dequeue_style( 'global-styles' );
            }, 100 );
        }

        // 21. Disable Heartbeat
        $hb_mode = get_option( 'uwb_general_disable_heartbeat', 'default' );
        if ( $hb_mode === 'disable_all' ) {
            add_action( 'init', function() {
                wp_deregister_script( 'heartbeat' );
            }, 1 );
        } elseif ( $hb_mode === 'only_edit' ) {
            add_action( 'init', function() {
                global $pagenow;
                if ( 'post.php' !== $pagenow && 'post-new.php' !== $pagenow ) {
                    wp_deregister_script( 'heartbeat' );
                }
            }, 1 );
        }

        // 22. Heartbeat Frequency
        $hb_freq = get_option( 'uwb_general_heartbeat_frequency', 'default' );
        if ( $hb_freq !== 'default' ) {
            add_filter( 'heartbeat_settings', function( $settings ) use ( $hb_freq ) {
                $settings['interval'] = intval( $hb_freq );
                return $settings;
            } );
        }

        // 23. Limit Post Revisions
        $revisions_limit = get_option( 'uwb_general_limit_post_revisions', 'default' );
        if ( $revisions_limit !== 'default' ) {
            add_filter( 'wp_revisions_to_keep', function( $num, $post ) use ( $revisions_limit ) {
                if ( $revisions_limit === 'disable' ) {
                    return 0;
                }
                return intval( $revisions_limit );
            }, 10, 2 );
        }

        // 24. Autosave Interval
        $autosave = get_option( 'uwb_general_autosave_interval', 'default' );
        if ( $autosave !== 'default' ) {
            add_filter( 'autosave_interval', function() use ( $autosave ) {
                return intval( $autosave );
            } );
        }

    }
}
