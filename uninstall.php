<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package ObfuscatedMalwareScanner
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Delete plugin options.
delete_option( 'oms_settings' );
delete_option( 'oms_scan_history' );
delete_option( 'oms_last_scan' );

// Clean up any scheduled events.
wp_clear_scheduled_hook( 'oms_scheduled_scan' );

// Remove any temporary files.
$upload_dir = wp_upload_dir();
$oms_dir = trailingslashit( $upload_dir['basedir'] ) . 'oms-temp';
if ( is_dir( $oms_dir ) ) {
    $files = glob( $oms_dir . '/*' );
    foreach ( $files as $file ) {
        if ( is_file( $file ) ) {
            unlink( $file );
        }
    }
    rmdir( $oms_dir );
}
