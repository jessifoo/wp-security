<?php
declare(strict_types=1);

/**
 * Core Integrity Checker
 *
 * @package ObfuscatedMalwareScanner
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access is not allowed.' );
}

/**
 * Class OMS_Core_Integrity_Checker
 */
class OMS_Core_Integrity_Checker {

	/**
	 * WordPress API URL for checksums.
	 */
	public const string API_URL = 'https://api.wordpress.org/core/checksums/1.0/';

	/**
	 * Constructor.
	 *
	 * @param OMS_Logger $logger Logger instance.
	 */
	public function __construct( private readonly OMS_Logger $logger ) {}

	/**
	 * Verify core files against official checksums.
	 *
	 * @return array{safe: string[], modified: string[], missing: string[], error?: string} Verfication results.
	 */
	public function verify_core_files(): array {
		$checksums = $this->fetch_checksums();
		if ( false === $checksums ) {
			$this->logger->error( 'Failed to fetch WordPress core checksums.' );
			return array(
				'safe'     => array(),
				'modified' => array(),
				'missing'  => array(),
				'error'    => 'Failed to fetch checksums',
			);
		}

		$results = array(
			'safe'     => array(),
			'modified' => array(),
			'missing'  => array(),
		);

		foreach ( $checksums as $file => $checksum ) {
			$full_path = ABSPATH . $file;

			if ( ! file_exists( $full_path ) ) {
				$results['missing'][] = $file;
				continue;
			}

			$local_checksum = md5_file( $full_path );
			if ( $local_checksum === $checksum ) {
				$results['safe'][] = $file;
			} else {
				// Check if it's a known safe modification (e.g., wp-config-sample.php might be modified by some hosts).
				// For now, we treat any mismatch as modified.
				$results['modified'][] = $file;
			}
		}

		return $results;
	}

	/**
	 * Fetch checksums from WordPress API.
	 *
	 * @return array|false Array of checksums or false on failure.
	 */
	private function fetch_checksums(): array|false {
		global $wp_version;

		$url = add_query_arg(
			array(
				'version' => $wp_version,
				'locale'  => get_locale(),
			),
			self::API_URL
		);

		$response = wp_remote_get( $url, array( 'timeout' => 10 ) );

		if ( is_wp_error( $response ) ) {
			// @phpstan-ignore-next-line
			$this->logger->error( 'Error fetching checksums: ' . $response->get_error_message() );
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			$this->logger->error( 'Error fetching checksums: HTTP ' . $code );
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) || ! isset( $data['checksums'] ) || ! is_array( $data['checksums'] ) ) {
			$this->logger->error( 'Invalid checksum response format.' );
			return false;
		}

		return $data['checksums'];
	}

	/**
	 * Check if a file is a verified core file.
	 *
	 * @param string $path Absolute path to file.
	 * @param array  $safe_files Array of safe relative paths from verify_core_files().
	 * @return bool True if file is a verified core file.
	 */
	public function is_verified_core_file( string $path, array $safe_files ): bool {
		$relative_path = str_replace( ABSPATH, '', $path );
		// Normalize slashes.
		$relative_path = str_replace( '\\', '/', $relative_path );

		return in_array( $relative_path, $safe_files, true );
	}
}
