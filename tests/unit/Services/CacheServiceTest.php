<?php
declare(strict_types=1);

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use OMS\Services\CacheService;

class CacheServiceTest extends TestCase {

	public function test_can_set_and_get_value(): void {
		$cache = new CacheService();
		$cache->set( 'foo', 'bar' );
		$this->assertEquals( 'bar', $cache->get( 'foo' ) );
	}

	public function test_returns_null_for_missing_keys(): void {
		$cache = new CacheService();
		$this->assertNull( $cache->get( 'missing' ) );
	}

	public function test_can_clear_cache(): void {
		$cache = new CacheService();
		$cache->set( 'foo', 'bar' );
		$cache->clear();
		$this->assertNull( $cache->get( 'foo' ) );
	}

	public function test_evicts_old_entries_when_full(): void {
		// Assume default max size is 100, but we can perhaps configure it?
		// Let's assume we can pass constructor args or it uses constants.
		// For this test, I'll rely on the default behavior documented in the proposed implementation
		// or hardcode a small limit in the implementation for testing if I could.

		// Actually, without internal access, testing 'max_size' is hard unless we fill it up.
		// I will trust the implementation follows the spec, or make size configurable.

		$cache = new CacheService( max_size: 2 );

		$cache->set( 'a', 1 );
		sleep( 1 ); // minimal delay to ensure timestamps differ
		$cache->set( 'b', 2 );
		sleep( 1 );
		$cache->set( 'c', 3 ); // Should evict 'a'

		$this->assertNull( $cache->get( 'a' ), 'Oldest key should be evicted' );
		$this->assertEquals( 2, $cache->get( 'b' ) );
		$this->assertEquals( 3, $cache->get( 'c' ) );
	}

	public function test_ttl_expiry(): void {
		// This is hard to test without mocking time().
		// I will skip strict TTL testing for this unit test unless I introduce a Clock.
		// Or I can add a `set_current_time` helper to the service just for testing.

		$cache = new CacheService();
		$cache->set( 'short_lived', 'value', -1 ); // Expired immediately
		$this->assertNull( $cache->get( 'short_lived' ) );
	}
}
