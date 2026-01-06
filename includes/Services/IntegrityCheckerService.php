<?php

declare(strict_types=1);

namespace OMS\Services;

use OMS\Interfaces\IntegrityCheckerInterface;
use OMS\Services\LoggerService;

/**
 * Service IntegrityCheckerService
 *
 * Verifies the integrity of WordPress core files against official checksums.
 *
 * @package OMS\Services
 */
class IntegrityCheckerService implements IntegrityCheckerInterface
{
    /**
     * WordPress API URL for checksums.
     */
    public const API_URL = 'https://api.wordpress.org/core/checksums/1.0/';

    /**
     * Constructor.
     *
     * @param LoggerService $logger Logger instance.
     */
    public function __construct(private readonly LoggerService $logger) {}

    /**
     * Verify core files against official checksums.
     *
     * @return array{safe: string[], modified: string[], missing: string[], error?: string} Verification results.
     */
    public function verify_core_files(): array
    {
        $checksums = $this->fetch_checksums();
        if (false === $checksums) {
            $this->logger->error('Failed to fetch WordPress core checksums.');
            return [
                'safe'     => [],
                'modified' => [],
                'missing'  => [],
                'error'    => 'Failed to fetch checksums',
            ];
        }

        $results = [
            'safe'     => [],
            'modified' => [],
            'missing'  => [],
        ];

        foreach ($checksums as $file => $checksum) {
            $full_path = ABSPATH . $file;

            if (! file_exists($full_path)) {
                $results['missing'][] = $file;
                continue;
            }

            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_md5_file
            $local_checksum = md5_file($full_path);
            if ($local_checksum === $checksum) {
                $results['safe'][] = $file;
            } else {
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
    private function fetch_checksums(): array|false
    {
        global $wp_version;

        $url = add_query_arg(
            [
                'version' => $wp_version,
                'locale'  => get_locale(),
            ],
            self::API_URL
        );

        // phpcs:ignore WordPress.VIP.RestrictedFunctions.wp_remote_get_wp_remote_get
        $response = wp_remote_get($url, ['timeout' => 10]);

        if (is_wp_error($response)) {
            $this->logger->error('Error fetching checksums: ' . $response->get_error_message());
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        if (200 !== $code) {
            $this->logger->error('Error fetching checksums: HTTP ' . $code);
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (! is_array($data) || ! isset($data['checksums']) || ! is_array($data['checksums'])) {
            $this->logger->error('Invalid checksum response format.');
            return false;
        }

        return $data['checksums'];
    }

    /**
     * Check if a file is a verified core file.
     *
     * @param string $path       Absolute path to file.
     * @param array  $safe_files Array of safe relative paths.
     * @return bool True if file is a verified core file.
     */
    public function is_verified_core_file(string $path, array $safe_files): bool
    {
        $relative_path = str_replace(ABSPATH, '', $path);
        // Normalize slashes.
        $relative_path = str_replace('\\', '/', $relative_path);

        return in_array($relative_path, $safe_files, true);
    }
}
