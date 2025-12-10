<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../WordPressMocksTrait.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-oms-utils.php';
require_once __DIR__ . '/../mocks/class-oms-security-exception.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-oms-filesystem.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-file-security-policy.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-oms-config.php';

/**
 * Mock OMS_Config class for testing if it doesn't exist
 */
if ( ! class_exists( 'OMS_Config' ) ) {
	class OMS_Config {
		const MALICIOUS_PATTERNS = array(
			'eval\s*\(\s*base64_decode',
			'base64_decode\s*\(\s*[\'"][^\'"]+[\'"]\s*\)',
			'(shell_exec|system|passthru|exec)\s*\(',
			'include(_once)?\s*\(\s*[\'"]https?://',
		);

		const OBFUSCATION_PATTERNS = array(
			array(
				'pattern'     => '\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*\s*=\s*[\'"](?:eval|assert|base64_decode)[\'"];\s*\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*\s*\(',
				'severity'    => 'CRITICAL',
				'description' => 'Variable function call obfuscation detected',
			),
			array(
				'pattern'     => '(?:chr\(\d+\)\.){3,}',
				'severity'    => 'HIGH',
				'description' => 'String splitting obfuscation detected',
			),
		);
	}
}

/**
 * Class OMS_File_Security_PolicyTest
 * Tests for the file security policy functionality
 */
class OMS_File_Security_PolicyTest extends TestCase {

	use WordPressMocksTrait;

	/**
	 * @var OMS_File_Security_Policy
	 */
	private $policy;

	/**
	 * @var string
	 */
	private $test_file;

	protected function setUp(): void {
		parent::setUp();
		$this->setup_wordpress_mocks();

		$filesystem   = new OMS_Filesystem();
		$this->policy = new OMS_File_Security_Policy( $filesystem );

		// Create a temporary test file in WordPress uploads directory
		$this->test_file = $this->wp_uploads_dir . '/test.txt';
		file_put_contents( $this->test_file, 'Test content' );
		chmod( $this->test_file, 0644 );
		touch( $this->test_file, strtotime( '2025-01-01 12:00:00' ) );
	}

	protected function tearDown(): void {
		// Clean up test file
		if ( file_exists( $this->test_file ) ) {
			unlink( $this->test_file );
		}

		$this->teardown_wordpress_mocks();
		parent::tearDown();
	}

	public function testValidateFileWithValidFile() {
		// Mock WordPress functions
		$this->mockWPVerifyNonce( true );
		$this->mockWPCheckFiletype(
			array(
				'type' => 'text/plain',
				'ext'  => 'txt',
			)
		);

		$result = $this->policy->validate_file(
			$this->test_file,
			array(
				'nonce' => 'valid_nonce',
			)
		);

		$this->assertTrue( $result['valid'] );
		$this->assertEquals( 'File passed all security checks', $result['reason'] );
	}

	public function testValidateFileWithInvalidNonce() {
		$this->expectException( OMS_Security_Exception::class );
		$this->expectExceptionMessage( 'Invalid security token' );

		// Mock WordPress functions
		$this->mockWPVerifyNonce( false );

		$this->policy->validate_file(
			$this->test_file,
			array(
				'nonce' => 'invalid_nonce',
			)
		);
	}

	public function testValidateFileWithZeroByteFile() {
		// Create empty file
		file_put_contents( $this->test_file, '' );

		// Mock WordPress functions
		$this->mockWPVerifyNonce( true );
		$this->mockWPCheckFiletype(
			array(
				'type' => 'text/plain',
				'ext'  => 'txt',
			)
		);

		$result = $this->policy->validate_file(
			$this->test_file,
			array(
				'nonce' => 'valid_nonce',
			)
		);

		$this->assertFalse( $result['valid'] );
		$this->assertEquals( 'Zero byte file not in allowlist', $result['reason'] );
	}

	public function testValidateFileWithForbiddenExtension() {
		// Create PHP file
		$php_file = $this->wp_uploads_dir . '/test.php';
		file_put_contents( $php_file, '<?php echo "test"; ?>' );

		// Mock WordPress functions
		$this->mockWPVerifyNonce( true );
		$this->mockWPCheckFiletype(
			array(
				'type' => 'application/x-php',
				'ext'  => 'php',
			)
		);

		$result = $this->policy->validate_file(
			$php_file,
			array(
				'nonce' => 'valid_nonce',
			)
		);

		$this->assertFalse( $result['valid'] );
		$this->assertEquals( 'Forbidden file extension', $result['reason'] );

		// Clean up
		unlink( $php_file );
	}

