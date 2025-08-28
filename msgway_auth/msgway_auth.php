<?php
/**
 * MSGWAY (راه‌پیام) — OTP Login & Register for WHMCS
 * کدنویسی: فاضل قائمی | Fazel Ghaemi
 * نسخه: 1.0.1
 */

if (!defined('WHMCS')) { die('This file cannot be accessed directly'); }

use WHMCS\Database\Capsule;

/**
 * Lazy-load Composer autoload if present. Avoid fatal if vendor/ is missing.
 */
function msgway_auth_load_sdk(): bool
{
    static $loaded = null;
    if ($loaded !== null) return $loaded;
    $autoload = __DIR__ . '/vendor/autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
        $loaded = true;
    } else {
        $loaded = false;
    }
    return $loaded;
}

/** =====================[ config ]===================== */
function msgway_auth_config()
{
    return [
        'name'        => 'MSGWAY OTP Auth (راه‌پیام) — by Fazel Ghaemi',
        'description' => 'ورود/عضویت کاربران WHMCS با OTP راه‌پیام و SSO امن',
        'author'      => 'Fazel Ghaemi',
        'version'     => '1.0.1',
        'fields'      => [
            'api_key' => [
                'FriendlyName' => 'MSGWAY API Key',
                'Type' => 'text',
                'Size' => '80',
                'Description' => 'کلید API از داشبورد MSGWAY',
            ],
            'country_code' => [
                'FriendlyName' => 'Country Code',
                'Type' => 'text',
                'Size' => '8',
                'Default' => '+98',
                'Description' => 'برای نرمال‌سازی شماره‌ها (E.164)',
            ],
            'otp_template_id' => [
                'FriendlyName' => 'OTP Template ID',
                'Type' => 'text',
                'Size' => '10',
                'Default' => '1',
                'Description' => 'Template ID مخصوص OTP در MSGWAY',
            ],
            'sso_destination' => [
                'FriendlyName' => 'SSO Destination',
                'Type' => 'text',
                'Size' => '40',
                'Default' => 'clientarea:home',
                'Description' => 'مقصد پس از ورود (مثلاً clientarea:home / clientarea:services / sso:custom_redirect)',
            ],
            'sso_redirect_path' => [
                'FriendlyName' => 'Custom Redirect Path',
                'Type' => 'text',
                'Size' => '60',
                'Description' => 'فقط اگر destination برابر sso:custom_redirect است، مسیر نسبی مثل cart.php?a=checkout',
            ],
            'admin_username' => [
                'FriendlyName' => 'Admin Username (localAPI)',
                'Type' => 'text',
                'Size' => '30',
                'Description' => 'اختیاری؛ از WHMCS 7.2 به بعد لازم نیست، ولی توصیه می‌شود.',
            ],
            'registration_enabled' => [
                'FriendlyName' => 'فعال‌سازی ثبت‌نام با OTP',
                'Type' => 'yesno',
                'Description' => 'اجازه ثبت‌نام کاربران جدید بعد از تائید OTP',
            ],
            'require_email_register' => [
                'FriendlyName' => 'ایمیل اجباری در ثبت‌نام',
                'Type' => 'yesno',
                'Description' => 'اگر فعال باشد، ورود اطلاعات ایمیل برای ثبت‌نام الزامی‌ست.',
                'Default' => 'on',
            ],
        ],
    ];
}

