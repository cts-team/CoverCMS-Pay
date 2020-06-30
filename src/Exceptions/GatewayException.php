<?php


namespace CoverCMS\Pay\Exceptions;


/**
 * Class GatewayException
 * @package CoverCMS\Pay\Exceptions
 */
class GatewayException extends Exception
{
    /**
     * GatewayException constructor.
     * @param $message
     * @param array|string $raw
     * @param int $code
     */
    public function __construct($message, $raw = [], $code = self::ERROR_GATEWAY)
    {
        parent::__construct('ERROR_GATEWAY: '.$message, $raw, $code);
    }
}
