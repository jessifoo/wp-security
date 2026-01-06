<?php
declare(strict_types=1);

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use OMS\Services\LoggerService;

class LoggerServiceTest extends TestCase {

	protected function setUp(): void {
		if ( ! defined( 'OMS_TEST_MODE' ) ) {
			define( 'OMS_TEST_MODE', true );
		}
		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', '/tmp' );
		}
		if ( ! defined( 'WP_DEBUG' ) ) {
			define( 'WP_DEBUG', true );
		}
		if ( ! function_exists( 'current_time' ) ) {
			function current_time( $type ) {
				return '2024-01-01 12:00:00'; }
		}
	}

	public function test_can_log_info_message(): void {
		$logger = new LoggerService();
		$logger->info( 'Test message' );

		$logs = $logger->get_memory_logs();
		$this->assertCount( 1, $logs );
		$this->assertStringContainsString( '[INFO]', $logs[0] );
		$this->assertStringContainsString( 'Test message', $logs[0] );
	}

	public function test_can_log_error_message(): void {
		$logger = new LoggerService();
		$logger->error( 'Error message' );

		$logs = $logger->get_memory_logs();
		$this->assertCount( 1, $logs );
		$this->assertStringContainsString( '[ERROR]', $logs[0] );
		$this->assertStringContainsString( 'Error message', $logs[0] );
	}

	public function test_validates_log_level(): void {
		$logger = new LoggerService();
		$logger->log( 'Test', 'INVALID_LEVEL' ); // Should default to info

		$logs = $logger->get_memory_logs();
		$this->assertStringContainsString( '[INFO]', $logs[0] );
	}
}
