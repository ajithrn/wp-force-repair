<?php
namespace WPForceRepair\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CoreReinstallController extends \WP_REST_Controller {

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        register_rest_route( 'wp-force-repair/v1', '/core/reinstall', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle_clean_reinstall' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
		] );
    }

    public function handle_clean_reinstall( $request ) {
        // Increases time limit for large operations
        @set_time_limit( 300 ); 

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/update.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/class-core-upgrader.php';

        $logs = [];
        $logs[] = "Initializing Clean Core Reinstall...";

        try {
            // 1. BACKUP WP-CONFIG
            $config_path = ABSPATH . 'wp-config.php';
            $config_content = '';
            if ( file_exists( $config_path ) ) {
                $config_content = file_get_contents( $config_path );
                $logs[] = "Backed up wp-config.php to memory.";
            } else {
                // Try one level up
                 if ( file_exists( dirname( ABSPATH ) . '/wp-config.php' ) ) {
                     $config_content = file_get_contents( dirname( ABSPATH ) . '/wp-config.php' );
                     $logs[] = "Backed up wp-config.php (parent dir) to memory.";
                 } else {
                     throw new \Exception( 'Cannot find wp-config.php! Aborting for safety.' );
                 }
            }

            // 2. DOWNLOAD LATEST WORDPRESS
            $version = $request->get_param('version') ?: 'latest';
            $download_url = "https://wordpress.org/wordpress-{$version}.zip"; 
            if ( $version === 'latest' ) {
                 $download_url = "https://wordpress.org/latest.zip";
            }
            
            $logs[] = "Downloading WordPress ($version)...";
            $temp_file = download_url( $download_url );
            if ( is_wp_error( $temp_file ) ) {
                throw new \Exception( 'Download failed: ' . $temp_file->get_error_message() );
            }

            // 3. AUTO-QUARANTINE UNKNOWNS (Optional)
           $do_quarantine = $request->get_param( 'quarantine_unknowns' );
           
           if ( $do_quarantine ) {
               $logs[] = "Scanning and quarantining unknown files...";
               // Note: This dependency should be injected or instantiated.
               // For now, simpler to just instantiate ScanController to get list, then QuarantineController
               // Or better: Replicate basic scan logic here to avoid coupling or use `ScanController` instance.
               // Given this is a refactoring, let's keep it self-contained or call the new controllers if needed.
               // Simpler for this context:
               
               $scan_controller = new ScanController();
               $scan_request = new \WP_REST_Request( 'POST', '/wp-force-repair/v1/core/scan' );
               $scan_response = $scan_controller->scan_root_files($scan_request);
               
               if ( ! is_wp_error( $scan_response ) ) {
                   $suspected = $scan_response->data['files']; // Assuming format matches
                    if ( ! empty( $suspected ) ) {
                        $files_to_q = array_column( $suspected, 'name' );
                        $quarantine_controller = new QuarantineController();
                        $q_request = new \WP_REST_Request( 'POST', '/wp-force-repair/v1/core/quarantine' );
                        $q_request->set_body_params( [ 'files' => $files_to_q ] );
                        $quarantine_controller->quarantine_files( $q_request );
                        $logs[] = "Quarantined " . count( $files_to_q ) . " unknown files.";
                    } else {
                        $logs[] = "No unknown files found to quarantine.";
                    }
               }
           } else {
               $logs[] = "Skipping quarantine (only replacing Core files).";
           }

            // 4. UNZIP
            $logs[] = "Unzipping package...";
            global $wp_filesystem;
            if ( ! $wp_filesystem ) {
                if ( false === WP_Filesystem() ) {
                     throw new \Exception( 'Could not initialize WP_Filesystem.' );
                }
            }
            
            $upgrade_folder = $wp_filesystem->wp_content_dir() . 'upgrade/';
            $wp_filesystem->mkdir( $upgrade_folder );
            $unzip_result = unzip_file( $temp_file, $upgrade_folder );
            
            if ( is_wp_error( $unzip_result ) ) {
                throw new \Exception( 'Unzip failed: ' . $unzip_result->get_error_message() );
            }
            
            $source_dir = $upgrade_folder . 'wordpress/'; 
            
            // 5. SURGICAL REPLACEMENT
            
            // Replace wp-admin
            $logs[] = "Replacing wp-admin...";
            $wp_filesystem->delete( ABSPATH . 'wp-admin', true );
            $wp_filesystem->move( $source_dir . 'wp-admin', ABSPATH . 'wp-admin', true );
            
            // Replace wp-includes
            $logs[] = "Replacing wp-includes...";
            $wp_filesystem->delete( ABSPATH . 'wp-includes', true );
            $wp_filesystem->move( $source_dir . 'wp-includes', ABSPATH . 'wp-includes', true );
            
            // Replace Root Files
            $logs[] = "Replacing root files...";
            $root_files = $wp_filesystem->dirlist( $source_dir );

            if ( $root_files ) {
                foreach ( $root_files as $file ) {
                    if ( $file['type'] === 'f' ) {
                        $filename = $file['name'];
                        $wp_filesystem->move( $source_dir . $filename, ABSPATH . $filename, true );
                    }
                }
            }
            
            // 6. RESTORE CONFIG CHECK
            if ( ! file_exists( $config_path ) && ! file_exists( dirname( ABSPATH ) . '/wp-config.php' ) ) {
                $logs[] = "⚠️ wp-config.php missing! Restoring from memory...";
                file_put_contents( $config_path, $config_content );
            }

            // Cleanup
            $wp_filesystem->delete( $upgrade_folder, true );
            if ( file_exists( $temp_file ) ) {
                unlink( $temp_file );
            }

            return new \WP_REST_Response( [
                'success' => true,
                'message' => 'WordPress Core Clean Reinstall Complete.',
                'logs'    => $logs
            ], 200 );

        } catch ( \Throwable $e ) {
            // Cleanup temp file if exists
            if ( isset( $temp_file ) && file_exists( $temp_file ) ) {
                unlink( $temp_file );
            }
            
            return new \WP_REST_Response( [
                'success' => false,
                'message' => 'Critical Error: ' . $e->getMessage(),
                'logs'    => array_merge( $logs, [ 'FATAL ERROR: ' . $e->getMessage(), 'Trace: ' . $e->getTraceAsString() ] )
            ], 500 );
        }
	}
}
