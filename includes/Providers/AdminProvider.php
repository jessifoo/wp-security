<?php
/**
 * Admin Provider.
 *
 * Registers admin services.
 *
 * @package OMS\Providers
 */

declare( strict_types=1 );

namespace OMS\Providers;

use OMS\Core\ServiceProvider;
use OMS\Core\Container;
use OMS\Admin\AdminService;
use OMS\Services\DatabaseScannerService;
use OMS\Services\FileScannerService;
use OMS\Services\LoggerService;

/**
 * Admin Service Provider class.
 *
 * Registers and boots admin-related services.
 *
 * @package OMS\Providers
 */
class AdminProvider implements ServiceProvider {

	/**
	 * Register services.
	 *
	 * @param Container $container The DI Container.
	 * @return void
	 */
	public function register( Container $container ): void {
		$container->singleton(
			AdminService::class,
			function ( Container $c ) {
				return new AdminService(
					$c->get( DatabaseScannerService::class ),
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
		if ( ! is_admin() ) {
			return;
		}

		$admin = $container->get( AdminService::class );

		// Add admin menu.
		add_action( 'admin_menu', array( $admin, 'add_admin_menu' ) );

		// Handle manual scan (admin-post.php).
		add_action(
			'admin_post_oms_manual_scan',
			function () use ( $admin ) {
				check_admin_referer( 'oms_manual_scan' );

				if ( $admin->execute_manual_scan() ) {
					wp_safe_redirect( admin_url( 'options-general.php?page=obfuscated-malware-scanner&scan=complete' ) );
					exit;
				}
			}
		);
	}
}
