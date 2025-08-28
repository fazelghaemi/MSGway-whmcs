<?php
/**
 * MsgwayHttpClient — minimal HTTP client to call MessageWay endpoints via cURL
 * This file has no external dependencies (no Composer).
 */

class MsgwayHttpClient
{
    protected $apiKey;
    protected $apiHeader;
    protected $bearer;
    protected $sendEndpoint;
    protected $verifyEndpoint;
    protected $templateId;

    public function __construct($opts = [])
    {
        $this->apiKey = $opts['api_key'] ?? '';
        $this->apiHeader = $opts['api_header'] ?? 'x-api-key';
        $this->bearer = !empty($opts['bearer']);
        $this->sendEndpoint = $opts['send_endpoint'] ?? '';
        $this->verifyEndpoint = $opts['verify_endpoint'] ?? '';
        $this->templateId = $opts['template_id'] ?? '';
    }

    protected function buildHeaders()
    {
        $h = [
            'Accept: application/json',
            'Content-Type: application/json',
        ];
        if ($this->apiKey !== '') {
            if ($this->bearer && (strtolower($this->apiHeader) === 'authorization')) {
                $h[] = 'Authorization: Bearer ' . $this->apiKey;
            } else {
                $h[] = $this->apiHeader . ': ' . $this->apiKey;
            }
        }
        return $h;
    }

    protected function httpPost($url, $payload, $timeout = 10)
    {
        if (!function_exists('curl_init')) {
            return ['success' => false, 'message' => 'cURL extension not available'];
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->buildHeaders());
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        // For production you should verify peer; keep default true.
        $resp = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($resp === false) {
            return ['success' => false, 'message' => 'cURL error: ' . $err, 'http_code' => $code];
        }
        $decoded = json_decode($resp, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['success' => ($code >= 200 && $code < 300), 'message' => 'Non-JSON response', 'http_code' => $code, 'body' => $resp];
        }
        return array_merge(['success' => ($code >= 200 && $code < 300), 'http_code' => $code], (array)$decoded);
    }

    public function sendOtp($mobile, $templateId = '', $params = [])
    {
        $url = $this->sendEndpoint;
        if (!$url) {
            return ['success' => false, 'message' => 'Send endpoint not configured'];
        }
        $tpl = $templateId ?: $this->templateId;
        $payload = [
            'mobile' => $mobile,
        ];
        if ($tpl) {
            $payload['template_id'] = $tpl;
        }
        if (!empty($params)) {
            $payload['params'] = $params;
        }
        if (!isset($payload['to'])) {
            $payload['to'] = $mobile;
        }
        if (!isset($payload['templateId']) && isset($payload['template_id'])) {
            $payload['templateId'] = $payload['template_id'];
        }

        return $this->httpPost($url, $payload);
    }

    public function verifyOtp($mobile, $otp)
    {
        $url = $this->verifyEndpoint;
    
        $url = $this->verifyEndpoint;
        if (!$url) {
            return ['success' => false, 'message' => 'Verify endpoint not configured'];
        }
        $payload = [
            'mobile' => $mobile,
            'otp' => $otp,
            'to' => $mobile,
            'code' => $otp,
        ];
        return $this->httpPost($url, $payload);
    }
}
