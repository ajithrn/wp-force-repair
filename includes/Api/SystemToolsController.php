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
        // Attempt to increase limits
        @set_time_limit( 0 );
        @ini_set( 'memory_limit', '256M' );

        $root = ABSPATH;
        
        try {
            $dir_iterator = new \RecursiveDirectoryIterator( $root, \RecursiveDirectoryIterator::SKIP_DOTS );
            
            // Use filter to prevent entering forbidden directories (avoids permission errors)
            $filter = new \RecursiveCallbackFilterIterator( $dir_iterator, function( $current, $key, $iterator ) {
                // Allow non-directories
                if ( ! $current->isDir() ) return true;

                $name = $current->getFilename();
                
                // Exclude dot folders (like .git, .idea) - except the root parts if needed, but usually safe to skip hidden config dirs
                if ( substr( $name, 0, 1 ) === '.' ) return false;

                // Exclude specific heavy/protected folders
                $forbidden = [ 'node_modules', 'wfr-quarantine', 'wfr-backups', 'vendor' ];
                if ( in_array( $name, $forbidden ) ) return false;

                return true;
            });

            $iterator = new \RecursiveIteratorIterator(
                $filter, 
                \RecursiveIteratorIterator::SELF_FIRST
            );
        } catch ( \Exception $e ) {
            return new \WP_REST_Response( [
                'success' => false,
                'message' => 'Failed to initialize file scanning: ' . $e->getMessage()
            ], 500 );
        }

        $count_dirs = 0;
        $count_files = 0;
        $errors = 0;
        $start_time = time();
        $limit_hit = false;

        try {
            foreach ( $iterator as $item ) {
                // Safety: Stop if running too long (e.g., > 20 seconds) to prevent Critical Error (Timeout)
                if ( time() - $start_time > 20 ) {
                    $limit_hit = true;
                    break;
                }

                // Double check exclusion (redundant but safe)
                if ( strpos( $item->getPathname(), 'wfr-quarantine' ) !== false ) continue;

                try {
                    $path = $item->getPathname();
                    if ( $item->isDir() ) {
                         // Only chmod if current perms are not correct (save I/O)
                         if ( ( fileperms( $path ) & 0777 ) !== 0755 ) {
                            if ( @chmod( $path, 0755 ) ) {
                                $count_dirs++;
                            } else {
                                $errors++;
                            }
                         }
                    } else {
                        if ( ( fileperms( $path ) & 0777 ) !== 0644 ) {
                            if ( @chmod( $path, 0644 ) ) {
                                $count_files++;
                            } else {
                                $errors++;
                            }
                        }
                    }
                } catch ( \Exception $e ) {
                    // Ignore individual file errors
                    $errors++;
                }
            }
        } catch ( \Exception $e ) {
            // Iterator/Directory access error
            return new \WP_REST_Response( [
                'success' => false,
                'message' => 'Scanning error (likely permission denied on folder): ' . $e->getMessage()
            ], 500 );
        }

        // Also fix root
        @chmod( $root, 0755 );

        $msg = "Permissions Reset Complete. Fixed $count_dirs folders and $count_files files.";
        if ( $limit_hit ) {
            $msg = "Partial Fix: Time limit reached. Fixed $count_dirs folders and $count_files files. Please Run Again to continue.";
        }

        return new \WP_REST_Response( [
            'success' => true,
            'message' => $msg,
            'errors' => $errors,
            'partial' => $limit_hit
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

        $real_path = realpath( $path );
        $real_root = realpath( ABSPATH );

        if ( $real_path === false || strpos( $real_path, $real_root ) !== 0 ) {
            return new \WP_Error( 'invalid_path', 'Invalid path.' );
        }

        if ( ! file_exists( $real_path ) ) {
            return new \WP_Error( 'file_not_found', 'File not found.' );
        }

        if ( is_dir( $real_path ) ) {
            return new \WP_Error( 'is_dir', 'Cannot read a directory.' );
        }

        $ext = strtolower( pathinfo( $real_path, PATHINFO_EXTENSION ) );

        // --- IMAGE FILES ---
        $image_exts = [ 'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'ico' ];
        if ( in_array( $ext, $image_exts ) ) {
            // Size cap: 5MB for images encoded as base64
            if ( filesize( $real_path ) > 5 * 1024 * 1024 ) {
                return new \WP_REST_Response( [
                    'type'    => 'too_large',
                    'message' => 'Image is too large to preview (> 5MB).',
                    'name'    => basename( $real_path ),
                    'size'    => size_format( filesize( $real_path ) ),
                ], 200 );
            }
            $mime_map = [
                'jpg'  => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png'  => 'image/png',
                'gif'  => 'image/gif',
                'webp' => 'image/webp',
                'bmp'  => 'image/bmp',
                'ico'  => 'image/x-icon',
            ];
            $mime      = $mime_map[ $ext ] ?? 'image/jpeg';
            $b64       = base64_encode( file_get_contents( $real_path ) );
            $data_uri  = "data:{$mime};base64,{$b64}";
            return new \WP_REST_Response( [
                'type'     => 'image',
                'name'     => basename( $real_path ),
                'mime'     => $mime,
                'data_uri' => $data_uri,
                'size'     => size_format( filesize( $real_path ) ),
            ], 200 );
        }

        // --- SVG (text-based image, safe to display as code OR as inline image) ---
        if ( $ext === 'svg' ) {
            if ( filesize( $real_path ) > 512 * 1024 ) {
                return new \WP_REST_Response( [
                    'type'    => 'too_large',
                    'message' => 'SVG is too large to preview.',
                    'name'    => basename( $real_path ),
                    'size'    => size_format( filesize( $real_path ) ),
                ], 200 );
            }
            $b64      = base64_encode( file_get_contents( $real_path ) );
            $data_uri = "data:image/svg+xml;base64,{$b64}";
            return new \WP_REST_Response( [
                'type'     => 'image',
                'name'     => basename( $real_path ),
                'mime'     => 'image/svg+xml',
                'data_uri' => $data_uri,
                'size'     => size_format( filesize( $real_path ) ),
            ], 200 );
        }

        // --- ARCHIVE / BINARY FILES ---
        $binary_exts = [ 'zip', 'tar', 'gz', 'tgz', 'rar', '7z', 'bz2', 'xz',
                         'exe', 'dll', 'so', 'bin', 'iso', 'dmg', 'pkg',
                         'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
                         'mp3', 'mp4', 'avi', 'mov', 'wav', 'ogg', 'flac',
                         'ttf', 'otf', 'woff', 'woff2', 'eot' ];
        if ( in_array( $ext, $binary_exts ) ) {
            $size = filesize( $real_path );
            return new \WP_REST_Response( [
                'type'    => 'binary',
                'name'    => basename( $real_path ),
                'ext'     => $ext,
                'size'    => size_format( $size ),
                'message' => "Binary files cannot be previewed in the browser.",
            ], 200 );
        }

        // --- TEXT / CODE FILES ---
        // Size cap: 1MB
        if ( filesize( $real_path ) > 1024 * 1024 ) {
            return new \WP_REST_Response( [
                'type'    => 'too_large',
                'message' => 'File is too large to view (> 1MB). Please download it.',
                'name'    => basename( $real_path ),
                'size'    => size_format( filesize( $real_path ) ),
            ], 200 );
        }

        $content = file_get_contents( $real_path );

        // Detect if content looks binary (null bytes in first 8KB)
        $sample = substr( $content, 0, 8192 );
        if ( strpos( $sample, "\x00" ) !== false ) {
            return new \WP_REST_Response( [
                'type'    => 'binary',
                'name'    => basename( $real_path ),
                'ext'     => $ext,
                'size'    => size_format( filesize( $real_path ) ),
                'message' => "This file appears to be binary and cannot be previewed.",
            ], 200 );
        }

        return new \WP_REST_Response( [
            'type'    => 'text',
            'name'    => basename( $real_path ),
            'ext'     => $ext,
            'content' => $content,
        ], 200 );
    }
}
