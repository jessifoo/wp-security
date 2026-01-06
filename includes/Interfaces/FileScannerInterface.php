<?php
/**
 * Interface FileScannerInterface
 *
 * Contract for file scanning services.
 *
 * @package OMS\Interfaces
 */

declare(strict_types=1);

namespace OMS\Interfaces;

interface FileScannerInterface {
	/**
	 * Scan a file for malware.
	 *
	 * @param string $file_path Absolute path to the file.
	 * @return array{
	 *     safe: bool,
	 *     issues: array,
	 *     reason?: string
	 * }
	 */
	public function scan_file( string $file_path ): array;
}
