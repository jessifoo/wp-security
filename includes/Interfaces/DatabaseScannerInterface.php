<?php
declare(strict_types=1);

namespace OMS\Interfaces;

/**
 * Interface DatabaseScannerInterface
 *
 * Contract for database scanning services.
 *
 * @package OMS\Interfaces
 */
interface DatabaseScannerInterface {
	/**
	 * Scan the database for security issues.
	 *
	 * @return array{
	 *     success: bool,
	 *     issues: array,
	 *     total?: int,
	 *     message?: string
	 * }
	 */
	public function scan(): array;
}
