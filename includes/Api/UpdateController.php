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

        register_rest_route( 'wp-force-repair/v1', '/check-update', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'check_self_update' ],
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
        ] );

        register_rest_route( 'wp-force-repair/v1', '/update/standard', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle_standard_update' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
            'args'                => [
				'slug' => [ 'required' => true ],
				'type' => [ 'required' => true ], // plugin/theme
			]
		] );

        register_rest_route( 'wp-force-repair/v1', '/install/upload', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_upload_install' ],
            'permission_callback' => function () {
                return current_user_can( 'upload_plugins' ) && current_user_can( 'install_plugins' );
            },
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

            // NEW: Download manually first to validate Zip contents if it's an external URL (or even repo URL for safety)
            // But repo URLs are trusted. External ones are not.
            // Let's be consistent and validate all if slug is provided.
            
            $tmp_file = download_url( $download_link );
            if ( is_wp_error( $tmp_file ) ) {
                 throw new \Exception( 'Download failed: ' . $tmp_file->get_error_message() );
            }

            // Validate Zip Contents
            if ( $slug ) {
                require_once dirname( __DIR__ ) . '/Utils/ZipValidator.php';
                $validation = \WPForceRepair\Utils\ZipValidator::validate( $tmp_file, $slug );
                
                if ( is_wp_error( $validation ) ) {
                    @unlink( $tmp_file );
                    return new \WP_REST_Response( [
                        'success' => false,
                        'message' => $validation->get_error_message(),
                    ], 400 );
                }
            }
            
            if ( 'plugin' === $type ) {
                $upgrader = new \Plugin_Upgrader( $skin );
                $plugin_file = $this->get_plugin_file( $slug );
                
                // Force Overwrite Filters
                add_filter( 'upgrader_package_options', function($options) {
                    $options['clear_destination'] = true;
                    $options['abort_if_destination_exists'] = false; 
                    return $options;
                } );
                
                // Install from local temp file
                $result = $upgrader->install( $tmp_file );
                
                // Reactivate if needed
                if ( $plugin_file && ! is_wp_error($result) && $result ) {
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
                
                $result = $upgrader->install( $tmp_file );
            }

            @unlink( $tmp_file ); // Cleanup

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

    public function handle_upload_install( \WP_REST_Request $request ) {
        @set_time_limit( 0 );
        
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/theme.php';
        
        // 1. Handle File Upload
        $files = $request->get_file_params(); // standard WP REST way to get $_FILES
        if ( empty( $files['package'] ) ) {
             return new \WP_Error( 'no_file', 'No file uploaded.', [ 'status' => 400 ] );
        }

        $overrides = [ 'test_form' => false ];
        $upload = wp_handle_upload( $files['package'], $overrides );

        if ( isset( $upload['error'] ) ) {
             return new \WP_Error( 'upload_error', $upload['error'], [ 'status' => 500 ] );
        }

        $file_path = $upload['file'];
        $type = $request->get_param('type');
        // Slug is optional for upload as the zip contains the folder, creates it. 
        // But for "updating" an existing one, we might want to ensure it matches?
        // Standard WP install just unzips. If folder exists, we force overwrite.
        
        if ( ! WP_Filesystem() ) {
            return new \WP_REST_Response( [ 'success' => false, 'message' => 'Filesystem error' ], 500 );
        }

        // 2. Validate Zip Contents (Safety Check)
        $target_slug = $request->get_param('slug');
        if ( $target_slug ) {
            require_once dirname( __DIR__ ) . '/Utils/ZipValidator.php';
            $validation = \WPForceRepair\Utils\ZipValidator::validate( $file_path, $target_slug );

            if ( is_wp_error( $validation ) ) {
                @unlink( $file_path );
                return new \WP_REST_Response( [
                    'success' => false,
                    'message' => $validation->get_error_message(),
                ], 400 );
            }
        }

        $skin = new JsonSkin();
        $result = null;

        try {
            if ( 'plugin' === $type ) {
                $upgrader = new \Plugin_Upgrader( $skin );
                add_filter( 'upgrader_package_options', function($options) {
                    $options['clear_destination'] = true;
                    $options['abort_if_destination_exists'] = false; 
                    return $options;
                } );
                // Install from local paths
                $result = $upgrader->install( $file_path );
                
                // Try to activate if we know the slug? 
                // Hard to know the main file from zip without scanning.
                // We'll skip auto-activation for upload-reinstall unless we are sure.
                // The frontend 'slug' param might be trustworthy if passed.
            } else {
                 $upgrader = new \Theme_Upgrader( $skin );
                 add_filter( 'upgrader_package_options', function($options) {
                    $options['clear_destination'] = true;
                    $options['abort_if_destination_exists'] = false; 
                    return $options;
                } );
                $result = $upgrader->install( $file_path );
            }

            // Cleanup uploaded zip
            @unlink( $file_path );

            if ( is_wp_error( $result ) ) {
                throw new \Exception( $result->get_error_message() );
            }

            if ( ! $result && empty($skin->errors) ) {
                 throw new \Exception( 'Unknown error during installation.' );
            }
            
            if ( ! empty( $skin->errors ) ) {
                 throw new \Exception( 'Errors occurred: ' . implode(', ', $skin->errors) );
            }

            return new \WP_REST_Response( [
                'success' => true,
                'message' => 'Installed successfully.',
                'logs'    => $skin->messages
            ], 200 );

        } catch ( \Exception $e ) {
            @unlink( $file_path ); // Ensure cleanup
            return new \WP_REST_Response( [
                'success' => false,
                'message' => $e->getMessage(),
                'logs'    => isset($skin) ? $skin->messages : [],
            ], 500 );
        }
    }

    public function handle_standard_update( \WP_REST_Request $request ) {
        @set_time_limit( 0 );
        
        $slug = $request->get_param( 'slug' ); // File path for plugins
        $type = $request->get_param( 'type' );

        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/misc.php';
        
        if ( ! WP_Filesystem() ) {
             return new \WP_REST_Response( [ 'success' => false, 'message' => 'Filesystem error' ], 500 );
        }

        $skin = new JsonSkin();
        $result = null;

        if ( $type === 'plugin' ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
            // Force refresh of update data
            wp_update_plugins();
            
            $upgrader = new \Plugin_Upgrader( $skin );
            $result = $upgrader->upgrade( $slug );
        } else {
            require_once ABSPATH . 'wp-admin/includes/theme.php';
            wp_update_themes();
            
            $upgrader = new \Theme_Upgrader( $skin );
            $result = $upgrader->upgrade( $slug );
        }

        if ( is_wp_error( $result ) ) {
            return new \WP_REST_Response( [ 
                'success' => false, 
                'message' => $result->get_error_message(),
                'logs' => $skin->messages 
            ], 500 );
        }

        // Result can be string (success msg), true/false/null depending on upgrader path
        if ( ! $result && empty( $skin->errors ) ) {
             // Sometimes standard upgrader returns false if already up to date or no package found
             // Check valid updates
             return new \WP_REST_Response( [ 
                'success' => false, 
                'message' => 'Update failed or no update available via standard path.',
                'logs' => $skin->messages 
            ], 400 );
        } elseif ( ! empty( $skin->errors ) ) {
             // Check if it's just "up_to_date" which isn't really a system error
             if ( in_array( 'up_to_date', $skin->errors ) ) {
                 return new \WP_REST_Response( [ 
                    'success' => true, 
                    'message' => 'Plugin is already up to date.',
                    'logs' => $skin->messages
                ], 200 );
             }

             // Convert errors array to string for clearer message
             $error_msg = 'Update failed: ' . implode( ', ', $skin->errors );
             
             return new \WP_REST_Response( [ 
                'success' => false, 
                'message' => $error_msg,
                'logs' => array_merge( $skin->messages, $skin->errors )
            ], 500 );
        }

        return new \WP_REST_Response( [
            'success' => true,
            'message' => 'Update complete.',
            'logs' => $skin->messages
        ], 200 );
    }

    public function check_self_update() {
        // Instantiate Updater manually (it requires file path and repo slug)
        // We know standard path: WP_PLUGIN_DIR . '/wp-force-repair/wp-force-repair.php'
        // But better to use constants if available.
        // Assuming 'WPForceRepair\WFR_PLUGIN_FILE' is not defined public constant, I'll reconstruct given this class's context.
        // Actually, main file defines WFR_VERSION.
        // I'll assume standard path.
        
        require_once dirname( dirname( __DIR__ ) ) . '/includes/Utils/GitHubUpdater.php';
        $updater = new \WPForceRepair\Utils\GitHubUpdater( 
            WP_PLUGIN_DIR . '/wp-force-repair/wp-force-repair.php', 
            'ajithrn/wp-force-repair' 
        );

        $remote = $updater->fetch_github_release();
        
        if ( ! $remote ) {
            return new \WP_REST_Response( [
                'has_update' => false,
                'current_version' => WFR_VERSION,
                'checked_at' => current_time( 'mysql' ),
                'error' => 'Could not fetch from GitHub.'
            ], 200 );
        }

        $has_update = version_compare( WFR_VERSION, $remote->new_version, '<' );
        
        return new \WP_REST_Response( [
            'has_update' => $has_update,
            'current_version' => WFR_VERSION,
            'new_version' => $remote->new_version,
            'download_link' => $remote->package,
            'changelog' => $remote->body,
            'checked_at' => current_time( 'mysql' )
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
