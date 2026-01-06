<?php
use PHPUnit\Framework\TestCase;

class OMS_APITest extends TestCase {
	use TestHelperTrait;
	use WordPressMocksTrait;

	private $api;
	private $logger;
	private $scanner;

	protected function setUp(): void {
		$this->createTemporaryDirectory();
		$this->mockWordPressEnvironment();

		$this->logger  = $this->createMock( OMS_Logger::class );
		$this->scanner = $this->createMock( Obfuscated_Malware_Scanner::class );

		$this->api = new OMS_API( $this->logger, $this->scanner );
	}

	protected function tearDown(): void {
		$this->cleanupTestEnvironment();
		$this->teardown_wordpress_mocks();
	}

	public function testHandleRegistrationSuccess() {
		$request = $this->createMock( WP_REST_Request::class );
		$request->method( 'get_json_params' )->willReturn(
			array(
				'master_key'    => OMS_Config::OMS_LINKING_KEY,
				'dashboard_url' => 'https://master.example.com',
			)
		);

		// Mock wp_generate_password
		global $wp_generate_password_return;
		$wp_generate_password_return = 'new_api_key';

		// Mock get_site_url
		global $site_url_return;
		$site_url_return = 'https://site.example.com';

		$response = $this->api->handle_registration( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertEquals( 'new_api_key', $data['api_key'] );

		// Verify options updated
		global $wp_options;
		$this->assertEquals( 'new_api_key', $wp_options['oms_api_key'] );
		$this->assertEquals( 'https://master.example.com', $wp_options['oms_master_dashboard_url'] );
	}

	public function testHandleRegistrationMissingParams() {
		$request = $this->createMock( WP_REST_Request::class );
		$request->method( 'get_json_params' )->willReturn( array() );

		$response = $this->api->handle_registration( $request );

		$this->assertEquals( 400, $response->get_status() );
	}

	public function testCheckApiPermissionSuccess() {
		global $wp_options;
		$wp_options['oms_api_key'] = 'valid_key';

		$request = $this->createMock( WP_REST_Request::class );
		$request->method( 'get_header' )->with( 'X-OMS-API-Key' )->willReturn( 'valid_key' );

		$result = $this->api->check_api_permission( $request );
		$this->assertTrue( $result );
	}

	public function testCheckApiPermissionFailure() {
		global $wp_options;
		$wp_options['oms_api_key'] = 'valid_key';

		$request = $this->createMock( WP_REST_Request::class );
		$request->method( 'get_header' )->with( 'X-OMS-API-Key' )->willReturn( 'invalid_key' );

		$result = $this->api->check_api_permission( $request );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 403, $result->get_error_data()['status'] );
	}

	public function testTriggerScanSuccess() {
		$this->scanner->expects( $this->once() )->method( 'run_full_cleanup' );

		$response = $this->api->trigger_scan();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['success'] );
	}

	public function testGetReport() {
		$expected_logs = array( "Line 1\n", 'Line 2' );

		// Configure logger mock to return expected memory logs
		$this->logger->method( 'get_memory_logs' )
			->willReturn( $expected_logs );

		// We don't need to mock get_log_path or file existence anymore
		// because the API prioritizes memory logs in test mode.

		$response = $this->api->get_report();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertCount( 2, $response->get_data()['logs'] );
	}
}
