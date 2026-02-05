<?php
namespace WPForceRepair\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DeleteController {

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes() {
		register_rest_route( 'wp-force-repair/v1', '/delete', [
			'methods'             => 'POST', // POST is often more reliable for actions than DELETE in some server configs, but semantically DELETE is better. Let's stick to POST for "action" style or allow DELETE. Let's use POST for safety.
			'callback'            => [ $this, 'handle_delete' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
			'args'                => [
				'type' => [
					'required' => true,
					'validate_callback' => function($param) {
						return in_array( $param, ['plugin', 'theme'] );
					}
				],
				'target' => [
					'required' => true,
					'description' => 'Plugin file path (relative to plugins dir) or Theme stylesheet (slug)',
				]
			]
		] );
	}

	public function handle_delete( \WP_REST_Request $request ) {
		$type   = $request->get_param( 'type' );
		$target = $request->get_param( 'target' );

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/theme.php';
		
		// Setup Filesystem credentials if needed (rarely needed for delete if FS_METHOD is direct, but good practice to check)
		// For REST API, Request_Filesystem_Credentials doesn't work well interactively. 
		// We rely on 'direct' method or defined constants.
		
		if ( 'plugin' === $type ) {
			// Validate plugin exists
			if ( ! array_key_exists( $target, get_plugins() ) ) {
				// It might be a single file plugin or deactivated one. get_plugins() lists all valid ones.
				// If not found, maybe force delete file? No, unvalidated deletion is dangerous.
				return new \WP_REST_Response( [
					'success' => false,
					'message' => 'Plugin not found or invalid path.'
				], 404 );
			}
			
			// Deactivate first if active
			if ( is_plugin_active( $target ) ) {
				deactivate_plugins( $target );
			}

			$result = delete_plugins( [ $target ] );
			
			if ( is_wp_error( $result ) ) {
				return new \WP_REST_Response( [
					'success' => false,
					'message' => $result->get_error_message()
				], 500 );
			} elseif ( false === $result ) {
				return new \WP_REST_Response( [
					'success' => false,
					'message' => 'Could not delete plugin. Check filesystem permissions.'
				], 500 );
			}

		} else {
			// Theme deletion
			$theme = wp_get_theme( $target );
			if ( ! $theme->exists() ) {
				return new \WP_REST_Response( [
					'success' => false,
					'message' => 'Theme not found.'
				], 404 );
			}
			
			if ( $theme->get_stylesheet() === get_stylesheet() || $theme->get_stylesheet() === get_template() ) {
				return new \WP_REST_Response( [
					'success' => false,
					'message' => 'Cannot delete active theme.'
				], 400 );
			}

			$result = delete_theme( $target );
			
			if ( is_wp_error( $result ) ) {
				return new \WP_REST_Response( [
					'success' => false,
					'message' => $result->get_error_message()
				], 500 );
			} elseif ( false === $result ) {
				return new \WP_REST_Response( [
					'success' => false,
					'message' => 'Could not delete theme. Check filesystem permissions.'
				], 500 );
			}
		}

		return new \WP_REST_Response( [
			'success' => true,
			'message' => 'Deleted successfully.'
		], 200 );
	}
}
