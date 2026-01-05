<?php
declare(strict_types=1);

namespace OMS\Services;

/**
 * Logger Service.
 *
 * Handles writing logs to file and WordPress error log.
 *
 * @package OMS\Services
 */
class LoggerService {
	/**
	 * Log levels
	 */
	public const string ERROR   = 'ERROR';
	public const string WARNING = 'WARNING';
	public const string INFO    = 'INFO';
	public const string DEBUG   = 'DEBUG';

	/**
	 * In-memory logs for testing.
	 *
	 * @var array<string>
	 */
	private array $memory_logs = array();

	/**
	 * Log file path.
	 *
	 * @var string|null
	 */
	private ?string $log_file;

	/**
	 * Constructor.
	 *
	 * @param string|null $log_path  Path to the log directory.
	 * @param bool        $test_mode Whether to run in test mode (no file usage).
	 */
	public function __construct( ?string $log_path = null, private bool $test_mode = false ) {
		// Fallback to global constant if passed (for backward compat during refactor)
		// or if strictly testing without params.
		if ( defined( 'OMS_TEST_MODE' ) && OMS_TEST_MODE ) {
			$this->test_mode = true;
		}

		if ( $this->test_mode ) {
			return;
		}

		if ( $log_path ) {
			$this->log_file = rtrim( $log_path, '/' ) . '/malware-scanner.log';
			$this->init_log_dir();
		}
	}

	/**
	 * Initialize log directory.
	 */
	private function init_log_dir(): void {
		if ( ! $this->log_file ) {
			return;
		}

		$log_dir = dirname( $this->log_file );
		if ( ! is_dir( $log_dir ) ) {
			wp_mkdir_p( $log_dir );
		}

		// Secure the log directory.
		$htaccess = $log_dir . '/.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			$content = "Order deny,allow\nDeny from all\nRequire all denied\n";
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			if ( false === file_put_contents( $htaccess, $content ) ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'OMS Logger: Failed to create .htaccess file' );
			}
		}
	}

	/**
	 * Log an info message.
	 *
	 * @param string $message The message.
	 */
	public function info( string $message ): void {
		$this->log( $message, self::INFO );
	}

	/**
	 * Log an error message.
	 *
	 * @param string $message The message.
	 */
	public function error( string $message ): void {
		$this->log( $message, self::ERROR );
	}

	/**
	 * Log a warning message.
	 *
	 * @param string $message The message.
	 */
	public function warning( string $message ): void {
		$this->log( $message, self::WARNING );
	}

	/**
	 * Log a debug message.
	 *
	 * @param string $message The message.
	 */
	public function debug( string $message ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$this->log( $message, self::DEBUG );
		}
	}

	/**
	 * Generic log method.
	 *
	 * @param string $message The message.
	 * @param string $level   The log level.
	 */
	public function log( string $message, string $level = 'info' ): void {
		$level       = $this->validate_log_level( $level );
		$log_message = $this->format_log_message( $message, $level );

		$this->write( $log_message, $level );
	}

	/**
	 * Validate log level.
	 *
	 * @param string $level Input level.
	 * @return string Valid level.
	 */
	private function validate_log_level( string $level ): string {
		$valid = array( 'debug', 'info', 'warning', 'error', 'critical' );
		$level = strtolower( $level );
		return in_array( $level, $valid, true ) ? strtoupper( $level ) : 'INFO';
	}

	/**
	 * Format the message.
	 *
	 * @param string $message The message.
	 * @param string $level   The level.
	 * @return string The formatted line.
	 */
	private function format_log_message( string $message, string $level ): string {
		$timestamp = function_exists( 'current_time' ) ? current_time( 'mysql' ) : date( 'Y-m-d H:i:s' );
		return sprintf( '[%s] [%s] %s', $timestamp, $level, $message );
	}

	/**
	 * Write to destination.
	 *
	 * @param string $message Formatted message.
	 * @param string $level   Level.
	 */
	private function write( string $message, string $level ): void {
		if ( $this->test_mode ) {
			$this->memory_logs[] = $message;
			return;
		}

		// Write to WP Error Log if urgent
		if ( in_array( $level, array( 'ERROR', 'CRITICAL', 'WARNING' ), true ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( $message );
		}

		// Write to file
		if ( $this->log_file ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $this->log_file, $message . PHP_EOL, FILE_APPEND | LOCK_EX );
		}
	}

	/**
	 * Get memory logs.
	 *
	 * @return array<string>
	 */
	public function get_memory_logs(): array {
		return $this->memory_logs;
	}
}
