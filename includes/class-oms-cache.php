<?php
declare(strict_types=1);

/**
 * Cache handler for malware scanner
 *
 * @package ObfuscatedMalwareScanner
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access is not allowed.' );
}

/**
 * Cache handler class for malware scanner.
 */
class OMS_Cache {
	/**
	 * Cache storage array.
	 *
	 * @var array
	 */
	private array $cache = array();

	/**
	 * Cache timestamps array.
	 *
	 * @var array
	 */
	private array $cache_times = array();

	/**
	 * Get cached value.
	 *
	 * @param string $key Cache key.
	 * @return mixed Cached value or null if not found.
	 */
	public function get( string $key ): mixed {
		// Use cast to int for timestamp comparison.
		if ( isset( $this->cache[ $key ] ) &&
			( time() - (int) ( $this->cache_times[ $key ] ?? 0 ) ) < (int) OMS_Config::CACHE_CONFIG['ttl'] ) {
			return $this->cache[ $key ];
		}
		return null;
	}

	/**
	 * Set cache value.
	 *
	 * @param string   $key Cache key.
	 * @param mixed    $value Value to cache.
	 * @param int|null $ttl Time to live in seconds (optional).
	 * @return void
	 */
	public function set( string $key, mixed $value, ?int $ttl = null ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		// Maintain cache size limit.
		if ( count( $this->cache ) >= (int) OMS_Config::CACHE_CONFIG['max_size'] ) {
			// Remove oldest cache entry.
			asort( $this->cache_times );
			$oldest_key = array_key_first( $this->cache_times );
			if ( null !== $oldest_key ) {
				unset( $this->cache[ $oldest_key ] );
				unset( $this->cache_times[ $oldest_key ] );
			}
		}

		$this->cache[ $key ]       = $value;
		$this->cache_times[ $key ] = time();
	}

	/**
	 * Clear cache.
	 *
	 * @return void
	 */
	public function clear(): void {
		$this->cache       = array();
		$this->cache_times = array();
	}
}
