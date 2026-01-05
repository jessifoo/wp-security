<?php
declare(strict_types=1);

namespace OMS\Services;

use OMS\Interfaces\FileScannerInterface;

/**
 * File Scanner Service.
 *
 * Scans files for malicious patterns.
 *
 * @package OMS\Services
 */
class FileScannerService implements FileScannerInterface {
	/**
	 * Malicious patterns to scan for.
	 *
	 * @var array<string>
	 */
	private const MALICIOUS_PATTERNS = array(
		'eval\(base64_decode',
		'shell_exec',
		'passthru',
		'system\(',
		'phpinfo',
		'base64_decode\(',
		'edoc_46esab', // Reversed base64_decode.
		'gniutcer_etal_fni', // Reversed inf_late_rcuiting.
	);

	/**
	 * Constructor.
	 *
	 * @param FilesystemService $filesystem Filesystem service.
	 * @param LoggerService     $logger     Logger service.
	 */
	public function __construct(
		private FilesystemService $filesystem,
		private LoggerService $logger
	) {}

	/**
	 * Scan a file for malware.
	 *
	 * @param string $file_path Absolute path to the file.
	 * @return array{safe: bool, issues: array, reason?: string}
	 */
	public function scan_file( string $file_path ): array {
		if ( ! $this->filesystem->is_readable( $file_path ) ) {
			$this->logger->warning( "File is not readable: $file_path" );
			return array(
				'safe'   => false,
				'issues' => array( 'unreadable' ),
				'reason' => 'File is not readable',
			);
		}

		$content = $this->filesystem->get_contents( $file_path );
		if ( false === $content ) {
			return array(
				'safe'   => false,
				'issues' => array( 'read_error' ),
				'reason' => 'Could not read file content',
			);
		}

		foreach ( self::MALICIOUS_PATTERNS as $pattern ) {
			if ( preg_match( '#' . $pattern . '#i', $content ) ) {
				$this->logger->warning( "Malicious pattern found in $file_path: $pattern" );
				return array(
					'safe'   => false,
					'issues' => array( 'malware_detected' ),
					'reason' => "File contains malicious code pattern: $pattern",
				);
			}
		}

		return array(
			'safe'   => true,
			'issues' => array(),
			'reason' => 'File content appears safe',
		);
	}
}
