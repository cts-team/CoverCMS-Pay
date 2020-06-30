<?php


namespace CoverCMS\Pay\Gateways\WeChat;


use CoverCMS\Pay\Events;
use CoverCMS\Pay\Exceptions\BusinessException;
use CoverCMS\Pay\Exceptions\GatewayException;
use CoverCMS\Pay\Exceptions\InvalidArgumentException;
use CoverCMS\Pay\Exceptions\InvalidSignException;
use CoverCMS\Pay\Gateways\WeChat;
use CoverCMS\Pay\Log;
use CoverCMS\Support\Collection;
use CoverCMS\Support\Config;
use CoverCMS\Support\Str;
use CoverCMS\Support\Traits\HasHttpRequest;
use Exception;

/**
 * Class Support
 * @package CoverCMS\Pay\Gateways\WeChat
 * @property string appid
 * @property string app_id
 * @property string miniapp_id
 * @property string sub_appid
 * @property string sub_app_id
 * @property string sub_miniapp_id
 * @property string mch_id
 * @property string sub_mch_id
 * @property string key
 * @property string return_url
 * @property string cert_client
 * @property string cert_key
 * @property array log
 * @property array http
 * @property string mode
 */
class Support
{
    use HasHttpRequest;

    /**
     * WeChat gateway.
     *
     * @var string
     */
    protected $baseUri;

    /**
     * Config.
     *
     * @var Config
     */
    protected $config;

    /**
     * Instance.
     *
     * @var Support
     */
    private static $instance;

    /**
     * Support constructor.
     * @param Config $config
     */
    private function __construct(Config $config)
    {
        $this->baseUri = WeChat::URL[$config->get('mode', WeChat::MODE_NORMAL)];
        $this->config = $config;

        $this->setHttpOptions();
    }

    /**
     * @param $key
     * @return mixed|null
     */
    public function __get($key)
    {
        return $this->getConfig($key);
    }

    /**
     * @param Config $config
     * @return Support
     * @throws BusinessException
     * @throws GatewayException
     * @throws InvalidArgumentException
     * @throws InvalidSignException
     */
    public static function create(Config $config)
    {
        if ('cli' === php_sapi_name() || !(self::$instance instanceof self)) {
            self::$instance = new self($config);

            self::setDevKey();
        }

        return self::$instance;
    }

    /**
     * @return Support
     * @throws InvalidArgumentException
     */
    public static function getInstance()
    {
        if (is_null(self::$instance)) {
            throw new InvalidArgumentException('You Should [Create] First Before Using');
        }

        return self::$instance;
    }

    public static function clear()
    {
        self::$instance = null;
    }

    /**
     * Request wechat api.
     *
     * @param string $endpoint
     * @param array $data
     * @param bool $cert
     * @return Collection
     * @throws BusinessException
     * @throws GatewayException
     * @throws InvalidArgumentException
     * @throws InvalidSignException
     */
    public static function requestApi($endpoint, $data, $cert = false): Collection
    {
        Events::dispatch(new Events\ApiRequesting('WeChat', '', self::$instance->getBaseUri() . $endpoint, $data));

        $result = self::$instance->post(
            $endpoint,
            self::toXml($data),
            $cert ? [
                'cert' => self::$instance->cert_client,
                'ssl_key' => self::$instance->cert_key,
            ] : []
        );
        $result = is_array($result) ? $result : self::fromXml($result);

        Events::dispatch(new Events\ApiRequested('WeChat', '', self::$instance->getBaseUri() . $endpoint, $result));

        return self::processingApiResult($endpoint, $result);
    }

    /**
     * Filter payload.
     *
     * @param $payload
     * @param $params
     * @param bool $preserve_notify_url
     * @return array
     * @throws InvalidArgumentException
     */
    public static function filterPayload($payload, $params, $preserve_notify_url = false): array
    {
        $type = self::getTypeName($params['type'] ?? '');

        $payload = array_merge(
            $payload,
            is_array($params) ? $params : ['out_trade_no' => $params]
        );
        $payload['appid'] = self::$instance->getConfig($type, '');

        if (WeChat::MODE_SERVICE === self::$instance->getConfig('mode', WeChat::MODE_NORMAL)) {
            $payload['sub_appid'] = self::$instance->getConfig('sub_' . $type, '');
        }

        unset($payload['trade_type'], $payload['type']);
        if (!$preserve_notify_url) {
            unset($payload['notify_url']);
        }

        $payload['sign'] = self::generateSign($payload);

        return $payload;
    }

    /**
     * Generate wechat sign.
     *
     * @param array $data
     * @return string
     * @throws InvalidArgumentException
     */
    public static function generateSign($data): string
    {
        $key = self::$instance->key;

        if (is_null($key)) {
            throw new InvalidArgumentException('Missing WeChat Config -- [key]');
        }

        ksort($data);

        $string = md5(self::getSignContent($data) . '&key=' . $key);

        Log::debug('WeChat Generate Sign Before UPPER', [$data, $string]);

        return strtoupper($string);
    }

