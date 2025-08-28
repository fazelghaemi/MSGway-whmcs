<?php

namespace MessageWay\Api;

use Exception;
use InvalidArgumentException;

/**
 * MessageWay API
 */
class MessageWayAPI
{
	protected const
		VERSION = '2.0.0',
		BASEURL = 'https://api.msgway.com',
		ACCEPT_LANGUAGE = 'fa',

		ENDPOINT_SEND = "/send",
		ENDPOINT_STATUS = "/status",
		ENDPOINT_VERIFY = "/otp/verify",
		ENDPOINT_TEMPLATE_GET = "/template/get",
		ENDPOINT_BALANCE_GET = "/balance/get",

        PROVIDERS = [
            // MESSENGER_PROVIDERS
            'whatsapp' => 1,'gap' => 2 , 'igap' => 8,
            // SMS_PROVIDERS
            '3000x' => 1 , '2000x' => 2 , '9000x' => 3 , '5000x' => 4 , '50004x' => 5,
            // IVR_PROVIDERS
            'ivr' => 1
        ];

	/**
	 * @var array
	 */
	protected array $config = [];
	/**
	 * @var string
	 */
	protected string $acceptLanguage = 'fa';
	/**
	 * @var string
	 */
	protected string $apiKey = '';

	/**
	 * @var string
	 */
	protected string $endpoint;
	/**
	 * @var string
	 */
	protected string $requestParams;
	/**
	 * @var array
	 */
	protected array $requestHeaders;

	/**
	 * @var array
	 */
	protected array $response;
	/**
	 * @var int
	 */
	protected int $httpCode;

	/**
	 * @param string $apiKey
	 */
	public function __construct(string $apiKey = '')
	{
		if (isset($apiKey)) {
			$this->setApiKey($apiKey);
		}
		$this->setAcceptLanguage(self::ACCEPT_LANGUAGE);
	}

	/**
	 * @param string $apiKey
	 * @return $this
	 */
	public function setApiKey(string $apiKey): MessageWayAPI
	{
		$this->apiKey = $apiKey;
		return $this;
	}

	/**
	 * @param string $acceptLanguage
	 * @return $this
	 */
	public function setAcceptLanguage(string $acceptLanguage): MessageWayAPI
	{
		$this->acceptLanguage = $acceptLanguage;
		return $this;
	}

	/**
	 * @param string $method
	 * @return $this
	 */
	public function setMethod(string $method): MessageWayAPI
	{
		$this->config['method'] = $method;
		return $this;
	}

	/**
	 * @param int $provider
	 * @return $this
	 */
	public function setProvider(int $provider): MessageWayAPI
	{
        if($provider != 0){
            $this->config['provider'] = $provider;
        }
		return $this;
	}

	/**
	 * @param string $mobile
	 * @return $this
	 */
	public function setMobile(string $mobile): MessageWayAPI
	{
		$this->config['mobile'] = $mobile;
		return $this;
	}

	/**
	 * @param int $templateID
	 * @return $this
	 */
	public function setTemplateID(int $templateID): MessageWayAPI
	{
		$this->config['templateID'] = $templateID;
		return $this;
	}

	/**
	 * @param int $countryCode
	 * @return $this
	 */
	public function setCountryCode(int $countryCode): MessageWayAPI
	{
		$this->config['countryCode'] = $countryCode;
		return $this;
	}

	/**
	 * @param int $length
	 * @return $this
	 */
	public function setLength(int $length): MessageWayAPI
	{
		$this->config['length'] = $length;
		return $this;
	}

	/**
	 * @param string $code
	 * @return $this
	 */
	public function setCode(string $code): MessageWayAPI
	{
		$this->config['code'] = $code;
		return $this;
	}

	/**
	 * @param array $params
	 * @return $this
	 */
	public function setParams(array $params): MessageWayAPI
	{
		$this->config['params'] = $params;
		return $this;
	}

	/**
	 * @param string $param1
	 * @return $this
	 * @deprecated
	 */
	public function setParam1(string $param1): MessageWayAPI
	{
		$this->config['params'][0] = $param1;
		return $this;
	}

	/**
	 * @param string $param2
	 * @return $this
	 * @deprecated
	 */
	public function setParam2(string $param2): MessageWayAPI
	{
		$this->config['params'][1] = $param2;
		return $this;
	}

	/**
	 * @param string $param3
	 * @return $this
	 * @deprecated
	 */
	public function setParam3(string $param3): MessageWayAPI
	{
		$this->config['params'][2] = $param3;
		return $this;
	}

