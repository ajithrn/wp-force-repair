<?php
namespace WPForceRepair\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ScanController extends \WP_REST_Controller {

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        register_rest_route( 'wp-force-repair/v1', '/core/status', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_core_status' ],
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
        ] );

        register_rest_route( 'wp-force-repair/v1', '/core/scan', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'scan_root_files' ],
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
        ] );
    }

    public function get_core_status() {
        global $wp_version;
        // Include required files for get_core_updates
        if ( ! function_exists( 'get_core_updates' ) ) {
            require_once ABSPATH . 'wp-admin/includes/update.php';
        }
        
        // Check for updates
        $updates = get_core_updates( array( 'dismissed' => false ) );
        $has_update = false;
        if ( ! empty( $updates ) && ! empty( $updates[0]->response ) && 'latest' !== $updates[0]->response ) {
            $has_update = true;
        }

        return new \WP_REST_Response( [
            'version' => $wp_version,
            'locale'  => get_locale(),
            'has_update' => $has_update,
            'latest_version' => !empty($updates) ? $updates[0]->version : $wp_version
        ], 200 );
    }

    /**
     * Scans directory for files
     */
    public function scan_root_files( $request ) {
        $root_path = ABSPATH;
        $relative_path = $request->get_param( 'path' ) ? untrailingslashit( $request->get_param( 'path' ) ) : '';
        
        // Security: Prevent traversal
        if ( strpos( $relative_path, '..' ) !== false ) {
            return new \WP_Error( 'invalid_path', 'Invalid path.' );
        }

        $scan_path = $relative_path ? $root_path . $relative_path . '/' : $root_path;

        if ( ! is_dir( $scan_path ) ) {
             return new \WP_Error( 'dir_not_found', 'Directory not found.' );
        }

        $files = scandir( $scan_path );
        $suspected = [];

        // Standard Exclusion Rules (Only apply at Root)
        $is_root = empty( $relative_path );

        $standard_files = [
            'index.php', 'wp-settings.php', 'wp-load.php', 'wp-blog-header.php', 
            'wp-config.php', 'wp-cron.php', 'wp-links-opml.php', 'wp-mail.php', 
            'wp-login.php', 'wp-signup.php', 'wp-trackback.php', 'xmlrpc.php', 
            'wp-comments-post.php', 'wp-activate.php', 'license.txt', 'readme.html', '.htaccess'
        ];

        // Ensure wp-content and other standard dirs are not hidden, but maybe marked safe?
        // User asked to SHOW wp-content.
        // We will just skip wp-admin and wp-includes from the LIST if at root, but show wp-content.
        $hidden_dirs = [ 'wp-admin', 'wp-includes' ]; 

        foreach ( $files as $file ) {
            if ( $file === '.' || $file === '..' ) continue;
            
            $full_path = $scan_path . $file;
            $is_dir = is_dir( $full_path );
            
            // At root, hide standard WP dirs except wp-content
            if ( $is_root && $is_dir && in_array( $file, $hidden_dirs ) ) continue;
            
            // At root, mark standard files as "Core" or hide them? 
            // Previous logic HID them. Let's keep hiding standard ROOT files to reduce noise, 
            // unless user specifically wants to see them.
            // But if we browse wp-content, we show everything.
            if ( $is_root && ! $is_dir && in_array( $file, $standard_files ) ) continue;

            $suspected[] = [
                'name' => $file,
                'path' => $relative_path ? $relative_path . '/' . $file : $file, // Relative to ABSPATH
                'type' => $is_dir ? 'directory' : 'file',
                'size' => $is_dir ? '-' : size_format( filesize( $full_path ) ),
                'perms'=> substr( sprintf( '%o', fileperms( $full_path ) ), -4 ),
                'mtime' => date( 'Y-m-d H:i:s', filemtime( $full_path ) )
            ];
        }

        return new \WP_REST_Response( [
            'success' => true,
            'files' => $suspected,
            'current_path' => $relative_path
        ], 200 );
    }
}
