<?php
declare(strict_types=1);

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
	 * @param string         $message Exception message.
	 * @param int            $code Exception code.
	 * @param Exception|null $previous Previous exception.
	 */
	public function __construct( string $message, int $code = 0, ?Exception $previous = null ) {
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
	public function handleException( Exception $e, string $context = '' ): never {
		error_log( sprintf( 'OMS Exception in %s: %s', esc_html( $context ), esc_html( $e->getMessage() ) ) );
		throw $e;
	}

	/**
	 * Convert exception to string
	 *
	 * @return string String representation of exception.
	 */
	public function __toString(): string {
		return __CLASS__ . ': [' . esc_html( (string) $this->code ) . ']: ' . esc_html( $this->message ) . "\n";
	}

	/**
	 * Get exception message
	 *
	 * @return string Exception message.
	 */
	public function get_message(): string {
		return $this->message;
	}

	/**
	 * Get exception code
	 *
	 * @return int Exception code.
	 */
	public function get_code(): int {
		return (int) $this->code;
	}
}
