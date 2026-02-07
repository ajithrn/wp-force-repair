<?php

namespace WPForceRepair\Api;

class DatabaseController extends \WP_REST_Controller {

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        register_rest_route( 'wp-force-repair/v1', '/database/health', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_health_stats' ],
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
        ] );

        register_rest_route( 'wp-force-repair/v1', '/database/drop-table', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'drop_table' ],
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
        ] );
        register_rest_route( 'wp-force-repair/v1', '/database/analyze', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'analyze_database' ],
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
        ] );

        register_rest_route( 'wp-force-repair/v1', '/database/optimize', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'optimize_database' ],
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
        ] );
    }

    private function format_size($bytes) {
        $bytes = (float) $bytes;
        if ($bytes <= 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
        return number_format($bytes / pow(1024, $power), 2, '.', ',') . ' ' . $units[$power];
    }
    
    public function analyze_database() {
        global $wpdb;
        @set_time_limit( 0 );

        // Get table names only
        $tables = $wpdb->get_col( "SHOW TABLES" );
        
        if ( empty( $tables ) ) {
            return new \WP_REST_Response( [ 'success' => false, 'message' => 'No tables found.' ], 404 );
        }

        foreach ( $tables as $table ) {
            $wpdb->query( "ANALYZE TABLE `$table`" );
        }

        return new \WP_REST_Response( [ 
            'success' => true, 
            'message' => "Stats recalculated for " . count( $tables ) . " tables." 
        ], 200 );
    }

    public function get_health_stats() {
        global $wpdb;

        // 1. Try INFORMATION_SCHEMA first (More reliable on some hosts)
        // Use $wpdb->dbname if available, or call DATABASE()
        $db_name = $wpdb->dbname;
        $query = "SELECT 
                    TABLE_NAME as name, 
                    TABLE_ROWS as rows, 
                    DATA_LENGTH as data_length, 
                    INDEX_LENGTH as index_length, 
                    DATA_FREE as data_free, 
                    ENGINE as engine 
                  FROM information_schema.TABLES 
                  WHERE table_schema = %s";
        
        $tables = $wpdb->get_results( $wpdb->prepare( $query, $db_name ), ARRAY_A );

        // Fallback: If info schema empty (perms issue?), try SHOW TABLE STATUS
        if ( empty( $tables ) ) {
             $tables = $wpdb->get_results( "SHOW TABLE STATUS", ARRAY_A );
        }
        
        $total_size = 0;
        $total_overhead = 0;
        $engine_counts = [];
        $all_tables = [];

        // Debug: Real Row Count Check
        $real_user_count = $wpdb->get_var( "SELECT COUNT(ID) FROM {$wpdb->users}" );

        foreach ( $tables as $table ) {
             // Normalize keys (Handle fallback case mixed keys)
            $table = array_change_key_case( (array) $table, CASE_LOWER );
            
            // Map keys if fallback used (Name -> name)
            $name = $table['name'] ?? ($table['Name'] ?? 'Unknown');
             
            // Force floats
            $data_len = (float) ($table['data_length'] ?? 0);
            $index_len = (float) ($table['index_length'] ?? 0);
            $data_free = (float) ($table['data_free'] ?? 0);
            $rows = (float) ($table['rows'] ?? 0);

            // FIX: If InnoDB stats are 0, force a real count (Expensive but accurate)
            if ( $rows == 0 ) {
                // Check if table actually exists to avoid error
                $rows = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$name`" );
            }

            $size = $data_len + $index_len;
            $total_size += $size;
            $total_overhead += $data_free;

            $engine = $table['engine'] ?? 'Unknown';
            if ( ! isset( $engine_counts[$engine] ) ) $engine_counts[$engine] = 0;
            $engine_counts[$engine]++;

            // For size, if 0, we can't do much without valid metadata.
            $formatted_size = ($size > 0) ? $this->format_size( $size ) : 'N/A';
            $formatted_overhead = ($data_free > 0) ? $this->format_size( $data_free ) : '';

            // Detect Owner
            $owner = $this->detect_table_owner( $name );

            $all_tables[] = [
                'name' => $name,
                'rows' => $rows,
                'size' => $formatted_size,
                'size_raw' => $size,
                'overhead' => $formatted_overhead,
                'overhead_raw' => $data_free,
                'engine' => $engine,
                'plugin' => $owner['name'],
                'plugin_status' => $owner['status'],
                'plugin_slug' => $owner['slug'] ?? ''
            ];
        }

        // 2. Autoload Size
        $autoload_size = $wpdb->get_var( "SELECT SUM(LENGTH(option_value)) FROM $wpdb->options WHERE autoload = 'yes'" );
        if ( is_null($autoload_size) ) $autoload_size = 0;
        
        // 3. Simple Connectivity Check
        $mysql_version = $wpdb->db_version();

        return new \WP_REST_Response( [
            'total_size' => $this->format_size( $total_size ),
            'total_size_raw' => $total_size,
            'total_overhead' => $this->format_size( $total_overhead ),
            'total_overhead_raw' => $total_overhead,
            'table_count' => count( $tables ),
            'engines' => $engine_counts,
            'autoload_size' => $this->format_size( $autoload_size ),
            'autoload_size_raw' => (float) $autoload_size,
            'all_tables' => $all_tables,
            'mysql_version' => $mysql_version,
            'prefix' => $wpdb->prefix,
            'debug_raw' => !empty($tables) ? $tables[0] : null,
            'debug_real_count' => $real_user_count
        ], 200 );
    }

    private $plugin_cache = null;

    private function detect_table_owner( $table_name ) {
        global $wpdb;

        // Strip prefix
        $prefix = $wpdb->prefix;
        $clean_name = $table_name;
        if ( strpos( $table_name, $prefix ) === 0 ) {
            $clean_name = substr( $table_name, strlen( $prefix ) );
        }

        // 1. Core Tables
        $core_tables = [
            'users', 'usermeta', 'posts', 'postmeta', 'comments', 'commentmeta', 
            'terms', 'termmeta', 'term_taxonomy', 'term_relationships', 'options', 'links'
        ];
        if ( in_array( $clean_name, $core_tables ) ) {
            return [ 'name' => 'WordPress Core', 'status' => 'core' ];
        }

        // 2. Build Plugin Cache (Once)
        if ( $this->plugin_cache === null ) {
            if ( ! function_exists( 'get_plugins' ) ) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            
            $all_plugins = get_plugins();
            $active_plugins = get_option( 'active_plugins', [] );
            $this->plugin_cache = [];

            foreach ( $all_plugins as $path => $data ) {
                $status = in_array( $path, $active_plugins ) || is_plugin_active_for_network( $path ) ? 'active' : 'inactive';
                
                $candidates = [];

                // 1. Slug (e.g. 'example-plugin') -> 'example_plugin'
                $slug = dirname( $path );
                if ( $slug === '.' ) $slug = basename( $path, '.php' );
                $candidates[] = str_replace( '-', '_', $slug );

                // 2. Acronyms from Name (e.g. "Example Plugin Pro" -> "epp", "My Plugin" -> "mp")
                // Remove generic words? Maybe not, 'wp' is common.
                $name_parts = preg_split( '/[\s\-_]+/', strtolower( $data['Name'] ) );
                $acronym = '';
                foreach ( $name_parts as $part ) {
                    if ( is_numeric( $part ) ) {
                        $acronym .= $part; // Keep numbers like '7'
                    } elseif ( ! empty( $part ) ) {
                        $acronym .= $part[0]; // First letter
                    }
                }
                if ( strlen( $acronym ) >= 2 ) { // Min 2 chars for acronyms
                    $candidates[] = $acronym;
                }

                // 3. Known Manual Map (The "Efficiency" part - defining exceptions)
                $exceptions = [
                    'wp-optimize' => ['wpo', 'tm'], // WP-Optimize
                    'woocommerce' => ['wc'],
                    'wordfence'   => ['wf'],
                    'gravityforms'=> ['gf'],
                    'elementor'   => ['e'], // 'e_' is rare but used by some addons
                ];
                
                if ( isset( $exceptions[ $slug ] ) ) {
                    foreach ( $exceptions[ $slug ] as $ex ) {
                        $candidates[] = $ex;
                    }
                }

                // 4. Text Domain
                if ( ! empty( $data['TextDomain'] ) ) {
                    $candidates[] = str_replace( '-', '_', $data['TextDomain'] );
                }

                // Filter & Clean
                $candidates = array_unique( $candidates );
                $candidates = array_filter( $candidates, function($c) { return strlen($c) >= 2; } );

                // Clean Display Name (e.g. "Example Plugin - Pro Version" -> "Example Plugin")
                $clean_display_name = preg_split( '/\s+[-|:]\s+/', $data['Name'] )[0];

                foreach ( $candidates as $prefix ) {
                    $this->plugin_cache[] = [
                        'prefix' => $prefix,
                        'name'   => $clean_display_name,
                        'status' => $status,
                        'slug'   => $slug  // Storing Slug for WP.org link
                    ];
                }
            }

            // Sort cache by prefix length descending (Longest match wins)
            usort( $this->plugin_cache, function($a, $b) {
                return strlen( $b['prefix'] ) - strlen( $a['prefix'] );
            } );
        }

        // 3. Find Match
        foreach ( $this->plugin_cache as $p ) {
            if ( strpos( $clean_name, $p['prefix'] ) === 0 ) {
                return [
                    'name' => $p['name'],
                    'status' => $p['status'],
                    'slug' => $p['slug']
                ];
            }
        }

        return [ 'name' => 'Unknown', 'status' => 'unknown', 'slug' => '' ];
    }

    public function optimize_database( $request ) {
        global $wpdb;
        @set_time_limit( 0 );

        $params = $request->get_json_params();
        $target_tables = $params['tables'] ?? []; // Optional array of table names

        if ( ! empty( $target_tables ) ) {
            // Validate table names (simple alphanumeric + underscore)
            $tables = [];
            foreach ( $target_tables as $t ) {
                 if ( preg_match( '/^[a-zA-Z0-9_]+$/', $t ) ) {
                     $tables[] = (object) ['Name' => $t];
                 }
            }
        } else {
            // Default: Optimize only tables with overhead
            $tables = $wpdb->get_results( "SHOW TABLE STATUS WHERE Data_free > 0", ARRAY_A );
        }
        
        if ( empty( $tables ) ) {
            return new \WP_REST_Response( [ 'success' => true, 'message' => 'Database is already optimized.' ], 200 );
        }

        $optimized_count = 0;
        foreach ( $tables as $table ) {
            $table_name = is_array($table) ? $table['Name'] : $table->Name;
            $wpdb->query( "OPTIMIZE TABLE `$table_name`" ); // Backticks for safety
            $optimized_count++;
        }

        return new \WP_REST_Response( [ 
            'success' => true, 
            'message' => "Successfully optimized $optimized_count tables.",
            'optimized_tables' => $optimized_count
        ], 200 );
    }

    public function drop_table( $request ) {
        global $wpdb;

        $table_name = $request->get_param( 'table' );
        
        if ( empty( $table_name ) ) {
            return new \WP_Error( 'missing_table', 'Table name is required.', [ 'status' => 400 ] );
        }

        // SECURITY: Protected Core Tables
        // We protect standard WP tables. Note: User might use custom prefix, but we check against current prefix.
        $protected = [
            'users', 'usermeta',
            'posts', 'postmeta',
            'comments', 'commentmeta',
            'terms', 'termmeta', 'term_taxonomy', 'term_relationships',
            'options', 'links'
        ];
        
        $is_protected = false;
        foreach ( $protected as $base ) {
            if ( $table_name === $wpdb->prefix . $base ) {
                $is_protected = true;
                break;
            }
        }

        if ( $is_protected ) {
            return new \WP_Error( 'protected_table', "Cannot delete core WordPress table: $table_name", [ 'status' => 403 ] );
        }

        // Validate table exists to prevent SQL injection via table name (though $wpdb->query should be safe if used right, better to be sure)
        // Actually, simple sanity check: must match [a-zA-Z0-9_]
        if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', $table_name ) ) {
             return new \WP_Error( 'invalid_name', "Invalid table name format.", [ 'status' => 400 ] );
        }

        // Execute DROP
        $result = $wpdb->query( "DROP TABLE `$table_name`" ); // Backticks for safety

        if ( $result === false ) {
            return new \WP_Error( 'drop_failed', "Failed to drop table. Check logs.", [ 'status' => 500 ] );
        }

        return new \WP_REST_Response( [
            'success' => true,
            'message' => "Table $table_name dropped successfully."
        ], 200 );
    }
}
