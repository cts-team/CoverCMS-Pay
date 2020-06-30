<?php


namespace CoverCMS\Pay\Gateways\AliPay;


use CoverCMS\Pay\Contracts\GatewayInterface;
use CoverCMS\Pay\Events;
use CoverCMS\Pay\Exceptions\GatewayException;
use CoverCMS\Pay\Exceptions\InvalidConfigException;
use CoverCMS\Pay\Exceptions\InvalidSignException;
use CoverCMS\Support\Collection;

/**
 * Class TransferGateway
 * @package CoverCMS\Pay\Gateways\AliPay
 */
class TransferGateway implements GatewayInterface
{
    /**
     * Pay an order.
     *
     * @param string $endpoint
     * @param array $payload
     * @return Collection
     * @throws GatewayException
     * @throws InvalidConfigException
     * @throws InvalidSignException
     */
    public function pay($endpoint, array $payload): Collection
    {
        $payload['method'] = 'alipay.fund.trans.uni.transfer';
        $payload['sign'] = Support::generateSign($payload);

        Events::dispatch(new Events\PayStarted('AliPay', 'Transfer', $endpoint, $payload));

        return Support::requestApi($payload);
    }

    /**
     * @param $order
     * @return array
     */
    public function find($order): array
    {
        return [
            'method' => 'alipay.fund.trans.order.query',
            'biz_content' => json_encode(is_array($order) ? $order : ['out_biz_no' => $order]),
        ];
    }
}