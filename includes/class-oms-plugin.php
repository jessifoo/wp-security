<?php
declare(strict_types=1);

/**
 * Plugin initialization class
 *
 * @package ObfuscatedMalwareScanner
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access is not allowed.' );
}

/**
 * Plugin initialization class.
 */
class OMS_Plugin {
	/**
	 * Plugin instance.
	 *
	 * @var OMS_Plugin|null
	 */
	private static ?OMS_Plugin $instance = null;

	/**
	 * Scanner instance.
	 *
	 * @var Obfuscated_Malware_Scanner|null
	 */
	private ?Obfuscated_Malware_Scanner $scanner = null;

	/**
	 * Get plugin instance.
	 *
	 * @return OMS_Plugin Plugin instance.
	 */
	public static function get_instance(): OMS_Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize plugin.
	 *
	 * @return void
	 */
	public function init(): void {

		$this->scanner = new Obfuscated_Malware_Scanner();

		// Initialize scanner.
		$this->scanner->init();

		// Add admin menu.
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
	}

	/**
	 * Add admin menu.
	 *
	 * @return void
	 */
	public function add_admin_menu(): void {
		add_menu_page(
			__( 'Malware Scanner', 'obfuscated-malware-scanner' ),
			__( 'Malware Scanner', 'obfuscated-malware-scanner' ),
			'manage_options',
			'obfuscated-malware-scanner',
			[ $this, 'render_admin_page' ],
			'dashicons-shield'
		);
	}

	/**
	 * Render admin page.
	 *
	 * @return void
	 */
	public function render_admin_page(): void {
		include_once OMS_PLUGIN_DIR . 'admin/partials/oms-admin-display.php';
	}

	/**
	 * Plugin activation handler.
	 *
	 * Creates necessary directories, sets up options, and schedules cron jobs.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function activate(): void {
		// Create protected directories.
		$directories = [
			'oms-logs'          => 'log',
			'oms-quarantine'    => 'quarantine',
			'oms-theme-backups' => 'backup',
			'oms-db-backups'    => 'database backup',
		];

		foreach ( $directories as $dir_name => $dir_type ) {
			$this->create_protected_directory( WP_CONTENT_DIR . '/' . $dir_name, $dir_type );
		}

		// Schedule cron job for daily cleanup.
		if ( ! wp_next_scheduled( 'oms_daily_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'oms_daily_cleanup' );
		}

		// Initialize options with default values.
		$this->initialize_default_options();
	}

	/**
	 * Create a protected directory with .htaccess file.
	 *
	 * @since 1.0.0
	 * @param string $dir_path Full path to the directory.
	 * @param string $dir_type Type of directory for error logging.
	 * @return void
	 */
	private function create_protected_directory( string $dir_path, string $dir_type ): void {
		if ( file_exists( $dir_path ) ) {
			return;
		}

		wp_mkdir_p( $dir_path );

		$htaccess_file = $dir_path . '/.htaccess';
		if ( ! file_exists( $htaccess_file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			$result = file_put_contents( $htaccess_file, "Order deny,allow\nDeny from all\nRequire all denied\n" );
			if ( false === $result ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'OMS Plugin: Failed to create .htaccess file for ' . $dir_type . ' directory: ' . esc_html( $htaccess_file ) );
			}
		}
	}

	/**
	 * Initialize default plugin options.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function initialize_default_options(): void {
		$default_options = [
			'oms_last_scan'           => 'never',
			'oms_files_scanned'       => 0,
			'oms_issues_found'        => 0,
			'oms_detected_issues'     => [],
			'oms_scan_schedule'       => 'daily',
			'oms_auto_quarantine'     => true,
			'oms_email_notifications' => true,
		];

		foreach ( $default_options as $option_name => $default_value ) {
			if ( false === get_option( $option_name ) ) {
				add_option( $option_name, $default_value );
			}
		}
	}

	/**
	 * Plugin deactivation handler.
	 *
	 * Cleans up scheduled events and temporary data.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function deactivate(): void {
		// Clear scheduled cron job.
		$timestamp = wp_next_scheduled( 'oms_daily_cleanup' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'oms_daily_cleanup' );
		}
	}
}
