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
	 * Constructor
	 *
	 * @param array $rate_limits Rate limits configuration.
	 */
	public function __construct( array $rate_limits ) {
		$this->rate_limits       = $rate_limits;
		$this->last_request_time = array();
	}

	/**
	 * Throttle requests based on rate limits
	 *
	 * @param string $key Rate limit key.
	 */
	public function throttle( $key = 'default' ) {
		$current_time = microtime( true );
		if ( ! isset( $this->last_request_time[ $key ] ) ) {
			$this->last_request_time[ $key ] = $current_time;
			return;
		}

		$elapsed = $current_time - $this->last_request_time[ $key ];
		$limit   = $this->rate_limits[ $key ] ?? 1;

		if ( $elapsed < $limit ) {
			usleep( ( $limit - $elapsed ) * 1000000 );
		}

		$this->last_request_time[ $key ] = microtime( true );
	}
}
