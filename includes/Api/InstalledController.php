<?php
namespace WPForceRepair\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class InstalledController {

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes() {
		register_rest_route( 'wp-force-repair/v1', '/installed', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'handle_get_installed' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
		] );

        register_rest_route( 'wp-force-repair/v1', '/installed/toggle', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle_toggle_status' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
		] );

        register_rest_route( 'wp-force-repair/v1', '/installed/bulk-action', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle_bulk_action' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
		] );
	}

    public function handle_toggle_status( $request ) {
        // These functions are not always loaded in REST API context
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/theme.php'; // For completeness, though switch_theme is core

        $type = $request->get_param( 'type' );
        $slug = $request->get_param( 'slug' );
        $action = $request->get_param( 'action' ); // 'activate' or 'deactivate'

        if ( ! $slug || ! $action ) {
            return new \WP_Error( 'missing_params', 'Slug and action are required.', [ 'status' => 400 ] );
        }

        if ( $type === 'plugin' ) {
            // Convert slug to file path if needed, or assume slug IS the file path for plugins
            // Frontend should send the full file path (e.g. 'plugin-dir/plugin-file.php')
            $plugin_file = $slug; 
            
            // Verify it exists in get_plugins() just to be safe?
            // Actually, activate_plugin handles checks.
            
            if ( $action === 'activate' ) {
                $result = activate_plugin( $plugin_file );
                if ( is_wp_error( $result ) ) {
                    return $result;
                }
            } else {
                deactivate_plugins( $plugin_file );
            }
            
            return new \WP_REST_Response( [ 'success' => true, 'status' => $action === 'activate' ? 'active' : 'inactive' ], 200 );

        } elseif ( $type === 'theme' ) {
            if ( $action === 'activate' ) {
                switch_theme( $slug );
                return new \WP_REST_Response( [ 'success' => true, 'status' => 'active' ], 200 );
            } else {
                return new \WP_Error( 'theme_deactivate', 'Themes cannot be deactivated, only switched.', [ 'status' => 400 ] );
            }
        }

        return new \WP_Error( 'invalid_type', 'Invalid type specified.', [ 'status' => 400 ] );
    }

    public function handle_bulk_action( $request ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/theme.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';

        $type = $request->get_param( 'type' );
        $action = $request->get_param( 'action' ); // activate, deactivate, delete
        $slugs = $request->get_param( 'slugs' ); // Array of slugs/files

        if ( ! $type || ! $action || empty( $slugs ) || ! is_array( $slugs ) ) {
            return new \WP_Error( 'invalid_params', 'Type, action, and slugs array are required.', [ 'status' => 400 ] );
        }

        $success_count = 0;
        $errors = [];

        foreach ( $slugs as $slug ) {
            // Plugin slug is the file path (e.g., folder/file.php)
            // Theme slug is the directory name
            
            if ( $type === 'plugin' ) {
                if ( $action === 'activate' ) {
                    $result = activate_plugin( $slug );
                    if ( is_wp_error( $result ) ) {
                        $errors[] = "Failed to activate $slug: " . $result->get_error_message();
                    } else {
                        $success_count++;
                    }
                } elseif ( $action === 'deactivate' ) {
                    deactivate_plugins( $slug );
                    // deactivate_plugins doesn't return value, assumes success
                    if ( ! is_plugin_active( $slug ) ) {
                        $success_count++;
                    } else {
                         $errors[] = "Failed to deactivate $slug";
                    }
                } elseif ( $action === 'delete' ) {
                    // Safety: Deactivate first
                    if ( is_plugin_active( $slug ) ) {
                        deactivate_plugins( $slug );
                    }
                    $result = delete_plugins( [ $slug ] );
                    if ( is_wp_error( $result ) ) {
                         $errors[] = "Failed to delete $slug: " . $result->get_error_message();
                    } elseif ( $result === false ) {
                         $errors[] = "Failed to delete $slug (filesystem error)";
                    } else {
                        $success_count++;
                    }
                }
            } elseif ( $type === 'theme' ) {
                if ( $action === 'activate' ) {
                     switch_theme( $slug );
                     $success_count++;
                } elseif ( $action === 'deactivate' ) {
                     $errors[] = "Themes cannot be deactivated, only switched.";
                } elseif ( $action === 'delete' ) {
                    $theme = wp_get_theme( $slug );
                    if ( $theme->exists() ) {
                         // Check active
                         if ( $theme->get_stylesheet() === get_stylesheet() || $theme->get_stylesheet() === get_template() ) {
                             $errors[] = "Cannot delete active theme $slug";
                             continue;
                         }
                         
                         $result = delete_theme( $slug );
                         if ( is_wp_error( $result ) ) {
                            $errors[] = "Failed to delete $slug: " . $result->get_error_message();
                        } elseif ( $result === false ) {
                            $errors[] = "Failed to delete $slug (filesystem error)";
                        } else {
                            $success_count++;
                        }
                    } else {
                        $errors[] = "Theme $slug not found";
                    }
                }
            }
        }

        return new \WP_REST_Response( [
            'success' => true,
            'processed' => count( $slugs ),
            'success_count' => $success_count,
            'errors' => $errors
        ], 200 );
    }

	public function handle_get_installed( $request ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/theme.php';

        $type = $request->get_param( 'type' );

		$formatted_plugins = [];
        if ( ! $type || 'plugin' === $type ) {
            $plugins = get_plugins();
            $plugin_updates = get_site_transient( 'update_plugins' );
            $active_plugins = get_option('active_plugins');

            foreach ( $plugins as $file => $data ) {
                
                $slug = dirname( $file );
                if ( '.' === $slug ) {
                    $slug = basename( $file, '.php' );
                }

                $update_available = false;
                $source = 'external'; // Default to external

                // Check for updates or repo status
                // Logic: A plugin is 'repo' if:
                // 1. It has an update package from wordpress.org
                // 2. Its PluginURI or AuthorURI is explicitly wordpress.org
                // 3. It's in the 'no_update' list of the wp.org API response (though some paid plugins hook here, we need care)
                
                if ( is_object( $plugin_updates ) ) {
                    // Check if update is available
                    if ( isset( $plugin_updates->response ) && isset( $plugin_updates->response[ $file ] ) ) {
                        $update_available = true;
                        $pkg = isset( $plugin_updates->response[ $file ]->package ) ? $plugin_updates->response[ $file ]->package : '';
                        
                        // Strict: Only trust w.org packages
                        if ( strpos( $pkg, 'wordpress.org' ) !== false ) {
                             $source = 'repo';
                        } else {
                             $source = 'external';
                        }
                    }
                    // Check if it's in the 'no_update' list meaning WP.org tracks it
                    elseif ( isset( $plugin_updates->no_update ) && isset( $plugin_updates->no_update[ $file ] ) ) {
                         $item = $plugin_updates->no_update[ $file ];
                         // Verify the INFO url points to wordpress.org
                         if ( isset( $item->url ) && strpos( $item->url, 'wordpress.org' ) !== false ) {
                             $source = 'repo';
                         }
                    }
                }

                $is_active = in_array( $file, $active_plugins ) || is_plugin_active_for_network( $file );

                $formatted_plugins[] = [
                    'file'        => $file, // Use file path as unique ID for actions
                    'slug'        => $slug,
                    'name'        => $data['Name'],
                    'version'     => $data['Version'],
                    'description' => $data['Description'],
                    'author'      => $data['AuthorName'],
                    'uri'         => $data['PluginURI'],
                    'update_available' => $update_available,
                    'source'      => $source,
                    'status'      => $is_active ? 'active' : 'inactive',
                    'type'        => 'plugin',
                ];
            }
        }

		$formatted_themes = [];
        if ( ! $type || 'theme' === $type ) {
            $themes  = wp_get_themes();
            $theme_updates  = get_site_transient( 'update_themes' );
            $current_theme = get_stylesheet();

            foreach ( $themes as $slug => $theme ) {
                $update_available = false;
                $source = 'external'; // Default to external

                if ( is_object( $theme_updates ) ) {
                    if ( isset( $theme_updates->response ) && isset( $theme_updates->response[ $slug ] ) ) {
                        $update_available = true;
                        $pkg = isset( $theme_updates->response[ $slug ]['package'] ) ? $theme_updates->response[ $slug ]['package'] : '';
                         if ( strpos( $pkg, 'wordpress.org' ) !== false ) {
                             $source = 'repo';
                         }
                    } elseif ( isset( $theme_updates->checked ) && isset( $theme_updates->checked[ $slug ] ) ) {
                        // Refined check: Themes usually don't have a 'no_update' list with metadata like plugins.
                        // We will trust ThemeURI for themes ONLY if strictly wordpress.org
                        $theme_uri = $theme->get('ThemeURI');
                         if ( strpos( $theme_uri, 'wordpress.org/themes/' ) !== false ) {
                             $source = 'repo';
                         }
                    }
                }

                $formatted_themes[] = [
                    'slug'        => $slug,
                    'name'        => $theme->get( 'Name' ),
                    'version'     => $theme->get( 'Version' ),
                    'description' => $theme->get( 'Description' ),
                    'author'      => $theme->get( 'Author' ),
                    'update_available' => $update_available,
                    'source'      => $source,
                    'status'      => ($slug === $current_theme) ? 'active' : 'inactive',
                    'type'        => 'theme',
                ];
            }
        }

		return new \WP_REST_Response( [
			'success' => true,
			'plugins' => $formatted_plugins,
			'themes'  => $formatted_themes
		], 200 );
	}
}
