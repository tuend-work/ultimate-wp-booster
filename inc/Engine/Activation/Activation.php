<?php
namespace Ultimate_WP_Booster\Engine\Activation;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class Activation {

    public static function activate_plugin() {
        \Uwb_Activator::activate();
    }
}
