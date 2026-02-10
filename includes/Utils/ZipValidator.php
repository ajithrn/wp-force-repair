<?php
namespace WPForceRepair\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ZipValidator {

    /**
     * Validates that a zip file contains a root folder matching the expected slug.
     *
     * @param string $file_path Absolute path to the zip file.
     * @param string $target_slug The expected plugin/theme slug (folder name).
     * @return true|\WP_Error True if valid, WP_Error on failure or mismatch.
     */
    public static function validate( $file_path, $target_slug ) {
        // Normalize slug: if 'folder/file.php', get 'folder'.
        $expected_folder = dirname( $target_slug );
        if ( $expected_folder === '.' ) {
             $expected_folder = $target_slug; // It was just 'folder'
        }

        if ( class_exists( '\ZipArchive' ) ) {
            $zip = new \ZipArchive();
            if ( $zip->open( $file_path ) === true ) {
                // Check first item. Usually it's "folder/"
                $stat = $zip->statIndex( 0 );
                if ( ! $stat ) {
                     $zip->close();
                     return new \WP_Error( 'empty_zip', 'The zip file appears to be empty or invalid.' );
                }
                
                $first_name = $stat['name'];
                // Remove trailing slash
                $detected_folder = rtrim( $first_name, '/' );
                $detected_folder = explode( '/', $detected_folder )[0]; // Get top level

                if ( $detected_folder !== $expected_folder ) {
                    // Allow match if expected is same as detected (covers basic cases)
                    // If mismatch, check if maybe it's a single file match?
                    if ( $expected_folder !== $detected_folder ) {
                         $zip->close();
                         return new \WP_Error( 'safety_mismatch', "Safety Mismatch: Zip contains root folder '$detected_folder', but you are reinstalling '$expected_folder'. Aborting to prevent duplicates." );
                    }
                }
                $zip->close();
                return true;
            }
            return new \WP_Error( 'zip_error', 'Could not open zip file.' );
        }
        
        // If ZipArchive not present, we can't validate.
        // Returning true to not block users on servers without ZipArchive, 
        // but maybe we should log a warning.
        return true; 
    }
}
