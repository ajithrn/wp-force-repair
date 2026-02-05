<?php
namespace WPForceRepair\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SearchController {

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes() {
		register_rest_route( 'wp-force-repair/v1', '/search', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'handle_search' ],
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
				'term' => [
					'required' => false,
					'default'  => '',
				],
				'page' => [
					'required' => false,
					'default'  => 1,
					'validate_callback' => function($param) {
						return is_numeric( $param );
					}
				]
			]
		] );
	}

	public function handle_search( \WP_REST_Request $request ) {
		$type = $request->get_param( 'type' );
		$term = $request->get_param( 'term' );
		$page = $request->get_param( 'page' );

		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		require_once ABSPATH . 'wp-admin/includes/theme.php';

		$args = [
			'page' => $page,
			'per_page' => 20,
			'locale' => get_user_locale(),
		];

		if ( ! empty( $term ) ) {
			$args['search'] = $term;
		}

		if ( 'plugin' === $type ) {
			$api = plugins_api( 'query_plugins', $args );
		} else {
			// Themes API is slightly different structure in args usually, but query_themes works similarly
			$args = [
				'page' => $page,
				'per_page' => 20,
				'search' => $term,
				'fields' => [
					'description' => true,
					'sections' => false,
					'rating' => true,
					'downloaded' => true,
					'download_link' => true,
					'last_updated' => true,
					'homepage' => true,
					'tags' => true,
					'versions' => true, // Important for force update to specific version if needed
				]
			];
			$api = themes_api( 'query_themes', $args );
		}

		if ( is_wp_error( $api ) ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => $api->get_error_message()
			], 500 );
		}

		return new \WP_REST_Response( [
			'success' => true,
			'data'    => $api
		], 200 );
	}
}
