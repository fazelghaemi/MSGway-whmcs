<?php
/**
 * MSGWAY WHMCS Addon Hooks
 * کدنویسی: فاضل قائمی
 */

if (!defined('WHMCS')) die('This file cannot be accessed directly');

use WHMCS\Database\Capsule;
use Msgway\MsgwayClient;

$autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

/** Helper: خواندن تنظیمات افزونه */
function msgway_getSetting($name)
{
    return Capsule::table('tbladdonmodules')->where('module', 'msgway')->where('setting', $name)->value('value');
}

/** Helper: ریت‌لیمیت ساده در دقیقه */
function msgway_canSendNow(int $limitPerMinute): bool
{
    if ($limitPerMinute <= 0) return true;
    $since = date('Y-m-d H:i:s', time() - 60);
    $count = Capsule::table('mod_msgway_logs')
        ->where('created_at', '>=', $since)
        ->where('status', 'sent')
        ->count();
    return $count < $limitPerMinute;
}

/** Helper: resolve mobile از $vars → tblclients */
function msgway_resolveClientMobile(array $vars): ?array
{
    // تلاش برای پیدا کردن userid
    $uid = $vars['userid'] ?? $vars['userId'] ?? $vars['clientid'] ?? $vars['clientId'] ?? null;
    if (!$uid && isset($vars['user']) && is_array($vars['user'])) {
        $uid = $vars['user']['id'] ?? null;
    }

    if ($uid) {
        $client = Capsule::table('tblclients')->where('id', $uid)->first();
        if ($client && !empty($client->phonenumber)) {
            return ['mobile' => $client->phonenumber, 'client' => $client];
        }
    }

    // تلاش بر اساس ایمیل
    $email = $vars['email'] ?? $vars['clientEmail'] ?? null;
    if ($email) {
        $client = Capsule::table('tblclients')->where('email', $email)->first();
        if ($client && !empty($client->phonenumber)) {
            return ['mobile' => $client->phonenumber, 'client' => $client];
        }
    }

    return null;
}

/** Helper: برساخت params (اختیاری) با نگاه به getTemplate */
function msgway_buildParamsForTemplate(MsgwayClient $client, int $templateId, array $vars, $clientRow): array
{
    try {
        $tpl = $client->getTemplate($templateId); // ['template' => '...', 'params' => ['name','invoiceid',...]]
        $keys = $tpl['params'] ?? [];
        $out  = [];
        foreach ($keys as $k) {
            $k = (string)$k;
            $val = null;
            // اولویت: از $vars → سپس از client → سپس تلاش‌های خاص
            if (isset($vars[$k])) {
                $val = $vars[$k];
            } elseif ($clientRow && isset($clientRow->$k)) {
                $val = $clientRow->$k;
            } else {
                // چند نگاشت رایج
                switch ($k) {
                    case 'name':
                        $val = trim(($clientRow->firstname ?? '').' '.($clientRow->lastname ?? ''));
                        break;
                    case 'invoiceid':
                        $val = $vars['invoiceid'] ?? $vars['invoiceId'] ?? null;
                        break;
                    case 'amount':
                        $val = $vars['amount'] ?? $vars['total'] ?? null;
                        break;
                    case 'ticketid':
                        $val = $vars['ticketid'] ?? $vars['ticketId'] ?? null;
                        break;
                    default:
                        $val = $vars[$k] ?? null;
                }
            }
            $out[$k] = (string)($val ?? '');
        }
        return $out;
    } catch (\Throwable $e) {
        // اگر نتوانستیم params بخوانیم، بدون params ادامه می‌دهیم
        return [];
    }
}

