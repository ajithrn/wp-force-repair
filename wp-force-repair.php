<?php
/**
 * Plugin Name:       WP Force Repair
 * Plugin URI:        https://github.com/ajithrn/wp-force-repair
 * Description:       The ultimate recovery tool. Force install plugins/themes, repair core files, fix database health, and debug system issues.
 * Version:           2.6.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Ajith R N
 * Author URI:        https://ajithrn.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-force-repair
 * Domain Path:       /languages
 */

namespace WPForceRepair;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WFR_VERSION', '2.10.0' );
define( 'WFR_PATH', plugin_dir_path( __FILE__ ) );
define( 'WFR_URL', plugin_dir_url( __FILE__ ) );

/**
 * Autoloader for strict PSR-4 (simplified for includes).
 */
spl_autoload_register( function( $class ) {
	$prefix = 'WPForceRepair\\';
	$base_dir = WFR_PATH . 'includes/';

	$len = strlen( $prefix );
	if ( strncmp( $prefix, $class, $len ) !== 0 ) {
		return;
	}

	$relative_class = substr( $class, $len );
	$file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

	if ( file_exists( $file ) ) {
		require $file;
	}
} );

/**
 * Initialize the plugin.
 */
function wfr_init() {
	// Initialize API Controllers
	new Api\SearchController();
	new Api\UpdateController();
    new Api\InstalledController();
    new Api\DeleteController();
    new Api\ScanController();
    new Api\QuarantineController();
    new Api\SystemToolsController();
    new Api\CoreReinstallController();
    new Api\BackupController(); // Phase 2.5: Backup Manager
    new Api\DatabaseController(); // Phase 3: Database Health
    
    // Auto Update
    new Utils\GitHubUpdater( __FILE__, 'ajithrn/wp-force-repair' );
}
add_action( 'plugins_loaded', 'WPForceRepair\\wfr_init' );

/**
 * Admin Menu and Assets.
 */
function wfr_admin_menu() {
	add_menu_page(
		__( 'Force Repair', 'wp-force-repair' ),
		__( 'Force Repair', 'wp-force-repair' ),
		'manage_options',
		'wp-force-repair',
		'WPForceRepair\wfr_render_admin_page',
		'dashicons-hammer',
		99
	);
}
add_action( 'admin_menu', 'WPForceRepair\\wfr_admin_menu' );

function wfr_render_admin_page() {
	echo '<div id="wfr-dashboard" class="wfr-dashboard"></div>';
}

/**
 * Enqueue Scripts.
 */
function wfr_enqueue_assets( $hook ) {
	if ( 'toplevel_page_wp-force-repair' !== $hook ) {
		return;
	}

	$asset_file = WFR_PATH . 'build/index.asset.php';

	if ( file_exists( $asset_file ) ) {
		$assets = require $asset_file;
		
		wp_enqueue_script(
			'wfr-app',
			WFR_URL . 'build/index.js',
			$assets['dependencies'],
			$assets['version'],
			true
		);

		wp_enqueue_style(
			'wfr-style',
			WFR_URL . 'build/style-index.css',
			[],
			$assets['version']
		);
		
		wp_localize_script( 'wfr-app', 'wfrSettings', [
			'root'    => esc_url_raw( rest_url() ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		] );
	}
}
add_action( 'admin_enqueue_scripts', 'WPForceRepair\\wfr_enqueue_assets' );
