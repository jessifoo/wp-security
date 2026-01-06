<?php
declare(strict_types=1);

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use OMS\Services\FileScannerService;
use OMS\Services\LoggerService;
use OMS\Services\FilesystemService;

class FileScannerServiceTest extends TestCase {
	private $filesystem;
	private $logger;
	private $service;

	protected function setUp(): void {
		$this->filesystem = $this->createMock( FilesystemService::class );
		$this->logger     = $this->createMock( LoggerService::class );

		$this->service = new FileScannerService(
			$this->filesystem,
			$this->logger
		);
	}

	public function test_scan_returns_unsafe_if_unreadable(): void {
		$this->filesystem->method( 'is_readable' )->willReturn( false );

		$result = $this->service->scan_file( '/path/to/missing.php' );

		$this->assertFalse( $result['safe'] );
		$this->assertEquals( 'File is not readable', $result['reason'] );
	}

	public function test_scan_detects_malicious_pattern(): void {
		$this->filesystem->method( 'is_readable' )->willReturn( true );
		$this->filesystem->method( 'get_contents' )->willReturn( '<?php eval(base64_decode("...")); ?>' );

		$result = $this->service->scan_file( '/path/to/malware.php' );

		$this->assertFalse( $result['safe'] );
		$this->assertStringContainsString( 'malicious', $result['reason'] );
	}

	public function test_scan_returns_safe_for_clean_file(): void {
		$this->filesystem->method( 'is_readable' )->willReturn( true );
		$this->filesystem->method( 'get_contents' )->willReturn( '<?php echo "Hello World"; ?>' );

		$result = $this->service->scan_file( '/path/to/clean.php' );

		$this->assertTrue( $result['safe'] );
	}
}
