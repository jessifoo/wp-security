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
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Uninstall script variables are acceptable.
$upload_dir = wp_upload_dir();
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Uninstall script variables are acceptable.
$oms_dir    = trailingslashit( $upload_dir['basedir'] ) . 'oms-temp';
if ( is_dir( $oms_dir ) ) {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Uninstall script variables are acceptable.
	$files = glob( $oms_dir . '/*' );
	foreach ( $files as $file ) {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Uninstall script variables are acceptable.
		if ( is_file( $file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Plugin uninstall requires direct file deletion.
			unlink( $file );
		}
	}
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Plugin uninstall requires direct directory removal.
	rmdir( $oms_dir );
}
