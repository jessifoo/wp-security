<?php
/**
 * Quarantine Manager class for the Obfuscated Malware Scanner
 *
 * @package ObfuscatedMalwareScanner
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access is not allowed.' );
}

/**
 * Quarantine Manager class.
 */
class OMS_Quarantine_Manager {
	/**
	 * Logger instance.
	 *
	 * @var OMS_Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param OMS_Logger $logger Logger instance.
	 */
	public function __construct( OMS_Logger $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Quarantine a file with fallback options and safety checks.
	 *
	 * @param string $path Path to the file to quarantine.
	 * @return bool True if quarantine succeeded, false otherwise.
	 * @throws Exception If quarantine fails.
	 */
	public function quarantine_file( $path ) {
		try {
			$quarantine_dir = OMS_Config::QUARANTINE_CONFIG['path'];

			// Ensure quarantine directory exists and is writable.
			if ( ! file_exists( $quarantine_dir ) ) {
				$mkdir_result = wp_mkdir_p( $quarantine_dir );
				if ( ! $mkdir_result ) {
					$error     = error_get_last();
					$error_msg = ( $error ) ? $error['message'] : 'Unknown error';
					$this->logger->error(
						sprintf( 'Failed to create quarantine directory: %s - Error: %s', esc_html( $quarantine_dir ), esc_html( $error_msg ) )
					);
					throw new Exception( 'Failed to create quarantine directory: ' . esc_html( $error_msg ) );
				}
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- Security requirement: must verify quarantine directory is writable.
			if ( ! is_writable( $quarantine_dir ) ) {
				throw new Exception( 'Quarantine directory is not writable' );
			}

			// Generate unique quarantine filename.
			$timestamp       = gmdate( 'Y-m-d_H-i-s' );
			$unique_id       = uniqid();
			$quarantine_path = sprintf(
				'%s/%s_%s_%s.quarantine',
				$quarantine_dir,
				pathinfo( $path, PATHINFO_FILENAME ),
				$timestamp,
				$unique_id
			);

			// Try primary quarantine method (rename).
			// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Security requirement: must rename files for quarantine, WP_Filesystem not suitable for atomic operations.
			$rename_result = rename( $path, $quarantine_path );
			if ( $rename_result ) {
				$this->logger->info(
					sprintf( 'File quarantined successfully - Original: %s, Quarantine: %s, Method: rename', esc_html( $path ), esc_html( $quarantine_path ) )
				);
				return true;
			} else {
				$error     = error_get_last();
				$error_msg = ( $error ) ? esc_html( $error['message'] ) : 'Unknown error';
				$this->logger->warning(
					sprintf( 'Failed to rename file for quarantine: %s - Error: %s', esc_html( $path ), $error_msg )
				);
			}

			// Fallback 1: Try copy and delete.
			$copy_result = copy( $path, $quarantine_path );
			if ( $copy_result ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Security requirement: must delete malicious files, wp_delete_file() not suitable for quarantine operations.
				$unlink_result = unlink( $path );
				if ( $unlink_result ) {
					$this->logger->info(
						sprintf( 'File quarantined using copy/delete - Original: %s, Quarantine: %s', esc_html( $path ), esc_html( $quarantine_path ) )
					);
					return true;
				} else {
					$error     = error_get_last();
					$error_msg = ( $error ) ? esc_html( $error['message'] ) : 'Unknown error';
					$this->logger->warning(
						sprintf( 'Failed to delete original file after copy: %s - Error: %s', esc_html( $path ), $error_msg )
					);
					// If we can't delete the original, remove the quarantine copy.
					// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Security requirement: must clean up failed quarantine operations.
					$unlink_quarantine = unlink( $quarantine_path );
					if ( ! $unlink_quarantine ) {
						$error     = error_get_last();
						$error_msg = ( $error ) ? esc_html( $error['message'] ) : 'Unknown error';
						$this->logger->error(
							sprintf( 'Failed to remove quarantine copy after failed delete: %s - Error: %s', esc_html( $quarantine_path ), $error_msg )
						);
					}
				}
			} else {
				$error     = error_get_last();
				$error_msg = ( $error ) ? esc_html( $error['message'] ) : 'Unknown error';
				$this->logger->warning(
					sprintf( 'Failed to copy file for quarantine: %s - Error: %s', esc_html( $path ), $error_msg )
				);
			}

			// Fallback 2: Try to make file inaccessible.
			if ( $this->make_file_inaccessible( $path ) ) {
				$this->logger->warning(
					sprintf( 'File made inaccessible as quarantine fallback: %s (method: chmod)', esc_html( $path ) )
				);
				return true;
			}

			throw new Exception( 'All quarantine methods failed' );
		} catch ( Exception $e ) {
			$this->logger->error(
				sprintf( 'Quarantine failed: %s - Error: %s - Trace: %s', esc_html( $path ), esc_html( $e->getMessage() ), esc_html( $e->getTraceAsString() ) )
			);
			return false;
		}
	}

	/**
	 * Make a file inaccessible as a last resort.
	 *
	 * @param string $path Path to the file.
	 * @return bool True if successful, false otherwise.
	 */
	private function make_file_inaccessible( $path ) {
		// Try to remove all permissions.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Security requirement: must change file permissions to make malicious files inaccessible.
		$chmod_result = chmod( $path, 0000 );
		if ( $chmod_result ) {
			return true;
		} else {
			$error     = error_get_last();
			$error_msg = ( $error ) ? esc_html( $error['message'] ) : 'Unknown error';
			$this->logger->warning(
				sprintf( 'Failed to chmod file to 0000: %s - Error: %s', esc_html( $path ), $error_msg )
			);
		}

		// Fallback: try to make file unreadable.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Security requirement: must change file permissions to make malicious files inaccessible.
		$chmod_result = chmod( $path, 0333 );
		if ( $chmod_result ) {
			return true;
		} else {
			$error     = error_get_last();
			$error_msg = ( $error ) ? esc_html( $error['message'] ) : 'Unknown error';
			$this->logger->warning(
				sprintf( 'Failed to chmod file to 0333: %s - Error: %s', esc_html( $path ), $error_msg )
			);
		}

		return false;
	}
}
