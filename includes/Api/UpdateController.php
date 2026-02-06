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
        // Prevent timeouts
        @set_time_limit( 0 );

		$slug = $request->get_param( 'slug' );
		$type = $request->get_param( 'type' );
		$download_link = $request->get_param( 'download_link' );

        // 1. Load Required WP Admin Files
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/theme.php';
        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        require_once ABSPATH . 'wp-admin/includes/misc.php'; // For got_mod_rewrite etc sometimes needed

        // 2. Initialize Filesystem
        if ( ! WP_Filesystem() ) {
            return new \WP_REST_Response( [
                'success' => false,
                'message' => 'Could not initialize WP Filesystem. Check permissions.'
            ], 500 );
        }

        try {
            // 3. Get Download Link if missing
            if ( empty( $download_link ) ) {
                if ( 'plugin' === $type ) {
                    $api = plugins_api( 'plugin_information', [ 'slug' => $slug, 'fields' => [ 'sections' => false ] ] );
                    if ( is_wp_error( $api ) ) throw new \Exception( $api->get_error_message() );
                    $download_link = $api->download_link;
                } else {
                    $api = themes_api( 'theme_information', [ 'slug' => $slug, 'fields' => [ 'sections' => false ] ] );
                    if ( is_wp_error( $api ) ) throw new \Exception( $api->get_error_message() );
                    $download_link = $api->download_link;
                }
            }

            $skin = new JsonSkin();
            $result = null;
            
            if ( 'plugin' === $type ) {
                $upgrader = new \Plugin_Upgrader( $skin );
                $plugin_file = $this->get_plugin_file( $slug );
                
                // Force Overwrite Filters
                add_filter( 'upgrader_package_options', function($options) {
                    $options['clear_destination'] = true;
                    $options['abort_if_destination_exists'] = false; 
                    return $options;
                } );
                
                $result = $upgrader->install( $download_link );
                
                // Reactivate if needed
                if ( $plugin_file && ! is_wp_error($result) && $result ) {
                    // Re-check plugin file as it might have changed or just been created
                     $new_plugin_file = $this->get_plugin_file( $slug );
                     if ($new_plugin_file) activate_plugin( $new_plugin_file );
                }
                
            } else {
                $upgrader = new \Theme_Upgrader( $skin );
                add_filter( 'upgrader_package_options', function($options) {
                    $options['clear_destination'] = true;
                    $options['abort_if_destination_exists'] = false;
                    return $options;
                } );
                
                $result = $upgrader->install( $download_link );
            }

            if ( is_wp_error( $result ) ) {
                throw new \Exception( $result->get_error_message() );
            }
            
            if ( ! $result ) {
                // If result is null/false but no error, check skins logs
                $err = $skin->errors ? implode(', ', $skin->errors) : 'Unknown installation error.';
                throw new \Exception( $err );
            }

            return new \WP_REST_Response( [
                'success' => true,
                'message' => 'Installed successfully.',
                'logs'    => $skin->messages
            ], 200 );

        } catch ( \Exception $e ) {
            return new \WP_REST_Response( [
                'success' => false,
                'message' => $e->getMessage(),
                'logs'    => isset($skin) ? $skin->messages : [],
            ], 500 );
        }
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
