<?php
/**
 * The Kernel.
 *
 * The Kernel is responsible for bootstrapping the application.
 * It is the only class that knows about the "Big Picture" (the list of providers).
 *
 * @package OMS\Core
 */

declare( strict_types=1 );

namespace OMS\Core;

/**
 * Application Kernel class.
 *
 * Bootstraps the application by registering and booting service providers.
 *
 * @package OMS\Core
 */
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
	 * Registers all services (wiring) then boots all services (hooks).
	 *
	 * @return void
	 */
	public function run(): void {
		// Phase 1: Registration.
		// Instantiate all providers and let them tell the container what they provide.
		$provider_instances = array();

		foreach ( $this->providers as $provider_class ) {
			/**
			 * Service provider instance.
			 *
			 * @var ServiceProvider $provider
			 */
			$provider = new $provider_class();
			$provider->register( $this->container );
			$provider_instances[] = $provider;
		}

		// Phase 2: Boot.
		// Now that all services are known to the container, we can safely start them.
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
