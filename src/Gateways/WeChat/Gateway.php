<?php


namespace CoverCMS\Pay\Gateways\WeChat;


use CoverCMS\Pay\Contracts\GatewayInterface;
use CoverCMS\Pay\Events;
use CoverCMS\Pay\Exceptions\BusinessException;
use CoverCMS\Pay\Exceptions\GatewayException;
use CoverCMS\Pay\Exceptions\InvalidArgumentException;
use CoverCMS\Pay\Exceptions\InvalidSignException;
use CoverCMS\Support\Collection;

/**
 * Class Gateway
 * @package CoverCMS\Pay\Gateways\WeChat
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

    /**
     * @param string|array $order
     * @return array
     */
    public function find($order): array
    {
        return [
            'endpoint' => 'pay/orderquery',
            'order' => is_array($order) ? $order : ['out_trade_no' => $order],
            'cert' => false,
        ];
    }

    /**
     * Get trade type config.
     *
     * @return string
     */
    abstract protected function getTradeType();

    /**
     * Schedule an order.
     *
     * @param array $payload
     * @return Collection
     * @throws InvalidArgumentException
     * @throws BusinessException
     * @throws GatewayException
     * @throws InvalidSignException
     */
    protected function preOrder($payload): Collection
    {
        $payload['sign'] = Support::generateSign($payload);

        Events::dispatch(new Events\MethodCalled('WeChat', 'PreOrder', '', $payload));

        return Support::requestApi('pay/unifiedorder', $payload);
    }
}