/** =====================[ activate/deactivate ]===================== */
function msgway_auth_activate()
{
    try {
        if (!Capsule::schema()->hasTable('mod_msgway_auth_logs')) {
            Capsule::schema()->create('mod_msgway_auth_logs', function ($t) {
                $t->increments('id');
                $t->string('mobile', 32);
                $t->string('mode', 16); // login|register
                $t->string('status', 16); // sent|verified|error
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

/** =====================[ helpers ]===================== */
function msgway_auth_setting($name)
{
    return Capsule::table('tbladdonmodules')
        ->where('module','msgway_auth')->where('setting',$name)->value('value');
}

/** تطبیق شماره با کلاینت — تلاش برای match روی الگوهای رایج ایران */
function msgway_auth_findClientByMobile(string $normalizedE164, string $countryCode = '+98')
{
    // کاندیدها: +98912..., 0912..., 912..., 98912...
    $digits = preg_replace('/\D+/', '', $normalizedE164);
    $cc = ltrim($countryCode, '+'); // 98
    if (strpos($digits, $cc) === 0) {
        $local = substr($digits, strlen($cc)); // 912xxxxxxx
    } else {
        $local = $digits;
    }
    $local0 = (strlen($local) === 10 || strlen($local) === 11) ? ('0' . ltrim($local, '0')) : ('0' . $local);

    // جست‌وجو با LIKE (سازگار با فرمت‌های مختلف ذخیره)
    $c = Capsule::table('tblclients')
        ->where('phonenumber', 'like', '%' . $local . '%')
        ->orWhere('phonenumber', 'like', '%' . $local0 . '%')
        ->orWhere('phonenumber', 'like', '%' . $digits . '%')
        ->orWhere('phonenumber', 'like', '%' . $normalizedE164 . '%')
        ->first();

    return $c ?: null;
}

function msgway_auth_findClientByEmail(string $email)
{
    return Capsule::table('tblclients')->where('email', $email)->first();
}

function msgway_auth_localApi(string $command, array $params = [])
{
    $admin = msgway_auth_setting('admin_username') ?: null;
    return localAPI($command, $params, $admin);
}

/** تولید پسورد رندوم برای AddClient */
function msgway_auth_randomPassword(int $len = 16): string
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%^&*()-_+=';
    $out = '';
    for ($i=0; $i<$len; $i++) {
        $out .= $chars[random_int(0, strlen($chars)-1)];
    }
    return $out;
}

/** =====================[ ADMIN OUTPUT ]===================== */
function msgway_auth_output($vars)
{
    echo '<h2>MSGWAY OTP Auth — راه‌پیام</h2>';
    echo '<p>کدنویسی: فاضل قائمی — نسخه 1.0.1</p>';
    echo '<p>صفحه ورود/ثبت‌نام کاربر: <code>/index.php?m=msgway_auth</code></p>';
    $ok = msgway_auth_load_sdk();
    echo $ok ? '<div class="successbox"><strong>Composer autoload پیدا شد.</strong></div>'
             : '<div class="errorbox"><strong>هشدار:</strong> فایل <code>vendor/autoload.php</code> یافت نشد. داخل پوشه افزونه <code>composer install</code> اجرا کنید.</div>';
}

/** =====================[ CLIENT AREA: OTP FLOW ]===================== */
function msgway_auth_clientarea($vars)
{
    $apiKey       = msgway_auth_setting('api_key');
    $countryCode  = msgway_auth_setting('country_code') ?: '+98';
    $otpTplId     = (int)(msgway_auth_setting('otp_template_id') ?: 0);
    $dest         = msgway_auth_setting('sso_destination') ?: 'clientarea:home';
    $customPath   = msgway_auth_setting('sso_redirect_path') ?: '';
    $registerOn   = (int)(msgway_auth_setting('registration_enabled') ?: 0);
    $requireEmail = (int)(msgway_auth_setting('require_email_register') ? 1 : 0);

    $errors = [];
    $notices = [];
    $success = [];
    $stage = 'form';
    $prefill = ['mode'=>'login','mobile'=>'','email'=>'','firstname'=>'','lastname'=>''];

    // session key
    if (!isset($_SESSION)) session_start();
    $sessKey = 'msgway_auth_ctx';

    $sdkReady = msgway_auth_load_sdk();
    if (!$sdkReady) {
        $errors[] = 'کتابخانه MSGWAY نصب نشده است. لطفاً داخل پوشه افزونه <code>composer install</code> اجرا کنید.';
    }

    // handle POST
    if ($sdkReady && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        try {
            $client = new \Msgway\MsgwayClient($apiKey, $countryCode);
            if ($action === 'send_otp') {
                $mode = ($_POST['mode'] ?? 'login') === 'register' ? 'register' : 'login';
                $mobileRaw = trim((string)($_POST['mobile'] ?? ''));
                $email = trim((string)($_POST['email'] ?? ''));
                $firstname = trim((string)($_POST['firstname'] ?? ''));
                $lastname  = trim((string)($_POST['lastname'] ?? ''));

                $prefill = compact('mode','mobileRaw','email','firstname','lastname');
                $prefill['mobile'] = $mobileRaw;

                if ($mobileRaw === '' || $otpTplId <= 0) {
                    $errors[] = 'شماره موبایل و Template ID الزامی‌ست.';
                } else {
                    $mobile = \Msgway\MsgwayClient::normalizeMobile($mobileRaw, $countryCode);

                    if ($mode === 'login') {
                        $clientRow = $email ? msgway_auth_findClientByEmail($email) : msgway_auth_findClientByMobile($mobile, $countryCode);
                        if (!$clientRow) {
                            $errors[] = 'کاربری با این اطلاعات یافت نشد.';
                        }
                    } else { // register
                        if (!$registerOn) {
                            $errors[] = 'ثبت‌نام با OTP غیرفعال است.';
                        }
                        if ($requireEmail && $email === '') {
                            $errors[] = 'ایمیل برای ثبت‌نام الزامی است.';
                        }
                        if ($email !== '') {
                            $exists = msgway_auth_findClientByEmail($email);
                            if ($exists) $errors[] = 'این ایمیل قبلاً ثبت شده است.';
                        }
                        if ($firstname === '' || $lastname === '') {
                            $errors[] = 'نام و نام خانوادگی را وارد کنید.';
                        }
                    }

                    if (empty($errors)) {
                        $resp = $client->sendTemplateSMS($mobile, $otpTplId);
                        $_SESSION[$sessKey] = [
                            'mode' => $mode,
                            'mobile' => $mobile,
                            'email' => $email,
                            'firstname' => $firstname,
                            'lastname' => $lastname,
                            'ts' => time()
                        ];
                        Capsule::table('mod_msgway_auth_logs')->insert([
                            'mobile' => $mobile, 'mode' => $mode, 'status' => 'sent',
                            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                            'meta' => json_encode($resp, JSON_UNESCAPED_UNICODE),
                            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')
                        ]);
                        $stage = 'sent';
                        $success[] = 'کد تایید ارسال شد.';
                    }
                }
            } elseif ($action === 'verify_otp') {
                $otp = trim((string)($_POST['otp'] ?? ''));
                $ctx = $_SESSION[$sessKey] ?? null;
                if (!$ctx || $otp === '') {
                    $errors[] = 'اطلاعات جلسه معتبر نیست یا کد خالی است.';
                } else {
                    $mobile = $ctx['mobile'];
                    $mode   = $ctx['mode'];
                    $email  = $ctx['email'];
                    $firstname = $ctx['firstname'];
                    $lastname  = $ctx['lastname'];

                    $verify = $client->verifyOtp($otp, $mobile);
                    $status = '';
                    if (is_array($verify)) {
                        $status = strtolower((string)($verify['status'] ?? ''));
                    }
                    if ($status !== 'true' && $status !== 'ok' && $status !== 'verified' && $status !== 'success' && $status !== '1') {
                        $errors[] = 'کد تایید معتبر نیست.';
                    } else {
                        if ($mode === 'login') {
                            $row = $email ? msgway_auth_findClientByEmail($email) : msgway_auth_findClientByMobile($mobile, $countryCode);
                            if (!$row) {
                                $errors[] = 'کاربر یافت نشد.';
                            } else {
                                $clientId = (int)$row->id;
                                $token = msgway_auth_localApi('CreateSsoToken', array_filter([
                                    'client_id' => $clientId,
                                    'destination' => $dest ?: null,
                                    'sso_redirect_path' => ($dest === 'sso:custom_redirect' && $customPath) ? $customPath : null,
                                ]));
                                if (($token['result'] ?? '') === 'success') {
                                    Capsule::table('mod_msgway_auth_logs')->insert([
                                        'mobile' => $mobile, 'mode' => 'login', 'status' => 'verified',
                                        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                                        'meta' => json_encode($verify, JSON_UNESCAPED_UNICODE),
                                        'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')
                                    ]);
                                    header('Location: ' . $token['redirect_url']);
                                    exit;
                                } else {
                                    $errors[] = 'عدم امکان ایجاد توکن ورود.';
                                }
                            }
                        } else {
                            if (!$registerOn) {
                                $errors[] = 'ثبت‌نام غیرفعال است.';
                            } else {
                                $password = msgway_auth_randomPassword(20);
                                $post = [
                                    'firstname'   => $firstname ?: 'User',
                                    'lastname'    => $lastname ?: 'OTP',
                                    'email'       => $email ?: ('u'.time().'@example.invalid'),
                                    'phonenumber' => $mobile,
                                    'password2'   => $password,
                                    'skipvalidation' => true,
                                    'marketingoptin' => false,
                                    'noemail'     => true,
                                ];
                                $add = msgway_auth_localApi('AddClient', $post);
                                if (($add['result'] ?? '') === 'success') {
                                    $clientId = (int)$add['clientid'];
                                    $token = msgway_auth_localApi('CreateSsoToken', array_filter([
                                        'client_id' => $clientId,
                                        'destination' => $dest ?: null,
                                        'sso_redirect_path' => ($dest === 'sso:custom_redirect' && $customPath) ? $customPath : null,
                                    ]));
                                    if (($token['result'] ?? '') === 'success') {
                                        Capsule::table('mod_msgway_auth_logs')->insert([
                                            'mobile' => $mobile, 'mode' => 'register', 'status' => 'verified',
                                            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                                            'meta' => json_encode(['verify'=>$verify,'add'=>$add], JSON_UNESCAPED_UNICODE),
                                            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')
                                        ]);
                                        header('Location: ' . $token['redirect_url']);
                                        exit;
                                    } else {
                                        $errors[] = 'ساخت توکن ورود پس از ثبت‌نام ناموفق بود.';
                                    }
                                } else {
                                    $errors[] = 'ساخت حساب کاربری ناموفق بود: '.htmlspecialchars((string)($add['message'] ?? ''));
                                }
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            $errors[] = 'خطای داخلی: ' . htmlspecialchars($e->getMessage());
        }
    }

    return [
        'pagetitle'    => 'ورود/عضویت با پیامک — راه‌پیام',
        'breadcrumb'   => ['index.php?m=msgway_auth' => 'ورود/عضویت با پیامک'],
        'templatefile' => 'otp',
        'requirelogin' => false,
        'vars' => [
            'errors'   => $errors,
            'notices'  => $notices,
            'success'  => $success,
            'stage'    => $stage ?: (isset($_SESSION[$sessKey]) ? 'sent' : 'form'),
            'prefill'  => $prefill,
            'modulelink' => $vars['modulelink'],
            'brand'    => 'MSGWAY | راه‌پیام — Coded by Fazel Ghaemi (v1.0.1)',
        ],
    ];
}
