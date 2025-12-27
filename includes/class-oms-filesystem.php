<?php
declare(strict_types=1);

/**
 * Filesystem wrapper for the Obfuscated Malware Scanner
 *
 * @package ObfuscatedMalwareScanner
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access is not allowed.' );
}

/**
 * Filesystem wrapper class.
 */
class OMS_Filesystem {
	/**
	 * Check file content for malicious patterns
	 *
	 * @param string $file_path Path to the file to check.
	 * @return array{safe: bool, reason: string} Array with 'safe' boolean and 'reason' string.
	 */
	public function check_file_content( string $file_path ): array {
		if ( ! is_readable( $file_path ) ) {
			return [
				'safe'   => false,
				'reason' => 'File is not readable',
			];
		}

		$content = file_get_contents( $file_path );
		if ( false === $content ) {
			return [
				'safe'   => false,
				'reason' => 'Could not read file content',
			];
		}

		// Check for malicious patterns from OMS_Config.
		foreach ( OMS_Config::MALICIOUS_PATTERNS as $pattern ) {
			if ( preg_match( '#' . $pattern . '#i', $content ) ) {
				return [
					'safe'   => false,
					'reason' => 'File contains malicious code pattern',
				];
			}
		}

		// Check for obfuscation patterns from OMS_Config.
		foreach ( OMS_Config::OBFUSCATION_PATTERNS as $pattern_data ) {
			if ( preg_match( '#' . $pattern_data['pattern'] . '#i', $content ) ) {
				return [
					'safe'   => false,
					'reason' => $pattern_data['description'],
				];
			}
		}

		// Check for high ratio of special characters (possible obfuscation).
		$special_chars = preg_match_all( '/[^a-zA-Z0-9\s]/', $content );
		$total_chars   = strlen( $content );
		if ( $total_chars > 0 && ( $special_chars / $total_chars ) > 0.3 ) {
			return [
				'safe'   => false,
				'reason' => 'File appears to be obfuscated (high special character ratio)',
			];
		}

		return [
			'safe'   => true,
			'reason' => 'File content appears safe',
		];
	}

	/**
	 * Check if file exists.
	 *
	 * @param string $path Path to check.
	 * @return bool True if file exists.
	 */
	public function file_exists( string $path ): bool {
		return file_exists( $path );
	}

	/**
	 * Get file permissions.
	 *
	 * @param string $path Path to check.
	 * @return int|false File permissions or false on failure.
	 */
	public function fileperms( string $path ): int|false {
		return fileperms( $path );
	}
}
