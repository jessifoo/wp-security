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
	 * Cache instance.
	 *
	 * @var OMS_Cache
	 */
	private $cache = null;

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
		$this->cache   = new OMS_Cache();
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
		// Create log directory.
		$log_dir = WP_CONTENT_DIR . '/oms-logs';
		if ( ! file_exists( $log_dir ) ) {
			wp_mkdir_p( $log_dir );
			// Create .htaccess to protect logs.
			$htaccess_file = $log_dir . '/.htaccess';
			if ( ! file_exists( $htaccess_file ) ) {
				file_put_contents( $htaccess_file, "deny from all\n" );
			}
		}

		// Create quarantine directory.
		$quarantine_dir = WP_CONTENT_DIR . '/oms-quarantine';
		if ( ! file_exists( $quarantine_dir ) ) {
			wp_mkdir_p( $quarantine_dir );
			// Create .htaccess to protect quarantine.
			$htaccess_file = $quarantine_dir . '/.htaccess';
			if ( ! file_exists( $htaccess_file ) ) {
				file_put_contents( $htaccess_file, "deny from all\n" );
			}
		}

		// Create theme backup directory.
		$backup_dir = WP_CONTENT_DIR . '/oms-theme-backups';
		if ( ! file_exists( $backup_dir ) ) {
			wp_mkdir_p( $backup_dir );
			// Create .htaccess to protect backups.
			$htaccess_file = $backup_dir . '/.htaccess';
			if ( ! file_exists( $htaccess_file ) ) {
				file_put_contents( $htaccess_file, "deny from all\n" );
			}
		}

		// Schedule cron job for daily cleanup.
		if ( ! wp_next_scheduled( 'oms_daily_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'oms_daily_cleanup' );
		}

		// Initialize options with default values.
		if ( false === get_option( 'oms_last_scan' ) ) {
			add_option( 'oms_last_scan', 'never' );
		}
		if ( false === get_option( 'oms_files_scanned' ) ) {
			add_option( 'oms_files_scanned', 0 );
		}
		if ( false === get_option( 'oms_issues_found' ) ) {
			add_option( 'oms_issues_found', 0 );
		}
		if ( false === get_option( 'oms_detected_issues' ) ) {
			add_option( 'oms_detected_issues', array() );
		}
		if ( false === get_option( 'oms_scan_schedule' ) ) {
			add_option( 'oms_scan_schedule', 'daily' );
		}
		if ( false === get_option( 'oms_auto_quarantine' ) ) {
			add_option( 'oms_auto_quarantine', true );
		}
		if ( false === get_option( 'oms_email_notifications' ) ) {
			add_option( 'oms_email_notifications', true );
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
