<?php
declare(strict_types=1);

/**
 * Service Container for dependency injection.
 *
 * Provides lazy-loading singleton access to all plugin services.
 * This is the ONLY place where service instances are created.
 *
 * @package ObfuscatedMalwareScanner
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access is not allowed.' );
}

/**
 * Service Container for the plugin.
 *
 * Usage:
 *   $logger = OMS_Container::get( OMS_Logger::class );
 *   $scanner = OMS_Container::get( Obfuscated_Malware_Scanner::class );
 */
final class OMS_Container {

	/**
	 * Registered service instances.
	 *
	 * @var array<string, object>
	 */
	private static array $instances = array();

	/**
	 * Service factory definitions.
	 *
	 * @var array<string, callable>
	 */
	private static array $factories = array();

	/**
	 * Whether the container has been booted.
	 *
	 * @var bool
	 */
	private static bool $booted = false;

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}

	/**
	 * Boot the container with service definitions.
	 *
	 * @return void
	 */
	public static function boot(): void {
		if ( self::$booted ) {
			return;
		}

		self::register_services();
		self::$booted = true;
	}

	/**
	 * Register all service factories.
	 *
	 * Services are created lazily on first access.
	 *
	 * @return void
	 */
	private static function register_services(): void {
		// Core services (no dependencies).
		self::$factories[ OMS_Logger::class ] = static function (): OMS_Logger {
			return new OMS_Logger();
		};

		self::$factories[ OMS_Cache::class ] = static function (): OMS_Cache {
			return new OMS_Cache();
		};

		self::$factories[ OMS_Filesystem::class ] = static function (): OMS_Filesystem {
			return new OMS_Filesystem();
		};

		// Services with dependencies.
		self::$factories[ OMS_Rate_Limiter::class ] = static function (): OMS_Rate_Limiter {
			return new OMS_Rate_Limiter(
				array( 'default' => Obfuscated_Malware_Scanner::RATE_LIMITS ),
				self::get( OMS_Logger::class )
			);
		};

		self::$factories[ OMS_File_Security_Policy::class ] = static function (): OMS_File_Security_Policy {
			return new OMS_File_Security_Policy(
				self::get( OMS_Filesystem::class )
			);
		};

		self::$factories[ OMS_Quarantine_Manager::class ] = static function (): OMS_Quarantine_Manager {
			return new OMS_Quarantine_Manager(
				self::get( OMS_Logger::class )
			);
		};

		self::$factories[ OMS_Core_Integrity_Checker::class ] = static function (): OMS_Core_Integrity_Checker {
			return new OMS_Core_Integrity_Checker(
				self::get( OMS_Logger::class )
			);
		};

		self::$factories[ OMS_Database_Scanner::class ] = static function (): OMS_Database_Scanner {
			global $wpdb;
			return new OMS_Database_Scanner(
				self::get( OMS_Logger::class ),
				self::get( OMS_Cache::class ),
				$wpdb
			);
		};

		// Main scanner - depends on many services.
		self::$factories[ Obfuscated_Malware_Scanner::class ] = static function (): Obfuscated_Malware_Scanner {
			return new Obfuscated_Malware_Scanner(
				self::get( OMS_Logger::class ),
				self::get( OMS_Cache::class ),
				self::get( OMS_Rate_Limiter::class ),
				self::get( OMS_Filesystem::class ),
				self::get( OMS_File_Security_Policy::class ),
				self::get( OMS_Quarantine_Manager::class ),
				self::get( OMS_Core_Integrity_Checker::class ),
				self::get( OMS_Database_Scanner::class )
			);
		};

		// API service.
		self::$factories[ OMS_API::class ] = static function (): OMS_API {
			return new OMS_API(
				self::get( OMS_Logger::class ),
				self::get( Obfuscated_Malware_Scanner::class )
			);
		};

		// Admin service.
		self::$factories[ OMS_Admin::class ] = static function (): OMS_Admin {
			return new OMS_Admin(
				'obfuscated-malware-scanner',
				OMS_VERSION,
				self::get( Obfuscated_Malware_Scanner::class )
			);
		};
	}

	/**
	 * Get a service instance.
	 *
	 * @template T of object
	 * @param class-string<T> $class_name The service class name.
	 * @return T The service instance.
	 * @throws InvalidArgumentException If service is not registered.
	 */
	public static function get( string $class_name ): object {
		// Boot if not already.
		if ( ! self::$booted ) {
			self::boot();
		}

		// Return cached instance if exists.
		if ( isset( self::$instances[ $class_name ] ) ) {
			return self::$instances[ $class_name ];
		}

		// Create instance from factory.
		if ( ! isset( self::$factories[ $class_name ] ) ) {
			throw new InvalidArgumentException(
				sprintf( 'Service not registered: %s', esc_html( $class_name ) )
			);
		}

		$instance                       = self::$factories[ $class_name ]();
		self::$instances[ $class_name ] = $instance;

		return $instance;
	}

	/**
	 * Check if a service is registered.
	 *
	 * @param string $class_name The service class name.
	 * @return bool True if registered.
	 */
	public static function has( string $class_name ): bool {
		if ( ! self::$booted ) {
			self::boot();
		}
		return isset( self::$factories[ $class_name ] );
	}

	/**
	 * Override a service (for testing).
	 *
	 * @param string $class_name The service class name.
	 * @param object $instance   The instance to use.
	 * @return void
	 */
	public static function set( string $class_name, object $instance ): void {
		self::$instances[ $class_name ] = $instance;
	}

	/**
	 * Reset the container (for testing).
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$instances = array();
		self::$factories = array();
		self::$booted    = false;
	}
}
