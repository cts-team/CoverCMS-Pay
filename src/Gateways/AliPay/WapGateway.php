<?php


namespace CoverCMS\Pay\Gateways\AliPay;


/**
 * Class WapGateway
 * @package CoverCMS\Pay\Gateways\AliPay
 */
class WapGateway extends WebGateway
{
    /**
     * Get method config.
     *
     * @return string
     */
    protected function getMethod(): string
    {
        return 'alipay.trade.wap.pay';
    }

    /**
     * Get productCode config.
     *
     * @return string
     */
    protected function getProductCode(): string
    {
        return 'QUICK_WAP_WAY';
    }
}