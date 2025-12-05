<?php
/**
 * API Handler for Centralized Management
 *
 * @package ObfuscatedMalwareScanner
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access is not allowed.' );
}

/**
 * Class OMS_API
 */
class OMS_API {

	/**
	 * Logger instance.
	 *
	 * @var OMS_Logger
	 */
	private $logger;

	/**
	 * Scanner instance.
	 *
	 * @var Obfuscated_Malware_Scanner
	 */
	private $scanner;

	/**
	 * Constructor.
	 *
	 * @param OMS_Logger                 $logger  Logger instance.
	 * @param Obfuscated_Malware_Scanner $scanner Scanner instance.
	 */
	public function __construct( $logger, $scanner ) {
		$this->logger  = $logger;
		$this->scanner = $scanner;
	}

	/**
	 * Initialize API routes.
	 */
	public function init() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API routes.
	 */
	public function register_routes() {
		$namespace = 'oms/v1';

		// Register site (Handshake)
		register_rest_route(
			$namespace,
			'/register',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_registration' ),
				'permission_callback' => '__return_true', // Open endpoint for initial handshake, secured by shared secret or manual approval logic if needed.
			)
		);

		// Get Status
		register_rest_route(
			$namespace,
			'/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_status' ),
				'permission_callback' => array( $this, 'check_api_permission' ),
			)
		);

		// Trigger Scan
		register_rest_route(
			$namespace,
			'/scan',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'trigger_scan' ),
				'permission_callback' => array( $this, 'check_api_permission' ),
			)
		);

		// Get Report
		register_rest_route(
			$namespace,
			'/report',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_report' ),
				'permission_callback' => array( $this, 'check_api_permission' ),
			)
		);
	}

	/**
	 * Check API permission.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error True if authorized, WP_Error otherwise.
	 */
	public function check_api_permission( $request ) {
		$api_key = $request->get_header( 'X-OMS-API-Key' );
		if ( ! $api_key ) {
			return new WP_Error( 'rest_forbidden', 'Missing API Key', array( 'status' => 401 ) );
		}

		$stored_key = get_option( 'oms_api_key' );
		if ( ! $stored_key || ! hash_equals( $stored_key, $api_key ) ) {
			return new WP_Error( 'rest_forbidden', 'Invalid API Key', array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Handle site registration.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function handle_registration( $request ) {
		$params = $request->get_json_params();

		// In a real scenario, we might want a temporary token or manual approval.
		// For now, we'll accept a 'master_key' to establish trust.
		// Validate master key against shared secret.
		if ( empty( $params['master_key'] ) || empty( $params['dashboard_url'] ) ) {
			return new WP_REST_Response( array( 'error' => 'Missing parameters' ), 400 );
		}

		if ( ! hash_equals( OMS_Config::OMS_LINKING_KEY, $params['master_key'] ) ) {
			$this->logger->warning( 'Invalid master key provided for registration from: ' . esc_html( $params['dashboard_url'] ) );
			return new WP_REST_Response( array( 'error' => 'Invalid master key' ), 403 );
		}

		// Generate a new API key for this site
		$new_api_key = wp_generate_password( 64, false );
		update_option( 'oms_api_key', $new_api_key );
		update_option( 'oms_master_dashboard_url', esc_url_raw( $params['dashboard_url'] ) );

		$this->logger->info( 'Site registered with master dashboard: ' . $params['dashboard_url'] );

		return new WP_REST_Response(
			array(
				'success'  => true,
				'api_key'  => $new_api_key,
				'site_url' => get_site_url(),
			),
			200
		);
	}

	/**
	 * Get site status.
	 *
	 * @return WP_REST_Response Response object.
	 */
	public function get_status() {
		// This would retrieve real status from the scanner
		$last_scan = get_option( 'oms_last_scan_time' );
		$status = array(
			'version'     => '1.0.0',
			'last_scan'   => $last_scan ? gmdate( 'c', $last_scan ) : null,
			'php_version' => phpversion(),
			'wp_version'  => get_bloginfo( 'version' ),
		);

		return new WP_REST_Response( $status, 200 );
	}

	/**
	 * Trigger a scan.
	 *
	 * @return WP_REST_Response Response object.
	 */
	public function trigger_scan() {
		// Trigger the scan asynchronously if possible, or synchronously for now
		try {
			$this->scanner->run_full_cleanup();
			return new WP_REST_Response( array( 'success' => true, 'message' => 'Scan completed' ), 200 );
		} catch ( Exception $e ) {
			return new WP_REST_Response( array( 'success' => false, 'error' => $e->getMessage() ), 500 );
		}
	}

	/**
	 * Get latest report.
	 *
	 * @return WP_REST_Response Response object.
	 */
	public function get_report() {
		// Retrieve logs or report data
		$log_path = $this->scanner->get_log_path();
		// Append log filename to directory path.
		$log_file = $log_path . '/security.log';

		$logs = array();
		if ( file_exists( $log_file ) ) {
			// Read last 100 lines or similar
			$logs = array_slice( file( $log_file ), -50 );
		}

		return new WP_REST_Response( array( 'logs' => $logs ), 200 );
	}
}
