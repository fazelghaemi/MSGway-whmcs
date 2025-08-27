<?php
/**
 * MSGWAY WHMCS Addon
 * Coded by: Fazel Ghaemi (فاضل قائمی)
 * License: MIT
 */

namespace Msgway;

use MessageWay\Api\MessageWayAPI;
use ReflectionMethod;
use Exception;

class MsgwayClient
{
    protected string $apiKey;
    protected ?string $countryCode;

    public function __construct(string $apiKey, ?string $countryCode = '+98')
    {
        $this->apiKey = trim($apiKey);
        $this->countryCode = $countryCode ?: '+98';
        if ($this->apiKey === '') {
            throw new Exception('MSGWAY: API key is empty.');
        }
    }

    public static function normalizeMobile(string $raw, string $countryCode = '+98'): string
    {
        $digitsFa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
        $digitsEn = ['0','1','2','3','4','5','6','7','8','9'];
        $m = str_replace($digitsFa, $digitsEn, trim($raw));
        $m = preg_replace('/\D+/', '', $m);
        if (substr($m, 0, 2) === '00') { $m = substr($m, 2); }
        if (substr($m, 0, 1) === '0')  { $m = ltrim($m, '0'); }
        $cc = ltrim($countryCode, '+');
        if (substr($m, 0, strlen($cc)) !== $cc) {
            $m = $cc . $m;
        }
        return '+' . $m;
    }

    public function getClient(): MessageWayAPI
    {
        return new MessageWayAPI($this->apiKey);
    }

    /** ارسال مبتنی بر Template از طریق SMS (با تشخیص خودکار پشتیبانی params) */
    public function sendTemplateSMS(string $mobile, int $templateId, array $params = []): array
    {
        $mw = $this->getClient();

        // اگر نسخه SDK پارامتر سوم را بپذیرد، از آن استفاده می‌کنیم.
        try {
            $rm = new ReflectionMethod($mw, 'sendViaSMS');
            if ($rm->getNumberOfParameters() >= 3) {
                // قرارداد رایج: ['params' => ['v1', 'v2', ...]]
                return $mw->sendViaSMS($mobile, $templateId, ['params' => array_values($params)]);
            }
        } catch (\Throwable $e) {
            // اگر Reflection شکست خورد، می‌رویم سراغ امضای قدیمی.
        }

        // امضای استاندارد بدون پارامتر (طبق README رسمی PHP SDK)
        return $mw->sendViaSMS($mobile, $templateId);
    }

    public function verifyOtp(string $otp, string $mobile): array
    {
        return $this->getClient()->verifyOTP($otp, $mobile);
    }

    public function getTemplate(int $templateId): array
    {
        return $this->getClient()->getTemplate($templateId);
    }

    public function getStatus(string $referenceId): array
    {
        return $this->getClient()->getStatus($referenceId);
    }

    public function getBalance()
    {
        return $this->getClient()->getBalance();
    }
}
