<?php
/**
 * MSGWAY (راه‌پیام) — WHMCS Addon
 * کدنویسی: فاضل قائمی
 * نسخه: 1.1.0
 */

if (!defined('WHMCS')) die('This file cannot be accessed directly');

use WHMCS\Database\Capsule;

function msgway_config()
{
    return [
        'name'        => 'MSGWAY (راه‌پیام) — by Fazel Ghaemi',
        'description' => 'ارسال پیامک/OTP با راه‌پیام برای رویدادهای WHMCS',
        'version'     => '1.1.0',
        'author'      => 'Fazel Ghaemi',
        'fields'      => [
            'api_key' => [
                'FriendlyName' => 'API Key',
                'Type' => 'text',
                'Size' => '80',
                'Description' => 'کلید API از داشبورد راه‌پیام',
            ],
            'country_code' => [
                'FriendlyName' => 'Country Code',
                'Type' => 'text',
                'Size' => '8',
                'Default' => '+98',
                'Description' => 'برای نرمال‌سازی شماره‌ها',
            ],
            'test_mobile' => [
                'FriendlyName' => 'Test Mobile',
                'Type' => 'text',
                'Size' => '20',
                'Description' => 'برای ارسال تست',
            ],
            'rate_limit' => [
                'FriendlyName' => 'حداکثر ارسال در دقیقه',
                'Type' => 'text',
                'Size' => '6',
                'Default' => '60',
                'Description' => 'برای مثال 60 یعنی حداکثر 60 پیام در دقیقه',
            ],
            'enable_queue' => [
                'FriendlyName' => 'فعال‌سازی صف',
                'Type' => 'yesno',
                'Description' => 'اگر حد پر شد/خطا رخ داد، پیام‌ها وارد صف شوند',
            ],
        ],
    ];
}

function msgway_activate()
{
    try {
        if (!Capsule::schema()->hasTable('mod_msgway_templates')) {
            Capsule::schema()->create('mod_msgway_templates', function ($table) {
                $table->increments('id');
                $table->string('hook', 100)->unique();
                $table->string('template_id', 50);
                $table->boolean('enabled')->default(true);
                $table->timestamps();
            });
        }
        if (!Capsule::schema()->hasTable('mod_msgway_logs')) {
            Capsule::schema()->create('mod_msgway_logs', function ($table) {
                $table->increments('id');
                $table->string('hook', 100)->nullable();
                $table->string('mobile', 32);
                $table->string('template_id', 50)->nullable();
                $table->string('status', 32)->default('queued'); // queued|sent|error|skipped
                $table->string('reference', 128)->nullable();
                $table->text('payload')->nullable();
                $table->timestamps();
            });
        }
        if (!Capsule::schema()->hasTable('mod_msgway_queue')) {
            Capsule::schema()->create('mod_msgway_queue', function ($t) {
                $t->increments('id');
                $t->string('hook', 100);
                $t->string('mobile', 32);
                $t->string('template_id', 50);
                $t->text('params_json')->nullable();
                $t->text('payload')->nullable();
                $t->integer('attempts')->default(0);
                $t->timestamp('next_attempt_at')->nullable();
                $t->timestamps();
            });
        }

        // مقداردهی اولیه چند هوک متداول اگر نبودند
        foreach (['ClientAdd','InvoicePaid','TicketOpen'] as $hook) {
            $exists = Capsule::table('mod_msgway_templates')->where('hook',$hook)->first();
            if (!$exists) {
                Capsule::table('mod_msgway_templates')->insert([
                    'hook' => $hook, 'template_id' => '0', 'enabled' => 0,
                    'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')
                ]);
            }
        }
    } catch (\Exception $e) {
        return ['status' => 'error', 'description' => $e->getMessage()];
    }

    return ['status' => 'success', 'description' => 'MSGWAY addon activated'];
}

function msgway_deactivate()
{
    return ['status' => 'success', 'description' => 'MSGWAY addon deactivated'];
}

/** Helper */
function msgway_getSetting($name)
{
    return Capsule::table('tbladdonmodules')->where('module', 'msgway')->where('setting', $name)->value('value');
}

