<?php

namespace WPForceRepair\Api;

class BackupController extends \WP_REST_Controller {

    private $backup_dir;
    private $upload_url;

    public function __construct() {
        $upload_dir = wp_upload_dir();
        $this->backup_dir = $upload_dir['basedir'] . '/wfr-backups/';
        $this->upload_url = $upload_dir['baseurl'] . '/wfr-backups/';
        
        if ( ! file_exists( $this->backup_dir ) ) {
            wp_mkdir_p( $this->backup_dir );
            // Secure directory
            file_put_contents( $this->backup_dir . 'index.php', '<?php // Silence is golden' );
            file_put_contents( $this->backup_dir . '.htaccess', 'Options -Indexes' );
        }

        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        register_rest_route( 'wp-force-repair/v1', '/backup/capabilities', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'check_capabilities' ],
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
        ] );

        register_rest_route( 'wp-force-repair/v1', '/backup/create', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'create_backup' ],
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
        ] );

        register_rest_route( 'wp-force-repair/v1', '/backup/delete', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'delete_backup' ],
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
        ] );
    }

    public function check_capabilities() {
        $caps = [
            'zip_archive' => class_exists( 'ZipArchive' ),
            'shell_exec'  => function_exists( 'shell_exec' ) && ! in_array( 'shell_exec', array_map( 'trim', explode( ',', ini_get( 'disable_functions' ) ) ) ),
            'memory_limit' => ini_get( 'memory_limit' ),
            'time_limit'   => function_exists( 'set_time_limit' ),
        ];

        return new \WP_REST_Response( $caps, 200 );
    }

    public function create_backup( $request ) {
        // Clear any previous output (warnings, notices, whitespace)
        if ( ob_get_length() ) ob_clean();
        
        // Start fresh buffer
        ob_start();

        // Register shutdown function to catch Fatal Errors (Timeouts/OOM)
        register_shutdown_function( function() {
            $error = error_get_last();
            if ( $error && in_array( $error['type'], [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ] ) ) {
                // If we are here, script died. Clean buffer and output JSON.
                ob_clean(); 
                // Set correct header
                if ( ! headers_sent() ) {
                    header( 'Content-Type: application/json; charset=UTF-8' );
                    http_response_code( 500 );
                }
                echo json_encode( [
                    'code' => 'fatal_error',
                    'message' => 'Process terminated unexpectedly: ' . $error['message'],
                    'data' => [ 'status' => 500 ]
                ] );
                exit;
            }
        } );

        @set_time_limit( 0 ); // Infinite time
        $type = $request->get_param( 'type' ); // 'db' or 'files'
        $exclude_media = $request->get_param( 'exclude_media' );
        
        $site_name = sanitize_title( get_bloginfo( 'name' ) );
        if ( empty( $site_name ) ) $site_name = 'site';
        
        // Prevent session locking
        if ( session_status() === PHP_SESSION_ACTIVE ) {
            session_write_close();
        }

        $filename = 'backup-' . $site_name . '-' . $type . '-' . date('Y-m-d_H-i-s');
        $file_path = '';

        try {
            if ( $type === 'db' ) {
                $filename .= '.sql';
                $file_path = $this->backup_dir . $filename;
                $this->dump_database( $file_path );
                
                // Compress if zip available
                if ( class_exists( 'ZipArchive' ) ) {
                    $zip = new \ZipArchive();
                    $zip_path = $file_path . '.zip';
                    if ( $zip->open( $zip_path, \ZipArchive::CREATE ) === TRUE ) {
                        $zip->addFile( $file_path, $filename );
                        $zip->close();
                        unlink( $file_path ); // Remove raw sql
                        $filename .= '.zip';
                        $file_path = $zip_path;
                    }
                }
            } elseif ( $type === 'files' ) {
                if ( ! class_exists( 'ZipArchive' ) ) {
                    throw new \Exception( 'ZipArchive extension missing on server.' );
                }
                $filename .= '.zip';
                $file_path = $this->backup_dir . $filename;
                $this->zip_files( $file_path, $exclude_media );
            } else {
                 throw new \Exception( 'Invalid backup type.' );
            }
            
            // Success! Clean buffer before returning
            ob_end_clean();

            return new \WP_REST_Response( [
                'success' => true,
                'url' => $this->upload_url . $filename,
                'file' => $filename,
                'message' => 'Backup created successfully.'
            ], 200 );

        } catch ( \Exception $e ) {
            ob_end_clean();
            return new \WP_Error( 'backup_failed', $e->getMessage() );
        }
    }

    private function dump_database( $output_file ) {
        if ( function_exists( 'shell_exec' ) && ! in_array( 'shell_exec', array_map( 'trim', explode( ',', ini_get( 'disable_functions' ) ) ) ) ) {
            // Try mysqldump
            $host = DB_HOST;
            $user = DB_USER;
            $pass = DB_PASSWORD;
            $name = DB_NAME;
            
            // Check if socket is in host
            $socket = '';
            if ( strpos( $host, ':' ) !== false ) {
                $parts = explode( ':', $host );
                $host = $parts[0];
                if ( is_numeric( $parts[1] ) ) {
                    $port = $parts[1];
                } else {
                    $socket = $parts[1];
                }
            }

            $cmd = "mysqldump --host=" . escapeshellarg($host) . " --user=" . escapeshellarg($user) . " --password=" . escapeshellarg($pass) . " " . escapeshellarg($name) . " > " . escapeshellarg($output_file);
            
            shell_exec( $cmd );

            if ( file_exists( $output_file ) && filesize( $output_file ) > 0 ) {
                return;
            }
        }

        // Fallback: PHP Dump
        $this->php_db_dump( $output_file );
    }

    private function php_db_dump( $output_file ) {
        global $wpdb;
        $tables = $wpdb->get_results( 'SHOW TABLES', ARRAY_N );

        $handle = fopen( $output_file, 'w' );
        if ( ! $handle ) throw new \Exception( "Cannot open file for writing: $output_file" );

        foreach ( $tables as $table ) {
            $table_name = $table[0];
            $create_table = $wpdb->get_row( "SHOW CREATE TABLE $table_name", ARRAY_N );
            fwrite( $handle, "\n\n" . $create_table[1] . ";\n\n" );

            $rows = $wpdb->get_results( "SELECT * FROM $table_name", ARRAY_A );
            foreach ( $rows as $row ) {
                $row = array_map( [ $wpdb, '_real_escape' ], $row );
                $sql = "INSERT INTO $table_name VALUES('" . implode( "','", $row ) . "');\n";
                fwrite( $handle, $sql );
            }
        }
        fclose( $handle );
    }

    private function zip_files( $output_file, $exclude_media = false ) {
        // IMPROVEMENT: Prevent client disconnect from killing process
        ignore_user_abort( true );
        @set_time_limit( 0 );
        @ini_set( 'memory_limit', '-1' );
        @ini_set( 'max_execution_time', 0 ); // Redundant but safe
        
        $rootPath = realpath( ABSPATH );
        $excludes = [ 'node_modules', '.git', 'wfr-backups' ];
        
        // Exclude uploads if requested
        if ( $exclude_media ) {
            $excludes[] = 'wp-content/uploads';
        }
        $disk_space_msg = '';

        // Check disk space if possible
        if ( function_exists( 'disk_free_space' ) ) {
            $free = @disk_free_space( dirname( $output_file ) );
            if ( $free !== false && $free < 100 * 1024 * 1024 ) { // Less than 100MB
                $disk_space_msg = " [WARNING: Low Disk Space: " . size_format( $free ) . "]";
            }
        }
        
        // 1. Try Shell Exec (Fastest, Robust)
        $shell_error = '';
        if ( function_exists( 'shell_exec' ) && ! in_array( 'shell_exec', array_map( 'trim', explode( ',', ini_get( 'disable_functions' ) ) ) ) ) {
            $exclude_args = '';
            foreach ($excludes as $ex) {
                $exclude_args .= " -x '*/$ex/*'";
            }
            $exclude_args .= " -x '" . basename($output_file) . "'";
            
            // Smart Exclusions
            $exclude_args .= " -x 'wp-content/*backup*'"; 
            $exclude_args .= " -x 'wp-content/uploads/*backup*'";

            // Capture stderr to see WHY it fails
            $cmd = "cd " . escapeshellarg($rootPath) . " && zip -r " . escapeshellarg($output_file) . " . $exclude_args 2>&1";
            
            $output = shell_exec($cmd);

            // Verify success
            if ( file_exists( $output_file ) && filesize( $output_file ) > 100 ) {
                return; // Success
            }
            
            // Capture failure reason
            $shell_error = " (Shell Error: " . substr(strip_tags($output), 0, 100) . ")";
        }

        // 2. Fallback: PHP ZipArchive (Memory & I/O Intensive)
        $zip = new \ZipArchive();
        $code = $zip->open( $output_file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE );
        
        if ( $code !== TRUE ) {
            $err = $this->get_zip_error_message( $code );
            throw new \Exception( "Cannot create zip file. Error: $err" . $disk_space_msg . $shell_error );
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator( $rootPath ),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        $count = 0;
        $batch_count = 0;
        $batch_limit = 500; // Close/Reopen every 500 files to save state and prevent massive write timeout

        foreach ( $files as $name => $file ) {
            if ( $file->isDir() ) continue;

            $filePath = $file->getRealPath();
            $relativePath = substr( $filePath, strlen( $rootPath ) + 1 );

            // 1. Standard Checks
            foreach ( $excludes as $exclude ) {
                if ( strpos( $relativePath, $exclude ) !== false ) continue 2; 
            }

            // 2. Smart Backup Exclusion
            if ( strpos( $relativePath, 'wp-content/' ) === 0 ) {
                $parts = explode( '/', $relativePath );
                if ( isset( $parts[1] ) ) {
                    $sub = $parts[1];
                    // Case A: Root of wp-content
                    if ( $sub !== 'plugins' && $sub !== 'themes' && strpos( strtolower($sub), 'backup' ) !== false ) {
                        continue;
                    }
                    // Case B: Uploads folder
                    if ( $sub === 'uploads' && isset( $parts[2] ) ) {
                        $uploadSub = $parts[2];
                        if ( strpos( strtolower($uploadSub), 'backup' ) !== false ) {
                             continue;
                        }
                    }
                }
            }

            // Attempt to add
            if ( ! $zip->addFile( $filePath, $relativePath ) ) {
                continue; 
            }
            $count++;
            $batch_count++;
            
            // Incremental Save to prevent timeout
            if ( $batch_count >= $batch_limit ) {
                if ( $zip->close() === TRUE ) {
                     // Re-open
                     $zip->open( $output_file ); 
                     $batch_count = 0;
                     // Reset timer if allowed (some hosts disable this)
                     @set_time_limit( 0 );
                } else {
                     throw new \Exception("Intermediate Zip Save failed. Disk full?");
                }
            }
        }
        
        if ( $count === 0 ) {
            $zip->close();
            @unlink($output_file);
            throw new \Exception("No files found to zip. Check permissions or path.");
        }

        // Final Close
        if ( $zip->close() !== TRUE ) {
            @unlink($output_file);
            throw new \Exception( "Zip failure during write (Timeout or Disk Full). Code 500." . $disk_space_msg . $shell_error );
        }
        
        if ( ! file_exists( $output_file ) || filesize( $output_file ) < 100 ) {
             throw new \Exception( "Zip file created but is empty." );
        }
    }

    private function get_zip_error_message( $code ) {
        switch ( $code ) {
            case \ZipArchive::ER_EXISTS: return 'File already exists.';
            case \ZipArchive::ER_INCONS: return 'Zip archive inconsistent.';
            case \ZipArchive::ER_INVAL: return 'Invalid argument.';
            case \ZipArchive::ER_MEMORY: return 'Malloc failure.';
            case \ZipArchive::ER_NOENT: return 'No such file.';
            case \ZipArchive::ER_NOZIP: return 'Not a zip archive.';
            case \ZipArchive::ER_OPEN: return 'Can\'t open file.';
            case \ZipArchive::ER_READ: return 'Read error.';
            case \ZipArchive::ER_SEEK: return 'Seek error.';
            default: return "Unknown Error ($code)";
        }
    }

    public function delete_backup( $request ) {
        $filename = $request->get_param( 'file' );
        $path = $this->backup_dir . basename( $filename );
        
        if ( file_exists( $path ) ) {
            unlink( $path );
            return new \WP_REST_Response( [ 'success' => true ], 200 );
        }
        
        // Return 404 for missing file, not 500
        return new \WP_Error( 'file_not_found', 'File not found.', [ 'status' => 404 ] );
    }
}
