<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../WordPressMocksTrait.php';
require_once __DIR__ . '/../mocks/class-wpdb-mock.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-oms-database-scanner.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-oms-logger.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-oms-cache.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-oms-database-backup.php';

class OMS_Database_ScannerTest extends TestCase {

	use WordPressMocksTrait;

	private $scanner;
	private $logger;
	private $cache;
	private $wpdb;
	private $backup;

	protected function setUp(): void {
		parent::setUp();
		$this->setup_wordpress_mocks();

		// Setup mock wpdb
		global $wpdb;
		$wpdb       = new wpdb( 'user', 'pass', 'db', 'host' );
		$this->wpdb = $wpdb;

		// Mock Logger and Cache
		$this->logger = $this->createMock( OMS_Logger::class );
		$this->cache  = $this->createMock( OMS_Cache::class );
		$this->backup = $this->createMock( OMS_Database_Backup::class );

		$this->scanner = new OMS_Database_Scanner( $this->logger, $this->cache, $this->backup );
	}

	protected function tearDown(): void {
		$this->teardown_wordpress_mocks();
		parent::tearDown();
	}

	public function testScanDatabaseIntegrity() {
		// Mock table existence check
		$this->wpdb->results = array( 1 ); // Table exists

		$result = $this->scanner->scan_database();

		$this->assertTrue( $result['success'] );
		$this->assertArrayHasKey( 'issues', $result );
	}

	public function testCleanDatabaseContent() {
		// Mock backup success
		$this->backup->method( 'backup_critical_tables' )
			->willReturn( array( 'success' => true ) );

		// Mock issues
		$issues = array(
			array(
				'type'     => 'malicious_content',
				'table'    => 'wp_options',
				'column'   => 'option_value',
				'row_id'   => 123,
				'severity' => 'CRITICAL',
			),
		);

		// Mock wpdb query for deletion
		// We expect a DELETE query

		$result = $this->scanner->clean_database_content( $issues );

		$this->assertTrue( $result['success'] );
		$this->assertEquals( 1, $result['cleaned'] );
	}
}
