<?php


namespace CoverCMS\Pay\Gateways;


use CoverCMS\Pay\Contracts\GatewayApplicationInterface;
use CoverCMS\Pay\Contracts\GatewayInterface;
use CoverCMS\Pay\Events;
use CoverCMS\Pay\Exceptions\GatewayException;
use CoverCMS\Pay\Exceptions\InvalidArgumentException;
use CoverCMS\Pay\Exceptions\InvalidConfigException;
use CoverCMS\Pay\Exceptions\InvalidGatewayException;
use CoverCMS\Pay\Exceptions\InvalidSignException;
use CoverCMS\Pay\Gateways\AliPay\Support;
use CoverCMS\Support\Collection;
use CoverCMS\Support\Config;
use CoverCMS\Support\Str;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class AliPay
 * @package CoverCMS\Pay\Gateways
 * @method Response   app(array $config)      APP 支付
 * @method Collection pos(array $config)      刷卡支付
 * @method Collection scan(array $config)     扫码支付
 * @method Collection transfer(array $config) 帐户转账
 * @method Response   wap(array $config)      手机网站支付
 * @method Response   web(array $config)      电脑支付
 * @method Collection mini(array $config)     小程序支付
 */
class AliPay implements GatewayApplicationInterface
{
    /**
     * Const mode_normal.
     */
    const MODE_NORMAL = 'normal';

    /**
     * Const mode_dev.
     */
    const MODE_DEV = 'dev';

    /**
     * Const mode_service.
     */
    const MODE_SERVICE = 'service';

    /**
     * Const url.
     */
    const URL = [
        self::MODE_NORMAL => 'https://openapi.alipay.com/gateway.do?charset=utf-8',
        self::MODE_DEV => 'https://openapi.alipaydev.com/gateway.do?charset=utf-8',
    ];

    /**
     * AliPay payload.
     *
     * @var array
     */
    protected $payload;

    /**
     * AliPay gateway.
     *
     * @var string
     */
    protected $gateway;

    /**
     * extends.
     *
     * @var array
     */
    protected $extends;

    /**
     * AliPay constructor.
     * @param Config $config
     * @throws Exception
     */
    public function __construct(Config $config)
    {
        $this->gateway = Support::create($config)->getBaseUri();
        $this->payload = [
            'app_id' => $config->get('app_id'),
            'method' => '',
            'format' => 'JSON',
            'charset' => 'utf-8',
            'sign_type' => 'RSA2',
            'version' => '1.0',
            'return_url' => $config->get('return_url'),
            'notify_url' => $config->get('notify_url'),
            'timestamp' => date('Y-m-d H:i:s'),
            'sign' => '',
            'biz_content' => '',
            'app_auth_token' => $config->get('app_auth_token'),
        ];

        if ($config->get('app_cert_public_key') && $config->get('alipay_root_cert')) {
            $this->payload['app_cert_sn'] = Support::getCertSN($config->get('app_cert_public_key'));
            $this->payload['alipay_root_cert_sn'] = Support::getRootCertSN($config->get('alipay_root_cert'));
        }
    }

    /**
     * Magic pay.
     *
     * @param $method
     * @param $params
     * @return Collection|Response
     * @throws GatewayException
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     * @throws InvalidGatewayException
     * @throws InvalidSignException
     */
    public function __call($method, $params)
    {
        if (isset($this->extends[$method])) {
            return $this->makeExtend($method, ...$params);
        }

        return $this->pay($method, ...$params);
    }

    /**
     * Pay an order.
     *
     * @param string $gateway
     * @param array $params
     * @return Collection|Response
     * @throws InvalidGatewayException
     */
    public function pay($gateway, $params = [])
    {
        Events::dispatch(new Events\PayStarting('AliPay', $gateway, $params));

        $this->payload['return_url'] = $params['return_url'] ?? $this->payload['return_url'];
        $this->payload['notify_url'] = $params['notify_url'] ?? $this->payload['notify_url'];

        unset($params['return_url'], $params['notify_url']);

        $this->payload['biz_content'] = json_encode($params);

        $gateway = get_class($this) . '\\' . Str::studly($gateway) . 'Gateway';

        if (class_exists($gateway)) {
            return $this->makePay($gateway);
        }

        throw new InvalidGatewayException("Pay Gateway [{$gateway}] not exists");
    }

    /**
     * Verify sign.
     *
     * @param array|null $data
     * @param bool $refund
     * @return Collection
     * @throws InvalidConfigException
     * @throws InvalidSignException
     */
    public function verify($data = null, bool $refund = false): Collection
    {
        if (is_null($data)) {
            $request = Request::createFromGlobals();

            $data = $request->request->count() > 0 ? $request->request->all() : $request->query->all();
        }

        if (isset($data['fund_bill_list'])) {
            $data['fund_bill_list'] = htmlspecialchars_decode($data['fund_bill_list']);
        }

        Events::dispatch(new Events\RequestReceived('AliPay', '', $data));

        if (Support::verifySign($data)) {
            return new Collection($data);
        }

        Events::dispatch(new Events\SignFailed('AliPay', '', $data));

        throw new InvalidSignException('AliPay Sign Verify FAILED', $data);
    }

