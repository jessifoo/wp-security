<?php
/**
 * Cache Service.
 *
 * Provides a simple in-memory object cache with TTL and Max Size (LRU eviction).
 *
 * @package OMS\Services
 */

declare( strict_types=1 );

namespace OMS\Services;

/**
 * Cache Service class.
 *
 * Implements a simple in-memory cache with time-to-live and LRU eviction.
 *
 * @package OMS\Services
 */
class CacheService {

	/**
	 * Cache store.
	 *
	 * @var array<string, mixed>
	 */
	private array $cache = array();

	/**
	 * Cache expiry times.
	 *
	 * @var array<string, int>
	 */
	private array $expiry = array();

	/**
	 * Cache creation times (for LRU).
	 *
	 * @var array<string, int>
	 */
	private array $created_at = array();

	/**
	 * Maximum number of items in cache.
	 *
	 * @var int
	 */
	private int $max_size;

	/**
	 * Default TTL in seconds.
	 *
	 * @var int
	 */
	private int $default_ttl;

	/**
	 * Constructor.
	 *
	 * @param int $max_size    Maximum number of items in cache.
	 * @param int $default_ttl Default TTL in seconds.
	 */
	public function __construct( int $max_size = 100, int $default_ttl = 3600 ) {
		$this->max_size    = $max_size;
		$this->default_ttl = $default_ttl;
	}

	/**
	 * Get a value from cache.
	 *
	 * @param string $key The key.
	 * @return mixed The value or null.
	 */
	public function get( string $key ): mixed {
		// Check if exists.
		if ( ! isset( $this->cache[ $key ] ) ) {
			return null;
		}

		// Check expiry.
		if ( isset( $this->expiry[ $key ] ) && time() > $this->expiry[ $key ] ) {
			$this->delete( $key );
			return null;
		}

		return $this->cache[ $key ];
	}

	/**
	 * Set a value.
	 *
	 * @param string   $key   The key.
	 * @param mixed    $value The value.
	 * @param int|null $ttl   TTL in seconds (null for default).
	 * @return void
	 */
	public function set( string $key, mixed $value, ?int $ttl = null ): void {
		// Eviction if full.
		if ( count( $this->cache ) >= $this->max_size && ! isset( $this->cache[ $key ] ) ) {
			$this->evict_oldest();
		}

		$this->cache[ $key ]      = $value;
		$this->created_at[ $key ] = time();

		// Calculate expiry.
		$actual_ttl = $ttl ?? $this->default_ttl;
		if ( $actual_ttl > 0 ) {
			$this->expiry[ $key ] = time() + $actual_ttl;
		} elseif ( $actual_ttl < 0 ) {
			// Negative TTL means already expired.
			$this->expiry[ $key ] = time() - 1;
		}
		// If ttl is 0, item does not expire based on time.
	}

	/**
	 * Delete a key.
	 *
	 * @param string $key The key.
	 * @return void
	 */
	public function delete( string $key ): void {
		unset( $this->cache[ $key ], $this->expiry[ $key ], $this->created_at[ $key ] );
	}

	/**
	 * Clear all cache entries.
	 *
	 * @return void
	 */
	public function clear(): void {
		$this->cache      = array();
		$this->expiry     = array();
		$this->created_at = array();
	}

	/**
	 * Evict oldest entry (LRU).
	 *
	 * @return void
	 */
	private function evict_oldest(): void {
		asort( $this->created_at );
		$oldest_key = array_key_first( $this->created_at );
		if ( null !== $oldest_key ) {
			$this->delete( (string) $oldest_key );
		}
	}
}
