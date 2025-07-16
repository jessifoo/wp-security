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

    // @TODO how to handle exceptions?
    public function handleException($e, $context = '')
    {
        return throw $e;
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
