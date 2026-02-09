<?php
namespace WPForceRepair\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class QuarantineController extends \WP_REST_Controller {

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
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

        register_rest_route( 'wp-force-repair/v1', '/core/quarantine/delete-folder', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'delete_quarantine_folder' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
		] );
    }

    private function get_storage_path() {
        // Use standard WordPress uploads directory for universal compatibility
        $upload_dir_info = wp_upload_dir();
        return $upload_dir_info['basedir'] . '/wfr-quarantine';
    }

    /**
     * Moves specified files to quarantine folder
     */
    public function quarantine_files( $request ) {
        $files_to_move = $request->get_param( 'files' ); // Array of relative paths
        if ( empty( $files_to_move ) || ! is_array( $files_to_move ) ) {
            return new \WP_Error( 'invalid_params', 'No files specified', [ 'status' => 400 ] );
        }

        $quarantine_main = $this->get_storage_path();
        $quarantine_base = $quarantine_main . '/' . date('Y-m-d_H');
        
        // Ensure main quarantine dir exists and is secure (it should be from get_storage_path but check security)
        @chmod( $quarantine_main, 0700 ); // Strict: Owner only
        
        // 1. Secure with .htaccess (Apache) - Overwrite to ensure update
        $htaccess_content = "# Protect Quarantine\n" .
                            "<IfModule mod_authz_core.c>\n" .
                            "    Require all denied\n" .
                            "</IfModule>\n" .
                            "<IfModule !mod_authz_core.c>\n" .
                            "    Order Deny,Allow\n" .
                            "    Deny from all\n" .
                            "</IfModule>\n" .
                            "Options -Indexes";
        file_put_contents( $quarantine_main . '/.htaccess', $htaccess_content );

        // 2. Secure with web.config (IIS)
        $web_config_content = '<configuration><system.webServer><authorization><deny users="*" /></authorization></system.webServer></configuration>';
        if ( ! file_exists( $quarantine_main . '/web.config' ) ) {
             file_put_contents( $quarantine_main . '/web.config', $web_config_content );
        }

        // 3. Create index.php to prevent listing if mod_rewrite fails
        if ( ! file_exists( $quarantine_main . '/index.php' ) ) {
            file_put_contents( $quarantine_main . '/index.php', "<?php // Silence is golden." );
        }

        if ( ! wp_mkdir_p( $quarantine_base ) ) {
            return new \WP_Error( 'fs_error', 'Could not create quarantine directory. Check permissions for ' . $quarantine_main );
        }
        @chmod( $quarantine_base, 0700 ); // Strict: Owner only

        $moved = [];
        $errors = [];

        foreach ( $files_to_move as $rel_path ) {
            // Security: Prevent traversal
            if ( strpos( $rel_path, '..' ) !== false ) {
                $errors[] = "Invalid path: $rel_path";
                continue;
            }

            // CRITICAL: Prevent moving essential WP folders/files
            // We check against the relative path provided.
            // Protected paths are relative to ABSPATH (root).
            $protected_paths = [
                'wp-admin', 
                'wp-includes', 
                'wp-content', 
                'wp-config.php',
                // Protect specific wp-content folders too (if relative path matches exactly)
                'wp-content/themes',
                'wp-content/plugins',
                'wp-content/mu-plugins',
                'wp-content/uploads',
                'wp-content/upgrade',
                'wp-content/wfr-quarantine',
                'wp-content/wfr-backups',
                'wp-content/index.php'
            ];
            
            $clean_path = trim( $rel_path, '/' );
            
            // Normalize path to check exactly against critical items
            // We only care about the top-level item being moved.
            // If moves 'wp-content', blocked.
            if ( in_array( $clean_path, $protected_paths ) ) {
                $errors[] = "Protected system path: $rel_path";
                continue;
            }

            // Also protect sub-paths of wfr-quarantine and wfr-backups to prevent recursion issues
            if ( strpos( $clean_path, 'wp-content/wfr-quarantine' ) === 0 || strpos( $clean_path, 'wp-content/wfr-backups' ) === 0 ) {
                 $errors[] = "Cannot quarantine internal storage folders.";
                 continue;
            }

            $source = ABSPATH . $rel_path;
            
            if ( file_exists( $source ) ) {
                $dest_name = basename( $rel_path );
                // Random suffix to prevent guessing URL
                $random_suffix = '.' . substr( md5( uniqid( rand(), true ) ), 0, 8 ); 
                $dest_path = $quarantine_base . '/' . $dest_name . '.quarantined' . $random_suffix;
                
                if ( rename( $source, $dest_path ) ) {
                    // SECURE THE FILE: Remove execution/read permissions for world
                    @chmod( $dest_path, 0600 );
                    
                    // SAVE METADATA: needed to restore to correct subdirectory
                    $meta_path = $dest_path . '.json';
                    $metadata = [
                        'original_path' => $rel_path,
                        'quarantined_at' => time()
                    ];
                    file_put_contents( $meta_path, json_encode( $metadata ) );
                    @chmod( $meta_path, 0600 );

                    $moved[] = $rel_path;
                } else {
                    $errors[] = "Failed to move $rel_path";
                }
            } else {
                 $errors[] = "File not found: $rel_path";
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
        $base_dir = $this->get_storage_path() . '/';
        
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
                        
                        // Skip metadata files in listing
                        if ( substr( $f, -5 ) === '.json' ) continue;

                        // Parse original name from randomized name
                        // Format: original.php.quarantined.xyz123
                        // We strip .quarantined.*
                        $clean_name = preg_replace( '/\.quarantined\.[a-f0-9]+$/', '', $f );
                        // Also fallback for old format (just .quarantined)
                        if ( $clean_name === $f ) {
                            $clean_name = str_replace( '.quarantined', '', $f );
                        }
                        
                        // Check for metadata
                        $meta_path = $base_dir . $folder . '/' . $f . '.json';
                        $original_path = $clean_name; // Default fallback
                        
                        if ( file_exists( $meta_path ) ) {
                            $meta = json_decode( file_get_contents( $meta_path ), true );
                            if ( $meta && isset( $meta['original_path'] ) ) {
                                $original_path = $meta['original_path'];
                            }
                        }

                        $files[] = [
                            'name' => $f, // Real filename on disk (randomized)
                            'original_name' => $clean_name, // Human readable
                            'original_path' => $original_path, // Full relative path including subdirs
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
        $path = $request->get_param( 'path' ); // relative path e.g. "2023-10.../myfile.php.quarantined.abc"
        if ( empty( $path ) ) {
            return new \WP_Error( 'missing_path', 'No file path specified', [ 'status' => 400 ] );
        }

        $base_dir = $this->get_storage_path();
        $full_source = $base_dir . '/' . $path;
        
        if ( ! file_exists( $full_source ) ) {
            return new \WP_Error( 'file_not_found', 'File not found in quarantine.' );
        }

        // Security check: ensure path is within quarantine
        if ( strpos( realpath( $full_source ), realpath( $base_dir ) ) !== 0 ) {
             return new \WP_Error( 'invalid_path', 'Invalid file path traversal detected.' );
        }

        $filename = basename( $full_source );
        $meta_path = $full_source . '.json';
        
        $dest_rel_path = '';

        // Try to get original path from metadata
        if ( file_exists( $meta_path ) ) {
            $meta = json_decode( file_get_contents( $meta_path ), true );
            if ( $meta && isset( $meta['original_path'] ) ) {
                $dest_rel_path = $meta['original_path'];
            }
        }

        // Fallback if no metadata (old quarantines)
        if ( empty( $dest_rel_path ) ) {
             // Remove .quarantined suffix if present (including random part)
            $dest_rel_path = preg_replace( '/\.quarantined(\.[a-f0-9]+)?$/', '', $filename );
        }
        
        // Ensure path starts with no slash
        $dest_rel_path = ltrim( $dest_rel_path, '/' );
        
        $dest = ABSPATH . $dest_rel_path;
        $dest_dir = dirname( $dest );

        // Ensure destination directory exists!
        if ( ! is_dir( $dest_dir ) ) {
             if ( ! wp_mkdir_p( $dest_dir ) ) {
                 return new \WP_Error( 'mkdir_failed', "Could not create directory: $dest_dir" );
             }
        }

        if ( file_exists( $dest ) ) {
            return new \WP_Error( 'file_exists', "File already exists at destination ($dest_rel_path). Please delete it first or rename it." );
        }

        if ( rename( $full_source, $dest ) ) {
            // Restore permissions: 0755 for folders, 0644 for files.
            $perms = is_dir( $dest ) ? 0755 : 0644;
            @chmod( $dest, $perms );

            // Cleanup metadata
            if ( file_exists( $meta_path ) ) unlink( $meta_path );

            // Check if folder is empty (ignoring .DS_Store), if so delete folder
            $dir = dirname( $full_source );
            $this->maybe_delete_empty_folder( $dir );

            return new \WP_REST_Response( [ 'success' => true, 'message' => "Restored to $dest_rel_path" ], 200 );
        } else {
            return new \WP_Error( 'restore_failed', 'Failed to move file back.' );
        }
    }

    public function delete_quarantined_file( $request ) {
        $path = $request->get_param( 'path' );
        if ( empty( $path ) ) {
            return new \WP_Error( 'missing_path', 'No file path specified', [ 'status' => 400 ] );
        }

        $base_dir = $this->get_storage_path();
        $full_source = $base_dir . '/' . $path;
        
        if ( ! file_exists( $full_source ) ) {
            return new \WP_Error( 'file_not_found', 'File not found in quarantine.' );
        }

        // Security check
        if ( strpos( realpath( $full_source ), realpath( $base_dir ) ) !== 0 ) {
             return new \WP_Error( 'invalid_path', 'Invalid file path traversal detected.' );
        }

        if ( unlink( $full_source ) ) {
            // Cleanup metadata
            $meta_path = $full_source . '.json';
            if ( file_exists( $meta_path ) ) unlink( $meta_path );

            // Check if folder is empty (ignoring .DS_Store), if so delete folder
            $dir = dirname( $full_source );
            $this->maybe_delete_empty_folder( $dir );

            return new \WP_REST_Response( [ 'success' => true, 'message' => "Permanently deleted file." ], 200 );
        } else {
            return new \WP_Error( 'delete_failed', 'Failed to delete file.' );
        }
    }

    public function delete_quarantine_folder( $request ) {
        $folder = $request->get_param( 'folder' );
        if ( empty( $folder ) ) {
            return new \WP_Error( 'missing_folder', 'No folder specified', [ 'status' => 400 ] );
        }

        $base_dir = $this->get_storage_path();
        $full_path = $base_dir . '/' . $folder;

        // Security check
        if ( strpos( realpath( $full_path ), realpath( $base_dir ) ) !== 0 ) {
             return new \WP_Error( 'invalid_path', 'Invalid folder path.' );
        }

        if ( ! is_dir( $full_path ) ) {
            return new \WP_Error( 'folder_not_found', 'Folder not found.' );
        }

        // Check content
        $files = scandir( $full_path );
        $count = 0;
        foreach ( $files as $f ) {
            if ( $f === '.' || $f === '..' ) continue;
            // Ignore system files AND metadata files when checking if "empty"
            if ( $f === '.DS_Store' || substr( $f, -5 ) === '.json' ) {
                continue;
            }
            $count++;
        }

        if ( $count > 0 ) {
             return new \WP_Error( 'not_empty', 'Folder is not empty. Delete files first.' );
        }
        
        // If we are here, it's "empty" of real files. Clean up junk before deleting.
        $junk_files = scandir( $full_path );
        foreach ( $junk_files as $f ) {
            if ( $f === '.' || $f === '..' ) continue;
            @unlink( $full_path . '/' . $f );
        }

        if ( rmdir( $full_path ) ) {
            return new \WP_REST_Response( [ 'success' => true, 'message' => "Folder deleted." ], 200 );
        } else {
            return new \WP_Error( 'delete_failed', 'Failed to delete folder.' );
        }
    }

    private function maybe_delete_empty_folder( $dir ) {
        if ( ! is_dir( $dir ) ) return;
        
        $files = scandir( $dir );
        $is_empty = true;
        foreach ( $files as $f ) {
            if ( $f === '.' || $f === '..' ) continue;
            if ( $f === '.DS_Store' ) {
                // If only .DS_Store remains, ignore it for now, check if anything else exists
            } else {
                $is_empty = false;
                break;
            }
        }

        if ( $is_empty ) {
            // Delete any potential .DS_Store before rmdir
            if ( file_exists( $dir . '/.DS_Store' ) ) unlink( $dir . '/.DS_Store' );
            rmdir( $dir );
        }
    }
}
