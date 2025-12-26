<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../WordPressMocksTrait.php';
require_once __DIR__ . '/../mocks/class-wpdb-mock.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-oms-database-scanner.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-oms-logger.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-oms-cache.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-oms-database-cleaner.php';

class OMS_Database_ScannerTest extends TestCase {

	use WordPressMocksTrait;

	private $scanner;
	private $logger;
	private $cache;
	private $wpdb;
	private $cleaner;

	public function setUp(): void {
		parent::setUp();
		$this->setup_wordpress_mocks();

		// Mock wpdb using PHPUnit builder
		$this->wpdb = $this->getMockBuilder( wpdb::class )
			->setConstructorArgs( array( 'user', 'pass', 'db', 'host' ) )
			->onlyMethods( array( 'get_results', 'get_var', 'get_col', 'prepare' ) )
			->getMock();

		// Simple prepare mock
		$this->wpdb->method( 'prepare' )->willReturnCallback( function( $query, ...$args ) {
			$query = str_replace( array( '%s', '%i' ), array( "'%s'", '%s' ), $query );
			return vsprintf( $query, $args );
		} );

		// Smart get_results
		$this->wpdb->method( 'get_results' )->willReturnCallback( function( $query ) {
			// Structure or content queries - return empty to pass checks safely or simulate no malware
			return [];
		} );

		// Smart get_var (table existence)
		$this->wpdb->method( 'get_var' )->willReturn( 1 ); // Always exists

		// Smart get_col (columns)
		$this->wpdb->method( 'get_col' )->willReturn( ['test_column'] ); // Always one column

		// Mock Logger and Cache
		$this->logger       = $this->createMock( OMS_Logger::class );
		$this->cache        = $this->createMock( OMS_Cache::class );
		$this->cleaner      = $this->createMock( OMS_Database_Cleaner::class );

		// scanner needs the mock
		$this->scanner = new OMS_Database_Scanner( $this->logger, $this->cache, $this->wpdb, $this->cleaner );
	}

	public function tearDown(): void {
		$this->teardown_wordpress_mocks();
		parent::tearDown();
	}

	public function testScanDatabaseIntegrity() {
		// Mock is handled in setUp via callback

		$result = $this->scanner->scan_database();

		$this->assertTrue( $result['success'] );
		$this->assertArrayHasKey( 'issues', $result );
	}

	public function testCleanDatabaseContent() {
		// Mock cleaner success
		$this->cleaner->method( 'clean_issues' )
			->willReturn( array( 'success' => true, 'cleaned' => 1 ) );

		$issues = array(
			array(
				'type'     => 'malicious_content',
				'table'    => 'wp_options',
				'column'   => 'option_value',
				'row_id'   => 123,
				'severity' => 'CRITICAL',
			),
		);

		$result = $this->scanner->clean_database_content( $issues );

		$this->assertTrue( $result['success'] );
		$this->assertEquals( 1, $result['cleaned'] );
	}
}
