<?php
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
	 * @return array Array with 'safe' boolean and 'reason' string.
	 */
	public function check_file_content( $file_path ) {
		if ( ! is_readable( $file_path ) ) {
			return array(
				'safe'   => false,
				'reason' => 'File is not readable',
			);
		}

		$content = file_get_contents( $file_path );
		if ( false === $content ) {
			return array(
				'safe'   => false,
				'reason' => 'Could not read file content',
			);
		}

		// Check for malicious patterns from OMS_Config.
		foreach ( OMS_Config::MALICIOUS_PATTERNS as $pattern ) {
			// Patterns are regex strings, wrap with delimiters and flags.
			// Use '#' delimiter to avoid conflicts with '/' in patterns.
			if ( preg_match( '#' . $pattern . '#i', $content ) ) {
				return array(
					'safe'   => false,
					'reason' => 'File contains malicious code pattern',
				);
			}
		}

		// Check for obfuscation patterns from OMS_Config.
		foreach ( OMS_Config::OBFUSCATION_PATTERNS as $pattern_data ) {
			if ( preg_match( '/' . $pattern_data['pattern'] . '/i', $content ) ) {
				return array(
					'safe'   => false,
					'reason' => $pattern_data['description'],
				);
			}
		}

		// Check for high ratio of special characters (possible obfuscation).
		$special_chars = preg_match_all( '/[^a-zA-Z0-9\s]/', $content );
		$total_chars   = strlen( $content );
		if ( $total_chars > 0 && ( $special_chars / $total_chars ) > 0.3 ) {
			return array(
				'safe'   => false,
				'reason' => 'File appears to be obfuscated (high special character ratio)',
			);
		}

		return array(
			'safe'   => true,
			'reason' => 'File content appears safe',
		);
	}

	/**
	 * Check if file exists.
	 *
	 * @param string $path Path to check.
	 * @return bool True if file exists.
	 */
	public function file_exists( $path ) {
		return file_exists( $path );
	}

	/**
	 * Get file permissions.
	 *
	 * @param string $path Path to check.
	 * @return int|false File permissions or false on failure.
	 */
	public function fileperms( $path ) {
		return fileperms( $path );
	}
}
