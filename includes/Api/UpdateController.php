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
					'required' => false,
					'validate_callback' => function($param) {
						return empty($param) || filter_var($param, FILTER_VALIDATE_URL);
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
        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

        // If no download link provided, try to fetch it from WP Repo
        if ( empty( $download_link ) ) {
            if ( 'plugin' === $type ) {
                $api = plugins_api( 'plugin_information', [ 'slug' => $slug, 'fields' => [ 'sections' => false ] ] );
                if ( is_wp_error( $api ) ) {
                    return new \WP_REST_Response( [
                        'success' => false,
                        'message' => 'Could not retrieve download link: ' . $api->get_error_message(),
                    ], 500 );
                }
                $download_link = $api->download_link;
            } else {
                $api = themes_api( 'theme_information', [ 'slug' => $slug, 'fields' => [ 'sections' => false ] ] );
                if ( is_wp_error( $api ) ) {
                    return new \WP_REST_Response( [
                        'success' => false,
                        'message' => 'Could not retrieve download link: ' . $api->get_error_message(),
                    ], 500 );
                }
                $download_link = $api->download_link;
            }
        }

		$skin = new JsonSkin();
		
		if ( 'plugin' === $type ) {
			$upgrader = new \Plugin_Upgrader( $skin );
			
			// Check if installed
			$plugin_file = $this->get_plugin_file( $slug );
			
			if ( $plugin_file && file_exists( WP_PLUGIN_DIR . '/' . $plugin_file ) ) {
				add_filter( 'upgrader_package_options', function($options) {
					$options['clear_destination'] = true;
					$options['abort_if_destination_exists'] = false; 
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
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugins = get_plugins();
		foreach ( $plugins as $file => $data ) {
			// Exact match for slug folder
			if ( strpos( $file, $slug . '/' ) === 0 ) {
				return $file;
			// Or if it's a single file plugin matching slug.php
			} elseif ( $file === $slug . '.php' ) {
                return $file;
            }
		}
		return null;
	}
}