	public function testValidateFileInRestrictedPath() {
		// Create test file in WordPress admin directory
		$test_file = $this->wp_root_dir . '/wp-admin/test.txt';

		// Ensure directory exists
		if ( ! file_exists( dirname( $test_file ) ) ) {
			mkdir( dirname( $test_file ), 0755, true );
		}

		file_put_contents( $test_file, 'Test content' );

		// Mock WordPress functions
		$this->mockWPVerifyNonce( true );
		$this->mockWPCheckFiletype(
			array(
				'type' => 'text/plain',
				'ext'  => 'txt',
			)
		);

		$result = $this->policy->validate_file(
			$test_file,
			array(
				'nonce' => 'valid_nonce',
			)
		);

		$this->assertFalse( $result['valid'] );
		$this->assertEquals( 'File in restricted path', $result['reason'] );

		// Clean up
		unlink( $test_file );
		if ( is_dir( dirname( $test_file ) ) ) {
			rmdir( dirname( $test_file ) );
		}
	}

	protected function mockWPVerifyNonce( $value ) {
		global $wp_verify_nonce_mock;
		$wp_verify_nonce_mock = $value;
	}

	protected function mockWPCheckFiletype( $value ) {
		global $wp_check_filetype_mock;
		$wp_check_filetype_mock = $value;
	}
	public function testValidateFileWithRandomFilename() {
		// Create file with random name but allowed extension
		$random_file = $this->wp_uploads_dir . '/x8k2m9p4q7w3.txt';
		file_put_contents( $random_file, 'Test content' );
		chmod( $random_file, 0644 );
		touch( $random_file, strtotime( '2025-01-01 12:00:00' ) );

		// Mock WordPress functions
		$this->mockWPVerifyNonce( true );
		$this->mockWPCheckFiletype(
			array(
				'type' => 'text/plain',
				'ext'  => 'txt',
			)
		);

		$result = $this->policy->validate_file(
			$random_file,
			array(
				'nonce' => 'valid_nonce',
			)
		);

		$this->assertFalse( $result['valid'] );
		$this->assertEquals( 'Suspicious random filename detected', $result['reason'] );

		// Clean up
		unlink( $random_file );
	}

	public function testValidateFileWithNormalFilename() {
		// Create file with normal name
		$normal_file = $this->wp_uploads_dir . '/wp-config.php';
		file_put_contents( $normal_file, '<?php echo "test"; ?>' );
		chmod( $normal_file, 0644 );
		touch( $normal_file, strtotime( '2025-01-01 12:00:00' ) );

		// Mock WordPress functions
		$this->mockWPVerifyNonce( true );
		$this->mockWPCheckFiletype(
			array(
				'type' => 'application/x-php',
				'ext'  => 'php',
			)
		);

		// We expect it to fail on extension, but NOT on random filename
		$result = $this->policy->validate_file(
			$normal_file,
			array(
				'nonce' => 'valid_nonce',
			)
		);

		$this->assertNotEquals( 'Suspicious random filename detected', $result['reason'] );

		// Clean up
		unlink( $normal_file );
	}

	public function testValidateFileWithDictionaryWord() {
		// Create file with high entropy but dictionary word
		// 'admin-ajax.php' has entropy ~2.8 but contains 'admin' and 'ajax'
		$dict_file = $this->wp_uploads_dir . '/admin-ajax.php';
		file_put_contents( $dict_file, '<?php echo "test"; ?>' );
		chmod( $dict_file, 0644 );
		touch( $dict_file, strtotime( '2025-01-01 12:00:00' ) );

		// Mock WordPress functions
		$this->mockWPVerifyNonce( true );
		$this->mockWPCheckFiletype(
			array(
				'type' => 'application/x-php',
				'ext'  => 'php',
			)
		);

		$result = $this->policy->validate_file(
			$dict_file,
			array(
				'nonce' => 'valid_nonce',
			)
		);

		// Should pass because of dictionary words
		$this->assertNotEquals( 'Suspicious random filename detected', $result['reason'] );

		// Clean up
		unlink( $dict_file );
	}

	private function calculateEntropy( $string ) {
		$size    = strlen( $string );
		$data    = count_chars( $string, 1 );
		$entropy = 0;

		foreach ( $data as $count ) {
			$p        = $count / $size;
			$entropy -= $p * log( $p, 2 );
		}

		return $entropy;
	}
}
