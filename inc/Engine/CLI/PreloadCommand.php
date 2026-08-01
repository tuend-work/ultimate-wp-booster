<?php
namespace Ultimate_WP_Booster\Engine\CLI;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

class PreloadCommand {

    public static function register() {
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            \WP_CLI::add_command( 'uwb-preload', array( __CLASS__, 'run' ) );
        }
    }

    public function run( $args, $assoc_args ) {
        $batch_size = isset( $assoc_args['batch-size'] ) ? intval( $assoc_args['batch-size'] ) : 0;
        $preloader = new \Ultimate_WP_Booster\Engine\Preload\Preloader();
        $res = $preloader->run_preload_batch( $batch_size );
        $count = is_array( $res ) && isset( $res['count'] ) ? $res['count'] : 0;
        \WP_CLI::success( "Preloaded {$count} URLs successfully!" );
    }
}
