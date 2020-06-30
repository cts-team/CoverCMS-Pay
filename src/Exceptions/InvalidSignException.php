<?php


namespace CoverCMS\Pay\Exceptions;


/**
 * Class InvalidSignException
 * @package CoverCMS\Pay\Exceptions
 */
class InvalidSignException extends Exception
{
    /**
     * InvalidSignException constructor.
     * @param string $message
     * @param array|string $raw
     */
    public function __construct($message, $raw = [])
    {
        parent::__construct('INVALID_SIGN: ' . $message, $raw, self::INVALID_SIGN);
    }
}