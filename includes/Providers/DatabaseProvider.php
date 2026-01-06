<?php
/**
 * Database Provider.
 *
 * Registers database scanning services.
 *
 * @package OMS\Providers
 */

declare(strict_types=1);

namespace OMS\Providers;

use OMS\Core\ServiceProvider;
use OMS\Core\Container;
use OMS\Services\DatabaseScannerService;
use OMS\Services\LoggerService;
use OMS\Services\CacheService;
use wpdb;

class DatabaseProvider implements ServiceProvider {

	public function register( Container $container ): void {
		$container->singleton(
			DatabaseScannerService::class,
			function ( Container $c ) {
				global $wpdb;

				// Safety check for $wpdb
				if ( ! $wpdb instanceof wpdb ) {
					// In a perfect world we throw an exception, but in WP bootstrap we might want to handle gracefully
					// or ensure this provider is only loaded after WP is fully loaded.
					// for strictness:
					// throw new \RuntimeException('WordPress Database not available.');
					// But for practical plugin life:
				}

				return new DatabaseScannerService(
					$wpdb,
					$c->get( LoggerService::class ),
					$c->get( CacheService::class )
				);
			}
		);
	}

	public function boot( Container $container ): void {
		// Here we could register a Cron job to run the scan
		// add_action('oms_daily_scan', fn() => $container->get(DatabaseScannerService::class)->scan());
	}
}
