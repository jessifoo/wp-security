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
		$this->log_file = WP_CONTENT_DIR . '/oms-logs/malware-scanner.log';
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
	 * Rotate log file if it's too large
	 */
	private function maybe_rotate_log() {
		if ( ! file_exists( $this->log_file ) ) {
			return;
		}

		$max_size = 10 * 1024 * 1024; // 10MB.
		if ( filesize( $this->log_file ) < $max_size ) {
			return;
		}

		$backup = $this->log_file . '.' . gmdate( 'Y-m-d-H-i-s' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Log rotation requires atomic rename operation.
		$rename_result = rename( $this->log_file, $backup );
		if ( false === $rename_result ) {
			error_log( 'OMS Logger: Failed to rename log file for rotation: ' . esc_html( $this->log_file ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Security logging required.
			return;
		}

		// Keep only last 5 backups.
		$backups = glob( $this->log_file . '.*' );
		if ( count( $backups ) > 5 ) {
			usort(
				$backups,
				function ( $a, $b ) {
					return filemtime( $b ) - filemtime( $a );
				}
			);

			$old_backups = array_slice( $backups, 5 );
			foreach ( $old_backups as $old_backup ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Log cleanup requires direct file deletion.
				$unlink_result = unlink( $old_backup );
				if ( false === $unlink_result ) {
					error_log( 'OMS Logger: Failed to delete old backup log file: ' . esc_html( $old_backup ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Security logging required.
				}
			}
		}
	}

	/**
	 * Log message.
	 *
	 * @param string $message Log message.
	 * @param string $level Log level.
	 */
	public function log( $message, $level = 'info' ) {
		$valid_levels = array( 'debug', 'info', 'warning', 'error', 'critical' );
		$level        = strtolower( $level );

		if ( ! in_array( $level, $valid_levels, true ) ) {
			$level = 'info';
		}

		$timestamp = current_time( 'mysql' );
		$caller    = 'unknown';
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace -- Debug only when WP_DEBUG is enabled.
			$backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 2 );
			$caller    = isset( $backtrace[1] ) ? $backtrace[1]['function'] : 'unknown';
		}

		$log_message = sprintf(
			'[%s] [%s] [%s] %s',
			$timestamp,
			strtoupper( $level ),
			$caller,
			$message
		);

		// Log to WordPress error log for warning and above (only when WP_DEBUG is enabled).
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && in_array( $level, array( 'warning', 'error', 'critical' ), true ) ) {
			error_log( $log_message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging only when WP_DEBUG is enabled.
		}

		// Store in database.
		if ( function_exists( 'update_option' ) ) {
			$log_key   = 'oms_security_log_' . gmdate( 'Y-m-d' );
			$daily_log = get_option( $log_key, array() );

			$daily_log[] = array(
				'timestamp' => $timestamp,
				'level'     => $level,
				'message'   => $message,
				'caller'    => $caller,
			);

			// Keep only last 1000 entries per day.
			if ( count( $daily_log ) > 1000 ) {
				$daily_log = array_slice( $daily_log, -1000 );
			}

			update_option( $log_key, $daily_log, false );

			// Clean up old logs (keep last 7 days).
			$this->cleanup_old_logs();
		}

		// Write to file if configured.
		if ( defined( 'OMS_LOG_FILE' ) && OMS_Config::LOG_CONFIG ) {
			$log_file = WP_CONTENT_DIR . '/oms-logs/security.log';
			$log_dir  = dirname( $log_file );

			if ( ! is_dir( $log_dir ) ) {
				wp_mkdir_p( $log_dir );
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- Logging requires checking directory writability.
			if ( is_writable( $log_dir ) ) {
				$result = file_put_contents(
					$log_file,
					$log_message . PHP_EOL,
					FILE_APPEND | LOCK_EX
				);
				if ( false === $result ) {
					error_log( 'OMS Logger: Failed to write to log file: ' . esc_html( $log_file ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Security logging required.
				}

				// Rotate log file if it exceeds 5MB.
				if ( file_exists( $log_file ) && filesize( $log_file ) > 5 * 1024 * 1024 ) {
					$this->rotate_log_file( $log_file );
				}
			}
		}
	}

	/**
	 * Clean up old logs
	 */
	private function cleanup_old_logs() {
		global $wpdb;

		$cutoff_date = gmdate( 'Y-m-d', strtotime( '-7 days' ) );
		$cache_key   = 'oms_old_logs_' . $cutoff_date;

		// Check cache first.
		$old_logs = wp_cache_get( $cache_key, 'oms_logs' );
		if ( false === $old_logs ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Log cleanup requires direct query, caching added.
			$old_logs = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT option_name FROM $wpdb->options 
					WHERE option_name LIKE %s 
					AND option_name < %s",
					'oms_security_log_%',
					'oms_security_log_' . $cutoff_date
				)
			);

			// Cache for 1 hour.
			wp_cache_set( $cache_key, $old_logs, 'oms_logs', HOUR_IN_SECONDS );
		}

		foreach ( $old_logs as $log ) {
			delete_option( $log->option_name );
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
