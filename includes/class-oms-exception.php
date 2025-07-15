<?php

/**
 * OMS Exception Class
 */
class OMS_Exception extends Exception
{
    public function __construct($message, $code = 0, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    // Basic exception handler used throughout the plugin.
    // Logs the exception and rethrows it so calling code can decide how to
    // recover. Returning the exception caused a fatal error in PHP 8.
    public function handleException($e, $context = '')
    {
        error_log(sprintf('OMS Exception in %s: %s', $context, $e->getMessage()));
        throw $e;
    }

    public function __toString()
    {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }

    public function get_message()
    {
        return $this->message;
    }

    public function get_code()
    {
        return $this->code;
    }
}
