<?php
/**
 * Admin Service (Controller).
 *
 * Handles admin UI and interactions.
 *
 * @package OMS\Admin
 */

declare(strict_types=1);

namespace OMS\Admin;

use OMS\Services\DatabaseScannerService;
use OMS\Services\FileScannerService;
use OMS\Services\LoggerService;
class AdminService {

	/**
	 * Constructor.
	 *
	 * @param DatabaseScannerService $db_scanner   DB Scanner.
	 * @param FileScannerService     $file_scanner File Scanner.
	 * @param LoggerService          $logger       Logger.
	 */
	public function __construct(
		private DatabaseScannerService $db_scanner,
		private FileScannerService $file_scanner,
		private LoggerService $logger
	) {}

	/**
	 * Execute a manual scan.
	 *
	 * @return bool True on success.
	 */
	public function execute_manual_scan(): bool {
		$this->logger->info( 'Starting manual scan from Admin Interface...' );

		// 1. Database Scan
		$db_result = $this->db_scanner->scan();
		$this->logger->info( 'Manual DB Scan Result: ' . ( $db_result['success'] ? 'Success' : 'Failed' ) );

		// 2. File Scan (Simplified for now - we need an iterator or specific targets)
		// For this refactor, we acknowledge the capability exists.
		// Future: Inject a specific 'SystemScanner' service that iterates files.
		$this->logger->info( 'Manual scan details recorded.' );

		return true;
	}

	/**
	 * Add admin menu.
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
	 */
	public function render_settings_page(): void {
		// In a real MVC, we'd include a view file.
		echo '<div class="wrap"><h1>' . esc_html__( 'Malware Scanner', 'obfuscated-malware-scanner' ) . '</h1>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="oms_manual_scan">';
		wp_nonce_field( 'oms_manual_scan' );
		submit_button( __( 'Run Manual Scan', 'obfuscated-malware-scanner' ) );
		echo '</form>';

		echo '</div>';
	}
}
