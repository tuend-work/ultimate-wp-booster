<?php
namespace Ultimate_WP_Booster\Engine\Admin;

use Ultimate_WP_Booster\EventManagement\Subscriber_Interface;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class AdminBarSubscriber implements Subscriber_Interface {

    public static function get_subscribed_events() {
        return array(
            'admin_bar_menu'                    => array( 'add_admin_bar_nodes', 999 ),
            'admin_post_uwb_purge_url'          => 'handle_purge_url',
            'admin_post_uwb_clear_cache_page'   => 'handle_clear_cache_page',
            'admin_post_uwb_flush_all_preload'  => 'handle_flush_all_preload',
            'admin_post_uwb_flush_object_cache' => 'handle_flush_object_cache',
            'admin_post_uwb_flush_opcache'         => 'handle_flush_opcache',
            'admin_post_uwb_clear_cdn_zone_cache'  => 'handle_clear_cdn_zone_cache',
            'admin_post_uwb_clear_s3_asset_cache'  => 'handle_clear_s3_asset_cache',
            'admin_post_uwb_clear_cdn_cache'       => 'handle_clear_s3_asset_cache',
            'wp_footer'                         => 'render_plugin_manager_modal',
            'admin_footer'                      => 'render_plugin_manager_modal',
        );
    }

    public function add_admin_bar_nodes( $wp_admin_bar ) {
        $can_manage = current_user_can( 'manage_options' );
        $can_edit_current_post = false;
        $current_post_id = 0;

        if ( ! is_admin() && is_singular() ) {
            $current_post_id = get_the_ID();
            if ( $current_post_id && current_user_can( 'edit_post', $current_post_id ) ) {
                $can_edit_current_post = true;
            }
        }

        if ( ! $can_manage && ! $can_edit_current_post ) {
            return;
        }

        // Add main node
        $wp_admin_bar->add_node( array(
            'id'    => 'uwb-admin-bar',
            'title' => 'WP Booster',
            'href'  => $can_manage ? admin_url( 'admin.php?page=ultimate-wp-booster' ) : null,
        ) );

        // Add sub-node: Purge This URL (only on frontend)
        if ( ! is_admin() ) {
            $current_url = ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            $clean_url = strtok( $current_url, '?' );
            
            $action_url = 'admin-post.php?action=uwb_purge_url&url=' . urlencode( $clean_url );
            if ( $current_post_id > 0 ) {
                $action_url .= '&post_id=' . $current_post_id;
            }
            $purge_url = wp_nonce_url( admin_url( $action_url ), 'uwb_purge_url_action' );
            
            $wp_admin_bar->add_node( array(
                'id'     => 'uwb-purge-url',
                'parent' => 'uwb-admin-bar',
                'title'  => 'Purge This URL',
                'href'   => $purge_url,
            ) );
        }

        // Add sub-node: Plugin Manager (opens quick panel modal)
        // Show on frontend and non-critical admin pages
        $is_critical_admin = false;
        if ( is_admin() ) {
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            if ( strpos( $uri, 'plugins.php' ) !== false 
                 || strpos( $uri, 'update.php' ) !== false 
                 || strpos( $uri, 'update-core.php' ) !== false 
                 || strpos( $uri, 'themes.php' ) !== false 
                 || strpos( $uri, 'customize.php' ) !== false
            ) {
                $is_critical_admin = true;
            }
        }

        if ( ! $is_critical_admin && $can_manage ) {
            $wp_admin_bar->add_node( array(
                'id'     => 'uwb-plugin-manager',
                'parent' => 'uwb-admin-bar',
                'title'  => '⚡ Plugin Manager',
                'href'   => '#',
                'meta'   => array(
                    'onclick' => 'jQuery("#uwb-quick-pm-modal").css("display", "flex"); return false;',
                )
            ) );
        }

        if ( ! $can_manage ) {
            return;
        }

        // Add sub-node: Clear Cache Page (only clear, no preload)
        $clear_cache_page_url = wp_nonce_url( admin_url( 'admin-post.php?action=uwb_clear_cache_page' ), 'uwb_clear_cache_page_action' );
        $wp_admin_bar->add_node( array(
            'id'     => 'uwb-clear-cache-page',
            'parent' => 'uwb-admin-bar',
            'title'  => 'Clear Cache Page',
            'href'   => $clear_cache_page_url,
        ) );

        // Add sub-node: Flush OPCache
        $flush_op_url = wp_nonce_url( admin_url( 'admin-post.php?action=uwb_flush_opcache' ), 'uwb_flush_opcache_action' );
        $wp_admin_bar->add_node( array(
            'id'     => 'uwb-flush-opcache',
            'parent' => 'uwb-admin-bar',
            'title'  => 'Clear OPCache',
            'href'   => $flush_op_url,
        ) );

        if ( wp_using_ext_object_cache() ) {
            // Add sub-node: Flush Object Cache
            $flush_oc_url = wp_nonce_url( admin_url( 'admin-post.php?action=uwb_flush_object_cache' ), 'uwb_flush_object_cache_action' );
            $wp_admin_bar->add_node( array(
                'id'     => 'uwb-flush-object-cache',
                'parent' => 'uwb-admin-bar',
                'title'  => 'Clear Object Cache',
                'href'   => $flush_oc_url,
            ) );

            // Add Global Cache Statistics to Admin Bar
            global $wp_object_cache;
            $hits = 0; $misses = 0;
            if ( isset( $wp_object_cache->cache_hits ) ) $hits = intval( $wp_object_cache->cache_hits );
            if ( isset( $wp_object_cache->cache_misses ) ) $misses = intval( $wp_object_cache->cache_misses );
            $total_req = $hits + $misses;
            $hit_ratio = $total_req > 0 ? round( ( $hits / $total_req ) * 100, 1 ) : 0;

            $wp_admin_bar->add_node( array(
                'id'     => 'uwb-oc-stats',
                'parent' => 'uwb-admin-bar',
                'title'  => sprintf( 'Object Cache Stats (Hit Ratio: %s%%)', $hit_ratio ),
                'href'   => admin_url( 'admin.php?page=ultimate-wp-booster' ),
            ) );

            $wp_admin_bar->add_node( array(
                'id'     => 'uwb-oc-hits',
                'parent' => 'uwb-oc-stats',
                'title'  => sprintf( 'Hits: %s', number_format( $hits ) ),
                'href'   => '#',
            ) );

            $wp_admin_bar->add_node( array(
                'id'     => 'uwb-oc-misses',
                'parent' => 'uwb-oc-stats',
                'title'  => sprintf( 'Misses: %s', number_format( $misses ) ),
                'href'   => '#',
            ) );

            $wp_admin_bar->add_node( array(
                'id'     => 'uwb-oc-total',
                'parent' => 'uwb-oc-stats',
                'title'  => sprintf( 'Total Requests: %s', number_format( $total_req ) ),
                'href'   => '#',
            ) );
        }
        // Add sub-node: Clear CDN Zone Cache (Cloudflare)
        $clear_zone_url = wp_nonce_url( admin_url( 'admin-post.php?action=uwb_clear_cdn_zone_cache' ), 'uwb_clear_cdn_zone_cache_action' );
        $wp_admin_bar->add_node( array(
            'id'     => 'uwb-clear-cdn-zone-cache',
            'parent' => 'uwb-admin-bar',
            'title'  => 'Clear Cloudflare Cache',
            'href'   => $clear_zone_url,
        ) );

        // Add sub-node: Clear S3 Asset Cache (Cloud Storage)
        $clear_s3_url = wp_nonce_url( admin_url( 'admin-post.php?action=uwb_clear_s3_asset_cache' ), 'uwb_clear_s3_asset_cache_action' );
        $wp_admin_bar->add_node( array(
            'id'     => 'uwb-clear-s3-asset-cache',
            'parent' => 'uwb-admin-bar',
            'title'  => 'Clear CSS / JS on S3 Storage',
            'href'   => $clear_s3_url,
        ) );
        // Add sub-node: Flush All & Preload Cache
        $flush_all_preload_url = wp_nonce_url( admin_url( 'admin-post.php?action=uwb_flush_all_preload' ), 'uwb_flush_all_preload_action' );
        $wp_admin_bar->add_node( array(
            'id'     => 'uwb-flush-all-preload',
            'parent' => 'uwb-admin-bar',
            'title'  => 'Flush All & Preload Cache',
            'href'   => $flush_all_preload_url,
        ) );



        // Add sub-node: Settings
        $wp_admin_bar->add_node( array(
            'id'     => 'uwb-settings',
            'parent' => 'uwb-admin-bar',
            'title'  => 'Settings',
            'href'   => admin_url( 'admin.php?page=ultimate-wp-booster' ),
        ) );
    }

    public function handle_purge_url() {
        $post_id = isset( $_GET['post_id'] ) ? intval( $_GET['post_id'] ) : 0;
        
        $can_purge = false;
        if ( current_user_can( 'manage_options' ) ) {
            $can_purge = true;
        } elseif ( $post_id > 0 && current_user_can( 'edit_post', $post_id ) ) {
            $can_purge = true;
        }

        if ( ! $can_purge ) {
            wp_die( 'Permission denied.' );
        }

        check_admin_referer( 'uwb_purge_url_action' );

        $url = isset( $_GET['url'] ) ? esc_url_raw( urldecode( $_GET['url'] ) ) : '';
        
        if ( $post_id > 0 && function_exists( 'rocket_clean_post' ) ) {
            rocket_clean_post( $post_id );
        } elseif ( ! empty( $url ) ) {
            $uwb_cache = new \Ultimate_WP_Booster\Engine\Cache\CacheManager();
            $uwb_cache->purge_url( $url );
        }

        wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url( '/' ) );
        exit;
    }

    public function handle_clear_cache_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Permission denied.' );
        }

        check_admin_referer( 'uwb_clear_cache_page_action' );

        $uwb_cache = new \Ultimate_WP_Booster\Engine\Cache\CacheManager();
        $uwb_cache->purge_all();

        $referer = wp_get_referer();
        if ( $referer && strpos( $referer, 'admin.php?page=ultimate-wp-booster' ) !== false ) {
            wp_safe_redirect( add_query_arg( 'uwb_msg', 'cache_cleared', $referer ) );
        } else {
            wp_safe_redirect( admin_url( 'admin.php?page=ultimate-wp-booster&uwb_msg=cache_cleared' ) );
        }
        exit;
    }

    public function handle_flush_all_preload() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Permission denied.' );
        }

        check_admin_referer( 'uwb_flush_all_preload_action' );

        // 1. Clear Static Page Cache
        $uwb_cache = new \Ultimate_WP_Booster\Engine\Cache\CacheManager();
        $uwb_cache->purge_all();

        // 2. Clear Cloudflare CDN Zone Cache (Edge Cache)
        \Ultimate_WP_Booster\Engine\CDN\CloudflareAPI::purge_everything();

        // 3. Clear S3 Asset Cache & Index
        \Ultimate_WP_Booster\Engine\CDN\CDNManager::clear_cdn_cache();

        // 4. Purge OPCache
        if ( function_exists( 'opcache_reset' ) ) {
            @opcache_reset();
        }

        // 5. Purge Object Cache
        $this->flush_object_cache_internal();

        // 6. Reset & Restart Preload Queue
        global $wpdb;
        $table_name = $wpdb->prefix . 'ultimate_wp_booster_queue';
        $wpdb->query( "TRUNCATE TABLE {$table_name}" );
        update_option( 'uwb_preload_running', 1 );

        wp_clear_scheduled_hook( 'uwb_start_preload_async' );
        wp_schedule_single_event( time(), 'uwb_start_preload_async' );

        $referer = wp_get_referer();
        if ( $referer && strpos( $referer, 'admin.php?page=ultimate-wp-booster' ) !== false ) {
            wp_safe_redirect( add_query_arg( 'uwb_msg', 'flush_all_preload_started', $referer ) );
        } else {
            wp_safe_redirect( admin_url( 'admin.php?page=ultimate-wp-booster&uwb_msg=flush_all_preload_started' ) );
        }
        exit;
    }

    public function handle_flush_object_cache() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Permission denied.' );
        }

        check_admin_referer( 'uwb_flush_object_cache_action' );

        $this->flush_object_cache_internal();

        wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url( '/' ) );
        exit;
    }

    public function handle_flush_opcache() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Permission denied.' );
        }

        check_admin_referer( 'uwb_flush_opcache_action' );

        if ( function_exists( 'opcache_reset' ) ) {
            @opcache_reset();
        }

        $referer = wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=ultimate-wp-booster' );
        wp_safe_redirect( add_query_arg( 'uwb_opcache_flushed', '1', $referer ) );
        exit;
    }

    public function handle_clear_cdn_zone_cache() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Permission denied.' );
        }

        check_admin_referer( 'uwb_clear_cdn_zone_cache_action' );

        $res = \Ultimate_WP_Booster\Engine\CDN\CloudflareAPI::purge_everything();

        $referer = wp_get_referer();
        $msg = is_wp_error( $res ) ? ( 'cdn_zone_error&err=' . urlencode( $res->get_error_message() ) ) : 'cdn_zone_cleared';

        if ( $referer && strpos( $referer, 'admin.php?page=ultimate-wp-booster' ) !== false ) {
            wp_safe_redirect( add_query_arg( 'uwb_msg', $msg, $referer ) );
        } else {
            wp_safe_redirect( admin_url( 'admin.php?page=ultimate-wp-booster&uwb_msg=' . $msg ) );
        }
        exit;
    }

    public function handle_clear_s3_asset_cache() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Permission denied.' );
        }

        check_admin_referer( 'uwb_clear_s3_asset_cache_action' );

        $res = \Ultimate_WP_Booster\Engine\CDN\CDNManager::clear_cdn_cache();
        $count = isset( $res['deleted_count'] ) ? intval( $res['deleted_count'] ) : 0;

        $referer = wp_get_referer();
        if ( $referer && strpos( $referer, 'admin.php?page=ultimate-wp-booster' ) !== false ) {
            wp_safe_redirect( add_query_arg( array( 'uwb_msg' => 's3_asset_cleared', 'count' => $count ), $referer ) );
        } else {
            wp_safe_redirect( admin_url( 'admin.php?page=ultimate-wp-booster&uwb_msg=s3_asset_cleared&count=' . $count ) );
        }
        exit;
    }

    public function handle_clear_cdn_cache() {
        $this->handle_clear_s3_asset_cache();
    }

    private function flush_object_cache_internal() {
        $oc_type = intval( get_option( 'uwb_redis_enabled', 0 ) );
        if ( $oc_type === 2 ) {
            if ( class_exists( 'Memcached' ) ) {
                $mc_host = get_option( 'uwb_redis_host', '127.0.0.1' );
                $mc_port = intval( get_option( 'uwb_redis_port', 11211 ) );
                if ( $mc_port === 6379 ) {
                    $mc_port = 11211;
                }
                $m = new \Memcached();
                $m->addServer( $mc_host, $mc_port );
                @$m->flush();
            }
        } else {
            if ( class_exists( 'Redis' ) ) {
                $conn_type = get_option( 'uwb_redis_conn_type', 'tcp' );
                $redis_host = get_option( 'uwb_redis_host', '127.0.0.1' );
                $redis_port = get_option( 'uwb_redis_port', 6379 );
                $redis_socket = get_option( 'uwb_redis_socket', '' );
                $redis_password = get_option( 'uwb_redis_password', '' );
                $redis_db = get_option( 'uwb_redis_db', 0 );

                $redis = new \Redis();
                try {
                    if ( $conn_type === 'socket' && ! empty( $redis_socket ) ) {
                        $connected = @$redis->connect( $redis_socket );
                    } else {
                        $connected = @$redis->connect( $redis_host, $redis_port, 1.0 );
                    }

                    if ( $connected ) {
                        if ( ! empty( $redis_password ) ) {
                            @$redis->auth( $redis_password );
                        }
                        if ( $redis_db > 0 ) {
                            @$redis->select( $redis_db );
                        }
                        $redis->flushDB();
                    }
                } catch ( \Exception $e ) {
                    // fall through
                }
            }
        }

        wp_cache_flush();
    }

    /**
     * Render the Quick Plugin Manager modal in wp_footer and admin_footer.
     */
    public function render_plugin_manager_modal() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Get raw/database active plugins (unfiltered by URO)
        global $wpdb;
        $active_plugins_serialized = $wpdb->get_var( "SELECT option_value FROM $wpdb->options WHERE option_name = 'active_plugins'" );
        $all_active_plugins = maybe_unserialize( $active_plugins_serialized );
        if ( ! is_array( $all_active_plugins ) ) {
            $all_active_plugins = array();
        }

        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $installed_plugins = get_plugins();

        $plugins_data = array();
        foreach ( $all_active_plugins as $plugin_file ) {
            if ( isset( $installed_plugins[ $plugin_file ] ) ) {
                $plugins_data[] = array(
                    'file' => $plugin_file,
                    'name' => $installed_plugins[ $plugin_file ]['Name'],
                    'version' => $installed_plugins[ $plugin_file ]['Version'],
                );
            }
        }

        // Sort alphabetically
        usort( $plugins_data, function( $a, $b ) {
            return strcmp( $a['name'], $b['name'] );
        } );

        $currently_loaded = get_option( 'active_plugins', array() );

        $uri = $_SERVER['REQUEST_URI'];
        $current_path = parse_url( $uri, PHP_URL_PATH );
        $current_path_clean = '/' . trim( $current_path, '/' );
        if ( substr( $current_path_clean, -4 ) !== '.php' ) {
            $current_path_clean .= '/';
        }
        if ( $current_path_clean === '//' ) {
            $current_path_clean = '/';
        }

        // For wp-admin pages, include critical query parameters (page, post_type, taxonomy)
        if ( is_admin() ) {
            $query = parse_url( $uri, PHP_URL_QUERY );
            if ( $query ) {
                parse_str( $query, $query_args );
                $keep_args = array();
                foreach ( array( 'page', 'post_type', 'taxonomy' ) as $arg ) {
                    if ( isset( $query_args[ $arg ] ) ) {
                        $keep_args[ $arg ] = $query_args[ $arg ];
                    }
                }
                if ( ! empty( $keep_args ) ) {
                    $current_path_clean .= '?' . http_build_query( $keep_args );
                }
            }
        }

        $rules = json_decode( get_option( 'uwb_uro_rules', '[]' ), true );
        $blocked_on_this_url = array();
        if ( is_array( $rules ) ) {
            foreach ( $rules as $rule ) {
                if ( isset( $rule['conditions']['url'] ) && count( $rule['conditions']['url'] ) === 1 && $rule['conditions']['url'][0] === $current_path_clean && $rule['action'] === 'deny' ) {
                    $blocked_on_this_url = $rule['plugins'] ?? array();
                    break;
                }
            }
        }

        $quick_nonce = wp_create_nonce( 'uwb_uro_quick_nonce' );
        ?>
        <div id="uwb-quick-pm-modal" style="display:none; position:fixed; inset:0; background:rgba(15, 23, 42, 0.7); backdrop-filter:blur(8px); -webkit-backdrop-filter:blur(8px); z-index:999999; align-items:center; justify-content:center; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen-Sans,Ubuntu,Cantarell,'Helvetica Neue',sans-serif; color:#1e293b;">
            <div style="background:#ffffff; border-radius:16px; width:90%; max-width:640px; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04); display:flex; flex-direction:column; max-height:85vh; border:1px solid #e2e8f0; overflow:hidden;">
                <!-- Header -->
                <div style="padding:20px 24px; border-bottom:1px solid #f1f5f9; display:flex; justify-content:space-between; align-items:center; background:#f8fafc;">
                    <div style="flex:1;">
                        <h3 style="margin:0; font-size:16px; font-weight:700; color:#0f172a; display:flex; align-items:center; gap:8px;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#4f46e5" stroke-width="2.5"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                            ⚡ URO Plugin Manager
                        </h3>
                        <p style="margin:4px 0 0; font-size:12px; color:#64748b; word-break:break-all;">
                            Chặn plugin trên đường dẫn: <strong style="color:#0f172a;"><?php echo esc_html( $current_path_clean ); ?></strong>
                        </p>
                    </div>
                    <button id="uwb-quick-pm-close" style="background:none; border:none; cursor:pointer; font-size:20px; color:#94a3b8; font-weight:700; padding:4px; line-height:1;">✕</button>
                </div>

                <!-- Search -->
                <div style="padding:12px 24px; border-bottom:1px solid #f1f5f9; display:flex; align-items:center; gap:8px; background:#fff;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" id="uwb-quick-pm-search" placeholder="Tìm kiếm plugin..." style="border:none; outline:none; font-size:13px; width:100%; color:#0f172a; padding:6px 0;">
                </div>

                <!-- Body / Plugins List -->
                <div style="padding:16px 24px; overflow-y:auto; flex:1; display:flex; flex-direction:column; gap:10px; background:#fff;" id="uwb-quick-pm-list">
                    <?php if ( empty( $plugins_data ) ) : ?>
                        <div style="text-align:center; padding:30px; color:#64748b; font-size:13px;">Không tìm thấy plugin hoạt động.</div>
                    <?php else : ?>
                        <?php foreach ( $plugins_data as $p ) : 
                            $is_loaded = in_array( $p['file'], $currently_loaded, true );
                            $is_blocked = in_array( $p['file'], $blocked_on_this_url, true );
                            ?>
                            <div class="uwb-quick-pm-item" data-name="<?php echo esc_attr( strtolower( $p['name'] ) ); ?>" style="display:flex; align-items:center; justify-content:space-between; padding:12px; border:1px solid #f1f5f9; border-radius:10px; transition:all 0.2s; background:#f8fafc;">
                                <div style="display:flex; align-items:center; gap:12px; flex:1; min-width:0;">
                                    <label style="display:flex; align-items:center; gap:10px; cursor:pointer; flex:1; min-width:0; user-select:none;">
                                        <input type="checkbox" class="uwb-quick-pm-checkbox" value="<?php echo esc_attr( $p['file'] ); ?>" <?php checked( $is_blocked ); ?> style="width:16px; height:16px; border-radius:4px; border:1.5px solid #cbd5e1; cursor:pointer;">
                                        <div style="min-width:0;">
                                            <div style="font-size:13.5px; font-weight:600; color:#0f172a; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?php echo esc_html( $p['name'] ); ?></div>
                                            <div style="font-size:11px; color:#64748b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; font-family:monospace;"><?php echo esc_html( $p['file'] ); ?></div>
                                        </div>
                                    </label>
                                </div>
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <?php if ( $is_loaded ) : ?>
                                        <span style="background:#d1fae5; color:#065f46; font-size:10.5px; font-weight:700; padding:3px 8px; border-radius:99px; display:inline-flex; align-items:center; gap:4px;">
                                            <span style="width:5px; height:5px; background:#10b981; border-radius:50%; display:inline-block;"></span>
                                            LOADED
                                        </span>
                                    <?php else : ?>
                                        <span style="background:#fee2e2; color:#b91c1c; font-size:10.5px; font-weight:700; padding:3px 8px; border-radius:99px; display:inline-flex; align-items:center; gap:4px;">
                                            <span style="width:5px; height:5px; background:#ef4444; border-radius:50%; display:inline-block;"></span>
                                            BLOCKED
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Footer -->
                <div style="padding:16px 24px; border-top:1px solid #f1f5f9; display:flex; justify-content:flex-end; gap:12px; background:#f8fafc;">
                    <button id="uwb-quick-pm-cancel" style="padding:9px 16px; border:1px solid #cbd5e1; background:#fff; color:#475569; font-weight:600; border-radius:8px; cursor:pointer; font-size:13px; outline:none;">Cancel</button>
                    <button id="uwb-quick-pm-save" style="padding:9px 20px; background:#4f46e5; border:none; color:#fff; font-weight:600; border-radius:8px; cursor:pointer; font-size:13px; outline:none; display:flex; align-items:center; gap:6px;">
                        Save &amp; Reload
                    </button>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Close modal
            $('#uwb-quick-pm-close, #uwb-quick-pm-cancel').on('click', function() {
                $('#uwb-quick-pm-modal').hide();
            });

            // Prevent closing when clicking modal dialog content
            $('#uwb-quick-pm-modal > div').on('click', function(e) {
                e.stopPropagation();
            });

            // Close when clicking overlay background
            $('#uwb-quick-pm-modal').on('click', function() {
                $(this).hide();
            });

            // Real-time Search filter
            $('#uwb-quick-pm-search').on('input', function() {
                var query = $(this).val().toLowerCase();
                $('.uwb-quick-pm-item').each(function() {
                    var name = $(this).data('name') || '';
                    if (name.indexOf(query) !== -1) {
                        $(this).css('display', 'flex');
                    } else {
                        $(this).css('display', 'none');
                    }
                });
            });

            // Save Rules
            $('#uwb-quick-pm-save').on('click', function() {
                var $btn = $(this);
                var blocked = [];
                $('.uwb-quick-pm-checkbox:checked').each(function() {
                    blocked.push($(this).val());
                });

                $btn.prop('disabled', true).text('Saving...');

                $.ajax({
                    url: '<?php echo esc_js( admin_url( "admin-ajax.php" ) ); ?>',
                    type: 'POST',
                    data: {
                        action: 'uwb_uro_quick_save_rule',
                        nonce: '<?php echo esc_js( $quick_nonce ); ?>',
                        url_path: '<?php echo esc_js( $current_path_clean ); ?>',
                        blocked_plugins: blocked
                    },
                    success: function(resp) {
                        if (resp.success) {
                            $btn.text('Rebuilding Cache...');
                            // Reload page to reflect changes
                            window.location.reload();
                        } else {
                            alert('Lỗi: ' + (resp.data || 'Không thể lưu.'));
                            $btn.prop('disabled', false).text('Save & Reload');
                        }
                    },
                    error: function() {
                        alert('Yêu cầu server thất bại.');
                        $btn.prop('disabled', false).text('Save & Reload');
                    }
                });
            });
        });
        </script>
        <?php
    }
}
