<?php
/**
 * Security Provider.
 *
 * Registers security scanning services.
 *
 * @package OMS\Providers
 */

declare( strict_types=1 );

namespace OMS\Providers;

use OMS\Core\ServiceProvider;
use OMS\Core\Container;
use OMS\Services\FilesystemService;
use OMS\Services\FileScannerService;
use OMS\Services\UploadMonitorService;
use OMS\Services\LoggerService;

/**
 * Security Service Provider class.
 *
 * Registers and boots file scanning and upload monitoring services.
 *
 * @package OMS\Providers
 */
class SecurityProvider implements ServiceProvider {

	/**
	 * Register services.
	 *
	 * @param Container $container The DI Container.
	 * @return void
	 */
	public function register( Container $container ): void {
		// Register low-level infrastructure.
		$container->singleton(
			FilesystemService::class,
			function ( Container $c ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
				return new FilesystemService();
			}
		);

		// Register file scanner service.
		$container->singleton(
			FileScannerService::class,
			function ( Container $c ) {
				return new FileScannerService(
					$c->get( FilesystemService::class ),
					$c->get( LoggerService::class )
				);
			}
		);

		// Register upload monitor service.
		$container->singleton(
			UploadMonitorService::class,
			function ( Container $c ) {
				return new UploadMonitorService(
					$c->get( FileScannerService::class ),
					$c->get( LoggerService::class )
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
	public function boot( Container $container ): void {
		// Hook into upload validation.
		$monitor = $container->get( UploadMonitorService::class );

		add_action( 'added_post_meta', array( $monitor, 'check_uploaded_file' ), 10, 4 );
	}
}
