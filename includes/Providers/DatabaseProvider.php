<?php
/**
 * Database Provider.
 *
 * Registers database scanning services.
 *
 * @package OMS\Providers
 */

declare( strict_types=1 );

namespace OMS\Providers;

use OMS\Core\ServiceProvider;
use OMS\Core\Container;
use OMS\Services\DatabaseScannerService;
use OMS\Services\LoggerService;
use OMS\Services\CacheService;
use wpdb;

/**
 * Database Service Provider class.
 *
 * Registers database scanning services.
 *
 * @package OMS\Providers
 */
class DatabaseProvider implements ServiceProvider {

	/**
	 * Register services.
	 *
	 * @param Container $container The DI Container.
	 * @return void
	 */
	public function register( Container $container ): void {
		$container->singleton(
			DatabaseScannerService::class,
			function ( Container $c ) {
				global $wpdb;

				// Safety check for $wpdb availability.
				if ( ! $wpdb instanceof wpdb ) {
					// In strict mode we'd throw, but for plugin compatibility we proceed.
					$logger = $c->get( LoggerService::class );
					$logger->warning( 'WordPress database not fully available during provider registration.' );
				}

				return new DatabaseScannerService(
					$wpdb,
					$c->get( LoggerService::class ),
					$c->get( CacheService::class )
				);
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
		// Database scanner is called on-demand, no boot actions required.
	}
}