	/**
	 * @param string $param4
	 * @return $this
	 * @deprecated
	 */
	public function setParam4(string $param4): MessageWayAPI
	{
		$this->config['params'][3] = $param4;
		return $this;
	}

	/**
	 * @param string $param5
	 * @return $this
	 * @deprecated
	 */
	public function setParam5(string $param5): MessageWayAPI
	{
		$this->config['params'][4] = $param5;
		return $this;
	}

	/**
	 * @param int $OTPExpireTime
	 * @return $this
	 */
	public function setExpireTime(int $OTPExpireTime): MessageWayAPI
	{
		$this->config['expireTime'] = $OTPExpireTime;
		return $this;
	}

	/**
	 * @param string $OTPReferenceID
	 * @return $this
	 */
	public function setOTPReferenceID(string $OTPReferenceID): MessageWayAPI
	{
		$this->config['OTPReferenceID'] = $OTPReferenceID;
		return $this;
	}

	/**
	 * @param string $OTP
	 * @return $this
	 */
	public function setOTP(string $OTP): MessageWayAPI
	{
		$this->config['OTP'] = $OTP;
		return $this;
	}

	/**
	 * @param $configs
	 * @return $this
	 */
	public function setConfig($configs): MessageWayAPI
	{
		foreach ($configs as $key => $value) {
			$param = 'set' . ucfirst($key);
			if (!method_exists($this, $param)) {
				throw new InvalidArgumentException("Param [$key] is not supported.");
			}
			$this->{$param}($value);
		}
		return $this;
	}

	/**
	 * @param string $method
	 * @param int $provider
	 * @return array
	 * @throws Exception
	 */
	public function send(string $method = '', int $provider = 0): array
	{
		if (!empty($method)) {
			$this->setMethod($method);
		}
		if (!empty($provider)) {
			$this->setProvider($provider);
		}
		$required = ['method', 'mobile', 'templateID'];
		$optional = ['countryCode', 'provider', 'length', 'code', 'params', 'expireTime'];
		return $this->setEndpoint(self::ENDPOINT_SEND)->build($required, $optional)->sendRequest();
	}

	/**
	 * @param string $OTPReferenceID
	 * @return array
	 * @throws Exception
	 */
	public function getStatus(string $OTPReferenceID = ''): array
	{
		if (!empty($OTPReferenceID)) {
			$this->setOTPReferenceID($OTPReferenceID);
		}
		$required = ['OTPReferenceID'];
		return $this->setEndpoint(self::ENDPOINT_STATUS)->build($required)->sendRequest();
	}

	/**
	 * @param string $OTP
	 * @param string $mobile
	 * @return array
	 * @throws Exception
	 */
	public function verifyOTP(string $OTP = '', string $mobile = ''): array
	{
		if (!empty($OTP)) {
			$this->setOTP($OTP);
		}
		if (!empty($mobile)) {
			$this->setMobile($mobile);
		}
		$required = ['OTP', 'mobile'];
		$optional = ['countryCode'];
		return $this->setEndpoint(self::ENDPOINT_VERIFY)->build($required, $optional)->sendRequest();
	}

	/**
	 * @return array
	 */
	public function getResponse(): array
	{
		return $this->response;
	}

	/**
	 * @return int
	 */
	public function getHttpCode(): int
	{
		return $this->httpCode;
	}

	/**
	 * @return string
	 */
	public function getVersion(): string
	{
		return self::VERSION;
	}

	/**
	 * @param string $mobile
	 * @param int $templateID
	 * @param int $provider
	 * @param array $options
	 * @return array
	 * @throws Exception
	 */
	public function sendViaSMS(string $mobile, int $templateID, int $provider = 0, array $options = []): array
	{
		return $this->setConfig($options)
			->setMethod('sms')
			->setMobile($mobile)
			->setProvider($provider)
			->setTemplateID($templateID)
			->send();
	}

	/**
	 * @param string $mobile
	 * @param int $templateID
	 * @param int $provider
	 * @param array $options
	 * @return array
	 * @throws Exception
	 */
	public function sendViaMessenger(string $mobile, int $templateID, int $provider = 0, array $options = []): array
	{
		return $this->setConfig($options)
			->setMethod('messenger')
			->setMobile($mobile)
			->setProvider($provider)
			->setTemplateID($templateID)
			->send();
	}

