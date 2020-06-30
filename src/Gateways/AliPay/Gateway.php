<?php


namespace CoverCMS\Pay\Gateways\AliPay;


use CoverCMS\Pay\Contracts\GatewayInterface;
use CoverCMS\Pay\Exceptions\InvalidArgumentException;
use CoverCMS\Support\Collection;

/**
 * Class Gateway
 * @package CoverCMS\Pay\Gateways\AliPay
 */
abstract class Gateway implements GatewayInterface
{
    /**
     * Mode.
     *
     * @var string
     */
    protected $mode;

    /**
     * Gateway constructor.
     * @throws InvalidArgumentException
     */
    public function __construct()
    {
        $this->mode = Support::getInstance()->mode;
    }

    /**
     * Pay an order.
     *
     * @param string $endpoint
     * @param array $payload
     * @return Collection
     */
    abstract public function pay($endpoint, array $payload);
}
