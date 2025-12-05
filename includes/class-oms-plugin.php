<?php
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
	 * @var OMS_Plugin
	 */
	private static $instance = null;

	/**
	 * Scanner instance.
	 *
	 * @var Obfuscated_Malware_Scanner
	 */
	private $scanner = null;



	/**
	 * Get plugin instance.
	 *
	 * @return OMS_Plugin Plugin instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize plugin.
	 */
	public function init() {

		$this->scanner = new Obfuscated_Malware_Scanner();

		// Initialize scanner.
		$this->scanner->init();

		// Add admin menu.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu() {
		add_menu_page(
			__( 'Malware Scanner', 'obfuscated-malware-scanner' ),
			__( 'Malware Scanner', 'obfuscated-malware-scanner' ),
			'manage_options',
			'obfuscated-malware-scanner',
			array( $this, 'render_admin_page' ),
			'dashicons-shield'
		);
	}

	/**
	 * Render admin page.
	 */
	public function render_admin_page() {
		include_once OMS_PLUGIN_DIR . 'admin/partials/oms-admin-display.php';
	}

	/**
	 * Plugin activation handler.
	 *
	 * Creates necessary directories, sets up options, and schedules cron jobs.
	 *
	 * @since 1.0.0
	 */
	public function activate() {
		// Create protected directories.
		$directories = array(
			'oms-logs'          => 'log',
			'oms-quarantine'    => 'quarantine',
			'oms-theme-backups' => 'backup',
			'oms-db-backups'    => 'database backup',
		);

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
	 */
	private function create_protected_directory( $dir_path, $dir_type ) {
		if ( file_exists( $dir_path ) ) {
			return;
		}

		wp_mkdir_p( $dir_path );

		$htaccess_file = $dir_path . '/.htaccess';
		if ( ! file_exists( $htaccess_file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Required for .htaccess creation.
			$result = file_put_contents( $htaccess_file, "deny from all\n" );
			if ( false === $result ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Security logging required.
				error_log( 'OMS Plugin: Failed to create .htaccess file for ' . $dir_type . ' directory: ' . esc_html( $htaccess_file ) );
			}
		}
	}

	/**
	 * Initialize default plugin options.
	 *
	 * @since 1.0.0
	 */
	private function initialize_default_options() {
		$default_options = array(
			'oms_last_scan'           => 'never',
			'oms_files_scanned'       => 0,
			'oms_issues_found'        => 0,
			'oms_detected_issues'     => array(),
			'oms_scan_schedule'       => 'daily',
			'oms_auto_quarantine'     => true,
			'oms_email_notifications' => true,
		);

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
	 */
	public function deactivate() {
		// Clear scheduled cron job.
		$timestamp = wp_next_scheduled( 'oms_daily_cleanup' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'oms_daily_cleanup' );
		}
	}
}
