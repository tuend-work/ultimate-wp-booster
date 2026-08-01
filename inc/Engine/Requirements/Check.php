<?php
namespace Ultimate_WP_Booster\Engine\Requirements;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class Check {

    private $min_php_version;
    private $min_wp_version;

    public function __construct( $min_php_version = '7.4', $min_wp_version = '5.6' ) {
        $this->min_php_version = $min_php_version;
        $this->min_wp_version  = $min_wp_version;
    }

    /**
     * Check requirements and return true if passed.
     *
     * @return bool
     */
    public function check() {
        if ( version_compare( PHP_VERSION, $this->min_php_version, '<' ) ) {
            add_action( 'admin_notices', array( $this, 'notice_php_version' ) );
            return false;
        }

        global $wp_version;
        if ( version_compare( $wp_version, $this->min_wp_version, '<' ) ) {
            add_action( 'admin_notices', array( $this, 'notice_wp_version' ) );
            return false;
        }

        return true;
    }

    public function notice_php_version() {
        echo '<div class="notice notice-error"><p>';
        printf(
            esc_html__( 'Ultimate WP Booster requires PHP %s or higher. Your current version is %s.', 'ultimate-wp-booster' ),
            $this->min_php_version,
            PHP_VERSION
        );
        echo '</p></div>';
    }

    public function notice_wp_version() {
        global $wp_version;
        echo '<div class="notice notice-error"><p>';
        printf(
            esc_html__( 'Ultimate WP Booster requires WordPress %s or higher. Your current version is %s.', 'ultimate-wp-booster' ),
            $this->min_wp_version,
            $wp_version
        );
        echo '</p></div>';
    }
}
