<?php


namespace CoverCMS\Pay\Events;


/**
 * Class PayStarting
 * @package CoverCMS\Pay\Events
 */
class PayStarting extends Event
{
    /**
     * Params.
     *
     * @var array
     */
    public $params;

    /**
     * PayStarting constructor.
     * @param string $driver
     * @param string $gateway
     * @param array $params
     */
    public function __construct(string $driver, string $gateway, array $params)
    {
        $this->params = $params;

        parent::__construct($driver, $gateway);
    }
}