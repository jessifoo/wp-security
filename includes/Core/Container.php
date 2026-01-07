<?php
/**
 * A Strict Dependency Injection Container.
 *
 * This class is responsible for managing class dependencies and performing
 * automatic wiring of services. It enforces strict typing and centralized
 * configuration.
 *
 * @package OMS\Core
 */

declare( strict_types=1 );

namespace OMS\Core;

use ReflectionClass;
use ReflectionNamedType;
use Exception;

/**
 * Dependency Injection Container class.
 *
 * Manages service instances and provides automatic dependency resolution.
 *
 * @package OMS\Core
 */
class Container {

	/**
	 * Specialized registry for singleton instances.
	 *
	 * @var array<string, object>
	 */
	private array $instances = array();

	/**
	 * Registry for service definitions/factories.
	 *
	 * @var array<string, callable>
	 */
	private array $definitions = array();

	/**
	 * Bind a service to a factory.
	 *
	 * @param string   $id       The service identifier (usually class/interface name).
	 * @param callable $concrete A factory function returning the instance.
	 * @return void
	 */
	public function bind( string $id, callable $concrete ): void {
		$this->definitions[ $id ] = $concrete;
	}

	/**
	 * Bind a service as a singleton.
	 *
	 * The factory will only be executed once.
	 *
	 * @param string   $id       The service identifier.
	 * @param callable $concrete A factory function.
	 * @return void
	 */
	public function singleton( string $id, callable $concrete ): void {
		$this->definitions[ $id ] = function ( Container $c ) use ( $concrete, $id ) {
			if ( ! isset( $this->instances[ $id ] ) ) {
				$this->instances[ $id ] = $concrete( $c );
			}
			return $this->instances[ $id ];
		};
	}

	/**
	 * Resolve a service instance.
	 *
	 * @param string $id The service identifier.
	 * @return object The resolved service.
	 * @throws Exception If resolution fails.
	 */
	public function get( string $id ): object {
		// Check if we have a definition for it.
		if ( isset( $this->definitions[ $id ] ) ) {
			$concrete = $this->definitions[ $id ];
			return $concrete( $this );
		}

		// If no definition, try to auto-wire it (Reflection).
		return $this->resolve( $id );
	}

	/**
	 * Auto-wire a class using Reflection.
	 *
	 * @param string $class_name The class name to instantiate.
	 * @return object The instantiated class.
	 * @throws Exception If the class cannot be instantiated or dependencies are missing.
	 */
	private function resolve( string $class_name ): object {
		if ( ! class_exists( $class_name ) ) {
			throw new Exception( esc_html( "Service not found: $class_name" ) );
		}

		$reflector = new ReflectionClass( $class_name );

		if ( ! $reflector->isInstantiable() ) {
			throw new Exception( esc_html( "Class is not instantiable: $class_name" ) );
		}

		$constructor = $reflector->getConstructor();

		// If no constructor, simple instantiation.
		if ( null === $constructor ) {
			return new $class_name();
		}

		// Resolve dependencies.
		$dependencies = array();
		foreach ( $constructor->getParameters() as $parameter ) {
			$type = $parameter->getType();

			if ( ! $type instanceof ReflectionNamedType || $type->isBuiltin() ) {
				$param_name = $parameter->getName();
				throw new Exception( esc_html( "Cannot auto-resolve non-class dependency '$param_name' in $class_name" ) );
			}

			// Recursive resolution.
			$dependencies[] = $this->get( $type->getName() );
		}

		return $reflector->newInstanceArgs( $dependencies );
	}
}
