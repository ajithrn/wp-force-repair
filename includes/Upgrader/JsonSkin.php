<?php
namespace WPForceRepair\Upgrader;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader-skin.php';

class JsonSkin extends \WP_Upgrader_Skin {

	public $messages = [];
	public $errors   = [];

	public function feedback( $string, ...$args ) {
		if ( isset( $args[0] ) ) {
			// Handle simplified sprintf arguments
			$string = sprintf( $string, ...$args );
		}
		// Clean up HTML tags for cleaner JSON output, or keep them if we want rich text on frontend
		// For now, let's keep it simple.
		$this->messages[] = strip_tags( $string );
	}

	public function error( $errors ) {
		if ( is_wp_error( $errors ) ) {
			foreach ( $errors->get_error_messages() as $message ) {
				$this->errors[] = $message;
			}
		} else {
			$this->errors[] = $errors;
		}
	}

	public function header() {}
	public function footer() {}
}
