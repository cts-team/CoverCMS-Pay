<?php


namespace CoverCMS\Pay\Exceptions;


/**
 * Class InvalidConfigException
 * @package CoverCMS\Pay\Exceptions
 */
class InvalidConfigException extends Exception
{
    /**
     * InvalidConfigException constructor.
     * @param string $message
     * @param array|string $raw
     */
    public function __construct($message, $raw = [])
    {
        parent::__construct('INVALID_CONFIG: ' . $message, $raw, self::INVALID_CONFIG);
    }
}