	/**
	 * @param string $mobile
	 * @param int $templateID
	 * @param int $provider
	 * @param array $options
	 * @return array
	 * @throws Exception
	 */
	public function sendViaIVR(string $mobile, int $templateID, int $provider = 0, array $options = []): array
	{
		return $this->setConfig($options)
			->setMethod('ivr')
			->setMobile($mobile)
			->setProvider($provider)
			->setTemplateID($templateID)
			->send();
	}

	/**
	 * @param string $mobile
	 * @param int $templateID
	 * @param array $options
	 * @return array
	 * @throws Exception
	 */
	public function sendViaSmart(string $mobile, int $templateID, array $options = []): array
	{
		return $this->setConfig($options)
			->setMethod('smart')
			->setMobile($mobile)
			->setTemplateID($templateID)
			->send();
	}

	/**
	 * @param int $templateID
	 * @return array
	 * @throws Exception
	 */
	public function getTemplate(int $templateID): array
	{
		$result = $this->setTemplateID($templateID)
			->setEndpoint(self::ENDPOINT_TEMPLATE_GET)
			->build(['templateID'])
			->sendRequest();
		$patternMessage = $result['data']['template'] ?? [];
		$patternParams = $matches = [];
		preg_match_all('/\[([A-Za-z0-9 ]+?)\]/', $patternMessage, $matches);
		foreach (current($matches) as $k => $param) {
			$patternParams[$k] = str_replace(['[', ']'], '', $param);
		}
		return [
			'template' => $patternMessage,
			'params' => $patternParams
		];
	}

    /**
     * @return int
     * @throws Exception
     */
    public function getBalance(): int
    {
        $result = $this->setEndpoint(self::ENDPOINT_BALANCE_GET)
            ->build([])
            ->sendRequest();
        return $result['data']['balance'] ?? 0;
    }

    /**
     * @param string $provider
     * @return int
     * @throws Exception
     */
    protected function getProviderByName(string $provider): int
    {
        if (empty($providerID = self::PROVIDERS[$provider])) {
            throw new Exception("provider `{$provider}` is invalid");
        }
        return $providerID;
    }

	/**
	 * @param string $endpoint
	 * @return MessageWayAPI
	 */
	protected function setEndpoint(string $endpoint): MessageWayAPI
	{
		$this->endpoint = self::BASEURL . $endpoint;
		return $this;
	}

	/**
	 * @param array $requiredFields
	 * @param array $optionalFields
	 * @return $this
	 */
	protected function build(array $requiredFields, array $optionalFields = []): MessageWayAPI
	{
		if (empty($this->apiKey)) {
			throw new InvalidArgumentException("Please set `apiKey`");
		}
		if (!empty($this->config['params'])) {
			ksort($this->config['params']);
			array_walk($this->config['params'], function (&$param) {
				$param = (string)$param;
			});
		}
		$params = [];
		foreach ($requiredFields as $field) {
			if (empty($this->config[$field])) {
				throw new InvalidArgumentException("Please set `$field`");
			}
			$params[$field] = $this->config[$field];
		}
		foreach ($optionalFields as $field) {
			if (!empty($this->config[$field])) {
				$params[$field] = $this->config[$field];
			}
		}
		if(!empty($params)){
			$this->requestParams = json_encode($params);
		}
		$this->requestHeaders = [
			"Content-Type: application/json",
			"accept-language: $this->acceptLanguage",
			"apiKey: $this->apiKey",
		];
		return $this;
	}

	/**
	 * @return array
	 * @throws Exception
	 */
	protected function sendRequest(): array
	{
		$curl = curl_init();
		$options = [
			CURLOPT_URL => $this->endpoint,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'POST',
			CURLOPT_HTTPHEADER => $this->requestHeaders,
		];
		if(!empty($this->requestParams)){
			$options[CURLOPT_POSTFIELDS] = $this->requestParams;
		}
		curl_setopt_array($curl, $options);
		$response = curl_exec($curl);
		if ($response === false) {
			throw new Exception(curl_error($curl));
		}
		$this->httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);
		$this->response = json_decode($response, true);
		if (json_last_error() !== JSON_ERROR_NONE) {
			throw new Exception('incorrect response: ' . $response);
		}
		if ($this->response['status'] == 'error') {
			$message = $this->response['error']['message'] ?? "an error was encountered";
			$code = $this->response['error']['code'] ?? 0;
			throw new Exception($message, $code);
		}
		return $this->response ?? [];
	}
}