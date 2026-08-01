<?php
namespace Ultimate_WP_Booster\Engine\Admin;

use Ultimate_WP_Booster\EventManagement\Subscriber_Interface;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class PostRowActionsSubscriber implements Subscriber_Interface {

    public static function get_subscribed_events() {
        return array(
            'post_row_actions' => array( 'add_post_row_actions', 10, 2 ),
            'page_row_actions' => array( 'add_post_row_actions', 10, 2 ),
        );
    }

    public function add_post_row_actions( $actions, $post ) {
        if ( current_user_can( 'manage_options' ) || current_user_can( 'edit_post', $post->ID ) ) {
            $clean_url = get_permalink( $post->ID );
            $purge_url = wp_nonce_url( 
                admin_url( 'admin-post.php?action=uwb_purge_url&url=' . urlencode( $clean_url ) . '&post_id=' . $post->ID ), 
                'uwb_purge_url_action' 
            );
            $actions['uwb_purge'] = '<a href="' . esc_url( $purge_url ) . '" title="' . esc_attr__( 'Purge cache for this post', 'ultimate-wp-booster' ) . '" style="color:#bc00dd;font-weight:600;">' . esc_html__( 'Xóa cache', 'ultimate-wp-booster' ) . '</a>';
        }
        return $actions;
    }
}
