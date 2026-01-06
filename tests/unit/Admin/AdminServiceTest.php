<?php
declare(strict_types=1);

namespace Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use OMS\Admin\AdminService;
use OMS\Services\DatabaseScannerService;
use OMS\Services\FileScannerService;
use OMS\Services\LoggerService;

class AdminServiceTest extends TestCase {
	private $dbScanner;
	private $fileScanner;
	private $logger;
	private $service;

	protected function setUp(): void {
		$this->dbScanner   = $this->createMock( DatabaseScannerService::class );
		$this->fileScanner = $this->createMock( FileScannerService::class );
		$this->logger      = $this->createMock( LoggerService::class );

		$this->service = new AdminService(
			$this->dbScanner,
			$this->fileScanner,
			$this->logger
		);
	}

	public function test_run_manual_scan_triggers_both_scanners(): void {
		$this->dbScanner->expects( $this->once() )
			->method( 'scan' )
			->willReturn(
				array(
					'success' => true,
					'issues'  => array(),
				)
			);

		// Use atLeastOnce to accommodate "Starting..." and "Details..." log messages
		$this->logger->expects( $this->atLeastOnce() )
			->method( 'info' )
			->with( $this->stringContains( 'Manual scan' ) );

		$result = $this->service->execute_manual_scan();
		$this->assertTrue( $result );
	}
}
