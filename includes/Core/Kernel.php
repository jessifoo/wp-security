<?php
/**
 * The Kernel.
 *
 * The Kernel is responsible for bootstrapping the application.
 * It is the only class that knows about the "Big Picture" (the list of providers).
 *
 * @package OMS\Core
 */

declare(strict_types=1);

namespace OMS\Core;

class Kernel {
	/**
	 * The DI Container.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * List of Service Providers to load.
	 *
	 * @var string[]
	 */
	private array $providers = array();

	/**
	 * Constructor.
	 *
	 * @param array $providers List of fully qualified Provider class names.
	 */
	public function __construct( array $providers ) {
		$this->container = new Container();
		$this->providers = $providers;
	}

	/**
	 * Run the application.
	 *
	 * 1. Register all services (Wiring).
	 * 2. Boot all services (Hooks).
	 *
	 * @return void
	 */
	public function run(): void {
		// Phase 1: Registration
		// We instantiate all providers and let them tell the container what they provide.
		// We keep the instances so we don't have to re-instantiate them for boot.
		$provider_instances = array();

		foreach ( $this->providers as $provider_class ) {
			/** @var ServiceProvider $provider */
			$provider = new $provider_class();
			$provider->register( $this->container );
			$provider_instances[] = $provider;
		}

		// Phase 2: Boot
		// Now that all services are known to the container, we can safe start them.
		foreach ( $provider_instances as $provider ) {
			$provider->boot( $this->container );
		}
	}

	/**
	 * Get the container (mostly for testing).
	 *
	 * @return Container
	 */
	public function get_container(): Container {
		return $this->container;
	}
}
