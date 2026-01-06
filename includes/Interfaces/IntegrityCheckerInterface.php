<?php

declare(strict_types=1);

namespace OMS\Interfaces;

/**
 * Interface IntegrityCheckerInterface
 *
 * @package OMS\Interfaces
 */
interface IntegrityCheckerInterface
{
    /**
     * Verify core files against official checksums.
     *
     * @return array{safe: string[], modified: string[], missing: string[], error?: string} Verification results.
     */
    public function verify_core_files(): array;

    /**
     * Check if a file is a verified core file.
     *
     * @param string $path       Absolute path to file.
     * @param array  $safe_files Array of safe relative paths.
     * @return bool True if file is a verified core file.
     */
    public function is_verified_core_file(string $path, array $safe_files): bool;
}
