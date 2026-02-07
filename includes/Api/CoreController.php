<?php
namespace WPForceRepair\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CoreController {

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

		register_rest_route( 'wp-force-repair/v1', '/core/quarantine', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'quarantine_files' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
		] );

        register_rest_route( 'wp-force-repair/v1', '/core/quarantine/list', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_quarantined_files' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
		] );

        register_rest_route( 'wp-force-repair/v1', '/core/restore', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'restore_file' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
		] );

        register_rest_route( 'wp-force-repair/v1', '/core/quarantine/delete', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'delete_quarantined_file' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
		] );

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

		register_rest_route( 'wp-force-repair/v1', '/core/reinstall', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle_clean_reinstall' ],
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
     * Scans root directory for files that are NOT part of standard WP Core
     */
    public function scan_root_files() {
        $root_path = ABSPATH;
        $files = scandir( $root_path );
        $suspected = [];

        // Standard WP Root Files (excluding directories)
        $standard_files = [
            'index.php', 'wp-settings.php', 'wp-load.php', 'wp-blog-header.php', 
            'wp-config.php', 'wp-cron.php', 'wp-links-opml.php', 'wp-mail.php', 
            'wp-login.php', 'wp-signup.php', 'wp-trackback.php', 'xmlrpc.php', 
            'wp-comments-post.php', 'wp-activate.php', 'license.txt', 'readme.html', '.htaccess'
        ];

        // Standard Directories
        $standard_dirs = [ 'wp-admin', 'wp-includes', 'wp-content' ];

        foreach ( $files as $file ) {
            if ( $file === '.' || $file === '..' ) continue;
            
            // Skip known standard files and directories
            if ( in_array( $file, $standard_files ) ) continue;
            if ( is_dir( $root_path . $file ) && in_array( $file, $standard_dirs ) ) continue;

            // Whatever is left is "Unknown/Suspected"
            $suspected[] = [
                'name' => $file,
                'type' => is_dir( $root_path . $file ) ? 'directory' : 'file',
                'size' => size_format( filesize( $root_path . $file ) ),
                'mtime' => date( 'Y-m-d H:i:s', filemtime( $root_path . $file ) )
            ];
        }

        return new \WP_REST_Response( [
            'success' => true,
            'suspected_files' => $suspected
        ], 200 );
    }

    /**
     * Moves specified files to quarantine folder
     */
    public function quarantine_files( $request ) {
        $files_to_move = $request->get_param( 'files' ); // Array of filenames
        if ( empty( $files_to_move ) || ! is_array( $files_to_move ) ) {
            return new \WP_Error( 'invalid_params', 'No files specified', [ 'status' => 400 ] );
        }

        $upload_dir = wp_upload_dir();
        $quarantine_base = $upload_dir['basedir'] . '/wfr-quarantine/' . date('Y-m-d_H-i-s');
        
        if ( ! wp_mkdir_p( $quarantine_base ) ) {
            return new \WP_Error( 'fs_error', 'Could not create quarantine directory' );
        }

        $moved = [];
        $errors = [];

        foreach ( $files_to_move as $file ) {
            $source = ABSPATH . basename( $file ); // Sanitize slightly by just taking basename
            if ( file_exists( $source ) ) {
                if ( rename( $source, $quarantine_base . '/' . $file ) ) {
                    $moved[] = $file;
                } else {
                    $errors[] = "Failed to move $file";
                }
            }
        }

        return new \WP_REST_Response( [
            'success' => true,
            'moved' => $moved,
            'errors' => $errors,
            'location' => $quarantine_base
        ], 200 );
    }

    public function get_quarantined_files() {
        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'] . '/wfr-quarantine/';
        
        $structure = [];
        if ( file_exists( $base_dir ) ) {
            $folders = scandir( $base_dir );
            foreach ( $folders as $folder ) {
                if ( $folder === '.' || $folder === '..' ) continue;
                if ( is_dir( $base_dir . $folder ) ) {
                    $files = [];
                    $subfiles = scandir( $base_dir . $folder );
                    foreach ( $subfiles as $f ) {
                        if ( $f === '.' || $f === '..' ) continue;
                        $files[] = [
                            'name' => $f,
                            'path' => $folder . '/' . $f,
                            'size' => size_format( filesize( $base_dir . $folder . '/' . $f ) )
                        ];
                    }
                    $structure[] = [
                        'timestamp' => $folder,
                        'files' => $files
                    ];
                }
            }
        }
        
        // Sort by timestamp desc
        usort( $structure, function($a, $b) {
            return strcmp( $b['timestamp'], $a['timestamp'] );
        } );

        return new \WP_REST_Response( $structure, 200 );
    }

    public function restore_file( $request ) {
        $path = $request->get_param( 'path' ); // relative path e.g. "2023-10-10_12-00-00/myfile.php"
        if ( empty( $path ) ) {
            return new \WP_Error( 'missing_path', 'No file path specified', [ 'status' => 400 ] );
        }

        $upload_dir = wp_upload_dir();
        $full_source = $upload_dir['basedir'] . '/wfr-quarantine/' . $path;
        
        // Security check: ensure path is within quarantine
        if ( strpos( realpath( $full_source ), realpath( $upload_dir['basedir'] . '/wfr-quarantine/' ) ) !== 0 ) {
             return new \WP_Error( 'invalid_path', 'Invalid file path traversal detected.' );
        }

        if ( ! file_exists( $full_source ) ) {
            return new \WP_Error( 'file_not_found', 'File not found in quarantine.' );
        }

        $filename = basename( $full_source );
        $dest = ABSPATH . $filename;

        if ( file_exists( $dest ) ) {
            return new \WP_Error( 'file_exists', 'File already exists in root. Please delete it first or rename it.' );
        }

        if ( rename( $full_source, $dest ) ) {
            return new \WP_REST_Response( [ 'success' => true, 'message' => "Restored $filename to root." ], 200 );
        } else {
            return new \WP_Error( 'restore_failed', 'Failed to move file back to root.' );
        }
    }

    public function delete_quarantined_file( $request ) {
        $path = $request->get_param( 'path' );
        if ( empty( $path ) ) {
            return new \WP_Error( 'missing_path', 'No file path specified', [ 'status' => 400 ] );
        }

        $upload_dir = wp_upload_dir();
        $full_source = $upload_dir['basedir'] . '/wfr-quarantine/' . $path;
        
        // Security check
        if ( strpos( realpath( $full_source ), realpath( $upload_dir['basedir'] . '/wfr-quarantine/' ) ) !== 0 ) {
             return new \WP_Error( 'invalid_path', 'Invalid file path traversal detected.' );
        }

        if ( ! file_exists( $full_source ) ) {
            return new \WP_Error( 'file_not_found', 'File not found in quarantine.' );
        }

        if ( unlink( $full_source ) ) {
            // Check if folder is empty, if so delete folder
            $dir = dirname( $full_source );
            $files = scandir( $dir );
             // scandir returns . and .. so empty is count 2
            if ( count( $files ) <= 2 ) {
                rmdir( $dir ); // clean up empty timestamp folder
            }
            return new \WP_REST_Response( [ 'success' => true, 'message' => "Permanently deleted file." ], 200 );
        } else {
            return new \WP_Error( 'delete_failed', 'Failed to delete file.' );
        }
    }

	public function handle_clean_reinstall( $request ) {
        // Increases time limit for large operations
        @set_time_limit( 300 ); 

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/update.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/class-core-upgrader.php';

        $logs = [];
        $logs[] = "Initializing Clean Core Reinstall...";

        try {
            // 1. BACKUP WP-CONFIG
            $config_path = ABSPATH . 'wp-config.php';
            $config_content = '';
            if ( file_exists( $config_path ) ) {
                $config_content = file_get_contents( $config_path );
                $logs[] = "Backed up wp-config.php to memory.";
            } else {
                // Try one level up
                 if ( file_exists( dirname( ABSPATH ) . '/wp-config.php' ) ) {
                     $config_content = file_get_contents( dirname( ABSPATH ) . '/wp-config.php' );
                     $logs[] = "Backed up wp-config.php (parent dir) to memory.";
                 } else {
                     throw new \Exception( 'Cannot find wp-config.php! Aborting for safety.' );
                 }
            }

            // 2. DOWNLOAD LATEST WORDPRESS
            $version = $request->get_param('version') ?: 'latest';
            $download_url = "https://wordpress.org/wordpress-{$version}.zip"; 
            if ( $version === 'latest' ) {
                 $download_url = "https://wordpress.org/latest.zip";
            }
            
            $logs[] = "Downloading WordPress ($version)...";
            $temp_file = download_url( $download_url );
            if ( is_wp_error( $temp_file ) ) {
                throw new \Exception( 'Download failed: ' . $temp_file->get_error_message() );
            }

            // 3. AUTO-QUARANTINE UNKNOWNS (Optional)
           $do_quarantine = $request->get_param( 'quarantine_unknowns' );
           
           if ( $do_quarantine ) {
               $logs[] = "Scanning and quarantining unknown files...";
               $scan = $this->scan_root_files();
               $suspected = $scan->data['suspected_files'];
               
               if ( ! empty( $suspected ) ) {
                   $files_to_q = array_column( $suspected, 'name' );
                   $quarantine_request = new \WP_REST_Request( 'POST', '/wp-force-repair/v1/core/quarantine' );
                   $quarantine_request->set_body_params( [ 'files' => $files_to_q ] );
                   $this->quarantine_files( $quarantine_request );
                   $logs[] = "Quarantined " . count( $files_to_q ) . " unknown files.";
               } else {
                   $logs[] = "No unknown files found to quarantine.";
               }
           } else {
               $logs[] = "Skipping quarantine (only replacing Core files).";
           }

            // 4. UNZIP
            $logs[] = "Unzipping package...";
            global $wp_filesystem;
            if ( ! $wp_filesystem ) {
                if ( false === WP_Filesystem() ) {
                     throw new \Exception( 'Could not initialize WP_Filesystem.' );
                }
            }
            
            $upgrade_folder = $wp_filesystem->wp_content_dir() . 'upgrade/';
            $wp_filesystem->mkdir( $upgrade_folder );
            $unzip_result = unzip_file( $temp_file, $upgrade_folder );
            
            if ( is_wp_error( $unzip_result ) ) {
                throw new \Exception( 'Unzip failed: ' . $unzip_result->get_error_message() );
            }
            
            $source_dir = $upgrade_folder . 'wordpress/'; 
            
            // 5. SURGICAL REPLACEMENT
            
            // Replace wp-admin
            $logs[] = "Replacing wp-admin...";
            $wp_filesystem->delete( ABSPATH . 'wp-admin', true );
            $wp_filesystem->move( $source_dir . 'wp-admin', ABSPATH . 'wp-admin', true );
            
            // Replace wp-includes
            $logs[] = "Replacing wp-includes...";
            $wp_filesystem->delete( ABSPATH . 'wp-includes', true );
            $wp_filesystem->move( $source_dir . 'wp-includes', ABSPATH . 'wp-includes', true );
            
            // Replace Root Files
            $logs[] = "Replacing root files...";
            $root_files = $wp_filesystem->dirlist( $source_dir );

            if ( $root_files ) {
                foreach ( $root_files as $file ) {
                    if ( $file['type'] === 'f' ) {
                        $filename = $file['name'];
                        $wp_filesystem->move( $source_dir . $filename, ABSPATH . $filename, true );
                    }
                }
            }
            
            // 6. RESTORE CONFIG CHECK
            if ( ! file_exists( $config_path ) && ! file_exists( dirname( ABSPATH ) . '/wp-config.php' ) ) {
                $logs[] = "⚠️ wp-config.php missing! Restoring from memory...";
                file_put_contents( $config_path, $config_content );
            }

            // Cleanup
            $wp_filesystem->delete( $upgrade_folder, true );
            if ( file_exists( $temp_file ) ) {
                unlink( $temp_file );
            }

            return new \WP_REST_Response( [
                'success' => true,
                'message' => 'WordPress Core Clean Reinstall Complete.',
                'logs'    => $logs
            ], 200 );

        } catch ( \Throwable $e ) {
            // Cleanup temp file if exists
            if ( isset( $temp_file ) && file_exists( $temp_file ) ) {
                unlink( $temp_file );
            }
            
            return new \WP_REST_Response( [
                'success' => false,
                'message' => 'Critical Error: ' . $e->getMessage(),
                'logs'    => array_merge( $logs, [ 'FATAL ERROR: ' . $e->getMessage(), 'Trace: ' . $e->getTraceAsString() ] )
            ], 500 );
        }
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
        $path = ABSPATH . basename( $file );
        
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
