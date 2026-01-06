<?php
/**
 * Upload Monitor Service.
 *
 * Monitors uploads and triggers scans.
 *
 * @package OMS\Services
 */

declare(strict_types=1);

namespace OMS\Services;

class UploadMonitorService {

	/**
	 * Constructor.
	 *
	 * @param FileScannerService $scanner File scanner.
	 * @param LoggerService      $logger  Logger service.
	 */
	public function __construct(
		private FileScannerService $scanner,
		private LoggerService $logger
	) {}

	/**
	 * Check uploaded file when metadata is added.
	 *
	 * @param int    $meta_id    Meta ID.
	 * @param int    $post_id    Post ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 * @return void
	 */
	public function check_uploaded_file( int $meta_id, int $post_id, string $meta_key, mixed $meta_value ): void {
		// Only check attachment metadata.
		if ( '_wp_attached_file' !== $meta_key ) {
			return;
		}

		$filename   = (string) $meta_value;
		$upload_dir = wp_upload_dir();
		$file_path  = $upload_dir['basedir'] . '/' . $filename;

		// Run the scan
		$result = $this->scanner->scan_file( $file_path );

		if ( ! $result['safe'] ) {
			$this->logger->warning( "Malware detected in upload (Post ID: $post_id): " . ( $result['reason'] ?? 'Unknown reason' ) );
			// In a real scenario, we might quarantine here or delete the post meta/attachment
			// For now, we just log as per the refactor scope (reproducing logic but cleaner).
		}
	}
}
