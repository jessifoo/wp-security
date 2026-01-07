<?php
/**
 * Logger class for the Obfuscated Malware Scanner.
 *
 * Handles logging operations and log file management.
 *
 * @package ObfuscatedMalwareScanner
 */

declare( strict_types=1 );

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access is not allowed.' );
}

/**
 * Logger class for the Obfuscated Malware Scanner
 *
 * @phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- Logger class name follows plugin naming convention.
 */
class OMS_Logger {
	/**
	 * Log levels
	 */
	public const string ERROR   = 'ERROR';
	public const string WARNING = 'WARNING';
	public const string INFO    = 'INFO';
	public const string DEBUG   = 'DEBUG';

	/**
	 * Log file path
	 *
	 * @var string
	 */
	private string $log_file;

	/**
	 * In-memory logs for testing.
	 *
	 * @var array
	 */
	private array $memory_logs = array();

	/**
	 * Constructor
	 */
	public function __construct() {
		if ( defined( 'OMS_TEST_MODE' ) ? OMS_TEST_MODE : false ) {
			return;
		}
		$this->log_file = (string) OMS_Config::LOG_CONFIG['path'] . '/malware-scanner.log';
		$this->init_log_dir();
	}

	/**
	 * Initialize log directory
	 *
	 * @return void
	 */
	private function init_log_dir(): void {
		$log_dir = dirname( $this->log_file );
		if ( ! is_dir( $log_dir ) ) {
			wp_mkdir_p( $log_dir );
		}

		// Secure the log directory.
		$htaccess = $log_dir . '/.htaccess';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_exists -- Checking for .htaccess file existence.
		if ( ! file_exists( $htaccess ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writing security .htaccess file for log directory.
			$result = file_put_contents( $htaccess, "Order deny,allow\nDeny from all\nRequire all denied\n" );
			if ( false === $result ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Error logging is the purpose of this class.
				error_log( 'OMS Logger: Failed to create .htaccess file for log directory: ' . esc_html( $htaccess ) );
			}
		}
	}

	/**
	 * Log an error message
	 *
	 * @param string $message Error message.
	 * @return void
	 */
	public function error( string $message ): void {
		$this->log( self::ERROR, $message );
	}

	/**
	 * Log a warning message
	 *
	 * @param string $message Warning message.
	 * @return void
	 */
	public function warning( string $message ): void {
		$this->log( self::WARNING, $message );
	}

	/**
	 * Log an info message
	 *
	 * @param string $message Info message.
	 * @return void
	 */
	public function info( string $message ): void {
		$this->log( self::INFO, $message );
	}

	/**
	 * Log a debug message
	 *
	 * @param string $message Debug message.
	 * @return void
	 */
	public function debug( string $message ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$this->log( self::DEBUG, $message );
		}
	}

	/**
	 * Log message.
	 *
	 * @param string $message Log message.
	 * @param string $level Log level.
	 * @return void
	 */
	public function log( string $message, string $level = 'info' ): void {
		$level       = $this->validate_log_level( $level );
		$log_message = $this->format_log_message( $message, $level );

		$this->log_to_wp_error_log( $log_message, $level );
		$this->log_to_file( $log_message );
	}

	/**
	 * Validate and normalize log level.
	 *
	 * @param string $level Log level to validate.
	 * @return string Validated log level.
	 */
	private function validate_log_level( string $level ): string {
		$valid_levels = array( 'debug', 'info', 'warning', 'error', 'critical' );
		$level        = strtolower( $level );

		return in_array( $level, $valid_levels, true ) ? $level : 'info';
	}

	/**
	 * Format log message with timestamp, level, and caller.
	 *
	 * @param string $message Raw message.
	 * @param string $level Log level.
	 * @return string Formatted log message.
	 */
	private function format_log_message( string $message, string $level ): string {
		$timestamp = current_time( 'mysql' );
		$caller    = $this->get_caller_function();

		return sprintf(
			'[%s] [%s] [%s] %s',
			$timestamp,
			strtoupper( $level ),
			$caller,
			$message
		);
	}

