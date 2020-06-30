<?php


namespace CoverCMS\Pay\Exceptions;


/**
 * Class BusinessException
 * @package CoverCMS\Pay\Exceptions
 */
class BusinessException extends GatewayException
{
    /**
     * BusinessException constructor.
     * @param string $message
     * @param array|string $raw
     */
    public function __construct($message, $raw = [])
    {
        parent::__construct('ERROR_BUSINESS: ' . $message, $raw, self::ERROR_BUSINESS);
    }
}