    /**
     * Generate sign content.
     *
     * @param array $data
     * @return string
     */
    public static function getSignContent($data): string
    {
        $buff = '';

        foreach ($data as $k => $v) {
            $buff .= ('sign' != $k && '' != $v && !is_array($v)) ? $k . '=' . $v . '&' : '';
        }

        Log::debug('WeChat Generate Sign Content Before Trim', [$data, $buff]);

        return trim($buff, '&');
    }

    /**
     * Decrypt refund contents.
     *
     * @param string $contents
     * @return string
     */
    public static function decryptRefundContents($contents): string
    {
        return openssl_decrypt(
            base64_decode($contents),
            'AES-256-ECB',
            md5(self::$instance->key),
            OPENSSL_RAW_DATA
        );
    }

    /**
     * Convert array to xml.
     *
     * @param array $data
     * @return string
     * @throws InvalidArgumentException
     */
    public static function toXml($data): string
    {
        if (!is_array($data) || count($data) <= 0) {
            throw new InvalidArgumentException('Convert To Xml Error! Invalid Array!');
        }

        $xml = '<xml>';
        foreach ($data as $key => $val) {
            $xml .= is_numeric($val) ? '<' . $key . '>' . $val . '</' . $key . '>' :
                '<' . $key . '><![CDATA[' . $val . ']]></' . $key . '>';
        }
        $xml .= '</xml>';

        return $xml;
    }

    /**
     * Convert xml to array.
     *
     * @param string $xml
     * @return array
     * @throws InvalidArgumentException
     */
    public static function fromXml($xml): array
    {
        if (!$xml) {
            throw new InvalidArgumentException('Convert To Array Error! Invalid Xml!');
        }

        libxml_disable_entity_loader(true);

        return json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA), JSON_UNESCAPED_UNICODE), true);
    }

    /**
     * Get service config.
     *
     * @param null $key
     * @param null $default
     * @return array|mixed|null
     */
    public function getConfig($key = null, $default = null)
    {
        if (is_null($key)) {
            return $this->config->all();
        }

        if ($this->config->has($key)) {
            return $this->config[$key];
        }

        return $default;
    }

    /**
     * Get app id according to param type.
     *
     * @param string $type
     * @return string
     */
    public static function getTypeName($type = ''): string
    {
        switch ($type) {
            case '':
                $type = 'app_id';
                break;
            case 'app':
                $type = 'appid';
                break;
            default:
                $type = $type . '_id';
        }

        return $type;
    }

    /**
     * Get Base Uri.
     *
     * @return string
     */
    public function getBaseUri()
    {
        return $this->baseUri;
    }

    /**
     * processingApiResult.
     *
     * @param $endpoint
     * @param array $result
     * @return Collection
     * @throws BusinessException
     * @throws GatewayException
     * @throws InvalidArgumentException
     * @throws InvalidSignException
     */
    protected static function processingApiResult($endpoint, array $result)
    {
        if (!isset($result['return_code']) || 'SUCCESS' != $result['return_code']) {
            throw new GatewayException('Get WeChat API Error:' . ($result['return_msg'] ?? $result['retmsg'] ?? ''), $result);
        }

        if (isset($result['result_code']) && 'SUCCESS' != $result['result_code']) {
            throw new BusinessException('WeChat Business Error: ' . $result['err_code'] . ' - ' . $result['err_code_des'], $result);
        }

        if ('pay/getsignkey' === $endpoint ||
            false !== strpos($endpoint, 'mmpaymkttransfers') ||
            self::generateSign($result) === $result['sign']) {
            return new Collection($result);
        }

        Events::dispatch(new Events\SignFailed('WeChat', '', $result));

        throw new InvalidSignException('WeChat Sign Verify FAILED', $result);
    }

    /**
     * @return Support
     * @throws BusinessException
     * @throws GatewayException
     * @throws InvalidArgumentException
     * @throws InvalidSignException
     * @throws Exception
     */
    private static function setDevKey()
    {
        if (WeChat::MODE_DEV == self::$instance->mode) {
            $data = [
                'mch_id' => self::$instance->mch_id,
                'nonce_str' => Str::random(),
            ];
            $data['sign'] = self::generateSign($data);

            $result = self::requestApi('pay/getsignkey', $data);

            self::$instance->config->set('key', $result['sandbox_signkey']);
        }

        return self::$instance;
    }

    /**
     * Set Http options.
     *
     * @return $this
     */
    private function setHttpOptions(): self
    {
        if ($this->config->has('http') && is_array($this->config->get('http'))) {
            $this->config->forget('http.base_uri');
            $this->httpOptions = $this->config->get('http');
        }

        return $this;
    }
}