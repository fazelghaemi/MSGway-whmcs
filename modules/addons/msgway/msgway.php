<?php
/**
 * MSGWAY (راه‌پیام) — WHMCS Addon
 * کدنویسی: فاضل قائمی
 * نسخه: 1.2.0
 */

if (!defined('WHMCS')) die('This file cannot be accessed directly');

use WHMCS\Database\Capsule;

function msgway_config()
{
    return [
        'name'        => 'MSGWAY (راه‌پیام) — by Fazel Ghaemi',
        'description' => 'ارسال پیامک/OTP با راه‌پیام برای رویدادهای WHMCS',
        'version'     => '1.2.0',
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
        if (!Capsule::schema()->hasTable('mod_msgway_param_maps')) {
            Capsule::schema()->create('mod_msgway_param_maps', function ($t) {
                $t->increments('id');
                $t->string('hook', 100);
                $t->string('template_id', 50);
                $t->string('param_key', 100);
                $t->enum('source', ['vars','client','literal']);
                $t->string('source_key', 100)->nullable();
                $t->text('static_value')->nullable();
                $t->timestamps();
                $t->unique(['hook','template_id','param_key']);
            });
        }

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

/** ارتقا نسخه: ایجاد جدول مپ پارامترها در نصب‌های قبلی */
function msgway_upgrade($vars)
{
    try {
        if (!Capsule::schema()->hasTable('mod_msgway_param_maps')) {
            Capsule::schema()->create('mod_msgway_param_maps', function ($t) {
                $t->increments('id');
                $t->string('hook', 100);
                $t->string('template_id', 50);
                $t->string('param_key', 100);
                $t->enum('source', ['vars','client','literal']);
                $t->string('source_key', 100)->nullable();
                $t->text('static_value')->nullable();
                $t->timestamps();
                $t->unique(['hook','template_id','param_key']);
            });
        }
    } catch (\Exception $e) {
        return ['status' => 'error', 'description' => $e->getMessage()];
    }
    return ['status' => 'success', 'description' => 'MSGWAY upgraded to '.$vars['version']];
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

/** UI ساده‌ی تب‌ها */
function msgway_nav($active)
{
    $tabs = [
        'dashboard' => 'داشبورد',
        'mapping'   => 'ویرایشگر مپ پارامترها',
        'analytics' => 'آنالیتیکس',
        'test'      => 'ارسال تست/موجودی',
    ];
    echo '<p>';
    foreach ($tabs as $k=>$label) {
        $style = $k===$active ? 'font-weight:bold;text-decoration:underline' : '';
        echo '<a href="?module=msgway&tab='.$k.'" style="margin-right:15px;'.$style.'">'.$label.'</a>';
    }
    echo '</p>';
}

function msgway_output($vars)
{
    $tab = $_GET['tab'] ?? 'dashboard';

    echo '<h2>MSGWAY | راه‌پیام — WHMCS Addon</h2>';
    echo '<p>کدنویسی: فاضل قائمی — نسخه 1.2.0</p>';
    msgway_nav($tab);

    switch ($tab) {
        case 'mapping':   return msgway_tab_mapping();
        case 'analytics': return msgway_tab_analytics();
        case 'test':      return msgway_tab_test();
        case 'dashboard':
        default:          return msgway_tab_dashboard();
    }
}

/** تب: داشبورد (لیست هوک‌ها + Template ID + فعال/غیرفعال) */
function msgway_tab_dashboard()
{
    $apiKey      = msgway_getSetting('api_key');
    $countryCode = msgway_getSetting('country_code') ?: '+98';

    // اکشن‌ها
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_token('WHMCS.admin.default');

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
    }

    $templates = Capsule::table('mod_msgway_templates')->orderBy('hook', 'asc')->get();
    $logs      = Capsule::table('mod_msgway_logs')->orderBy('id', 'desc')->limit(10)->get();

    echo '<form method="post" style="margin:10px 0;">'.generate_token('WHMCS.admin.default');
    echo '<button class="btn btn-default" name="add_recommended" value="1">افزودن هوک‌های پیشنهادی</button>';
    echo '</form>';

    echo '<h3>Map هوک‌ها ↔ Template ID</h3>';
    echo '<form method="post">'.generate_token('WHMCS.admin.default');
    echo '<table class="form" width="100%" border="0" cellspacing="2" cellpadding="3">';
    echo '<tr><th>Hook</th><th>Template ID</th><th>Enabled</th><th>Actions</th></tr>';
    foreach ($templates as $t) {
        $hook = htmlspecialchars($t->hook);
        $tid  = htmlspecialchars($t->template_id);
        $checked = $t->enabled ? 'checked' : '';
        $mapUrl = 'index.php?module=msgway&tab=mapping&hook='.urlencode($t->hook).'&template_id='.urlencode($t->template_id);
        echo "<tr>
                <td>{$hook}</td>
                <td><input type='text' name='template[{$hook}]' value='{$tid}' size='10' /></td>
                <td style='text-align:center;'><input type='checkbox' name='enabled[{$hook}]' {$checked} /></td>
                <td><a class='btn btn-default' href='{$mapUrl}'>ویرایش مپ پارامترها</a></td>
              </tr>";
    }
    echo '</table>';
    echo '<p><button type="submit" name="save_templates" class="btn btn-primary">ذخیره تنظیمات</button></p>';
    echo '</form>';

    echo '<h3>۱۰ لاگ اخیر</h3>';
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

/** تب: ویرایشگر مپ پارامترها */
function msgway_tab_mapping()
{
    $apiKey      = msgway_getSetting('api_key');
    $countryCode = msgway_getSetting('country_code') ?: '+98';
    $hook        = trim($_GET['hook'] ?? '');
    $templateId  = (int)($_GET['template_id'] ?? 0);

    echo '<h3>ویرایشگر مپ پارامترها</h3>';

    if ($hook === '' || $templateId <= 0) {
        echo '<div class="errorbox"><strong>ابتدا از داشبورد، Template ID معتبر ثبت کنید و از آنجا وارد این صفحه شوید.</strong></div>';
        return;
    }

    // اکشن ذخیره
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_map'])) {
        check_token('WHMCS.admin.default');
        // پاک‌سازی قدیمی‌ها
        Capsule::table('mod_msgway_param_maps')->where('hook',$hook)->where('template_id',$templateId)->delete();

        $keys = $_POST['param_key'] ?? [];
        $sources = $_POST['source'] ?? [];
        $skeys = $_POST['source_key'] ?? [];
        $lits  = $_POST['static_value'] ?? [];

        $count = count($keys);
        for ($i=0; $i<$count; $i++) {
            $pk = trim((string)$keys[$i]);
            if ($pk === '') continue;

            $src = in_array($sources[$i], ['vars','client','literal'], true) ? $sources[$i] : 'vars';
            $sk  = trim((string)($skeys[$i] ?? ''));
            $sv  = trim((string)($lits[$i] ?? ''));

            Capsule::table('mod_msgway_param_maps')->insert([
                'hook'        => $hook,
                'template_id' => (string)$templateId,
                'param_key'   => $pk,
                'source'      => $src,
                'source_key'  => $src === 'literal' ? null : $sk,
                'static_value'=> $src === 'literal' ? $sv : null,
                'created_at'  => date('Y-m-d H:i:s'),
                'updated_at'  => date('Y-m-d H:i:s'),
            ]);
        }
        echo '<div class="successbox"><strong>مپ پارامترها ذخیره شد.</strong></div>';
    }

    // خواندن پارامترهای Template از راه‌پیام
    $params = [];
    try {
        require_once __DIR__ . '/vendor/autoload.php';
        $client = new \Msgway\MsgwayClient($apiKey, $countryCode);
        $tpl = $client->getTemplate($templateId);
        $params = $tpl['params'] ?? [];
    } catch (\Throwable $e) {
        echo '<div class="errorbox"><strong>عدم امکان دریافت پارامترها از API: </strong>'.htmlspecialchars($e->getMessage()).'</div>';
    }

    // خواندن مپ ذخیره‌شده
    $maps = Capsule::table('mod_msgway_param_maps')
        ->where('hook',$hook)->where('template_id',$templateId)->get()
        ->keyBy('param_key')->toArray();

    // راهنمای فیلدها
    $clientFields = [
        'id','firstname','lastname','companyname','email','address1','address2','city','state','postcode','country','phonenumber','currency','status'
    ];
    $varsHints = 'vars یک آرایه از داده‌های رویداد است (مثل invoiceid, amount, ticketid, subject, ...).';

    echo '<p><strong>Hook:</strong> '.htmlspecialchars($hook).' &nbsp; | &nbsp; <strong>Template ID:</strong> '.$templateId.'</p>';
    echo '<form method="post">'.generate_token('WHMCS.admin.default');
    echo '<table class="form" width="100%" border="0" cellspacing="2" cellpadding="3">';
    echo '<tr><th>#</th><th>پارامتر الگو (به‌ترتیب API)</th><th>Source</th><th>کلید (برای vars/client)</th><th>مقدار ثابت (literal)</th></tr>';

    $i = 1;
    foreach ($params as $pk) {
        $pk = (string)$pk;
        $m = $maps[$pk] ?? null;
        $src = $m->source ?? 'vars';
        $sk  = $m->source_key ?? '';
        $sv  = $m->static_value ?? '';

        echo '<tr>';
        echo '<td>'.($i++).'</td>';
        echo '<td><input type="text" name="param_key[]" value="'.htmlspecialchars($pk).'" readonly /></td>';
        echo '<td>
                <select name="source[]">
                    <option value="vars" '.($src==='vars'?'selected':'').'>vars</option>
                    <option value="client" '.($src==='client'?'selected':'').'>client</option>
                    <option value="literal" '.($src==='literal'?'selected':'').'>literal</option>
                </select>
              </td>';
        echo '<td><input type="text" name="source_key[]" value="'.htmlspecialchars($sk).'" placeholder="مثلاً: invoiceid یا firstname" /></td>';
        echo '<td><input type="text" name="static_value[]" value="'.htmlspecialchars($sv).'" placeholder="برای literal" /></td>';
        echo '</tr>';
    }
    echo '</table>';

    echo '<p><em>'.$varsHints.'</em></p>';
    echo '<p><strong>فیلدهای رایج client:</strong> '.implode(', ', $clientFields).'</p>';

    echo '<p><button type="submit" name="save_map" class="btn btn-primary">ذخیره مپ</button></p>';
    echo '</form>';
}

/** تب: آنالیتیکس */
function msgway_tab_analytics()
{
    echo '<h3>آنالیتیکس</h3>';

    // آمار کلی
    $total = Capsule::table('mod_msgway_logs')->count();
    $byStatus = Capsule::table('mod_msgway_logs')
        ->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c','status')->toArray();

    echo '<p><strong>کل لاگ‌ها:</strong> '.$total.'</p>';
    echo '<table class="datatable"><tr><th>Status</th><th>Count</th></tr>';
    foreach (['sent','queued','error','skipped'] as $s) {
        $c = (int)($byStatus[$s] ?? 0);
        echo "<tr><td>{$s}</td><td>{$c}</td></tr>";
    }
    echo '</table>';

    // 7 روز اخیر
    $since = date('Y-m-d H:i:s', strtotime('-7 days'));
    $last7 = Capsule::table('mod_msgway_logs')
        ->selectRaw('DATE(created_at) as d, status, COUNT(*) as c')
        ->where('created_at','>=',$since)
        ->groupBy('d','status')->orderBy('d','asc')->get();

    echo '<h4>۷ روز اخیر</h4>';
    echo '<table class="datatable"><tr><th>Date</th><th>Status</th><th>Count</th></tr>';
    foreach ($last7 as $row) {
        echo '<tr><td>'.htmlspecialchars($row->d).'</td><td>'.htmlspecialchars($row->status).'</td><td>'.(int)$row->c.'</td></tr>';
    }
    echo '</table>';

    // Top Hooks
    $topHooks = Capsule::table('mod_msgway_logs')
        ->selectRaw('hook, COUNT(*) as c')
        ->whereNotNull('hook')
        ->groupBy('hook')->orderBy('c','desc')->limit(20)->get();

    echo '<h4>Top Hooks</h4>';
    echo '<table class="datatable"><tr><th>Hook</th><th>Count</th></tr>';
    foreach ($topHooks as $row) {
        echo '<tr><td>'.htmlspecialchars($row->hook).'</td><td>'.(int)$row->c.'</td></tr>';
    }
    echo '</table>';
}

/** تب: ارسال تست و موجودی */
function msgway_tab_test()
{
    $apiKey      = msgway_getSetting('api_key');
    $countryCode = msgway_getSetting('country_code') ?: '+98';
    $testMobile  = msgway_getSetting('test_mobile');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_token('WHMCS.admin.default');

        if (isset($_POST['send_test'])) {
            $tmobile = trim($_POST['tmobile'] ?? $testMobile ?? '');
            $tid = (int)($_POST['ttemplate'] ?? 0);
            if ($apiKey && $tmobile && $tid > 0) {
                try {
                    require_once __DIR__ . '/vendor/autoload.php';
                    $client = new \Msgway\MsgwayClient($apiKey, $countryCode);
                    $mobile = \Msgway\MsgwayClient::normalizeMobile($tmobile, $countryCode);
                    // بدون پارامتر (یا با پارامتر در صورت پشتیبانی SDK)
                    $resp = $client->sendTemplateSMS($mobile, $tid);
                    Capsule::table('mod_msgway_logs')->insert([
                        'hook'        => 'manual-test',
                        'mobile'      => $mobile,
                        'template_id' => (string)$tid,
                        'status'      => 'sent',
                        'reference'   => is_array($resp) && isset($resp['referenceID']) ? $resp['referenceID'] : null,
                        'payload'     => json_encode(['manual'=>true], JSON_UNESCAPED_UNICODE),
                        'created_at'  => date('Y-m-d H:i:s'),
                        'updated_at'  => date('Y-m-d H:i:s'),
                    ]);
                    echo '<div class="successbox"><strong>ارسال تست موفق.</strong></div>';
                } catch (\Throwable $e) {
                    echo '<div class="errorbox"><strong>خطای ارسال تست: </strong>'.htmlspecialchars($e->getMessage()).'</div>';
                }
            } else {
                echo '<div class="errorbox"><strong>API Key، شماره و Template ID معتبر وارد کنید.</strong></div>';
            }
        }

        if (isset($_POST['check_balance'])) {
            try {
                require_once __DIR__ . '/vendor/autoload.php';
                $client = new \Msgway\MsgwayClient($apiKey, $countryCode);
                $balance = $client->getBalance();
                echo '<div class="infobox"><strong>موجودی:</strong> '.htmlspecialchars((string)$balance).'</div>';
            } catch (\Throwable $e) {
                echo '<div class="errorbox"><strong>خطا در استعلام موجودی: </strong>'.htmlspecialchars($e->getMessage()).'</div>';
            }
        }
    }

    echo '<h3>ارسال تست / موجودی</h3>';
    echo '<form method="post">'.generate_token('WHMCS.admin.default');
    echo '<p>شماره: <input type="text" name="tmobile" value="' . htmlspecialchars($testMobile ?? '') . '" size="20" />';
    echo ' Template ID: <input type="number" name="ttemplate" min="1" value="1" size="5" />';
    echo ' <button type="submit" name="send_test" class="btn btn-default">ارسال تست</button> ';
    echo ' <button type="submit" name="check_balance" class="btn btn-default">استعلام موجودی</button></p>';
    echo '</form>';
}
