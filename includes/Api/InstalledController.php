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

	public function handle_get_installed() {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/theme.php';

		$plugins = get_plugins();
		$themes  = wp_get_themes();

		$formatted_plugins = [];
		foreach ( $plugins as $file => $data ) {
			// Basic categorization: If it has 'PluginURI' pointing to wordpress.org, it's likely repo.
			// Accurate check requires API lookup (too slow for list) or checking 'Update URI' header (WP 5.8+).
			// For now, return raw data, let logic happen if needed.
			
			$slug = dirname( $file );
			if ( '.' === $slug ) {
				$slug = basename( $file, '.php' );
			}

			$formatted_plugins[] = [
				'file'        => $file,
				'slug'        => $slug,
				'name'        => $data['Name'],
				'version'     => $data['Version'],
				'description' => $data['Description'],
				'author'      => $data['AuthorName'],
				'uri'         => $data['PluginURI'],
				'type'        => 'plugin',
			];
		}

		$formatted_themes = [];
		foreach ( $themes as $slug => $theme ) {
			$formatted_themes[] = [
				'slug'        => $slug,
				'name'        => $theme->get( 'Name' ),
				'version'     => $theme->get( 'Version' ),
				'description' => $theme->get( 'Description' ),
				'author'      => $theme->get( 'Author' ),
				'type'        => 'theme',
			];
		}

		return new \WP_REST_Response( [
			'success' => true,
			'plugins' => $formatted_plugins,
			'themes'  => $formatted_themes
		], 200 );
	}
}
