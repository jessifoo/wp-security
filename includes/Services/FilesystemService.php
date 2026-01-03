<?php
declare(strict_types=1);

namespace OMS\Services;

/**
 * Filesystem Service.
 *
 * Wrapper around native filesystem functions for testability.
 *
 * @package OMS\Services
 */
class FilesystemService {
	/**
	 * Check if file exists.
	 *
	 * @param string $path Path to check.
	 * @return bool True if exists.
	 */
	public function exists( string $path ): bool {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_exists
		return file_exists( $path );
	}

	/**
	 * Check if path is readable.
	 *
	 * @param string $path Path to check.
	 * @return bool True if readable.
	 */
	public function is_readable( string $path ): bool {
		return is_readable( $path );
	}

	/**
	 * Get file contents.
	 *
	 * @param string $path Path to read.
	 * @return string|false Content or false on failure.
	 */
	public function get_contents( string $path ): string|false {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		return file_get_contents( $path );
	}

    /**
     * Get file size.
     *
     * @param string $path Path to check.
     * @return int|false Size or false on failure.
     */
    public function size( string $path ): int|false {
        return filesize( $path );
    }

    /**
     * Open file.
     *
     * @param string $path Path to file.
     * @param string $mode Mode.
     * @return resource|false Handle or false.
     */
    public function fopen( string $path, string $mode ) {
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
        return fopen( $path, $mode );
    }

    /**
     * Read file.
     *
     * @param resource $stream Stream.
     * @param int $length Length.
     * @return string|false Content.
     */
    public function fread( $stream, int $length ) {
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
        return fread( $stream, $length );
    }

    /**
     * Close file.
     *
     * @param resource $stream Stream.
     * @return bool Result.
     */
    public function fclose( $stream ): bool {
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        return fclose( $stream );
    }
}
