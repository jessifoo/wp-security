<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../WordPressMocksTrait.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-oms-utils.php';

/**
 * Class OMS_UtilsTest
 * Tests for the utility functions
 */
class OMS_UtilsTest extends TestCase {

	use WordPressMocksTrait;

	protected function setUp(): void {
		parent::setUp();
		$this->setup_wordpress_mocks();
	}

	protected function tearDown(): void {
		$this->teardown_wordpress_mocks();
		parent::tearDown();
	}

	public function testSanitizeUrl() {
		// Test with valid URL
		$url = 'https://example.com/path?param=value';
		$this->assertEquals(
			'https://example.com/path?param=value',
			OMS_Utils::sanitize_url( $url )
		);

		// Test with potentially malicious URL
		$malicious_url = 'javascript:alert(1)';
		$this->assertEquals( '', OMS_Utils::sanitize_url( $malicious_url ) );

		// Test with invalid URL
		$invalid_url = 'not_a_url';
		$this->assertEquals( '', OMS_Utils::sanitize_url( $invalid_url ) );
	}

	public function testSanitizePath() {
		// Test with valid path
		$path      = $this->wp_uploads_dir . '/file.txt';
		$sanitized = OMS_Utils::sanitize_path( $path );
		$this->assertEquals( $path, $sanitized );

		// Test with path traversal attempt
		$this->expectException( InvalidArgumentException::class );
		OMS_Utils::sanitize_path( $this->wp_uploads_dir . '/../../../etc/passwd' );
	}

	public function testGetRelativePath() {
		// Test path within WordPress root
		$path     = $this->wp_uploads_dir . '/file.txt';
		$relative = OMS_Utils::get_relative_path( $path );
		$this->assertEquals( 'wp-content/uploads/file.txt', $relative );

		// Test path outside WordPress root
		$path     = '/var/www/other/file.txt';
		$relative = OMS_Utils::get_relative_path( $path );
		$this->assertEquals( $path, $relative );
	}

	public function testIsPathSafe() {
		// Test safe paths
		$safe_paths = array(
			$this->wp_uploads_dir . '/file.txt',
			$this->wp_content_dir . '/plugins/my-plugin/file.php',
			$this->wp_content_dir . '/themes/my-theme/style.css',
		);

		foreach ( $safe_paths as $path ) {
			$this->assertTrue(
				OMS_Utils::is_path_safe( $path ),
				"Path should be considered safe: $path"
			);
		}

		// Test unsafe paths
		$unsafe_paths = array(
			$this->wp_uploads_dir . '/../etc/passwd',
			$this->wp_content_dir . '/plugins/../../config.php',
			'php://input',
			'file:///etc/passwd',
			"file.txt\0.jpg",
			$this->wp_uploads_dir . '/<script>alert(1)</script>',
			$this->wp_uploads_dir . '/file.php;.jpg',
			$this->wp_uploads_dir . '/|ls',
			$this->wp_uploads_dir . '/`whoami`',
		);

		foreach ( $unsafe_paths as $path ) {
			$this->assertFalse(
				OMS_Utils::is_path_safe( $path ),
				"Path should be considered unsafe: $path"
			);
		}
	}
}
