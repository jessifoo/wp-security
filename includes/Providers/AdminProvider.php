<?php
/**
 * Admin Provider.
 *
 * Registers admin services.
 *
 * @package OMS\Providers
 */

declare(strict_types=1);

namespace OMS\Providers;

use OMS\Core\ServiceProvider;
use OMS\Core\Container;
use OMS\Admin\AdminService;
use OMS\Services\DatabaseScannerService;
use OMS\Services\FileScannerService;
use OMS\Services\LoggerService;

class AdminProvider implements ServiceProvider {

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

	public function boot( Container $container ): void {
		if ( ! is_admin() ) {
			return;
		}

		$admin = $container->get( AdminService::class );

		// Add Menu
		add_action( 'admin_menu', array( $admin, 'add_admin_menu' ) );

		// Handle Manual Scan (admin-post.php)
		add_action(
			'admin_post_oms_manual_scan',
			function () use ( $admin ) {
				// Security check should be inside the service method or here
				// Verify nonce here for early exit?
				// Service handles logic.
				// check_admin_referer('oms_manual_scan');
				// We'll let the service handle the execution logic, but nonce checks usually happen early.
				// For this refactor, let's assume the service handles the full flow or we wrap it.

				check_admin_referer( 'oms_manual_scan' );

				if ( $admin->execute_manual_scan() ) {
					wp_redirect( admin_url( 'options-general.php?page=obfuscated-malware-scanner&scan=complete' ) );
					exit;
				}
			}
		);
	}
}
