<?php
namespace WPForceRepair\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SystemToolsController extends \WP_REST_Controller {

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        register_rest_route( 'wp-force-repair/v1', '/core/tools/flush-permalinks', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'flush_permalinks' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
		] );

		register_rest_route( 'wp-force-repair/v1', '/core/tools/regenerate-htaccess', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'regenerate_htaccess' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
		] );

        register_rest_route( 'wp-force-repair/v1', '/core/tools/regenerate-salts', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'regenerate_salts' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
		] );

        register_rest_route( 'wp-force-repair/v1', '/core/tools/comment-stats', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_comment_stats' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
		] );

        register_rest_route( 'wp-force-repair/v1', '/core/tools/cleanup-comments', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'cleanup_comments' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
		] );

        register_rest_route( 'wp-force-repair/v1', '/core/tools/reset-permissions', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'reset_file_permissions' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
		] );

        register_rest_route( 'wp-force-repair/v1', '/core/tools/view-file', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_file_content' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
		] );

		register_rest_route( 'wp-force-repair/v1', '/core/tools/check-loopback', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'check_loopback' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
		] );
    }

    public function flush_permalinks() {
        flush_rewrite_rules();
        return new \WP_REST_Response( [
            'success' => true,
            'message' => 'Permalinks flushed successfully.'
        ], 200 );
    }

    public function regenerate_htaccess() {
        if ( ! function_exists( 'save_mod_rewrite_rules' ) ) {
            require_once ABSPATH . 'wp-admin/includes/misc.php';
        }
        
        $htaccess_path = ABSPATH . '.htaccess';
        $backup_created = false;

        // Backup existing .htaccess if it exists
        if ( file_exists( $htaccess_path ) ) {
            $backup_path = $htaccess_path . '.bak.' . date('Y-m-d_H-i-s');
            if ( copy( $htaccess_path, $backup_path ) ) {
                $backup_created = basename( $backup_path );
            }
        }

        // Force regeneration
        save_mod_rewrite_rules();
        
        // Verify
        if ( file_exists( $htaccess_path ) ) {
            return new \WP_REST_Response( [
                'success' => true,
                'message' => '.htaccess regenerated successfully.',
                'backup' => $backup_created ? "Backup created: $backup_created" : null
            ], 200 );
        } else {
             return new \WP_Error( 'write_error', 'Could not create .htaccess file. Check permissions.' );
        }
    }

    public function regenerate_salts() {
        $config_path = ABSPATH . 'wp-config.php';
        if ( ! file_exists( $config_path ) ) {
            if ( file_exists( dirname( ABSPATH ) . '/wp-config.php' ) ) {
                $config_path = dirname( ABSPATH ) . '/wp-config.php';
            } else {
                return new \WP_Error( 'config_not_found', 'wp-config.php not found.' );
            }
        }

        if ( ! is_writable( $config_path ) ) {
            return new \WP_Error( 'config_not_writable', 'wp-config.php is not writable.' );
        }

        // Fetch new salts
        $salt_api_url = 'https://api.wordpress.org/secret-key/1.1/salt/';
        $new_salts = wp_remote_retrieve_body( wp_remote_get( $salt_api_url ) );

        if ( empty( $new_salts ) ) {
            return new \WP_Error( 'api_error', 'Failed to fetch new salts from WordPress.org.' );
        }

        $config_content = file_get_contents( $config_path );
        
        // Regex to find the block of salt definitions
        // It looks for define('AUTH_KEY'... down to the last salt
        $pattern = "/define\(\s*'AUTH_KEY'[\s\S]*?define\(\s*'NONCE_SALT'[\s\S]*?\);/m";

        if ( preg_match( $pattern, $config_content ) ) {
            $new_content = preg_replace( $pattern, $new_salts, $config_content );
            if ( file_put_contents( $config_path, $new_content ) ) {
                return new \WP_REST_Response( [
                    'success' => true,
                    'message' => 'Salt keys regenerated. You will be logged out.'
                ], 200 );
            }
        }

        return new \WP_Error( 'update_failed', 'Failed to update wp-config.php. Pattern may not match.' );
    }

    public function get_comment_stats() {
        $stats = wp_count_comments();
        return new \WP_REST_Response( [
            'spam'      => $stats->spam,
            'trash'     => $stats->trash,
            'moderated' => $stats->moderated, // Pending
            'total_junk'=> $stats->spam + $stats->trash + $stats->moderated
        ], 200 );
    }

    public function cleanup_comments( $request ) {
        $type = $request->get_param( 'type' ); // spam, trash, moderated
        
        if ( ! in_array( $type, [ 'spam', 'trash', 'moderated' ] ) ) {
            return new \WP_Error( 'invalid_type', 'Invalid comment type.' );
        }

        $args = [
            'status' => $type === 'moderated' ? 'hold' : $type,
            'number' => 100, // Process in batches if needed, but for now loop
            'fields' => 'ids'
        ];

        // For massive amounts, we might need a safer query or loop
        // but for a simple tool, standard WP_Comment_Query is fine.
        // We'll delete up to 500 at a time to avoid timeout
        $query = new \WP_Comment_Query;
        $comments = $query->query( [
            'status' => $type === 'moderated' ? 'hold' : $type,
            'number' => 500,
            'fields' => 'ids'
        ] );

        $count = 0;
        foreach ( $comments as $comment_id ) {
            wp_delete_comment( $comment_id, true ); // true = force delete
            $count++;
        }

        // Check if more remain
        $stats = wp_count_comments();
        $remaining = 0;
        if ( $type === 'spam' ) $remaining = $stats->spam;
        if ( $type === 'trash' ) $remaining = $stats->trash;
        if ( $type === 'moderated' ) $remaining = $stats->moderated;

        return new \WP_REST_Response( [
            'success' => true,
            'deleted' => $count,
            'remaining' => $remaining,
            'message' => "Deleted $count $type comments." . ( $remaining > 0 ? " ($remaining remaining)" : "" )
        ], 200 );
    }

    public function reset_file_permissions() {
        // Increase time limit
        @set_time_limit( 300 );

        $root = ABSPATH;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator( $root, \RecursiveDirectoryIterator::SKIP_DOTS ),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        $count_dirs = 0;
        $count_files = 0;
        $errors = 0;

        foreach ( $iterator as $item ) {
            // Skip .git and node_modules to be fast and safe
            if ( strpos( $item->getPathname(), '.git' ) !== false || strpos( $item->getPathname(), 'node_modules' ) !== false ) {
                continue;
            }

            try {
                if ( $item->isDir() ) {
                    if ( chmod( $item->getPathname(), 0755 ) ) {
                        $count_dirs++;
                    } else {
                        $errors++;
                    }
                } else {
                    if ( chmod( $item->getPathname(), 0644 ) ) {
                        $count_files++;
                    } else {
                        $errors++;
                    }
                }
            } catch ( \Exception $e ) {
                $errors++;
            }
        }

        // Also fix root
        @chmod( $root, 0755 );

        return new \WP_REST_Response( [
            'success' => true,
            'message' => "Permissions Reset Complete. Fixed $count_dirs folders and $count_files files.",
            'errors' => $errors
        ], 200 );
    }

    public function check_loopback() {
        $url = admin_url( 'admin-ajax.php' );
        $args = [
            'timeout'   => 5,
            'cookies'   => [],
            'sslverify' => false, // We just want to check reachability
        ];
        
        $response = wp_remote_get( $url, $args );
        
        if ( is_wp_error( $response ) ) {
            return new \WP_REST_Response( [
                'status' => 'error',
                'message' => $response->get_error_message(),
                'code' => 0
            ], 200 );
        }
        
        $code = wp_remote_retrieve_response_code( $response );
        $message = wp_remote_retrieve_response_message( $response );
        
        return new \WP_REST_Response( [
            'status' => ( $code >= 200 && $code < 300 ) || $code === 400 ? 'ok' : 'error', // 400 is ok for admin-ajax with no action
            'code' => $code,
            'message' => $message
        ], 200 );
    }

    public function get_file_content( $request ) {
        $file = $request->get_param( 'file' );
        if ( empty( $file ) ) {
            return new \WP_Error( 'missing_file', 'No file specified.' );
        }

        // Security: Prevent traversal and ensure it's in root
        $path = ABSPATH . $file;
        
        if ( strpos( realpath($path), realpath(ABSPATH) ) !== 0 ) {
             return new \WP_Error( 'invalid_path', 'Invalid path.' );
        }
        
        if ( ! file_exists( $path ) ) {
             return new \WP_Error( 'file_not_found', 'File not found.' );
        }
        
        if ( is_dir( $path ) ) {
             return new \WP_Error( 'is_dir', 'Cannot read a directory.' );
        }

        // Limit size to avoid memory issues (e.g., 1MB)
        if ( filesize( $path ) > 1024 * 1024 ) {
            return new \WP_Error( 'file_too_large', 'File is too large to view.' );
        }

        $content = file_get_contents( $path );
        return new \WP_REST_Response( [
            'success' => true,
            'content' => $content
        ], 200 );
    }
}
