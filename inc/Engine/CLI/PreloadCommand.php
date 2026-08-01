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
        if ( class_exists( 'Uwb_Preloader' ) ) {
            $preloader = new \Uwb_Preloader();
            $processed = $preloader->run_preload_batch( $batch_size );
            \WP_CLI::success( "Preloaded {$processed} URLs successfully!" );
        } else {
            \WP_CLI::error( "Preloader class not found." );
        }
    }
}