/** ارسال و لاگ با رعایت ریت‌لیمیت/صف */
function msgway_send_and_log_dynamic(string $hook, string $rawMobile, int $templateId, array $payload = [], array $params = []): void
{
    $apiKey      = msgway_getSetting('api_key');
    $countryCode = msgway_getSetting('country_code') ?: '+98';
    $rateLimit   = (int)(msgway_getSetting('rate_limit') ?: 60);
    $enableQueue = (int)(msgway_getSetting('enable_queue') ?: 0);

    if (!$apiKey || !$templateId) return;

    try {
        $mobile = MsgwayClient::normalizeMobile($rawMobile, $countryCode);
        $client = new MsgwayClient($apiKey, $countryCode);

        if (!msgway_canSendNow($rateLimit) && $enableQueue) {
            // ورود به صف
            Capsule::table('mod_msgway_queue')->insert([
                'hook'           => $hook,
                'mobile'         => $mobile,
                'template_id'    => (string)$templateId,
                'params_json'    => json_encode(array_values($params), JSON_UNESCAPED_UNICODE),
                'payload'        => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'attempts'       => 0,
                'next_attempt_at'=> date('Y-m-d H:i:s'),
                'created_at'     => date('Y-m-d H:i:s'),
                'updated_at'     => date('Y-m-d H:i:s'),
            ]);
            Capsule::table('mod_msgway_logs')->insert([
                'hook'        => $hook,
                'mobile'      => $mobile,
                'template_id' => (string)$templateId,
                'status'      => 'queued',
                'reference'   => null,
                'payload'     => json_encode(['queued'=>true] + $payload, JSON_UNESCAPED_UNICODE),
                'created_at'  => date('Y-m-d H:i:s'),
                'updated_at'  => date('Y-m-d H:i:s'),
            ]);
            return;
        }

        $resp = $client->sendTemplateSMS($mobile, $templateId, $params);

        Capsule::table('mod_msgway_logs')->insert([
            'hook'        => $hook,
            'mobile'      => $mobile,
            'template_id' => (string)$templateId,
            'status'      => 'sent',
            'reference'   => is_array($resp) && isset($resp['referenceID']) ? $resp['referenceID'] : null,
            'payload'     => json_encode($payload + (empty($params) ? [] : ['params'=>$params]), JSON_UNESCAPED_UNICODE),
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);
    } catch (\Throwable $e) {
        Capsule::table('mod_msgway_logs')->insert([
            'hook'        => $hook,
            'mobile'      => $rawMobile,
            'template_id' => (string)$templateId,
            'status'      => 'error',
            'reference'   => null,
            'payload'     => json_encode(['error'=>$e->getMessage()] + $payload, JSON_UNESCAPED_UNICODE),
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);
        logActivity('MSGWAY ['.$hook.'] error: ' . $e->getMessage());
    }
}

/** رجیستر داینامیک: برای هر هوک فعال از دیتابیس، یک add_hook بساز */
try {
    $rows = Capsule::table('mod_msgway_templates')->where('enabled', 1)->get();
    foreach ($rows as $row) {
        $hookName   = (string)$row->hook;
        $templateId = (int)$row->template_id;
        if ($hookName && $templateId > 0) {
            add_hook($hookName, 1, function($vars) use ($hookName, $templateId) {
                $resolved = msgway_resolveClientMobile($vars);
                if (!$resolved) {
                    // اگر موبایل پیدا نشد، لاگِ skipped
                    Capsule::table('mod_msgway_logs')->insert([
                        'hook'        => $hookName,
                        'mobile'      => '',
                        'template_id' => (string)$templateId,
                        'status'      => 'skipped',
                        'reference'   => null,
                        'payload'     => json_encode(['reason'=>'no-mobile','vars'=>array_keys($vars)], JSON_UNESCAPED_UNICODE),
                        'created_at'  => date('Y-m-d H:i:s'),
                        'updated_at'  => date('Y-m-d H:i:s'),
                    ]);
                    return;
                }
                $apiKey      = msgway_getSetting('api_key');
                $countryCode = msgway_getSetting('country_code') ?: '+98';
                $client      = new MsgwayClient($apiKey, $countryCode);
                $params      = msgway_buildParamsForTemplate($client, $templateId, $vars, $resolved['client'] ?? null);

                msgway_send_and_log_dynamic(
                    $hookName,
                    $resolved['mobile'],
                    $templateId,
                    ['hook_vars' => $vars],
                    $params
                );
            });
        }
    }
} catch (\Throwable $e) {
    // اگر جدول هنوز ساخته نشده باشد (قبل از Activate)، سکوت می‌کنیم.
}

/** پردازش صف با کران: AfterCronJob */
add_hook('AfterCronJob', 1, function() {
    $apiKey      = msgway_getSetting('api_key');
    $countryCode = msgway_getSetting('country_code') ?: '+98';
    $rateLimit   = (int)(msgway_getSetting('rate_limit') ?: 60);

    if (!$apiKey) return;

    try {
        $client = new MsgwayClient($apiKey, $countryCode);

        // محدود: حداکثر 200 آیتم در هر ران برای جلوگیری از فشار
        $now = date('Y-m-d H:i:s');
        $batch = Capsule::table('mod_msgway_queue')
            ->where(function($q) use ($now) {
                $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', $now);
            })
            ->orderBy('id','asc')->limit(200)->get();

        foreach ($batch as $job) {
            if (!msgway_canSendNow($rateLimit)) break;

            $mobile = MsgwayClient::normalizeMobile($job->mobile, $countryCode);
            $params = json_decode($job->params_json ?? '[]', true) ?: [];
            $templateId = (int)$job->template_id;

            try {
                $resp = $client->sendTemplateSMS($mobile, $templateId, $params);

                Capsule::table('mod_msgway_logs')->insert([
                    'hook'        => $job->hook,
                    'mobile'      => $mobile,
                    'template_id' => (string)$templateId,
                    'status'      => 'sent',
                    'reference'   => is_array($resp) && isset($resp['referenceID']) ? $resp['referenceID'] : null,
                    'payload'     => $job->payload,
                    'created_at'  => date('Y-m-d H:i:s'),
                    'updated_at'  => date('Y-m-d H:i:s'),
                ]);

                Capsule::table('mod_msgway_queue')->where('id', $job->id)->delete();
            } catch (\Throwable $e) {
                $attempts = (int)$job->attempts + 1;
                $delayMin = min(30, 2 ** min($attempts, 5)); // 2,4,8,16,32 → capped at 30
                $next = date('Y-m-d H:i:s', time() + ($delayMin * 60));

                Capsule::table('mod_msgway_queue')->where('id', $job->id)->update([
                    'attempts'       => $attempts,
                    'next_attempt_at'=> $next,
                    'updated_at'     => date('Y-m-d H:i:s'),
                ]);

                Capsule::table('mod_msgway_logs')->insert([
                    'hook'        => $job->hook,
                    'mobile'      => $mobile,
                    'template_id' => (string)$templateId,
                    'status'      => 'error',
                    'reference'   => null,
                    'payload'     => json_encode(['queue_error' => $e->getMessage()], JSON_UNESCAPED_UNICODE),
                    'created_at'  => date('Y-m-d H:i:s'),
                    'updated_at'  => date('Y-m-d H:i:s'),
                ]);
            }
        }
    } catch (\Throwable $e) {
        logActivity('MSGWAY queue processor error: '.$e->getMessage());
    }
});
