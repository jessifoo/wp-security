<?php
/**
 * Scanner class for malware detection
 *
 * Handles file scanning and pattern matching functionality.
 * Part of the refactoring effort to improve code organization and maintainability.
 *
 * @package ObfuscatedMalwareScanner
 */

declare(strict_types=1);

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access is not allowed.' );
}

/**
 * Scanner class responsible for file analysis and malware detection
 */
class OMS_Scanner {
	/**
	 * Compiled regex patterns.
	 *
	 * @var array<string>
	 */
	private array $compiled_patterns = array();

	/**
	 * Constructor
	 *
	 * @param OMS_Logger       $logger Logger instance.
	 * @param OMS_Rate_Limiter $rate_limiter Rate limiter instance.
	 * @param OMS_Cache        $cache Cache instance.
	 */
	public function __construct(
		private readonly OMS_Logger $logger,
		private readonly OMS_Rate_Limiter $rate_limiter,
		private readonly OMS_Cache $cache
	) {
		$this->compiled_patterns = $this->compile_patterns();
	}

	/**
	 * Compile malware detection patterns
	 *
	 * @return array<string> Array of compiled patterns
	 */
	private function compile_patterns(): array {
		$patterns = array();

		// Compile standard patterns.
		foreach ( OMS_Config::MALICIOUS_PATTERNS as $pattern ) {
			// Validate regex pattern without error suppression.
			$test_result = preg_match( '#' . $pattern . '#i', '' );
			if ( false !== $test_result ) {
				$patterns[] = '#' . $pattern . '#i';
			} else {
				$error = preg_last_error();
				$this->logger->log( 'Invalid pattern: ' . $pattern . ' (error code: ' . $error . ')', 'error', 'scanner' );
			}
		}

		// Compile obfuscation patterns.
		foreach ( OMS_Config::OBFUSCATION_PATTERNS as $pattern_config ) {
			$pattern = $pattern_config['pattern'];
			// Validate regex pattern without error suppression.
			$test_result = preg_match( '#' . $pattern . '#i', '' );
			if ( false !== $test_result ) {
				$patterns[] = '#' . $pattern . '#i';
			} else {
				$error = preg_last_error();
				$this->logger->log( 'Invalid obfuscation pattern: ' . $pattern . ' (error code: ' . $error . ')', 'error', 'scanner' );
			}
		}

		return $patterns;
	}

	/**
	 * Calculate optimal chunk size based on available memory
	 *
	 * @param int $filesize Size of the file being scanned.
	 * @return int Optimal chunk size in bytes.
	 */
	private function calculate_optimal_chunk_size( int $filesize ): int {
		$memory_limit = $this->get_memory_limit();

		// Use 10% of available memory or configured limit.
		$chunk_size = (int) min( $memory_limit * 0.1, OMS_Config::SCAN_CONFIG['max_chunk_size'] );

		// Ensure within bounds.
		$chunk_size = max( $chunk_size, OMS_Config::SCAN_CONFIG['min_chunk_size'] );

		// If file is smaller than chunk size, just use file size (plus buffer).
		if ( $filesize < $chunk_size ) {
			return $filesize + 1024;
		}

		return $chunk_size;
	}

	/**
	 * Get PHP memory limit in bytes
	 *
	 * @return int Memory limit in bytes.
	 */
	private function get_memory_limit(): int {
		$ini_limit = ini_get( 'memory_limit' );
		if ( '-1' === $ini_limit ) {
			return 1024 * 1024 * 1024; // 1GB default for no limit.
		}

		$val  = trim( $ini_limit );
		$last = strtolower( $val[ strlen( $val ) - 1 ] );
		$val  = (int) $val;

		switch ( $last ) {
			case 'g':
				$val *= 1024;
				// Fallthrough.
			case 'm':
				$val *= 1024;
				// Fallthrough.
			case 'k':
				$val *= 1024;
		}

		return $val;
	}

