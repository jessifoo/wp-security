<?php
/**
 * Admin Service (Controller).
 *
 * Handles admin UI and interactions.
 *
 * @package OMS\Admin
 */

declare( strict_types=1 );

namespace OMS\Admin;

use OMS\Services\DatabaseScannerService;
use OMS\Services\FileScannerService;
use OMS\Services\LoggerService;

/**
 * Admin Service class.
 *
 * Manages admin interface and handles user interactions.
 *
 * @package OMS\Admin
 */
class AdminService {

	/**
	 * Database scanner service.
	 *
	 * @var DatabaseScannerService
	 */
	private DatabaseScannerService $db_scanner;

	/**
	 * File scanner service.
	 *
	 * @var FileScannerService
	 */
	private FileScannerService $file_scanner;

	/**
	 * Logger service.
	 *
	 * @var LoggerService
	 */
	private LoggerService $logger;

	/**
	 * Constructor.
	 *
	 * @param DatabaseScannerService $db_scanner   DB Scanner.
	 * @param FileScannerService     $file_scanner File Scanner.
	 * @param LoggerService          $logger       Logger.
	 */
	public function __construct(
		DatabaseScannerService $db_scanner,
		FileScannerService $file_scanner,
		LoggerService $logger
	) {
		$this->db_scanner   = $db_scanner;
		$this->file_scanner = $file_scanner;
		$this->logger       = $logger;
	}

	/**
	 * Execute a manual scan.
	 *
	 * @return bool True on success.
	 */
	public function execute_manual_scan(): bool {
		$this->logger->info( 'Starting manual scan from Admin Interface...' );

		// Perform database scan.
		$db_result = $this->db_scanner->scan();
		$this->logger->info( 'Manual scan - Database result: ' . ( $db_result['success'] ? 'Success' : 'Failed' ) );

		// File scan capability exists but requires specific targets.
		$this->logger->info( 'Manual scan completed.' );

		return true;
	}

	/**
	 * Add admin menu.
	 *
	 * @return void
	 */
	public function add_admin_menu(): void {
		add_options_page(
			__( 'Obfuscated Malware Scanner', 'obfuscated-malware-scanner' ),
			__( 'Malware Scanner', 'obfuscated-malware-scanner' ),
			'manage_options',
			'obfuscated-malware-scanner',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		echo '<div class="wrap"><h1>' . esc_html__( 'Malware Scanner', 'obfuscated-malware-scanner' ) . '</h1>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="oms_manual_scan">';
		wp_nonce_field( 'oms_manual_scan' );
		submit_button( __( 'Run Manual Scan', 'obfuscated-malware-scanner' ) );
		echo '</form>';

		echo '</div>';
	}
}
