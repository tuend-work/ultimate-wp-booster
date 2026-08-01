<?php
namespace Ultimate_WP_Booster\Engine\Deactivation;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class Deactivation {

    public static function deactivate_plugin() {
        \Uwb_Deactivator::deactivate();
    }
}
