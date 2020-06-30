<?php


namespace CoverCMS\Pay\Gateways\WeChat;


use CoverCMS\Pay\Exceptions\InvalidArgumentException;

/**
 * Class RefundGateway
 * @package CoverCMS\Pay\Gateways\WeChat
 */
class RefundGateway extends Gateway
{
    /**
     * @param array|string $order
     * @return array
     */
    public function find($order): array
    {
        return [
            'endpoint' => 'pay/refundquery',
            'order' => is_array($order) ? $order : ['out_trade_no' => $order],
            'cert' => false,
        ];
    }

    /**
     * Pay an order.
     *
     * @param string $endpoint
     * @param array $payload
     * @throws InvalidArgumentException
     */
    public function pay($endpoint, array $payload)
    {
        throw new InvalidArgumentException('Not Support Refund In Pay');
    }

    /**
     * Get trade type config.
     *
     * @throws InvalidArgumentException
     */
    protected function getTradeType()
    {
        throw new InvalidArgumentException('Not Support Refund In Pay');
    }
}