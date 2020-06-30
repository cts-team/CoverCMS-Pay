<?php


namespace CoverCMS\Pay\Gateways\AliPay;


use CoverCMS\Pay\Events;
use CoverCMS\Pay\Exceptions\GatewayException;
use CoverCMS\Pay\Exceptions\InvalidArgumentException;
use CoverCMS\Pay\Exceptions\InvalidConfigException;
use CoverCMS\Pay\Exceptions\InvalidSignException;
use CoverCMS\Pay\Gateways\AliPay;
use CoverCMS\Pay\Log;
use CoverCMS\Support\Arr;
use CoverCMS\Support\Collection;
use CoverCMS\Support\Config;
use CoverCMS\Support\Str;
use CoverCMS\Support\Traits\HasHttpRequest;
use Exception;

/**
 * Class Support
 * @package CoverCMS\Pay\Gateways\AliPay
 * @property string app_id alipay app_id
 * @property string ali_public_key
 * @property string private_key
 * @property array http http options
 * @property string mode current mode
 * @property array log log options
 * @property string pid ali pid
 */
class Support
{
    use HasHttpRequest;

    /**
     * AliPay gateway.
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
        $this->baseUri = AliPay::URL[$config->get('mode', AliPay::MODE_NORMAL)];
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
     */
    public static function create(Config $config)
    {
        if ('cli' === php_sapi_name() || !(self::$instance instanceof self)) {
            self::$instance = new self($config);
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

    public function clear()
    {
        self::$instance = null;
    }

    /**
     * Get AliPay API result.
     *
     * @param array $data
     * @return Collection
     * @throws GatewayException
     * @throws InvalidConfigException
     * @throws InvalidSignException
     */
    public static function requestApi(array $data): Collection
    {
        Events::dispatch(new Events\ApiRequesting('AliPay', '', self::$instance->getBaseUri(), $data));

        $data = array_filter($data, function ($value) {
            return ('' == $value || is_null($value)) ? false : true;
        });

        $result = json_decode(self::$instance->post('', $data), true);

        Events::dispatch(new Events\ApiRequested('AliPay', '', self::$instance->getBaseUri(), $result));

        return self::processingApiResult($data, $result);
    }

    /**
     * Generate sign.
     *
     * @param array $params
     * @return string
     * @throws InvalidConfigException
     */
    public static function generateSign(array $params): string
    {
        $privateKey = self::$instance->private_key;

        if (is_null($privateKey)) {
            throw new InvalidConfigException('Missing AliPay Config -- [private_key]');
        }

        if (Str::endsWith($privateKey, '.pem')) {
            $privateKey = openssl_pkey_get_private(
                Str::startsWith($privateKey, 'file://') ? $privateKey : 'file://' . $privateKey
            );
        } else {
            $privateKey = "-----BEGIN RSA PRIVATE KEY-----\n" .
                wordwrap($privateKey, 64, "\n", true) .
                "\n-----END RSA PRIVATE KEY-----";
        }

        openssl_sign(self::getSignContent($params), $sign, $privateKey, OPENSSL_ALGO_SHA256);

        $sign = base64_encode($sign);

        Log::debug('AliPay Generate Sign', [$params, $sign]);

        if (is_resource($privateKey)) {
            openssl_free_key($privateKey);
        }

        return $sign;
    }

    /**
     * Verify sign.
     *
     * @param array $data
     * @param bool $sync
     * @param null $sign
     * @return bool
     * @throws InvalidConfigException
     */
    public static function verifySign(array $data, $sync = false, $sign = null): bool
    {
        $publicKey = self::$instance->ali_public_key;

        if (is_null($publicKey)) {
            throw new InvalidConfigException('Missing AliPay Config -- [ali_public_key]');
        }

        if (Str::endsWith($publicKey, '.crt')) {
            $publicKey = file_get_contents($publicKey);
        } elseif (Str::endsWith($publicKey, '.pem')) {
            $publicKey = openssl_pkey_get_public(
                Str::startsWith($publicKey, 'file://') ? $publicKey : 'file://' . $publicKey
            );
        } else {
            $publicKey = "-----BEGIN PUBLIC KEY-----\n" .
                wordwrap($publicKey, 64, "\n", true) .
                "\n-----END PUBLIC KEY-----";
        }

        $sign = $sign ?? $data['sign'];

        $toVerify = $sync ? json_encode($data, JSON_UNESCAPED_UNICODE) : self::getSignContent($data, true);

        $isVerify = 1 === openssl_verify($toVerify, base64_decode($sign), $publicKey, OPENSSL_ALGO_SHA256);

        if (is_resource($publicKey)) {
            openssl_free_key($publicKey);
        }

        return $isVerify;
    }

    /**
     * Get signContent that is to be signed.
     *
     * @param array $data
     * @param bool $verify
     * @return string
     */
    public static function getSignContent(array $data, $verify = false): string
    {
        ksort($data);

        $stringToBeSigned = '';
        foreach ($data as $k => $v) {
            if ($verify && 'sign' != $k && 'sign_type' != $k) {
                $stringToBeSigned .= $k . '=' . $v . '&';
            }
            if (!$verify && '' !== $v && !is_null($v) && 'sign' != $k && '@' != substr($v, 0, 1)) {
                $stringToBeSigned .= $k . '=' . $v . '&';
            }
        }

        Log::debug('AliPay Generate Sign Content Before Trim', [$data, $stringToBeSigned]);

        return trim($stringToBeSigned, '&');
    }

    /**
     * Convert encoding.
     *
     * @param string|array $data
     * @param string $to
     * @param string $from
     * @return array
     */
    public static function encoding($data, $to = 'utf-8', $from = 'gb2312'): array
    {
        return Arr::encoding((array)$data, $to, $from);
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
     * Get Base Uri.
     *
     * @return string
     */
    public function getBaseUri()
    {
        return $this->baseUri;
    }

    /**
     * 生成应用证书SN.
     *
     * @param $certPath
     * @return string
     * @throws Exception
     * @author 大冰 https://sbing.vip/archives/2019-new-alipay-php-docking.html
     */
    public static function getCertSN($certPath): string
    {
        if (!is_file($certPath)) {
            throw new Exception('unknown certPath -- [getCertSN]');
        }
        $x509data = file_get_contents($certPath);
        if (false === $x509data) {
            throw new Exception('AliPay CertSN Error -- [getCertSN]');
        }
        openssl_x509_read($x509data);
        $certdata = openssl_x509_parse($x509data);
        if (empty($certdata)) {
            throw new Exception('AliPay openssl_x509_parse Error -- [getCertSN]');
        }
        $issuer_arr = [];
        foreach ($certdata['issuer'] as $key => $val) {
            $issuer_arr[] = $key . '=' . $val;
        }
        $issuer = implode(',', array_reverse($issuer_arr));
        Log::debug('getCertSN:', [$certPath, $issuer, $certdata['serialNumber']]);

        return md5($issuer . $certdata['serialNumber']);
    }

    /**
     * 生成支付宝根证书SN.
     *
     * @param $certPath
     * @return string
     * @throws Exception
     * @author 大冰 https://sbing.vip/archives/2019-new-alipay-php-docking.html
     */
    public static function getRootCertSN($certPath)
    {
        if (!is_file($certPath)) {
            throw new Exception('unknown certPath -- [getRootCertSN]');
        }
        $x509data = file_get_contents($certPath);
        if (false === $x509data) {
            throw new Exception('AliPay CertSN Error -- [getRootCertSN]');
        }
        $kCertificateEnd = '-----END CERTIFICATE-----';
        $certStrList = explode($kCertificateEnd, $x509data);
        $md5_arr = [];
        foreach ($certStrList as $one) {
            if (!empty(trim($one))) {
                $_x509data = $one . $kCertificateEnd;
                openssl_x509_read($_x509data);
                $_certdata = openssl_x509_parse($_x509data);
                if (in_array($_certdata['signatureTypeSN'], ['RSA-SHA256', 'RSA-SHA1'])) {
                    $issuer_arr = [];
                    foreach ($_certdata['issuer'] as $key => $val) {
                        $issuer_arr[] = $key . '=' . $val;
                    }
                    $_issuer = implode(',', array_reverse($issuer_arr));
                    if (0 === strpos($_certdata['serialNumber'], '0x')) {
                        $serialNumber = self::bchexdec($_certdata['serialNumber']);
                    } else {
                        $serialNumber = $_certdata['serialNumber'];
                    }
                    $md5_arr[] = md5($_issuer . $serialNumber);
                    Log::debug('getRootCertSN Sub:', [$certPath, $_issuer, $serialNumber]);
                }
            }
        }

        return implode('_', $md5_arr);
    }

    /**
     * processingApiResult.
     *
     * @param $data
     * @param $result
     * @return Collection
     * @throws GatewayException
     * @throws InvalidConfigException
     * @throws InvalidSignException
     */
    protected static function processingApiResult($data, $result): Collection
    {
        $method = str_replace('.', '_', $data['method']) . '_response';

        if (!isset($result['sign']) || '10000' != $result[$method]['code']) {
            throw new GatewayException('Get AliPay API Error:' . $result[$method]['msg'] . (isset($result[$method]['sub_code']) ? (' - ' . $result[$method]['sub_code']) : ''), $result);
        }

        if (self::verifySign($result[$method], true, $result['sign'])) {
            return new Collection($result[$method]);
        }

        Events::dispatch(new Events\SignFailed('AliPay', '', $result));

        throw new InvalidSignException('AliPay Sign Verify FAILED', $result);
    }

    /**
     * Set Http options.
     *
     * @return $this
     */
    protected function setHttpOptions(): self
    {
        if ($this->config->has('http') && is_array($this->config->get('http'))) {
            $this->config->forget('http.base_uri');
            $this->httpOptions = $this->config->get('http');
        }

        return $this;
    }

    /**
     * 0x转高精度数字.
     *
     * @param $hex
     * @return int|string
     * @author 大冰 https://sbing.vip/archives/2019-new-alipay-php-docking.html
     */
    private static function bchexdec($hex)
    {
        $dec = 0;
        $len = strlen($hex);
        for ($i = 1; $i <= $len; ++$i) {
            if (ctype_xdigit($hex[$i - 1])) {
                $dec = bcadd($dec, bcmul(strval(hexdec($hex[$i - 1])), bcpow('16', strval($len - $i))));
            }
        }

        return str_replace('.00', '', $dec);
    }
}
