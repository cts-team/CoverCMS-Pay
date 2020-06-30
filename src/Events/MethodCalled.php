<?php


namespace CoverCMS\Pay\Events;

/**
 * Class MethodCalled
 * @package CoverCMS\Pay\Events
 */
class MethodCalled extends Event
{
    /**
     * endpoint.
     *
     * @var string
     */
    public $endpoint;

    /**
     * payload.
     *
     * @var array
     */
    public $payload;

    /**
     * MethodCalled constructor.
     * @param string $driver
     * @param string $gateway
     * @param string $endpoint
     * @param array $payload
     */
    public function __construct(string $driver, string $gateway, string $endpoint, array $payload = [])
    {
        $this->endpoint = $endpoint;
        $this->payload = $payload;

        parent::__construct($driver, $gateway);
    }
}