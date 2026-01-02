<?php
declare(strict_types=1);

/**
 * Plugin lifecycle management.
 *
 * Handles activation, deactivation, and installation logic.
 * This is a static utility class - no state is needed.
 *
 * @package ObfuscatedMalwareScanner
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access is not allowed.' );
}

/**
 * Plugin lifecycle handler.
 *
 * Static methods for activation, deactivation, and uninstall.
 * Following WordPress best practices for lifecycle hooks.
 */
final class OMS_Plugin {

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}

	/**
	 * Plugin activation handler.
	 *
	 * Called from register_activation_hook() callback.
	 * Creates directories, schedules cron, sets default options.
	 *
	 * @return void
	 */
	public static function activate(): void {
		// Verify minimum requirements.
		self::check_requirements();

		// Create protected directories.
		self::create_protected_directories();

		// Schedule cron job for daily cleanup.
		if ( ! wp_next_scheduled( 'oms_daily_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'oms_daily_cleanup' );
		}

		// Initialize options with default values.
		self::initialize_default_options();

		// Flush rewrite rules for REST API.
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation handler.
	 *
	 * Called from register_deactivation_hook() callback.
	 * Cleans up scheduled events and temporary data.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		// Clear scheduled cron job.
		$timestamp = wp_next_scheduled( 'oms_daily_cleanup' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'oms_daily_cleanup' );
		}

		// Clear all OMS scheduled events.
		wp_clear_scheduled_hook( 'oms_daily_cleanup' );

		// Clear transient cache.
		delete_transient( 'oms_core_checksums' );
	}

	/**
	 * Check minimum requirements.
	 *
	 * @throws RuntimeException If requirements not met.
	 * @return void
	 */
	private static function check_requirements(): void {
		$php_version = '8.4';
		$wp_version  = '6.5';

		// @phpstan-ignore-next-line Runtime check for environments with older PHP.
		if ( version_compare( PHP_VERSION, $php_version, '<' ) ) {
			deactivate_plugins( plugin_basename( OMS_PLUGIN_FILE ) );
			wp_die(
				esc_html(
					sprintf(
						/* translators: %s: Required PHP version */
						__( 'Obfuscated Malware Scanner requires PHP %s or higher.', 'obfuscated-malware-scanner' ),
						$php_version
					)
				),
				'Plugin Activation Error',
				array( 'back_link' => true )
			);
		}

		global $wp_version;
		if ( version_compare( $wp_version, $wp_version, '<' ) ) {
			deactivate_plugins( plugin_basename( OMS_PLUGIN_FILE ) );
			wp_die(
				esc_html(
					sprintf(
						/* translators: %s: Required WordPress version */
						__( 'Obfuscated Malware Scanner requires WordPress %s or higher.', 'obfuscated-malware-scanner' ),
						$wp_version
					)
				),
				'Plugin Activation Error',
				array( 'back_link' => true )
			);
		}
	}

	/**
	 * Create protected directories with .htaccess files.
	 *
	 * @return void
	 */
	private static function create_protected_directories(): void {
		$directories = array(
			'oms-logs'          => 'log',
			'oms-quarantine'    => 'quarantine',
			'oms-theme-backups' => 'backup',
			'oms-db-backups'    => 'database backup',
		);

		foreach ( $directories as $dir_name => $dir_type ) {
			$dir_path = WP_CONTENT_DIR . '/' . $dir_name;
			self::create_protected_directory( $dir_path, $dir_type );
		}
	}

	/**
	 * Create a protected directory with .htaccess file.
	 *
	 * @param string $dir_path Full path to the directory.
	 * @param string $dir_type Type of directory for error logging.
	 * @return void
	 */
	private static function create_protected_directory( string $dir_path, string $dir_type ): void {
		if ( file_exists( $dir_path ) ) {
			return;
		}

		wp_mkdir_p( $dir_path );

		$htaccess_file = $dir_path . '/.htaccess';
		if ( ! file_exists( $htaccess_file ) ) {
			// Create .htaccess that works with both Apache and Apache 2.4+.
			$htaccess_content = <<<'HTACCESS'
# Deny direct access to files in this directory
<IfModule mod_authz_core.c>
  Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
  Order deny,allow
  Deny from all
</IfModule>
HTACCESS;

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			$result = file_put_contents( $htaccess_file, $htaccess_content );
			if ( false === $result ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log(
					sprintf(
						'OMS Plugin: Failed to create .htaccess file for %s directory: %s',
						$dir_type,
						esc_html( $htaccess_file )
					)
				);
			}
		}

		// Also create index.php to prevent directory listing.
		$index_file = $dir_path . '/index.php';
		if ( ! file_exists( $index_file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $index_file, "<?php\n// Silence is golden.\n" );
		}
	}

	/**
	 * Initialize default plugin options.
	 *
	 * @return void
	 */
	private static function initialize_default_options(): void {
		$default_options = array(
			'oms_last_scan'           => 'never',
			'oms_files_scanned'       => 0,
			'oms_issues_found'        => 0,
			'oms_detected_issues'     => array(),
			'oms_scan_schedule'       => 'daily',
			'oms_auto_quarantine'     => true,
			'oms_email_notifications' => true,
			'oms_version'             => OMS_VERSION,
		);

		foreach ( $default_options as $option_name => $default_value ) {
			if ( false === get_option( $option_name ) ) {
				add_option( $option_name, $default_value );
			}
		}
	}
}
