<?php


namespace CoverCMS\Pay\Gateways\AliPay;


use CoverCMS\Pay\Events;
use CoverCMS\Pay\Exceptions\InvalidArgumentException;
use CoverCMS\Pay\Exceptions\InvalidConfigException;
use CoverCMS\Pay\Gateways\AliPay;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class WebGateway
 * @package CoverCMS\Pay\Gateways\AliPay
 */
class WebGateway extends Gateway
{
    /**
     * Pay an order.
     *
     * @param string $endpoint
     * @param array $payload
     * @return Response
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     */
    public function pay($endpoint, array $payload): Response
    {
        $biz_array = json_decode($payload['biz_content'], true);
        $biz_array['product_code'] = $this->getProductCode();

        $method = $biz_array['http_method'] ?? 'POST';

        unset($biz_array['http_method']);
        if ((AliPay::MODE_SERVICE === $this->mode) && (!empty(Support::getInstance()->pid))) {
            $biz_array['extend_params'] = is_array($biz_array['extend_params']) ? array_merge(['sys_service_provider_id' => Support::getInstance()->pid], $biz_array['extend_params']) : ['sys_service_provider_id' => Support::getInstance()->pid];
        }
        $payload['method'] = $this->getMethod();
        $payload['biz_content'] = json_encode($biz_array);
        $payload['sign'] = Support::generateSign($payload);

        Events::dispatch(new Events\PayStarted('AliPay', 'Web/Wap', $endpoint, $payload));

        return $this->buildPayHtml($endpoint, $payload, $method);
    }

    /**
     * @param $order
     * @return array
     */
    public function find($order): array
    {
        return [
            'method' => 'alipay.trade.query',
            'biz_content' => json_encode(is_array($order) ? $order : ['out_trade_no' => $order]),
        ];
    }

    /**
     * Build Html response.
     *
     * @param string $endpoint
     * @param array $payload
     * @param string $method
     * @return Response
     */
    protected function buildPayHtml($endpoint, $payload, $method = 'POST'): Response
    {
        if ('GET' === strtoupper($method)) {
            return new RedirectResponse($endpoint . '&' . http_build_query($payload));
        }

        $sHtml = "<form id='alipay_submit' name='alipay_submit' action='" . $endpoint . "' method='" . $method . "'>";
        foreach ($payload as $key => $val) {
            $val = str_replace("'", '&apos;', $val);
            $sHtml .= "<input type='hidden' name='" . $key . "' value='" . $val . "'/>";
        }
        $sHtml .= "<input type='submit' value='ok' style='display:none;'></form>";
        $sHtml .= "<script>document.forms['alipay_submit'].submit();</script>";

        return new Response($sHtml);
    }

    /**
     * Get method config.
     *
     * @return string
     */
    protected function getMethod(): string
    {
        return 'alipay.trade.page.pay';
    }

    /**
     * Get productCode config.
     *
     * @return string
     */
    protected function getProductCode(): string
    {
        return 'FAST_INSTANT_TRADE_PAY';
    }
}
