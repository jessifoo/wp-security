<?php
/**
 * Integrity Checker Interface.
 *
 * Defines the contract for integrity checking services.
 *
 * @package OMS\Interfaces
 */

declare( strict_types=1 );

namespace OMS\Interfaces;

/**
 * Interface IntegrityCheckerInterface
 *
 * Defines methods for verifying WordPress core file integrity.
 *
 * @package OMS\Interfaces
 */
interface IntegrityCheckerInterface {

	/**
	 * Verify core files against official checksums.
	 *
	 * @return array{safe: string[], modified: string[], missing: string[], error?: string} Verification results.
	 */
	public function verify_core_files(): array;

	/**
	 * Check if a file is a verified core file.
	 *
	 * @param string   $path       Absolute path to file.
	 * @param string[] $safe_files Array of safe relative paths.
	 * @return bool True if file is a verified core file.
	 */
	public function is_verified_core_file( string $path, array $safe_files ): bool;
}
