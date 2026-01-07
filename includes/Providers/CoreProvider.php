<?php
/**
 * Register Core Services.
 *
 * @package OMS\Providers
 */

declare( strict_types=1 );

namespace OMS\Providers;

use OMS\Core\ServiceProvider;
use OMS\Core\Container;
use OMS\Services\LoggerService;
use OMS\Services\CacheService;

/**
 * Core Service Provider class.
 *
 * Registers core services like logging and caching.
 *
 * @package OMS\Providers
 */
class CoreProvider implements ServiceProvider {

	/**
	 * Register services.
	 *
	 * @param Container $container The DI Container.
	 * @return void
	 */
	public function register( Container $container ): void {
		$container->singleton(
			LoggerService::class,
			function ( Container $c ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
				// Determine log path from constants or default to WP content location.
				$log_path = defined( 'OMS_LOG_DIR' ) ? OMS_LOG_DIR : ( defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR . '/oms-logs' : '/tmp' );

				return new LoggerService( $log_path );
			}
		);

		$container->singleton(
			CacheService::class,
			function ( Container $c ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
				// Configuration could be pulled from constants or a ConfigService.
				$max_size = defined( 'OMS_CACHE_MAX_SIZE' ) ? (int) OMS_CACHE_MAX_SIZE : 500;
				$ttl      = defined( 'OMS_CACHE_TTL' ) ? (int) OMS_CACHE_TTL : 3600;

				return new CacheService( $max_size, $ttl );
			}
		);
	}

	/**
	 * Boot services.
	 *
	 * @param Container $container The DI Container.
	 * @return void
	 */
	public function boot( Container $container ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		// Nothing to boot for core services yet.
	}
}
