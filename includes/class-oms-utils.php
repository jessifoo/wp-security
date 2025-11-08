<?php
/**
 * Utility functions for the Obfuscated Malware Scanner
 *
 * @package ObfuscatedMalwareScanner
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access is not allowed.' );
}

/**
 * Utility functions for the Obfuscated Malware Scanner
 */
class OMS_Utils {
	/**
	 * Sanitize a URL to ensure it's safe
	 *
	 * @param string $url The URL to sanitize.
	 * @return string The sanitized URL.
	 */
	public static function sanitize_url( $url ) {
		return esc_url_raw( $url );
	}

	/**
	 * Sanitize a file path to ensure it's safe
	 *
	 * @param string $path The file path to sanitize.
	 * @return string The sanitized path.
	 * @throws InvalidArgumentException If path is invalid or contains path traversal.
	 */
	public static function sanitize_path( $path ) {
		// Normalize path.
		$path = wp_normalize_path( $path );

		// Check for path traversal and safety.
		if ( ! self::is_path_safe( $path ) ) {
			throw new InvalidArgumentException( 'Path contains path traversal or is invalid' );
		}

		// Check file permissions if file exists.
		if ( file_exists( $path ) ) {
			$perms = fileperms( $path );
			if ( ( $perms & 0x0100 ) || ( $perms & 0x0010 ) || ( $perms & 0x0001 ) ) { // Check for executable bits.
				throw new InvalidArgumentException( 'File has unsafe permissions' );
			}
		}

		return $path;
	}

	/**
	 * Get the relative path from WordPress root
	 *
	 * @param string $path Absolute path to get relative path for.
	 * @return string Relative path from WordPress root.
	 */
	public static function get_relative_path( $path ) {
		$path    = wp_normalize_path( $path );
		$wp_root = wp_normalize_path( ABSPATH );

		// If path starts with WordPress root, remove it.
		if ( 0 === strpos( $path, $wp_root ) ) {
			return substr( $path, strlen( $wp_root ) );
		}

		return $path;
	}

	/**
	 * Check if a path is safe
	 *
	 * @param string $path The path to check.
	 * @return bool True if path is safe, false otherwise.
	 */
	public static function is_path_safe( $path ) {
		// Check for null bytes.
		if ( false !== strpos( $path, "\0" ) ) {
			return false;
		}

		// Check for path traversal.
		$normalized = wp_normalize_path( $path );
		$parts      = explode( '/', $normalized );
		$stack      = array();

		foreach ( $parts as $part ) {
			if ( '.' === $part || '' === $part ) {
				continue;
			}

			if ( '..' === $part ) {
				if ( empty( $stack ) ) {
					return false;
				}
				array_pop( $stack );
			} else {
				$stack[] = $part;
			}
		}

		// Check for stream wrappers.
		if ( preg_match( '#^[a-z0-9]+://#i', $path ) ) {
			return false;
		}

		// Check for special characters that could be used for command injection.
		if ( preg_match( '/[\x00-\x1F\x7F<>"|&;$`]/', $path ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Check file content for malicious patterns
	 *
	 * @param string $file_path Path to the file to check.
	 * @return array Array with 'safe' boolean and 'reason' string.
	 */
	public static function check_file_content( $file_path ) {
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
			if ( preg_match( '/' . $pattern . '/i', $content ) ) {
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
}
