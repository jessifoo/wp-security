<?php
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
	private $cache = array();

	/**
	 * Cache timestamps array.
	 *
	 * @var array
	 */
	private $cache_times = array();

	/**
	 * Get cached value.
	 *
	 * @param string $key Cache key.
	 * @return mixed|null Cached value or null if not found.
	 */
	public function get( $key ) {
		if ( isset( $this->cache[ $key ] ) &&
			( time() - $this->cache_times[ $key ] ) < OMS_Config::CACHE_EXPIRY ) {
			return $this->cache[ $key ];
		}
		return null;
	}

	/**
	 * Set cache value.
	 *
	 * @param string $key Cache key.
	 * @param mixed  $value Value to cache.
	 * @return void
	 */
	public function set( $key, $value ) {
		// Maintain cache size limit.
		if ( count( $this->cache ) >= OMS_Config::MAX_CACHE_SIZE ) {
			// Remove oldest cache entry.
			asort( $this->cache_times );
			$oldest_key = key( $this->cache_times );
			unset( $this->cache[ $oldest_key ] );
			unset( $this->cache_times[ $oldest_key ] );
		}

		$this->cache[ $key ]       = $value;
		$this->cache_times[ $key ] = time();
	}

	/**
	 * Clear cache.
	 *
	 * @return void
	 */
	public function clear() {
		$this->cache       = array();
		$this->cache_times = array();
	}
}
