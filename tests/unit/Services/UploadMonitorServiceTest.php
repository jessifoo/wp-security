<?php
declare(strict_types=1);

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use OMS\Services\UploadMonitorService;
use OMS\Services\FileScannerService;
use OMS\Services\LoggerService;

class UploadMonitorServiceTest extends TestCase {
	private $scanner;
	private $logger;
	private $service;

	protected function setUp(): void {
		$this->scanner = $this->createMock( FileScannerService::class );
		$this->logger  = $this->createMock( LoggerService::class );

		$this->service = new UploadMonitorService(
			$this->scanner,
			$this->logger
		);
	}

	public function test_check_file_scans_valid_attachment(): void {
		// Mock WP functions if needed, or rely on pass-through values if we design the service to be testable without WP hooks firing in unit tests.
		// We will test the 'handle_upload_check' logic directly.

		// Let's assume the method signature mimics the hook: (int $meta_id, int $post_id, string $meta_key, mixed $meta_value)

		// We need 'wp_upload_dir' to function or be mocked. Since we can't easily function-mock in PHPUnit without extensions,
		// we might need to abstract the "path resolver" or just assume a fixed path in test mode if the service allows injection of base dir.
		// For now, let's assume get_attached_file wrapper or similar.

		// In the bootstrap, wp_upload_dir() works because WP constants and mock functions are loaded.
		$upload_dir    = wp_upload_dir();
		$expected_path = $upload_dir['basedir'] . '/test.jpg';

		$this->scanner->expects( $this->once() )
			->method( 'scan_file' )
			->with( $expected_path )
			->willReturn(
				array(
					'safe'   => true,
					'issues' => array(),
				)
			);

		$this->service->check_uploaded_file( 1, 10, '_wp_attached_file', 'test.jpg' );
	}

	public function test_ignores_non_attachment_meta(): void {
		$this->scanner->expects( $this->never() )->method( 'scan_file' );
		$this->service->check_uploaded_file( 1, 10, 'some_other_key', 'value' );
	}
}
