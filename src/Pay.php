<?php


namespace CoverCMS\Pay;


use CoverCMS\Pay\Contracts\GatewayApplicationInterface;
use CoverCMS\Pay\Exceptions\InvalidGatewayException;
use CoverCMS\Pay\Gateways\AliPay;
use CoverCMS\Pay\Gateways\WeChat;
use CoverCMS\Pay\Listeners\KernelLogSubscriber;
use CoverCMS\Support\Config;
use CoverCMS\Support\Log;
use CoverCMS\Support\Logger;
use CoverCMS\Support\Str;
use Exception;

/**
 * Class Pay
 * @package CoverCMS\Pay
 * @method static AliPay aliPay(array $config) 支付宝
 * @method static WeChat weChat(array $config) 微信
 */
class Pay
{
    /**
     * Config.
     *
     * @var Config
     */
    protected $config;

    /**
     * Pay constructor.
     * @param array $config
     * @throws Exception
     */
    public function __construct(array $config)
    {
        $this->config = new Config($config);

        $this->registerLogService();
        $this->registerEventService();
    }

    /**
     * @param $method
     * @param $params
     * @return GatewayApplicationInterface
     * @throws Exception
     */
    public static function __callStatic($method, $params): GatewayApplicationInterface
    {
        $app = new self(...$params);

        return $app->create($method);
    }

    /**
     * @param string $method
     * @return GatewayApplicationInterface
     * @throws InvalidGatewayException
     */
    protected function create($method): GatewayApplicationInterface
    {
        $gateway = __NAMESPACE__ . '\\Gateways\\' . Str::studly($method);

        if (class_exists($gateway)) {
            return self::make($gateway);
        }

        throw new InvalidGatewayException("Gateway [{$method}] Not Exists");
    }

    /**
     * Make a gateway.
     *
     * @param string $gateway
     * @return GatewayApplicationInterface
     * @throws InvalidGatewayException
     */
    protected function make($gateway): GatewayApplicationInterface
    {
        $app = new $gateway($this->config);

        if ($app instanceof GatewayApplicationInterface) {
            return $app;
        }

        throw new InvalidGatewayException("Gateway [{$gateway}] Must Be An Instance Of GatewayApplicationInterface");
    }

    /**
     * Register log service.
     */
    protected function registerLogService()
    {
        $config = $this->config->get('log');
        $config['identify'] = 'covercms.pay';

        $logger = new Logger();
        $logger->setConfig($config);

        Log::setInstance($logger);
    }

    /**
     * Register event service.
     */
    protected function registerEventService()
    {
        Events::setDispatcher(Events::createDispatcher());

        Events::addSubscriber(new KernelLogSubscriber());
    }
}
