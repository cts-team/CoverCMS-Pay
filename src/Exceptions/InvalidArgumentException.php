<?php


namespace CoverCMS\Pay\Exceptions;


/**
 * Class InvalidArgumentException
 * @package CoverCMS\Pay\Exceptions
 */
class InvalidArgumentException extends Exception
{
    /**
     * InvalidArgumentException constructor.
     * @param string $message
     * @param array|string $raw
     */
    public function __construct($message, $raw = [])
    {
        parent::__construct('INVALID_ARGUMENT: ' . $message, $raw, self::INVALID_ARGUMENT);
    }
}