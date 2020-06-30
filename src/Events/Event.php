<?php


namespace CoverCMS\Pay\Events;


/**
 * Class Event
 * @package CoverCMS\Pay\Events
 */
class Event extends \Symfony\Contracts\EventDispatcher\Event
{
    /**
     * Driver.
     *
     * @var string
     */
    public $driver;

    /**
     * Method.
     *
     * @var string
     */
    public $gateway;

    /**
     * Extra attributes.
     *
     * @var mixed
     */
    public $attributes;

    /**
     * Event constructor.
     * @param string $driver
     * @param string $gateway
     */
    public function __construct(string $driver, string $gateway)
    {
        $this->driver = $driver;
        $this->gateway = $gateway;
    }
}