    /**
     * Query an order.
     *
     * @param array|string $order
     * @param string $type
     * @return Collection
     * @throws GatewayException
     * @throws InvalidConfigException
     * @throws InvalidSignException
     */
    public function find($order, string $type = 'wap'): Collection
    {
        $gateway = get_class($this) . '\\' . Str::studly($type) . 'Gateway';

        if (!class_exists($gateway) || !is_callable([new $gateway(), 'find'])) {
            throw new GatewayException("{$gateway} Done Not Exist Or Done Not Has FIND Method");
        }

        $config = call_user_func([new $gateway(), 'find'], $order);

        $this->payload['method'] = $config['method'];
        $this->payload['biz_content'] = $config['biz_content'];
        $this->payload['sign'] = Support::generateSign($this->payload);

        Events::dispatch(new Events\MethodCalled('AliPay', 'Find', $this->gateway, $this->payload));

        return Support::requestApi($this->payload);
    }

    /**
     * Refund an order.
     *
     * @param array $order
     * @return Collection
     * @throws GatewayException
     * @throws InvalidConfigException
     * @throws InvalidSignException
     */
    public function refund(array $order): Collection
    {
        $this->payload['method'] = 'alipay.trade.refund';
        $this->payload['biz_content'] = json_encode($order);
        $this->payload['sign'] = Support::generateSign($this->payload);

        Events::dispatch(new Events\MethodCalled('AliPay', 'Refund', $this->gateway, $this->payload));

        return Support::requestApi($this->payload);
    }

    /**
     * Cancel an order.
     *
     * @param array|string $order
     * @return Collection
     * @throws GatewayException
     * @throws InvalidConfigException
     * @throws InvalidSignException
     */
    public function cancel($order): Collection
    {
        $this->payload['method'] = 'alipay.trade.cancel';
        $this->payload['biz_content'] = json_encode(is_array($order) ? $order : ['out_trade_no' => $order]);
        $this->payload['sign'] = Support::generateSign($this->payload);

        Events::dispatch(new Events\MethodCalled('AliPay', 'Cancel', $this->gateway, $this->payload));

        return Support::requestApi($this->payload);
    }

    /**
     * Close an order.
     *
     * @param array|string $order
     * @return Collection
     * @throws GatewayException
     * @throws InvalidConfigException
     * @throws InvalidSignException
     */
    public function close($order): Collection
    {
        $this->payload['method'] = 'alipay.trade.close';
        $this->payload['biz_content'] = json_encode(is_array($order) ? $order : ['out_trade_no' => $order]);
        $this->payload['sign'] = Support::generateSign($this->payload);

        Events::dispatch(new Events\MethodCalled('AliPay', 'Close', $this->gateway, $this->payload));

        return Support::requestApi($this->payload);
    }

    /**
     * Download bill.
     *
     * @param array|string $bill
     * @return string
     * @throws GatewayException
     * @throws InvalidConfigException
     * @throws InvalidSignException
     */
    public function download($bill): string
    {
        $this->payload['method'] = 'alipay.data.dataservice.bill.downloadurl.query';
        $this->payload['biz_content'] = json_encode(is_array($bill) ? $bill : ['bill_type' => 'trade', 'bill_date' => $bill]);
        $this->payload['sign'] = Support::generateSign($this->payload);

        Events::dispatch(new Events\MethodCalled('AliPay', 'Download', $this->gateway, $this->payload));

        $result = Support::requestApi($this->payload);

        return ($result instanceof Collection) ? $result->get('bill_download_url') : '';
    }

    /**
     * Reply success to alipay.
     *
     * @return Response
     */
    public function success(): Response
    {
        Events::dispatch(new Events\MethodCalled('AliPay', 'Success', $this->gateway));

        return new Response('success');
    }

    /**
     * extend
     *
     * @param string $method
     * @param callable $function
     * @param bool $now
     * @return Collection|null
     * @throws GatewayException
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     * @throws InvalidSignException
     */
    public function extend(string $method, callable $function, bool $now = true): ?Collection
    {
        if (!$now && !method_exists($this, $method)) {
            $this->extends[$method] = $function;

            return null;
        }

        $customize = $function($this->payload);

        if (!is_array($customize) && !($customize instanceof Collection)) {
            throw new InvalidArgumentException('Return Type Must Be Array Or Collection');
        }

        Events::dispatch(new Events\MethodCalled('AliPay', 'extend', $this->gateway, $customize));

        if (is_array($customize)) {
            $this->payload = $customize;
            $this->payload['sign'] = Support::generateSign($this->payload);

            return Support::requestApi($this->payload);
        }

        return $customize;
    }

    /**
     * Make pay gateway.
     *
     * @param string $gateway
     * @return Collection|Response
     * @throws InvalidGatewayException
     */
    protected function makePay(string $gateway)
    {
        $app = new $gateway();

        if ($app instanceof GatewayInterface) {
            return $app->pay($this->gateway, array_filter($this->payload, function ($value) {
                return '' !== $value && !is_null($value);
            }));
        }

        throw new InvalidGatewayException("Pay Gateway [{$gateway}] Must Be An Instance Of GatewayInterface");
    }

    /**
     * makeExtend.
     *
     * @param string $method
     * @param array ...$params
     * @return Collection
     * @throws GatewayException
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     * @throws InvalidSignException
     */
    protected function makeExtend(string $method, array ...$params): Collection
    {
        $params = count($params) >= 1 ? $params[0] : $params;

        $function = $this->extends[$method];

        $customize = $function($this->payload, $params);

        if (!is_array($customize) && !($customize instanceof Collection)) {
            throw new InvalidArgumentException('Return Type Must Be Array Or Collection');
        }

        Events::dispatch(new Events\MethodCalled(
            'AliPay',
            'extend - ' . $method,
            $this->gateway,
            is_array($customize) ? $customize : $customize->toArray()
        ));

        if (is_array($customize)) {
            $this->payload = $customize;
            $this->payload['sign'] = Support::generateSign($this->payload);

            return Support::requestApi($this->payload);
        }

        return $customize;
    }
}
