<?php
/**
 * Register Core Services.
 *
 * @package OMS\Providers
 */

declare(strict_types=1);

namespace OMS\Providers;

use OMS\Core\ServiceProvider;
use OMS\Core\Container;
use OMS\Services\LoggerService;
use OMS\Services\CacheService;
class CoreProvider implements ServiceProvider {

	/**
	 * Register services.
	 *
	 * @param Container $container The DI Container.
	 */
	public function register( Container $container ): void {
		$container->singleton(
			LoggerService::class,
			function ( Container $c ) {
				// Determine log path.
				// Ideally this comes from a ConfigService, but for now we look for the constant
				// or default to standard WP content location.
				$log_path = defined( 'OMS_LOG_DIR' ) ? OMS_LOG_DIR : ( defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR . '/oms-logs' : '/tmp' );

				return new LoggerService( $log_path );
			}
		);

		$container->singleton(
			CacheService::class,
			function ( Container $c ) {
				// Configuration could be pulled from constants or a ConfigService.
				// Defaulting to "Principal Engineer" defaults (not global constants) if missing.
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
	 */
	public function boot( Container $container ): void {
		// Nothing to boot for logger yet.
		// Future: might attach a shutdown handler to flush logs if we buffered them.
	}
}
