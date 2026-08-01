<?php
namespace Ultimate_WP_Booster\Engine\Admin;

use Ultimate_WP_Booster\EventManagement\Subscriber_Interface;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class AdminNoticeSubscriber implements Subscriber_Interface {

    public static function get_subscribed_events() {
        return array(
            'admin_notices' => 'check_cache_status_notice',
        );
    }

    public function check_cache_status_notice() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $errors = array();

        if ( ! defined( 'WP_CACHE' ) || ! WP_CACHE ) {
            $errors[] = 'Constant <code>WP_CACHE</code> is not defined or is set to <code>false</code> in your <code>wp-config.php</code> file. Static caching is currently disabled.';
        }

        $dropin = WP_CONTENT_DIR . '/advanced-cache.php';
        if ( ! file_exists( $dropin ) ) {
            $errors[] = 'The cache drop-in file <code>wp-content/advanced-cache.php</code> is missing or failed to copy. Page caching will not work.';
        }

        if ( ! empty( $errors ) ) {
            ?>
            <div class="notice notice-error is-dismissible" style="border-left-color: #d54e21; padding: 12px 20px; border-radius: 6px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-top: 15px;">
                <h3 style="margin: 0 0 8px 0; color: #d54e21; font-weight: 600;">Ultimate WP Booster Caching Warning</h3>
                <ul style="margin: 0; padding-left: 20px; list-style-type: disc;">
                    <?php foreach ( $errors as $err ) : ?>
                        <li style="font-size: 14px; line-height: 1.5; color: #32373c; margin-bottom: 5px;"><?php echo $err; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php
        }
    }
}
