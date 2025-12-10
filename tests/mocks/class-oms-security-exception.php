<?php
/**
 * Mock security exception class for testing
 */
class OMS_Security_Exception extends Exception {
	/**
	 * Constructor
	 *
	 * @param string         $message Exception message
	 * @param int            $code Exception code
	 * @param Exception|null $previous Previous exception
	 */
	public function __construct( string $message = '', int $code = 0, ?Exception $previous = null ) {
		parent::__construct( $message, $code, $previous );
	}
}
