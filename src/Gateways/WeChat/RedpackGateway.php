<?php


namespace CoverCMS\Pay\Gateways\WeChat;


use CoverCMS\Pay\Events;
use CoverCMS\Pay\Exceptions\BusinessException;
use CoverCMS\Pay\Exceptions\GatewayException;
use CoverCMS\Pay\Exceptions\InvalidArgumentException;
use CoverCMS\Pay\Exceptions\InvalidSignException;
use CoverCMS\Pay\Gateways\WeChat;
use CoverCMS\Support\Collection;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class RedpackGateway
 * @package CoverCMS\Pay\Gateways\WeChat
 */
class RedpackGateway extends Gateway
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
        $payload['wxappid'] = $payload['appid'];

        if ('cli' !== php_sapi_name()) {
            $payload['client_ip'] = Request::createFromGlobals()->server->get('SERVER_ADDR');
        }

        if (WeChat::MODE_SERVICE === $this->mode) {
            $payload['msgappid'] = $payload['appid'];
        }

        unset($payload['appid'], $payload['trade_type'],
            $payload['notify_url'], $payload['spbill_create_ip']);

        $payload['sign'] = Support::generateSign($payload);

        Events::dispatch(new Events\PayStarted('WeChat', 'Redpack', $endpoint, $payload));

        return Support::requestApi(
            'mmpaymkttransfers/sendredpack',
            $payload,
            true
        );
    }

    /**
     * Get trade type config.
     *
     * @return string
     */
    protected function getTradeType(): string
    {
        return '';
    }
}
