<?php
/**
 * MSGWAY OTP Auth — WHMCS Addon (standalone, no composer)
 * Finalized package for OTP login/registration via HTTP to MessageWay
 * Coder: Fazel Ghaemi
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

use WHMCS\Database\Capsule;

/**
 * Load local helper classes
 */
require_once __DIR__ . '/includes/MsgwayHttpClient.php';

/**
 * Module config
 */
function msgway_auth_config()
{
    return [
        'name' => 'MSGWAY OTP Auth (راه‌پیام) — Coded by: Fazel Ghaemi',
        'description' => 'Login/Register via SMS OTP using MessageWay API (configurable endpoints).',
        'author' => 'Fazel Ghaemi',
        'version' => '2.0.0',
        'fields' => [
            'api_key' => [
                'FriendlyName' => 'MessageWay API Key',
                'Type' => 'text',
                'Size' => '80',
                'Description' => 'API key provided by MessageWay (or leave blank if you use other auth headers).',
            ],
            'api_key_header' => [
                'FriendlyName' => 'API Key Header Name',
                'Type' => 'text',
                'Size' => '40',
                'Default' => 'x-api-key',
                'Description' => 'Header name to send the API key (e.g. Authorization, x-api-key, apiKey). If using \"Authorization: Bearer <key>\" set this to Authorization and enable bearer_auth below.',
            ],
            'bearer_auth' => [
                'FriendlyName' => 'Bearer auth?',
                'Type' => 'yesno',
                'Description' => 'If enabled, API key will be sent as \"Authorization: Bearer {key}\".',
            ],
            'send_endpoint' => [
                'FriendlyName' => 'Send OTP Endpoint',
                'Type' => 'text',
                'Size' => '80',
                'Default' => 'https://api.msgway.com/v1/sms/send',
                'Description' => 'Full URL to the send-otp endpoint (configurable).',
            ],
            'verify_endpoint' => [
                'FriendlyName' => 'Verify OTP Endpoint',
                'Type' => 'text',
                'Size' => '80',
                'Default' => 'https://api.msgway.com/v1/sms/verify',
                'Description' => 'Full URL to the verify-otp endpoint (configurable).',
            ],
            'country_code' => [
                'FriendlyName' => 'Default Country Code',
                'Type' => 'text',
                'Size' => '8',
                'Default' => '+98',
                'Description' => 'Used to normalize entered mobile numbers to E.164.',
            ],
            'otp_template_id' => [
                'FriendlyName' => 'OTP Template ID',
                'Type' => 'text',
                'Size' => '30',
                'Default' => '',
                'Description' => 'Optional template ID if MessageWay requires a template identifier.',
            ],
            'admin_username' => [
                'FriendlyName' => 'Admin Username (localAPI)',
                'Type' => 'text',
                'Size' => '30',
                'Description' => 'Optional admin username for localAPI calls (CreateSsoToken/AddClient).',
            ],
            'registration_enabled' => [
                'FriendlyName' => 'Enable registration via OTP',
                'Type' => 'yesno',
                'Description' => 'Allow creating new WHMCS clients after OTP verification.',
            ],
            'require_email_register' => [
                'FriendlyName' => 'Require email for registration',
                'Type' => 'yesno',
                'Description' => 'If enabled, registration form requires an email address.',
                'Default' => 'on',
            ],
        ],
    ];
}

/**
 * Activation / Deactivation
 */
function msgway_auth_activate()
{
    try {
        if (!Capsule::schema()->hasTable('mod_msgway_auth_logs')) {
            Capsule::schema()->create('mod_msgway_auth_logs', function ($t) {
                $t->increments('id');
                $t->string('mobile', 64);
                $t->string('mode', 16); // login|register
                $t->string('status', 32); // sent|verified|error
                $t->string('ip', 64)->nullable();
                $t->text('meta')->nullable();
                $t->timestamps();
            });
        }
    } catch (\Exception $e) {
        return ['status' => 'error', 'description' => $e->getMessage()];
    }
    return ['status' => 'success', 'description' => 'MSGWAY OTP Auth activated'];
}

function msgway_auth_deactivate()
{
    return ['status' => 'success', 'description' => 'MSGWAY OTP Auth deactivated'];
}

/**
 * Helper: fetch module setting
 */
function msgway_auth_setting($name)
{
    return Capsule::table('tbladdonmodules')
        ->where('module', 'msgway_auth')
        ->where('setting', $name)
        ->value('value');
}

/**
 * Normalize mobile to E.164 based on default country code (simple)
 */
function msgway_auth_normalize_mobile($raw, $countryCode = '+98')
{
    $digitsFa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
    $digitsEn = ['0','1','2','3','4','5','6','7','8','9'];
    $m = str_replace($digitsFa, $digitsEn, trim($raw));
    $m = preg_replace('/\D+/', '', $m);
    if (strpos($m, '00') === 0) {
        $m = substr($m, 2);
    }
    // remove leading zeros
    $m = ltrim($m, '0');
    $cc = ltrim($countryCode, '+');
    if (strpos($m, $cc) !== 0) {
        $m = $cc . $m;
    }
    return '+' . $m;
}

