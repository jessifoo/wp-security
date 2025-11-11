<?php
/**
 * OMS Exception Class
 *
 * @package ObfuscatedMalwareScanner
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access is not allowed.' );
}

/**
 * OMS Exception Class
 */
class OMS_Exception extends Exception {
	/**
	 * Constructor
	 *
	 * @param string    $message Exception message.
	 * @param int       $code Exception code.
	 * @param Exception $previous Previous exception.
	 */
	public function __construct( $message, $code = 0, Exception $previous = null ) {
		// Call parent constructor with sanitized message.
		parent::__construct( sanitize_text_field( $message ), (int) $code, $previous );
	}

	/**
	 * Handle exception.
	 *
	 * @param Exception $e Exception to handle.
	 * @param string    $context Context information.
	 * @return never
	 * @throws Exception Always throws the exception.
	 */
	public function handleException( $e, $context = '' ) {
		error_log( sprintf( 'OMS Exception in %s: %s', $context, esc_html( $e->getMessage() ) ) );
		throw $e;
	}

	/**
	 * Convert exception to string
	 *
	 * @return string String representation of exception.
	 */
	public function __toString() {
		return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
	}

	/**
	 * Get exception message
	 *
	 * @return string Exception message.
	 */
	public function get_message() {
		return $this->message;
	}

	/**
	 * Get exception code
	 *
	 * @return int Exception code.
	 */
	public function get_code() {
		return $this->code;
	}
}
