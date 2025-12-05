<?php
/**
 * Logger class for the Obfuscated Malware Scanner
 *
 * @package ObfuscatedMalwareScanner
 */

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
	const ERROR   = 'ERROR';
	const WARNING = 'WARNING';
	const INFO    = 'INFO';
	const DEBUG   = 'DEBUG';

	/**
	 * Log file path
	 *
	 * @var string
	 */
	private $log_file;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->log_file = OMS_Config::LOG_CONFIG['path'] . '/malware-scanner.log';
		$this->init_log_dir();
	}

	/**
	 * Initialize log directory
	 */
	private function init_log_dir() {
		$log_dir = dirname( $this->log_file );
		if ( ! is_dir( $log_dir ) ) {
			wp_mkdir_p( $log_dir );
		}

		// Secure the log directory.
		$htaccess = $log_dir . '/.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			$result = file_put_contents( $htaccess, "Order deny,allow\nDeny from all" );
			if ( false === $result ) {
				error_log( 'OMS Logger: Failed to create .htaccess file for log directory: ' . esc_html( $htaccess ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Security logging required.
			}
		}
	}

	/**
	 * Log an error message
	 *
	 * @param string $message Error message.
	 */
	public function error( $message ) {
		$this->log( self::ERROR, $message );
	}

	/**
	 * Log a warning message
	 *
	 * @param string $message Warning message.
	 */
	public function warning( $message ) {
		$this->log( self::WARNING, $message );
	}

	/**
	 * Log an info message
	 *
	 * @param string $message Info message.
	 */
	public function info( $message ) {
		$this->log( self::INFO, $message );
	}

	/**
	 * Log a debug message
	 *
	 * @param string $message Debug message.
	 */
	public function debug( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$this->log( self::DEBUG, $message );
		}
	}



	/**
	 * Log message.
	 *
	 * @param string $message Log message.
	 * @param string $level Log level.
	 */
	public function log( $message, $level = 'info' ) {
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
	private function validate_log_level( $level ) {
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
	private function format_log_message( $message, $level ) {
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
	private function get_caller_function() {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return 'unknown';
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace -- Debug only when WP_DEBUG is enabled.
		$backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 3 );
		return isset( $backtrace[2] ) ? $backtrace[2]['function'] : 'unknown';
	}

	/**
	 * Log to WordPress error log for warning and above.
	 *
	 * @param string $log_message Formatted log message.
	 * @param string $level Log level.
	 */
	private function log_to_wp_error_log( $log_message, $level ) {
		$should_log   = defined( 'WP_DEBUG' ) && WP_DEBUG;
		$is_important = in_array( $level, array( 'warning', 'error', 'critical' ), true );

		if ( $should_log && $is_important ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging only when WP_DEBUG is enabled.
			error_log( $log_message );
		}
	}

	/**
	 * Write log message to file.
	 *
	 * @param string $log_message Formatted log message.
	 */
	private function log_to_file( $log_message ) {
		if ( ! defined( 'OMS_LOG_FILE' ) ) {
			return;
		}

		$log_file = OMS_Config::LOG_CONFIG['path'] . '/security.log';
		$log_dir  = dirname( $log_file );

		if ( ! is_dir( $log_dir ) ) {
			wp_mkdir_p( $log_dir );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- Logging requires checking directory writability.
		if ( ! is_writable( $log_dir ) ) {
			return;
		}

		$result = file_put_contents(
			$log_file,
			$log_message . PHP_EOL,
			FILE_APPEND | LOCK_EX
		);

		if ( false === $result ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Security logging required.
			error_log( 'OMS Logger: Failed to write to log file: ' . esc_html( $log_file ) );
		}

		// Rotate log file if it exceeds 5MB.
		$this->maybe_rotate_log_file( $log_file );
	}

	/**
	 * Check if log file needs rotation and rotate if needed.
	 *
	 * @param string $log_file Log file path.
	 */
	private function maybe_rotate_log_file( $log_file ) {
		$max_size = 5 * 1024 * 1024; // 5MB.

		if ( file_exists( $log_file ) && filesize( $log_file ) > $max_size ) {
			$this->rotate_log_file( $log_file );
		}
	}

	/**
	 * Rotate log file
	 *
	 * @param string $log_file Log file path.
	 */
	private function rotate_log_file( $log_file ) {
		$max_backups = 5;

		// Remove oldest backup if exists.
		if ( file_exists( $log_file . '.' . $max_backups ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Log rotation requires direct file deletion.
			$unlink_result = unlink( $log_file . '.' . $max_backups );
			if ( false === $unlink_result ) {
				error_log( 'OMS Logger: Failed to delete oldest backup log file: ' . esc_html( $log_file . '.' . $max_backups ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Security logging required.
			}
		}

		// Rotate existing backups.
		for ( $i = $max_backups - 1; $i >= 1; $i-- ) {
			$old_file = $log_file . '.' . $i;
			$new_file = $log_file . '.' . ( $i + 1 );
			if ( file_exists( $old_file ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Log rotation requires atomic rename operation.
				$rename_result = rename( $old_file, $new_file );
				if ( false === $rename_result ) {
					error_log( 'OMS Logger: Failed to rotate backup log file: ' . esc_html( $old_file ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Security logging required.
				}
			}
		}

		// Rotate current log file.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Log rotation requires atomic rename operation.
		$rename_result = rename( $log_file, $log_file . '.1' );
		if ( false === $rename_result ) {
			error_log( 'OMS Logger: Failed to rename current log file for rotation: ' . esc_html( $log_file ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Security logging required.
			return;
		}

		// Create new empty log file.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_touch -- Log rotation requires creating new log file.
		$touch_result = touch( $log_file );
		if ( false === $touch_result ) {
			error_log( 'OMS Logger: Failed to create new log file: ' . esc_html( $log_file ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Security logging required.
			return;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Log rotation requires setting file permissions.
		$chmod_result = chmod( $log_file, 0644 );
		if ( false === $chmod_result ) {
			error_log( 'OMS Logger: Failed to set permissions on log file: ' . esc_html( $log_file ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Security logging required.
		}
	}
}
