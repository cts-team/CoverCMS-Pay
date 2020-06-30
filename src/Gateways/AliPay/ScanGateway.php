<?php


namespace CoverCMS\Pay\Gateways\AliPay;


use CoverCMS\Pay\Events;
use CoverCMS\Pay\Exceptions\GatewayException;
use CoverCMS\Pay\Exceptions\InvalidArgumentException;
use CoverCMS\Pay\Exceptions\InvalidConfigException;
use CoverCMS\Pay\Exceptions\InvalidSignException;
use CoverCMS\Pay\Gateways\AliPay;
use CoverCMS\Support\Collection;

/**
 * Class ScanGateway
 * @package CoverCMS\Pay\Gateways\AliPay
 */
class ScanGateway extends Gateway
{
    /**
     * Pay an order.
     *
     * @param string $endpoint
     * @param array $payload
     * @return Collection
     * @throws GatewayException
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     * @throws InvalidSignException
     */
    public function pay($endpoint, array $payload): Collection
    {
        $payload['method'] = 'alipay.trade.precreate';
        $biz_array = json_decode($payload['biz_content'], true);
        if ((AliPay::MODE_SERVICE === $this->mode) && (!empty(Support::getInstance()->pid))) {
            $biz_array['extend_params'] = is_array($biz_array['extend_params']) ? array_merge(['sys_service_provider_id' => Support::getInstance()->pid], $biz_array['extend_params']) : ['sys_service_provider_id' => Support::getInstance()->pid];
        }
        $payload['biz_content'] = json_encode(array_merge($biz_array, ['product_code' => '']));
        $payload['sign'] = Support::generateSign($payload);

        Events::dispatch(new Events\PayStarted('AliPay', 'Scan', $endpoint, $payload));

        return Support::requestApi($payload);
    }
}