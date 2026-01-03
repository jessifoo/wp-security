<?php
declare(strict_types=1);

namespace OMS\Core;

/**
 * Interface ServiceProvider
 *
 * All modules (Database, Logging, Security) must implement this interface.
 * It strictly separates "Configuration" (register) from "Execution" (boot).
 *
 * @package OMS\Core
 */
interface ServiceProvider {
	/**
	 * Register services into the container.
	 *
	 * Use this method to bind classes, interfaces, and settings.
	 * DO NOT use this method to add hooks or execute logic.
	 *
	 * @param Container $container The DI container.
	 * @return void
	 */
	public function register( Container $container ): void;

	/**
	 * Boot the service.
	 *
	 * Use this method to add WordPress hooks (add_action, add_filter)
	 * or generic startup logic using the fully resolved services.
	 *
	 * @param Container $container The DI container.
	 * @return void
	 */
	public function boot( Container $container ): void;
}
