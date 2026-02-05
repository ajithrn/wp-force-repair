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
	}

	public function handle_get_installed( $request ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/theme.php';

        $type = $request->get_param( 'type' );

		$formatted_plugins = [];
        if ( ! $type || 'plugin' === $type ) {
            $plugins = get_plugins();
            $plugin_updates = get_site_transient( 'update_plugins' );

            foreach ( $plugins as $file => $data ) {
                
                $slug = dirname( $file );
                if ( '.' === $slug ) {
                    $slug = basename( $file, '.php' );
                }

                $update_available = false;
                $source = 'external'; // Default to external

                // Check for updates or repo status
                if ( is_object( $plugin_updates ) ) {
                    // Check if update is available
                    if ( isset( $plugin_updates->response ) && isset( $plugin_updates->response[ $file ] ) ) {
                        $update_available = true;
                        $source = 'repo';
                    }
                    // Check if it's in the 'no_update' list (meaning it's tracked by repo but up to date)
                    elseif ( isset( $plugin_updates->no_update ) && isset( $plugin_updates->no_update[ $file ] ) ) {
                         $source = 'repo';
                    }
                }

                $formatted_plugins[] = [
                    'file'        => $file,
                    'slug'        => $slug,
                    'name'        => $data['Name'],
                    'version'     => $data['Version'],
                    'description' => $data['Description'],
                    'author'      => $data['AuthorName'],
                    'uri'         => $data['PluginURI'],
                    'update_available' => $update_available,
                    'source'      => $source,
                    'type'        => 'plugin',
                ];
            }
        }

		$formatted_themes = [];
        if ( ! $type || 'theme' === $type ) {
            $themes  = wp_get_themes();
            $theme_updates  = get_site_transient( 'update_themes' );

            foreach ( $themes as $slug => $theme ) {
                $update_available = false;
                $source = 'external'; // Default to external

                if ( is_object( $theme_updates ) ) {
                    if ( isset( $theme_updates->response ) && isset( $theme_updates->response[ $slug ] ) ) {
                        $update_available = true;
                        $source = 'repo';
                    } elseif ( isset( $theme_updates->checked ) && isset( $theme_updates->checked[ $slug ] ) ) {
                        // If it's in the checked list, it *might* be repo, but 'checked' includes everything.
                        // Better check: does it have a w.org URI?
                        // Many themes don't explicitly list a URI that helps.
                        // But if it's in 'no_update' (themes equivalent is just missing from response but present in checked?)
                        // ACTUALLY: themes don't have a 'no_update' array in the transient usually. 
                        // Let's check the theme's 'ThemeURI'. If it contains wordpress.org, likely repo.
                        $theme_uri = $theme->get('ThemeURI');
                        if ( strpos( $theme_uri, 'wordpress.org' ) !== false ) {
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
