<?php
/**
 * Register Integrity Services.
 *
 * @package OMS\Providers
 */

declare( strict_types=1 );

namespace OMS\Providers;

use OMS\Core\ServiceProvider;
use OMS\Core\Container;
use OMS\Services\IntegrityCheckerService;
use OMS\Services\LoggerService;

/**
 * Integrity Service Provider class.
 *
 * Registers integrity checking services.
 *
 * @package OMS\Providers
 */
class IntegrityProvider implements ServiceProvider {

	/**
	 * Register services.
	 *
	 * @param Container $container The DI Container.
	 * @return void
	 */
	public function register( Container $container ): void {
		$container->singleton(
			IntegrityCheckerService::class,
			function ( Container $c ) {
				return new IntegrityCheckerService( $c->get( LoggerService::class ) );
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
		// No boot actions required for Integrity Checker (it's called on demand).
	}
}