/**
 * Find client by mobile or email
 */
function msgway_auth_findClientByMobile($normalizedE164, $countryCode = '+98')
{
    $digits = preg_replace('/\D+/', '', $normalizedE164);
    $cc = ltrim($countryCode, '+');
    if (strpos($digits, $cc) === 0) {
        $local = substr($digits, strlen($cc));
    } else {
        $local = $digits;
    }
    $local0 = (strlen($local) >= 9) ? ('0' . ltrim($local, '0')) : ('0' . $local);

    $c = Capsule::table('tblclients')
        ->where('phonenumber', 'like', '%' . $local . '%')
        ->orWhere('phonenumber', 'like', '%' . $local0 . '%')
        ->orWhere('phonenumber', 'like', '%' . $digits . '%')
        ->first();

    return $c ?: null;
}

function msgway_auth_findClientByEmail($email)
{
    return Capsule::table('tblclients')->where('email', $email)->first();
}

function msgway_auth_randomPassword($len = 16)
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%^&*()-_+=';
    $out = '';
    for ($i = 0; $i < $len; $i++) {
        $out .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $out;
}

/**
 * Admin area output
 */
function msgway_auth_output($vars)
{
    echo '<h2>MSGWAY OTP Auth</h2>';
    echo '<p>Coded by: Fazel Ghaemi — version 2.0.0</p>';
    $ok = msgway_auth_setting('send_endpoint') ? true : false;
    echo $ok ? '<div class="successbox">Settings appear configured. Configure API Key & Endpoints in the module settings.</div>'
             : '<div class="errorbox">Module not fully configured. Set API key and endpoints.</div>';
}

/**
 * Client area (main flow)
 */
