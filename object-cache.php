<?php
/**
 * Ultimate WP Booster Redis Drop-in
 * Persistent Object Cache Backend using Redis.
 *
 * Target: wp-content/object-cache.php
 */

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

if ( ! class_exists( 'WP_Object_Cache' ) ) {

    class WP_Object_Cache {

        private $cache = array();
        private $redis = null;
        private $redis_connected = false;
        private $global_groups = array();
        private $non_persistent_groups = array();
        private $blog_id = 0;
        private $prefix = '';

        public $cache_hits = 0;
        public $cache_misses = 0;

        public function __construct() {
            $this->blog_id = get_current_blog_id();
            $this->prefix = is_multisite() ? $this->blog_id . ':' : '';

            // Read settings from configuration
            $config_file = WP_CONTENT_DIR . '/cache/ultimate-wp-booster-config.json';
            if ( file_exists( $config_file ) ) {
                $config_data = @json_decode( @file_get_contents( $config_file ), true );
                if ( ! empty( $config_data['redis_enabled'] ) && class_exists( 'Redis' ) ) {
                    $this->redis = new Redis();
                    try {
                        $conn_type = isset( $config_data['redis_conn_type'] ) ? $config_data['redis_conn_type'] : 'tcp';
                        $password  = isset( $config_data['redis_password'] ) ? $config_data['redis_password'] : '';
                        $db        = isset( $config_data['redis_db'] ) ? intval( $config_data['redis_db'] ) : 0;

                        if ( $conn_type === 'socket' && ! empty( $config_data['redis_socket'] ) ) {
                            $connected = @$this->redis->connect( $config_data['redis_socket'] );
                        } else {
                            $host      = ! empty( $config_data['redis_host'] ) ? $config_data['redis_host'] : '127.0.0.1';
                            $port      = ! empty( $config_data['redis_port'] ) ? intval( $config_data['redis_port'] ) : 6379;
                            $connected = @$this->redis->connect( $host, $port, 1.0 );
                        }

                        if ( $connected ) {
                            if ( ! empty( $password ) ) {
                                @$this->redis->auth( $password );
                            }
                            if ( $db > 0 ) {
                                @$this->redis->select( $db );
                            }
                            $this->redis_connected = true;
                        }
                    } catch ( Exception $e ) {
                        $this->redis_connected = false;
                    }
                }
            }
        }

        private function get_redis_key( $key, $group ) {
            if ( empty( $group ) ) {
                $group = 'default';
            }
            if ( in_array( $group, $this->global_groups, true ) ) {
                return 'uwb_oc:' . $group . ':' . $key;
            }
            return 'uwb_oc:' . $this->prefix . $group . ':' . $key;
        }

        public function get( $key, $group = 'default', $force = false, &$found = null ) {
            if ( empty( $group ) ) {
                $group = 'default';
            }

            // Check local memory cache first
            if ( isset( $this->cache[ $group ][ $key ] ) ) {
                $found = true;
                $this->cache_hits++;
                return is_object( $this->cache[ $group ][ $key ] ) ? clone $this->cache[ $group ][ $key ] : $this->cache[ $group ][ $key ];
            }

            if ( $this->redis_connected && ! in_array( $group, $this->non_persistent_groups, true ) ) {
                try {
                    $redis_key = $this->get_redis_key( $key, $group );
                    $value = $this->redis->get( $redis_key );
                    if ( $value !== false ) {
                        $data = unserialize( $value );
                        $this->cache[ $group ][ $key ] = $data;
                        $found = true;
                        $this->cache_hits++;
                        return $data;
                    }
                } catch ( Exception $e ) {
                    // Fail silently
                }
            }

            $found = false;
            $this->cache_misses++;
            return false;
        }

        public function set( $key, $value, $group = 'default', $expire = 0 ) {
            if ( empty( $group ) ) {
                $group = 'default';
            }

            $this->cache[ $group ][ $key ] = $value;

            if ( $this->redis_connected && ! in_array( $group, $this->non_persistent_groups, true ) ) {
                try {
                    $redis_key = $this->get_redis_key( $key, $group );
                    $serialized = serialize( $value );
                    if ( $expire > 0 ) {
                        $this->redis->setex( $redis_key, $expire, $serialized );
                    } else {
                        $this->redis->set( $redis_key, $serialized );
                    }
                } catch ( Exception $e ) {
                    // Fail silently
                }
            }
            return true;
        }

        public function add( $key, $value, $group = 'default', $expire = 0 ) {
            $found = false;
            $this->get( $key, $group, false, $found );
            if ( $found ) {
                return false;
            }
            return $this->set( $key, $value, $group, $expire );
        }

        public function replace( $key, $value, $group = 'default', $expire = 0 ) {
            $found = false;
            $this->get( $key, $group, false, $found );
            if ( ! $found ) {
                return false;
            }
            return $this->set( $key, $value, $group, $expire );
        }

        public function delete( $key, $group = 'default' ) {
            if ( empty( $group ) ) {
                $group = 'default';
            }

            unset( $this->cache[ $group ][ $key ] );

            if ( $this->redis_connected && ! in_array( $group, $this->non_persistent_groups, true ) ) {
                try {
                    $redis_key = $this->get_redis_key( $key, $group );
                    $this->redis->del( $redis_key );
                } catch ( Exception $e ) {
                    // Fail silently
                }
            }
            return true;
        }

        public function flush() {
            $this->cache = array();
            if ( $this->redis_connected ) {
                try {
                    $this->redis->flushDB();
                } catch ( Exception $e ) {
                    // Fail silently
                }
            }
            return true;
        }

        public function add_global_groups( $groups ) {
            $groups = (array) $groups;
            $this->global_groups = array_unique( array_merge( $this->global_groups, $groups ) );
        }

        public function add_non_persistent_groups( $groups ) {
            $groups = (array) $groups;
            $this->non_persistent_groups = array_unique( array_merge( $this->non_persistent_groups, $groups ) );
        }
    }

    // Global Wrapper Functions
    function wp_cache_add( $key, $data, $group = '', $expire = 0 ) {
        global $wp_object_cache;
        return $wp_object_cache->add( $key, $data, $group, $expire );
    }

    function wp_cache_close() {
        return true;
    }

    function wp_cache_delete( $key, $group = '' ) {
        global $wp_object_cache;
        return $wp_object_cache->delete( $key, $group );
    }

    function wp_cache_flush() {
        global $wp_object_cache;
        return $wp_object_cache->flush();
    }

    function wp_cache_get( $key, $group = '', $force = false, &$found = null ) {
        global $wp_object_cache;
        return $wp_object_cache->get( $key, $group, $force, $found );
    }

    function wp_cache_init() {
        global $wp_object_cache;
        $wp_object_cache = new WP_Object_Cache();
    }

    function wp_cache_replace( $key, $data, $group = '', $expire = 0 ) {
        global $wp_object_cache;
        return $wp_object_cache->replace( $key, $data, $group, $expire );
    }

    function wp_cache_set( $key, $data, $group = '', $expire = 0 ) {
        global $wp_object_cache;
        return $wp_object_cache->set( $key, $data, $group, $expire );
    }

    function wp_cache_switch_to_blog( $blog_id ) {
        return true;
    }

    function wp_cache_add_global_groups( $groups ) {
        global $wp_object_cache;
        $wp_object_cache->add_global_groups( $groups );
    }

    function wp_cache_add_non_persistent_groups( $groups ) {
        global $wp_object_cache;
        $wp_object_cache->add_non_persistent_groups( $groups );
    }
}