	/**
	 * Get the calling function name.
	 *
	 * @return string Caller function name.
	 */
	private function get_caller_function(): string {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return 'unknown';
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace -- Backtrace needed for logging caller information in debug mode.
		$backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 3 );
		return isset( $backtrace[2] ) ? (string) $backtrace[2]['function'] : 'unknown';
	}

	/**
	 * Log to WordPress error log for warning and above.
	 *
	 * @param string $log_message Formatted log message.
	 * @param string $level Log level.
	 * @return void
	 */
	private function log_to_wp_error_log( string $log_message, string $level ): void {
		$should_log   = defined( 'WP_DEBUG' ) && WP_DEBUG;
		$is_important = in_array( $level, array( 'warning', 'error', 'critical' ), true );

		if ( $should_log && $is_important ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Error logging is the purpose of this logger class.
			error_log( $log_message );
		}
	}

	/**
	 * Write log message to file.
	 *
	 * @param string $log_message Formatted log message.
	 * @return void
	 */
	private function log_to_file( string $log_message ): void {
		if ( defined( 'OMS_TEST_MODE' ) ? OMS_TEST_MODE : false ) {
			$this->memory_logs[] = $log_message;
			return;
		}

		if ( ! defined( 'OMS_LOG_FILE' ) ) {
			return;
		}

		$log_file = (string) OMS_Config::LOG_CONFIG['path'] . '/security.log';
		$log_dir  = dirname( $log_file );

		if ( ! is_dir( $log_dir ) ) {
			wp_mkdir_p( $log_dir );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- Checking log directory writability.
		if ( ! is_writable( $log_dir ) ) {
			return;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writing to log file with locking for thread safety.
		$result = file_put_contents(
			$log_file,
			$log_message . PHP_EOL,
			FILE_APPEND | LOCK_EX
		);

		if ( false === $result ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Error logging is the purpose of this logger class.
			error_log( 'OMS Logger: Failed to write to log file: ' . esc_html( $log_file ) );
		}

		// Rotate log file if it exceeds 5MB.
		$this->maybe_rotate_log_file( $log_file );
	}

	/**
	 * Get in-memory logs (for testing).
	 *
	 * @return array
	 */
	public function get_memory_logs(): array {
		return $this->memory_logs;
	}

	/**
	 * Check if log file needs rotation and rotate if needed.
	 *
	 * @param string $log_file Log file path.
	 * @return void
	 */
	private function maybe_rotate_log_file( string $log_file ): void {
		$max_size = 5 * 1024 * 1024; // 5MB.

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_exists,WordPress.WP.AlternativeFunctions.file_system_operations_filesize -- Checking log file size for rotation.
		if ( file_exists( $log_file ) && filesize( $log_file ) > $max_size ) {
			$this->rotate_log_file( $log_file );
		}
	}

	/**
	 * Rotate log file
	 *
	 * @param string $log_file Log file path.
	 * @return void
	 */
	private function rotate_log_file( string $log_file ): void {
		$max_backups = 5;

		// Remove oldest backup if exists.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_exists -- Checking for log backup file existence.
		if ( file_exists( $log_file . '.' . $max_backups ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Removing old log backup file.
			$unlink_result = unlink( $log_file . '.' . $max_backups );
			if ( false === $unlink_result ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Error logging is the purpose of this logger class.
				error_log( 'OMS Logger: Failed to delete oldest backup log file: ' . esc_html( $log_file . '.' . $max_backups ) );
			}
		}

		// Rotate existing backups.
		for ( $i = $max_backups - 1; $i >= 1; $i-- ) {
			$old_file = $log_file . '.' . $i;
			$new_file = $log_file . '.' . ( $i + 1 );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_exists -- Checking for log backup file existence.
			if ( file_exists( $old_file ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Rotating log backup files.
				$rename_result = rename( $old_file, $new_file );
				if ( false === $rename_result ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Error logging is the purpose of this logger class.
					error_log( 'OMS Logger: Failed to rotate backup log file: ' . esc_html( $old_file ) );
				}
			}
		}

		// Rotate current log file.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Rotating current log file to backup.
		$rename_result = rename( $log_file, $log_file . '.1' );
		if ( false === $rename_result ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Error logging is the purpose of this logger class.
			error_log( 'OMS Logger: Failed to rename current log file for rotation: ' . esc_html( $log_file ) );
			return;
		}

		// Create new empty log file.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_touch -- Creating new log file after rotation.
		$touch_result = touch( $log_file );
		if ( false === $touch_result ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Error logging is the purpose of this logger class.
			error_log( 'OMS Logger: Failed to create new log file: ' . esc_html( $log_file ) );
			return;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Setting secure permissions on log file.
		$chmod_result = chmod( $log_file, 0644 );
		if ( false === $chmod_result ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Error logging is the purpose of this logger class.
			error_log( 'OMS Logger: Failed to set permissions on log file: ' . esc_html( $log_file ) );
		}
	}
}
