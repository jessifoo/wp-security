<?php
/**
 * OMS Rate Limiter Class
 *
 * @package ObfuscatedMalwareScanner
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access is not allowed.' );
}

/**
 * OMS Rate Limiter Class
 */
class OMS_Rate_Limiter {
	/**
	 * Rate limits configuration
	 *
	 * @var array
	 */
	private $rate_limits;

	/**
	 * Last request time tracking
	 *
	 * @var array
	 */
	private $last_request_time;

	/**
	 * Logger instance
	 *
	 * @var OMS_Logger
	 */
	private $logger;

	/**
	 * Constructor
	 *
	 * @param array      $rate_limits Rate limits configuration.
	 * @param OMS_Logger $logger      Logger instance.
	 */
	public function __construct( array $rate_limits, OMS_Logger $logger ) {
		$this->rate_limits       = $rate_limits;
		$this->logger            = $logger;
		$this->last_request_time = array();
	}

	/**
	 * Throttle requests based on rate limits
	 *
	 * @param string $key Rate limit key.
	 */
	public function throttle( $key = 'default' ) {
		// Check system load first.
		if ( $this->should_throttle( $key ) ) {
			usleep( OMS_Config::SCAN_CONFIG['batch_pause'] * 1000 );
		}

		$current_time = microtime( true );
		if ( ! isset( $this->last_request_time[ $key ] ) ) {
			$this->last_request_time[ $key ] = $current_time;
			return;
		}

		$elapsed = $current_time - $this->last_request_time[ $key ];
		$limit   = $this->rate_limits[ $key ] ?? 1;

		if ( $elapsed < $limit ) {
			usleep( (int) ( ( $limit - $elapsed ) * 1000000 ) );
		}

		$this->last_request_time[ $key ] = microtime( true );
	}

	/**
	 * Check if should throttle based on system load.
	 *
	 * @param string $context Context for throttling (unused for now but good for future).
	 * @return bool True if should throttle.
	 */
	public function should_throttle( $context = 'default' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Context reserved for future use.
		try {
			if ( ! $this->is_rate_limiting_enabled() ) {
				return false;
			}

			return $this->is_server_overloaded()
				|| $this->is_memory_exceeded()
				|| $this->is_request_limit_exceeded()
				|| $this->is_peak_hours_throttle_needed();
		} catch ( Exception $e ) {
			$this->logger->error( 'Error checking rate limits: ' . esc_html( $e->getMessage() ) );
			return false;
		}
	}

	/**
	 * Check if rate limiting is enabled.
	 *
	 * @return bool True if enabled.
	 */
	private function is_rate_limiting_enabled() {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- OMS_ is the plugin prefix.
		if ( ! defined( 'OMS_RATE_LIMIT_ENABLED' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- OMS_ is the plugin prefix.
			define( 'OMS_RATE_LIMIT_ENABLED', true );
		}

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- OMS_ is the plugin prefix.
		return OMS_RATE_LIMIT_ENABLED;
	}

	/**
	 * Check if server load is too high.
	 *
	 * @return bool True if server is overloaded.
	 */
	private function is_server_overloaded() {
		if ( ! function_exists( 'sys_getloadavg' ) ) {
			return false;
		}

		$load     = sys_getloadavg();
		$max_load = OMS_Config::RATE_LIMIT_CONFIG['max_cpu_load'];

		if ( $load[0] > $max_load ) {
			$this->logger->warning( sprintf( 'High server load detected: %.2f', $load[0] ) );
			return true;
		}

		return false;
	}

	/**
	 * Check if memory usage exceeds threshold.
	 *
	 * @return bool True if memory exceeded.
	 */
	private function is_memory_exceeded() {
		$memory_usage = memory_get_usage( true );
		$memory_limit = $this->get_memory_limit();
		$max_memory   = $memory_limit * ( OMS_Config::RATE_LIMIT_CONFIG['max_memory_percent'] / 100 );

		if ( $memory_usage > $max_memory ) {
			$this->logger->warning( sprintf( 'High memory usage detected: %s', size_format( $memory_usage ) ) );
			return true;
		}

		return false;
	}

	/**
	 * Check if request limit exceeded for current hour.
	 *
	 * @return bool True if exceeded.
	 */
	private function is_request_limit_exceeded() {
		$request_key   = 'oms_request_count_' . gmdate( 'Y-m-d-H' );
		$request_count = (int) get_transient( $request_key );
		$max_requests  = OMS_Config::RATE_LIMIT_CONFIG['requests_per_hour'];

		if ( $request_count > $max_requests ) {
			$this->logger->warning( 'Request limit exceeded for current hour' );
			return true;
		}

		// Increment request count.
		set_transient( $request_key, $request_count + 1, HOUR_IN_SECONDS );

		return false;
	}

	/**
	 * Check if throttle needed during peak hours.
	 *
	 * @return bool True if should throttle during peak hours.
	 */
	private function is_peak_hours_throttle_needed() {
		$hour       = (int) current_time( 'G' );
		$peak_start = OMS_Config::RATE_LIMIT_CONFIG['peak_hour_start'];
		$peak_end   = OMS_Config::RATE_LIMIT_CONFIG['peak_hour_end'];

		if ( $hour < $peak_start || $hour > $peak_end ) {
			return false;
		}

		// During peak hours, be more conservative.
		if ( ! function_exists( 'sys_getloadavg' ) ) {
			return false;
		}

		$load     = sys_getloadavg();
		$max_load = OMS_Config::RATE_LIMIT_CONFIG['max_cpu_load'];

		if ( $load[0] > $max_load * 0.8 ) {
			$this->logger->info( 'Throttling during peak hours' );
			return true;
		}

		return false;
	}

	/**
	 * Get PHP memory limit in bytes
	 *
	 * @return int Memory limit in bytes.
	 */
	private function get_memory_limit() {
		$memory_limit = ini_get( 'memory_limit' );
		if ( '-1' === $memory_limit ) {
			return PHP_INT_MAX;
		}

		$unit  = strtolower( substr( $memory_limit, -1 ) );
		$bytes = (int) $memory_limit;

		switch ( $unit ) {
			case 'g':
				$bytes *= 1024;
				// Fall through.
			case 'm':
				$bytes *= 1024;
				// Fall through.
			case 'k':
				$bytes *= 1024;
		}

		return $bytes;
	}
}
