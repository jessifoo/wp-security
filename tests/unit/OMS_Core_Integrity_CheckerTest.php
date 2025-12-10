<?php

class OMS_Core_Integrity_CheckerTest extends \PHPUnit\Framework\TestCase {
	protected $checker;
	protected $logger;
	protected $test_dir;

	protected function setUp(): void {
		parent::setUp();

		$this->logger  = $this->createMock( OMS_Logger::class );
		$this->checker = new OMS_Core_Integrity_Checker( $this->logger );

		// Ensure test directory exists
		$this->test_dir = sys_get_temp_dir() . '/wordpress';
		if ( ! file_exists( $this->test_dir ) ) {
			mkdir( $this->test_dir, 0777, true );
		}
	}

	protected function tearDown(): void {
		parent::tearDown();
		// Clean up test files
		$this->rrmdir( $this->test_dir );

		// Reset mocks
		global $wp_remote_get_mock;
		$wp_remote_get_mock = null;
	}

	private function rrmdir( $dir ) {
		if ( is_dir( $dir ) ) {
			$objects = scandir( $dir );
			foreach ( $objects as $object ) {
				if ( '.' !== $object && '..' !== $object ) {
					if ( is_dir( $dir . '/' . $object ) ) {
						$this->rrmdir( $dir . '/' . $object );
					} else {
						unlink( $dir . '/' . $object );
					}
				}
			}
			rmdir( $dir );
		}
	}

	public function testVerifyCoreFilesSuccess() {
		// Create dummy files
		$sample_content = 'sample content';
		$index_content  = 'index content';

		file_put_contents( $this->test_dir . '/wp-config-sample.php', $sample_content );
		file_put_contents( $this->test_dir . '/index.php', $index_content );

		$checksums = array(
			'checksums' => array(
				'wp-config-sample.php' => md5( $sample_content ),
				'index.php'            => md5( $index_content ),
			),
		);

		global $wp_remote_get_mock;
		$wp_remote_get_mock = array(
			'response' => array( 'code' => 200 ),
			'body'     => json_encode( $checksums ),
		);

		$results = $this->checker->verify_core_files();

		$this->assertIsArray( $results );
		$this->assertCount( 2, $results['safe'] );
		$this->assertEmpty( $results['modified'] );
		$this->assertEmpty( $results['missing'] );
		$this->assertContains( 'wp-config-sample.php', $results['safe'] );
		$this->assertContains( 'index.php', $results['safe'] );
	}

	public function testVerifyCoreFilesMismatch() {
		// Create dummy files with mismatch
		$sample_content = 'modified content';
		file_put_contents( $this->test_dir . '/wp-config-sample.php', $sample_content );

		$checksums = array(
			'checksums' => array(
				'wp-config-sample.php' => md5( 'original content' ), // Mismatch
			),
		);

		global $wp_remote_get_mock;
		$wp_remote_get_mock = array(
			'response' => array( 'code' => 200 ),
			'body'     => json_encode( $checksums ),
		);

		$results = $this->checker->verify_core_files();

		$this->assertIsArray( $results );
		$this->assertEmpty( $results['safe'] );
		$this->assertCount( 1, $results['modified'] );
		$this->assertContains( 'wp-config-sample.php', $results['modified'] );
	}

	public function testVerifyCoreFilesMissing() {
		// Don't create any files

		$checksums = array(
			'checksums' => array(
				'missing.php' => md5( 'missing' ),
			),
		);

		global $wp_remote_get_mock;
		$wp_remote_get_mock = array(
			'response' => array( 'code' => 200 ),
			'body'     => json_encode( $checksums ),
		);

		$results = $this->checker->verify_core_files();

		$this->assertIsArray( $results );
		$this->assertEmpty( $results['safe'] );
		$this->assertCount( 1, $results['missing'] );
		$this->assertContains( 'missing.php', $results['missing'] );
	}
}
