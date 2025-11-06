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
			file_put_contents( $htaccess, "Order deny,allow\nDeny from all" );
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

		$backup = $this->log_file . '.' . date( 'Y-m-d-H-i-s' );
		rename( $this->log_file, $backup );

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
				unlink( $old_backup );
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
		$backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 2 );
		$caller    = isset( $backtrace[1] ) ? $backtrace[1]['function'] : 'unknown';

		$log_message = sprintf(
			'[%s] [%s] [%s] %s',
			$timestamp,
			strtoupper( $level ),
			$caller,
			$message
		);

		// Log to WordPress error log for warning and above.
		if ( in_array( $level, array( 'warning', 'error', 'critical' ), true ) ) {
			error_log( $log_message );
		}

		// Store in database.
		if ( function_exists( 'update_option' ) ) {
			$log_key   = 'oms_security_log_' . date( 'Y-m-d' );
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
				mkdir( $log_dir, 0755, true );
			}

			if ( is_writable( $log_dir ) ) {
				file_put_contents(
					$log_file,
					$log_message . PHP_EOL,
					FILE_APPEND | LOCK_EX
				);

				// Rotate log file if it exceeds 5MB.
				if ( filesize( $log_file ) > 5 * 1024 * 1024 ) {
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

		$cutoff_date = date( 'Y-m-d', strtotime( '-7 days' ) );
		$old_logs    = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name FROM $wpdb->options 
				WHERE option_name LIKE 'oms_security_log_%%' 
				AND option_name < %s",
				'oms_security_log_' . $cutoff_date
			)
		);

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
			unlink( $log_file . '.' . $max_backups );
		}

		// Rotate existing backups.
		for ( $i = $max_backups - 1; $i >= 1; $i-- ) {
			$old_file = $log_file . '.' . $i;
			$new_file = $log_file . '.' . ( $i + 1 );
			if ( file_exists( $old_file ) ) {
				rename( $old_file, $new_file );
			}
		}

		// Rotate current log file.
		rename( $log_file, $log_file . '.1' );

		// Create new empty log file.
		touch( $log_file );
		chmod( $log_file, 0644 );
	}
}