	/**
	 * Check if a file contains malware patterns
	 *
	 * @param string $path Path to file to check.
	 * @return bool True if malware detected, false otherwise.
	 * @throws OMS_Exception If file cannot be read or processed.
	 */
	public function contains_malware( string $path ): bool {
		if ( ! is_readable( $path ) ) {
			throw new OMS_Exception( 'File is not readable: ' . esc_html( $path ) );
		}

		// Quick check for obvious binary files.
		$finfo = new finfo( FILEINFO_MIME_TYPE );
		$mime  = $finfo->file( $path );

		if ( false === strpos( $mime, 'text/' ) && 'application/x-php' !== $mime && 'application/json' !== $mime ) {
			// Skip likely binary files unless explicitly PHP.
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_filesize -- Direct file size check required for malware scanning.
		$filesize = filesize( $path );
		if ( $filesize > OMS_Config::SCAN_CONFIG['max_file_size'] ) {
			$this->logger->log( 'File too large to scan: ' . $path, 'warning', 'scanner' );
			return false;
		}

		$chunk_size = $this->calculate_optimal_chunk_size( $filesize );

		return $this->scan_file_chunks( $path, $chunk_size );
	}

	/**
	 * Scan file in chunks for malware patterns
	 *
	 * @param string $path Path to file.
	 * @param int    $chunk_size Size of chunks to read.
	 * @return bool True if malware detected.
	 * @throws OMS_Exception If file cannot be read.
	 */
	private function scan_file_chunks( string $path, int $chunk_size ): bool {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Direct file access required for malware scanning.
		$handle = fopen( $path, 'rb' );
		if ( false === $handle ) {
			throw new OMS_Exception( 'Could not open file: ' . esc_html( $path ) );
		}

		$position     = 0;
		$overlap_size = OMS_Config::SCAN_CONFIG['overlap_size'];

		try {
			while ( ! feof( $handle ) ) {
				$this->apply_rate_limiting();

				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- Direct file reading required for chunked malware scanning.
				$content = fread( $handle, $chunk_size );
				if ( false === $content ) {
					break;
				}

				if ( $this->match_patterns( $content, $path, $position ) ) {
					return true;
				}

				// Handle overlap for patterns spanning chunks.
				if ( ! feof( $handle ) ) {
					$seek_pos = $chunk_size - $overlap_size;
					fseek( $handle, -$seek_pos, SEEK_CUR );
					$position += $seek_pos;
				} else {
					$position += strlen( $content );
				}
			}
		} finally {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing file handle opened with fopen.
			fclose( $handle );
		}

		return false;
	}

	/**
	 * Apply rate limiting with configurable threshold
	 */
	private function apply_rate_limiting(): void {
		if ( $this->rate_limiter->should_limit() ) {
			usleep( (int) ( OMS_Config::SCAN_CONFIG['batch_pause'] * 1000 ) );
		}
	}

	/**
	 * Match patterns against content
	 *
	 * @param string $content Content to check.
	 * @param string $path File path for logging.
	 * @param int    $position Current position in file.
	 * @return bool True if match found.
	 */
	private function match_patterns( string $content, string $path, int $position ): bool {
		foreach ( $this->compiled_patterns as $pattern ) {
			if ( preg_match( $pattern, $content, $matches, PREG_OFFSET_CAPTURE ) ) {
				$this->log_pattern_match( $matches, $path, $pattern, $position, $content );
				return true;
			}
		}
		return false;
	}

	/**
	 * Log pattern match with context
	 *
	 * @param array<array-key, mixed> $matches Pattern matches.
	 * @param string                  $path File path.
	 * @param string                  $pattern_name Name of matched pattern.
	 * @param int                     $position Current position in file.
	 * @param string                  $content File content.
	 */
	private function log_pattern_match( array $matches, string $path, string $pattern_name, int $position, string $content ): void {
		$match_pos = $matches[0][1];
		$context   = $this->extract_match_context( $content, $match_pos );

		$this->logger->log(
			sprintf(
				'Malware pattern detected in %s at position %d. Pattern: %s. Context: %s',
				$path,
				$position + $match_pos,
				$pattern_name,
				$context
			),
			'critical',
			'scanner'
		);
	}

	/**
	 * Extract context around pattern match
	 *
	 * @param string $content Full content.
	 * @param int    $match_pos Position of match.
	 * @return string Context around match.
	 */
	private function extract_match_context( string $content, int $match_pos ): string {
		$start  = max( 0, $match_pos - 50 );
		$length = min( strlen( $content ) - $start, 100 );
		return substr( $content, $start, $length );
	}

	/**
	 * Check if a file is suspicious based on its characteristics
	 *
	 * @param string      $path File path.
	 * @param SplFileInfo $file File information.
	 * @return bool True if file is suspicious.
	 */
	public function is_file_suspicious( string $path, SplFileInfo $file ): bool {
		if ( $this->check_size( $path, $file ) ) {
			return true;
		}

		if ( $this->check_permissions( $path, $file ) ) {
			return true;
		}

		if ( $this->check_modification_time( $path, $file ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if file size is suspicious
	 *
	 * @param string      $path File path.
	 * @param SplFileInfo $file File information.
	 * @return bool True if size is suspicious.
	 */
	private function check_size( string $path, SplFileInfo $file ): bool {
		// Empty files might be suspicious in some contexts, but config handles allowable ones.
		if ( $file->getSize() === 0 && ! in_array( $file->getFilename(), OMS_Config::ALLOWED_EMPTY_FILES, true ) ) {
			// Check if it's a critical file that shouldn't be empty.
			if ( in_array( $file->getFilename(), OMS_Config::CRITICAL_FILES, true ) ) {
				$this->logger->log( 'Critical file is zero bytes: ' . $path, 'critical', 'scanner' );
				return true;
			}
		}
		return false;
	}

	/**
	 * Check if file permissions are suspicious
	 *
	 * @param string      $path File path.
	 * @param SplFileInfo $file File information.
	 * @return bool True if permissions are suspicious.
	 */
	private function check_permissions( string $path, SplFileInfo $file ): bool {
		$perms = $file->getPerms();

		// World writable?
		if ( ( $perms & 0x0002 ) ) { // 0002 is world writable bit (S_IWOTH).
			$this->logger->log( 'File is world writable: ' . $path, 'warning', 'scanner' );
			return true;
		}

		return false;
	}

	/**
	 * Check if file modification time is suspicious
	 *
	 * @param string      $path File path.
	 * @param SplFileInfo $file File information.
	 * @return bool True if modification time is suspicious.
	 */
	private function check_modification_time( string $path, SplFileInfo $file ): bool {
		$mtime = $file->getMTime();
		// phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date -- Date formatting needed for time-based security analysis.
		$hour = (int) date( 'G', $mtime );

		// Check night hours.
		$night_start = OMS_Config::SUSPICIOUS_TIMES['night_hours'][0];
		$night_end   = OMS_Config::SUSPICIOUS_TIMES['night_hours'][1];

		if ( $hour >= $night_start && $hour <= $night_end ) {
			// This alone isn't critical, but worth logging if verbose.
			// $this->logger->log('File modified during night hours: ' . $path, 'info', 'scanner');.
			return false;
		}

		return false;
	}
}
