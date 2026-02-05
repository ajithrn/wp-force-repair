<?php
namespace WPForceRepair\Api;

use WPForceRepair\Upgrader\JsonSkin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UpdateController {

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes() {
		register_rest_route( 'wp-force-repair/v1', '/install', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle_install' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
			'args'                => [
				'slug' => [
					'required' => true,
				],
				'type' => [
					'required' => true,
					'validate_callback' => function($param) {
						return in_array( $param, ['plugin', 'theme'] );
					}
				],
				'download_link' => [
					'required' => true,
					'validate_callback' => function($param) {
						return filter_var($param, FILTER_VALIDATE_URL);
					}
				]
			]
		] );
	}

	public function handle_install( \WP_REST_Request $request ) {
		$slug = $request->get_param( 'slug' );
		$type = $request->get_param( 'type' );
		$download_link = $request->get_param( 'download_link' );

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/theme.php';

		$skin = new JsonSkin();
		
		// Force overwrite logic usually handled by upgrader if destination exists.
		// Standard `install` might fail if folder exists unless we use `upgrade` or manually delete first.
		// However, for "Force Update", we often want to overwrite. 
		// `Plugin_Upgrader::install` expects package. 
		
		// Let's use the Upgrader.
		if ( 'plugin' === $type ) {
			$upgrader = new \Plugin_Upgrader( $skin );
			// To force overwrite, we might need a hook on 'upgrader_package_options' or similar, 
			// OR just rely on standard install which might complain. 
			// Actually, `Plugin_Upgrader` has `install` method. 
			// If we want to overwrite, we typically need to delete the old one or use `upgrade` if it's installed.
			
			// Check if installed
			$plugin_file = $this->get_plugin_file( $slug );
			
			if ( $plugin_file && file_exists( WP_PLUGIN_DIR . '/' . $plugin_file ) ) {
				// It is installed. Use upgrade process logic, OR delete and install.
				// "Force Update" implies overwrite. 
				// Let's try `upgrade`. `upgrade` methods often require version checks.
				// Simplest "Force" is: download, unzip, replace.
				
				// Standard `install` will fail if directory exists.
				// Let's hook into `upgrader_pre_install` to delete existing if we want standard "clean" install?
				// Or better, use `upgrade` with `clear_destination => true`.

				add_filter( 'upgrader_package_options', function($options) {
					$options['clear_destination'] = true;
					$options['abort_if_destination_exists'] = false; // Hook/Key might vary by WordPress version but clear_destination is the standard "overwrite" flag
					return $options;
				} );
			}
			
			$result = $upgrader->install( $download_link );
			
			// After install, we might need to reactivate it if it was active.
			if ( $plugin_file && is_plugin_active( $plugin_file ) ) {
				activate_plugin( $plugin_file );
			}
			
		} else {
			$upgrader = new \Theme_Upgrader( $skin );
			
			// Similar logic for themes
			add_filter( 'upgrader_package_options', function($options) {
				$options['clear_destination'] = true;
				$options['abort_if_destination_exists'] = false;
				return $options;
			} );
			
			$result = $upgrader->install( $download_link );
		}

		if ( is_wp_error( $result ) ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => $result->get_error_message(),
				'logs'    => $skin->messages,
				'errors'  => $skin->errors
			], 500 );
		}
		
		if ( ! $result ) {
             // Null result often means generic failure in upgrader
			return new \WP_REST_Response( [
				'success' => false,
				'message' => 'Installation failed. Check logs.',
				'logs'    => $skin->messages,
				'errors'  => $skin->errors
			], 500 );
		}

		return new \WP_REST_Response( [
			'success' => true,
			'message' => 'Installed successfully.',
			'logs'    => $skin->messages
		], 200 );
	}

	private function get_plugin_file( $slug ) {
		// Helper to find the main plugin file from slug.
		// This is tricky because slug doesn't always equal folder name, but usually does.
		// And we need the 'folder/file.php' format.
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugins = get_plugins();
		foreach ( $plugins as $file => $data ) {
			if ( strpos( $file, $slug . '/' ) === 0 ) {
				return $file;
			}
		}
		return null;
	}
}
