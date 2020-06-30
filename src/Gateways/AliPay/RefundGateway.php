<?php


namespace CoverCMS\Pay\Gateways\AliPay;


/**
 * Class RefundGateway
 * @package CoverCMS\Pay\Gateways\AliPay
 */
class RefundGateway
{
    /**
     * @param $order
     * @return array
     */
    public function find($order): array
    {
        return [
            'method' => 'alipay.trade.fastpay.refund.query',
            'biz_content' => json_encode(is_array($order) ? $order : ['out_trade_no' => $order]),
        ];
    }
}
