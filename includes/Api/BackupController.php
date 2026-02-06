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
        @set_time_limit( 0 ); // Infinite time
        $type = $request->get_param( 'type' ); // 'db' or 'files'
        
        $site_name = sanitize_title( get_bloginfo( 'name' ) );
        if ( empty( $site_name ) ) $site_name = 'site';

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
                    return new \WP_Error( 'no_zip', 'ZipArchive extension missing on server.' );
                }
                $filename .= '.zip';
                $file_path = $this->backup_dir . $filename;
                $this->zip_files( $file_path );
            } else {
                return new \WP_Error( 'invalid_type', 'Invalid backup type.' );
            }

            return new \WP_REST_Response( [
                'success' => true,
                'url' => $this->upload_url . $filename,
                'file' => $filename,
                'message' => 'Backup created successfully.'
            ], 200 );

        } catch ( \Exception $e ) {
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

    private function zip_files( $output_file ) {
        $zip = new \ZipArchive();
        if ( $zip->open( $output_file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) !== TRUE ) {
            throw new \Exception( "Cannot create zip file." );
        }

        $rootPath = realpath( ABSPATH );
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator( $rootPath ),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        $excludes = [ 'node_modules', '.git', 'wfr-backups' ];

        foreach ( $files as $name => $file ) {
            if ( $file->isDir() ) continue;

            $filePath = $file->getRealPath();
            $relativePath = substr( $filePath, strlen( $rootPath ) + 1 );

            // Check exclusions
            foreach ( $excludes as $exclude ) {
                if ( strpos( $relativePath, $exclude ) !== false ) continue 2;
            }

            $zip->addFile( $filePath, $relativePath );
        }

        $zip->close();
    }

    public function delete_backup( $request ) {
        $filename = $request->get_param( 'file' );
        $path = $this->backup_dir . basename( $filename );
        
        if ( file_exists( $path ) ) {
            unlink( $path );
            return new \WP_REST_Response( [ 'success' => true ], 200 );
        }
        
        return new \WP_Error( 'file_not_found', 'File not found.' );
    }
}
