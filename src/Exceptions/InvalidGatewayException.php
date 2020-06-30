<?php


namespace CoverCMS\Pay\Exceptions;


/**
 * Class InvalidGatewayException
 * @package CoverCMS\Pay\Exceptions
 */
class InvalidGatewayException extends Exception
{
    /**
     * InvalidGatewayException constructor.
     * @param string $message
     * @param array|string $raw
     */
    public function __construct($message, $raw = [])
    {
        parent::__construct('INVALID_GATEWAY: ' . $message, $raw, self::INVALID_GATEWAY);
    }
}
