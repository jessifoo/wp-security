<?php
/**
 * Upload Monitor Service.
 *
 * Monitors uploads and triggers scans.
 *
 * @package OMS\Services
 */

declare( strict_types=1 );

namespace OMS\Services;

/**
 * Upload Monitor Service class.
 *
 * Monitors file uploads and triggers security scans.
 *
 * @package OMS\Services
 */
class UploadMonitorService {

	/**
	 * File scanner service.
	 *
	 * @var FileScannerService
	 */
	private FileScannerService $scanner;

	/**
	 * Logger service.
	 *
	 * @var LoggerService
	 */
	private LoggerService $logger;

	/**
	 * Constructor.
	 *
	 * @param FileScannerService $scanner File scanner.
	 * @param LoggerService      $logger  Logger service.
	 */
	public function __construct(
		FileScannerService $scanner,
		LoggerService $logger
	) {
		$this->scanner = $scanner;
		$this->logger  = $logger;
	}

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

		// Run the scan.
		$result = $this->scanner->scan_file( $file_path );

		if ( ! $result['safe'] ) {
			$reason = isset( $result['reason'] ) ? $result['reason'] : 'Unknown reason';
			$this->logger->warning( "Malware detected in upload (Post ID: $post_id): " . $reason );
			// In production, we might quarantine here or delete the attachment.
		}
	}
}
