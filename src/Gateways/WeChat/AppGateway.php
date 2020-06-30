<?php


namespace CoverCMS\Pay\Gateways\WeChat;


use CoverCMS\Pay\Events;
use CoverCMS\Pay\Exceptions\BusinessException;
use CoverCMS\Pay\Exceptions\GatewayException;
use CoverCMS\Pay\Exceptions\InvalidArgumentException;
use CoverCMS\Pay\Exceptions\InvalidSignException;
use CoverCMS\Pay\Gateways\WeChat;
use CoverCMS\Support\Str;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class AppGateway
 * @package CoverCMS\Pay\Gateways\WeChat
 */
class AppGateway extends Gateway
{
    /**
     * Pay an order.
     *
     * @param string $endpoint
     * @param array $payload
     * @return Response
     * @throws BusinessException
     * @throws GatewayException
     * @throws InvalidArgumentException
     * @throws InvalidSignException
     * @throws Exception
     */
    public function pay($endpoint, array $payload): Response
    {
        $payload['appid'] = Support::getInstance()->appid;
        $payload['trade_type'] = $this->getTradeType();

        if (WeChat::MODE_SERVICE === $this->mode) {
            $payload['sub_appid'] = Support::getInstance()->sub_appid;
        }

        $pay_request = [
            'appid' => WeChat::MODE_SERVICE === $this->mode ? $payload['sub_appid'] : $payload['appid'],
            'partnerid' => WeChat::MODE_SERVICE === $this->mode ? $payload['sub_mch_id'] : $payload['mch_id'],
            'prepayid' => $this->preOrder($payload)->get('prepay_id'),
            'timestamp' => strval(time()),
            'noncestr' => Str::random(),
            'package' => 'Sign=WXPay',
        ];
        $pay_request['sign'] = Support::generateSign($pay_request);

        Events::dispatch(new Events\PayStarted('WeChat', 'App', $endpoint, $pay_request));

        return new JsonResponse($pay_request);
    }

    /**
     * Get trade type config.
     *
     * @return string
     */
    protected function getTradeType(): string
    {
        return 'APP';
    }
}