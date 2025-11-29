<?php
/**
 * Error Handler class for the Obfuscated Malware Scanner
 *
 * @package ObfuscatedMalwareScanner
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access is not allowed.' );
}

/**
 * Error Handler class.
 */
class OMS_Error_Handler {
	/**
	 * Logger instance.
	 *
	 * @var OMS_Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param OMS_Logger $logger Logger instance.
	 */
	public function __construct( OMS_Logger $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Handle exception.
	 *
	 * @param Exception $e Exception to handle.
	 * @param string    $context Context message.
	 * @return void
	 */
	public function handle_exception( Exception $e, $context = '' ) {
		$message = $e->getMessage();
		if ( ! empty( $context ) ) {
			$message = $context . ': ' . $message;
		}

		$this->logger->error( $message );

		// Log trace if in debug mode.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$this->logger->debug( $e->getTraceAsString() );
		}
	}

	/**
	 * Handle error.
	 *
	 * @param string $message Error message.
	 * @param string $level Error level.
	 * @return void
	 */
	public function handle_error( $message, $level = 'error' ) {
		$this->logger->log( $message, $level );
	}
}