function msgway_output($vars)
{
    $apiKey       = msgway_getSetting('api_key');
    $countryCode  = msgway_getSetting('country_code') ?: '+98';
    $testMobile   = msgway_getSetting('test_mobile');
    $rateLimit    = (int)(msgway_getSetting('rate_limit') ?: 60);
    $enableQueue  = (int)(msgway_getSetting('enable_queue') ?: 0);

    echo '<h2>MSGWAY | راه‌پیام — WHMCS Addon</h2>';
    echo '<p>کدنویسی: فاضل قائمی — نسخه 1.1.0</p>';

    // اقدامات فرم‌ها
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_token('WHMCS.admin.default');

        // ذخیره مپ الگوها
        if (isset($_POST['save_templates'])) {
            foreach (($_POST['template'] ?? []) as $hook => $tid) {
                Capsule::table('mod_msgway_templates')
                    ->updateOrInsert(
                        ['hook' => $hook],
                        [
                            'template_id' => trim($tid),
                            'enabled'     => isset($_POST['enabled'][$hook]) ? 1 : 0,
                            'updated_at'  => date('Y-m-d H:i:s')
                        ]
                    );
            }
            echo '<div class="successbox"><strong>تنظیمات ذخیره شد.</strong></div>';
        }

        // افزودن هوک جدید
        if (isset($_POST['add_hook']) && isset($_POST['new_hook'])) {
            $new = trim($_POST['new_hook']);
            if ($new !== '') {
                Capsule::table('mod_msgway_templates')->updateOrInsert(
                    ['hook' => $new],
                    [
                        'template_id' => '0',
                        'enabled'     => 0,
                        'updated_at'  => date('Y-m-d H:i:s'),
                        'created_at'  => date('Y-m-d H:i:s'),
                    ]
                );
                echo '<div class="successbox"><strong>هوک اضافه شد.</strong></div>';
            }
        }

        // افزودن هوک‌های پیشنهادی
        if (isset($_POST['add_recommended'])) {
            $recs = [
                'InvoiceCreated','InvoicePaid','InvoicePaymentReminder',
                'TicketOpen','TicketUserReply','TicketAdminReply',
                'ServiceSuspended','ServiceUnsuspended',
                'ClientAdd','ClientLogin','OrderAccepted','OrderCancelled'
            ];
            foreach ($recs as $h) {
                Capsule::table('mod_msgway_templates')->updateOrInsert(
                    ['hook' => $h],
                    ['template_id' => '0', 'enabled' => 0, 'updated_at' => date('Y-m-d H:i:s'), 'created_at' => date('Y-m-d H:i:s')]
                );
            }
            echo '<div class="successbox"><strong>هوک‌های پیشنهادی افزوده شد.</strong></div>';
        }

        // ارسال تست
        if (isset($_POST['send_test'])) {
            $tmobile = trim($_POST['tmobile'] ?? $testMobile ?? '');
            $tid = (int)($_POST['ttemplate'] ?? 0);
            if ($apiKey && $tmobile && $tid > 0) {
                try {
                    require_once __DIR__ . '/vendor/autoload.php';
                    $client = new \Msgway\MsgwayClient($apiKey, $countryCode);
                    $mobile = \Msgway\MsgwayClient::normalizeMobile($tmobile, $countryCode);
                    $resp = $client->sendTemplateSMS($mobile, $tid /*, پارامترها اختیاری */);

                    Capsule::table('mod_msgway_logs')->insert([
                        'hook'        => 'manual-test',
                        'mobile'      => $mobile,
                        'template_id' => (string)$tid,
                        'status'      => 'sent',
                        'reference'   => is_array($resp) && isset($resp['referenceID']) ? $resp['referenceID'] : null,
                        'payload'     => json_encode(['manual' => true], JSON_UNESCAPED_UNICODE),
                        'created_at'  => date('Y-m-d H:i:s'),
                        'updated_at'  => date('Y-m-d H:i:s'),
                    ]);

                    echo '<div class="successbox"><strong>ارسال تست موفق.</strong></div>';
                } catch (\Throwable $e) {
                    echo '<div class="errorbox"><strong>خطای ارسال تست: </strong>' . htmlspecialchars($e->getMessage()) . '</div>';
                }
            } else {
                echo '<div class="errorbox"><strong>API Key، شماره و Template ID معتبر وارد کنید.</strong></div>';
            }
        }

        // پیش‌نمایش پارامترهای الگو
        if (isset($_POST['preview_template_params'])) {
            $tid = (int)($_POST['preview_template_id'] ?? 0);
            if ($apiKey && $tid > 0) {
                try {
                    require_once __DIR__ . '/vendor/autoload.php';
                    $client = new \Msgway\MsgwayClient($apiKey, $countryCode);
                    $tpl = $client->getTemplate($tid);
                    echo '<div class="infobox"><strong>Template '.$tid.':</strong> ' . htmlspecialchars($tpl['template'] ?? '') . '<br>';
                    $params = $tpl['params'] ?? [];
                    echo '<em>Params:</em> ' . htmlspecialchars(implode(', ', $params)) . '</div>';
                } catch (\Throwable $e) {
                    echo '<div class="errorbox"><strong>خطا در دریافت اطلاعات الگو: </strong>' . htmlspecialchars($e->getMessage()) . '</div>';
                }
            }
        }

        // استعلام موجودی
        if (isset($_POST['check_balance'])) {
            try {
                require_once __DIR__ . '/vendor/autoload.php';
                $client = new \Msgway\MsgwayClient($apiKey, $countryCode);
                $balance = $client->getBalance();
                echo '<div class="infobox"><strong>موجودی:</strong> ' . htmlspecialchars((string)$balance) . '</div>';
            } catch (\Throwable $e) {
                echo '<div class="errorbox"><strong>خطا در استعلام موجودی: </strong>' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        }
    }

    // داده‌ها
    $templates = Capsule::table('mod_msgway_templates')->orderBy('hook', 'asc')->get();
    $logs = Capsule::table('mod_msgway_logs')->orderBy('id', 'desc')->limit(20)->get();

    // اکشن‌های سریع
    echo '<form method="post" style="margin:10px 0;">'.generate_token('WHMCS.admin.default');
    echo '<button class="btn btn-default" name="add_recommended" value="1">افزودن هوک‌های پیشنهادی</button> ';
    echo '<button class="btn btn-default" name="check_balance" value="1">استعلام موجودی</button>';
    echo '</form>';

    // جدول مپ هوک‌ها
    echo '<h3>Map هوک‌ها ↔ Template ID</h3>';
    echo '<form method="post">'.generate_token('WHMCS.admin.default');
    echo '<table class="form" width="100%" border="0" cellspacing="2" cellpadding="3">';
    echo '<tr><th>Hook</th><th>Template ID</th><th>Enabled</th><th>Actions</th></tr>';
    foreach ($templates as $t) {
        $hook = htmlspecialchars($t->hook);
        $tid  = htmlspecialchars($t->template_id);
        $checked = $t->enabled ? 'checked' : '';
        echo "<tr>
                <td>{$hook}</td>
                <td><input type='text' name='template[{$hook}]' value='{$tid}' size='10' /></td>
                <td style='text-align:center;'><input type='checkbox' name='enabled[{$hook}]' {$checked} /></td>
                <td>
                    <button class='btn btn-default' name='preview_template_params' value='1' formaction='' formmethod='post' onclick=\"this.form.preview_template_id.value='{$tid}';\">پیش‌نمایش پارامترها</button>
                </td>
              </tr>";
    }
    echo '</table>';
    echo '<input type="hidden" name="preview_template_id" value="" />';
    echo '<p><button type="submit" name="save_templates" class="btn btn-primary">ذخیره تنظیمات</button></p>';
    echo '</form>';

    // افزودن هوک جدید
    echo '<h3>افزودن هوک جدید</h3>';
    echo '<form method="post">'.generate_token('WHMCS.admin.default');
    echo '<p>Hook Name: <input type="text" name="new_hook" size="30" placeholder="مثلاً: InvoiceCreated" /> ';
    echo '<button type="submit" class="btn btn-default" name="add_hook" value="1">افزودن</button></p>';
    echo '</form>';

    // ارسال تست
    echo '<h3>ارسال تست</h3>';
    echo '<form method="post">'.generate_token('WHMCS.admin.default');
    echo '<p>شماره: <input type="text" name="tmobile" value="' . htmlspecialchars($testMobile ?? '') . '" size="20" />';
    echo ' Template ID: <input type="number" name="ttemplate" min="1" value="1" size="5" />';
    echo ' <button type="submit" name="send_test" class="btn btn-default">ارسال تست</button></p>';
    echo '</form>';

    // لاگ‌ها
    echo '<h3>۲۰ لاگ اخیر</h3>';
    echo '<table class="datatable" width="100%" border="0" cellspacing="2" cellpadding="3">';
    echo '<tr><th>ID</th><th>Hook</th><th>Mobile</th><th>Template</th><th>Status</th><th>Ref</th><th>Created</th></tr>';
    foreach ($logs as $log) {
        echo '<tr>';
        echo '<td>' . (int)$log->id . '</td>';
        echo '<td>' . htmlspecialchars($log->hook ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($log->mobile) . '</td>';
        echo '<td>' . htmlspecialchars($log->template_id ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($log->status) . '</td>';
        echo '<td>' . htmlspecialchars($log->reference ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($log->created_at) . '</td>';
        echo '</tr>';
    }
    echo '</table>';
}