function msgway_auth_clientarea($vars)
{
    $apiKey = msgway_auth_setting('api_key');
    $apiHeader = msgway_auth_setting('api_key_header') ?: 'x-api-key';
    $bearer = msgway_auth_setting('bearer_auth') ? true : false;
    $sendEndpoint = msgway_auth_setting('send_endpoint') ?: 'https://api.msgway.com/v1/sms/send';
    $verifyEndpoint = msgway_auth_setting('verify_endpoint') ?: 'https://api.msgway.com/v1/sms/verify';
    $countryCode = msgway_auth_setting('country_code') ?: '+98';
    $otpTpl = msgway_auth_setting('otp_template_id') ?: '';
    $registerOn = msgway_auth_setting('registration_enabled') ? true : false;
    $requireEmail = msgway_auth_setting('require_email_register') ? true : false;
    $adminUser = msgway_auth_setting('admin_username') ?: null;

    $errors = [];
    $success = [];
    $stage = 'form';
    $prefill = ['mode' => 'login', 'mobile' => '', 'firstname' => '', 'lastname' => '', 'email' => ''];

    if (!isset($_SESSION)) {
        session_start();
    }
    $sessKey = 'msgway_auth_ctx';

    // instantiate client
    $httpClient = new MsgwayHttpClient([
        'api_key' => $apiKey,
        'api_header' => $apiHeader,
        'bearer' => $bearer,
        'send_endpoint' => $sendEndpoint,
        'verify_endpoint' => $verifyEndpoint,
        'template_id' => $otpTpl,
    ]);

    // handle POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        if ($action === 'send_otp') {
            $mode = ($_POST['mode'] ?? 'login') === 'register' ? 'register' : 'login';
            $mobileRaw = trim((string)($_POST['mobile'] ?? ''));
            $email = trim((string)($_POST['email'] ?? ''));
            $firstname = trim((string)($_POST['firstname'] ?? ''));
            $lastname = trim((string)($_POST['lastname'] ?? ''));

            $prefill = ['mode' => $mode, 'mobile' => $mobileRaw, 'firstname' => $firstname, 'lastname' => $lastname, 'email' => $email];

            if ($mobileRaw === '') {
                $errors[] = 'شماره موبایل را وارد کنید.';
            } else {
                $mobile = msgway_auth_normalize_mobile($mobileRaw, $countryCode);

                if ($mode === 'login') {
                    $clientRow = $email ? msgway_auth_findClientByEmail($email) : msgway_auth_findClientByMobile($mobile, $countryCode);
                    if (!$clientRow) {
                        $errors[] = 'کاربر یافت نشد. برای ورود باید حساب کاربری موجود باشد.';
                    }
                } else { // register
                    if (!$registerOn) {
                        $errors[] = 'ثبت‌نام با OTP غیرفعال است.';
                    }
                    if ($requireEmail && $email === '') {
                        $errors[] = 'ایمیل برای ثبت‌نام اجباری است.';
                    }
                    if ($email !== '' && msgway_auth_findClientByEmail($email)) {
                        $errors[] = 'این ایمیل قبلاً ثبت شده است.';
                    }
                    if ($firstname === '' || $lastname === '') {
                        $errors[] = 'نام و نام خانوادگی را وارد کنید.';
                    }
                }

                if (empty($errors)) {
                    # send via HTTP client
                    $resp = $httpClient->sendOtp($mobile, $otpTpl, ['mode' => $mode]);
                    # log
                    Capsule::table('mod_msgway_auth_logs')->insert([
                        'mobile' => $mobile,
                        'mode' => $mode,
                        'status' => isset($resp['success']) && $resp['success'] ? 'sent' : 'error',
                        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                        'meta' => json_encode($resp, JSON_UNESCAPED_UNICODE),
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);

            
                    if (isset($resp['success']) && $resp['success']) {
                        $_SESSION[$sessKey] = [
                            'mode' => $mode,
                            'mobile' => $mobile,
                            'email' => $email,
                            'firstname' => $firstname,
                            'lastname' => $lastname,
                            'ts' => time(),
                        ];
                        $stage = 'sent';
                        $success[] = 'کد تایید ارسال شد.';
                    } else {
                        $errors[] = 'ارسال کد ناموفق بود: ' . ($resp['message'] ?? 'unknown error');
                    }
                }
            }
        } elseif ($action === 'verify_otp') {
            $otp = trim((string)($_POST['otp'] ?? ''));
            $ctx = $_SESSION[$sessKey] ?? null;
            if (!$ctx) {
                $errors[] = 'جلسه‌ی ارسال کد منقضی شده یا وجود ندارد.';
            } elseif ($otp === '') {
                $errors[] = 'کد تایید را وارد کنید.';
            } else {
                $mobile = $ctx['mobile'];
                $mode = $ctx['mode'];
                $email = $ctx['email'] ?? '';
                $firstname = $ctx['firstname'] ?? '';
                $lastname = $ctx['lastname'] ?? '';

                $verify = $httpClient->verifyOtp($mobile, $otp);
                # log verify response
                Capsule::table('mod_msgway_auth_logs')->insert([
                    'mobile' => $mobile,
                    'mode' => $mode,
                    'status' => (isset($verify['success']) && $verify['success']) ? 'verified' : 'error',
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                    'meta' => json_encode($verify, JSON_UNESCAPED_UNICODE),
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

                if (isset($verify['success']) && $verify['success']) {
                    # login or register
                    if ($mode === 'login') {
                        $row = $email ? msgway_auth_findClientByEmail($email) : msgway_auth_findClientByMobile($mobile, $countryCode);
                        if (!$row) {
                            $errors[] = 'کاربر یافت نشد.';
                        } else {
                            $clientId = (int)$row->id;
                            $token = localAPI('CreateSsoToken', array_filter([
                                'client_id' => $clientId,
                            ]), $adminUser ?: null);

                            if (($token['result'] ?? '') === 'success' && isset($token['redirect_url'])) {
                                header('Location: ' . $token['redirect_url']);
                                exit;
                            } else {
                                $errors[] = 'ایجاد توکن ورود ناموفق بود.';
                            }
                        }
                    } else { # register
                        if (!$registerOn) {
                            $errors[] = 'ثبت‌نام با OTP غیرفعال است.';
                        } else {
                            $password = msgway_auth_randomPassword(20);
                            $post = [
                                'firstname' => $firstname ?: 'User',
                                'lastname' => $lastname ?: 'OTP',
                                'email' => $email ?: ('u' . time() . '@example.invalid'),
                                'phonenumber' => $mobile,
                                'password2' => $password,
                                'skipvalidation' => true,
                                'noemail' => true,
                            ];
                            $add = localAPI('AddClient', $post, $adminUser ?: null);
                            if (($add['result'] ?? '') === 'success') {
                                $clientId = (int)$add['clientid'];
                                $token = localAPI('CreateSsoToken', array_filter([
                                    'client_id' => $clientId,
                                ]), $adminUser ?: null);
                                if (($token['result'] ?? '') === 'success' && isset($token['redirect_url'])) {
                                    header('Location: ' . $token['redirect_url']);
                                    exit;
                                } else {
                                    $errors[] = 'ورود بعد از ثبت‌نام ناموفق بود.';
                                }
                            } else {
                                $errors[] = 'ساخت حساب کاربری موفق نبود: ' . htmlspecialchars((string)($add['message'] ?? ''));
                            }
                        }
                    }
                } else {
                    $errors[] = 'کد تایید نامعتبر است.';
                }
            }
        }
    }

    return [
        'pagetitle' => 'ورود/عضویت با پیامک — راه‌پیام',
        'breadcrumb' => ['index.php?m=msgway_auth' => 'ورود با پیامک'],
        'templatefile' => 'otp',
        'requirelogin' => false,
        'vars' => [
            'errors' => $errors,
            'success' => $success,
            'stage' => $stage,
            'prefill' => $prefill,
            'modulelink' => $vars['modulelink'],
            'brand' => 'MSGWAY | راه‌پیام — Coded by Fazel Ghaemi',
        ],
    ];
}
