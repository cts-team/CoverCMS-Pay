<?php


namespace CoverCMS\Pay\Gateways\WeChat;


use CoverCMS\Pay\Events;
use CoverCMS\Pay\Exceptions\BusinessException;
use CoverCMS\Pay\Exceptions\GatewayException;
use CoverCMS\Pay\Exceptions\InvalidArgumentException;
use CoverCMS\Pay\Exceptions\InvalidSignException;
use CoverCMS\Support\Collection;

/**
 * Class PosGateway
 * @package CoverCMS\Pay\Gateways\WeChat
 */
class PosGateway extends Gateway
{
    /**
     * Pay an order.
     *
     * @param string $endpoint
     * @param array $payload
     * @return Collection
     * @throws BusinessException
     * @throws GatewayException
     * @throws InvalidArgumentException
     * @throws InvalidSignException
     */
    public function pay($endpoint, array $payload): Collection
    {
        unset($payload['trade_type'], $payload['notify_url']);

        $payload['sign'] = Support::generateSign($payload);

        Events::dispatch(new Events\PayStarted('WeChat', 'Pos', $endpoint, $payload));

        return Support::requestApi('pay/micropay', $payload);
    }

    /**
     * Get trade type config.
     *
     * @return string
     */
    protected function getTradeType(): string
    {
        return 'MICROPAY';
    }
}