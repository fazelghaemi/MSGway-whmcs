{* MSGWAY OTP Auth — Coded by: Fazel Ghaemi *}
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">

<style>
  .mw-card{max-width:620px;margin:2rem auto;padding:1.25rem;border:1px solid #eee;border-radius:14px}
  .mw-row{display:flex;gap:.75rem}
  .mw-row > div{flex:1}
  .rtl{direction:rtl;text-align:right}
  code{background:#f6f8fa;padding:.1rem .3rem;border-radius:6px}
</style>

<div class="mw-card rtl">
  <h3>ورود / عضویت با پیامک (راه‌پیام)</h3>
  <p><small>{$brand}</small></p>

  {if $errors && count($errors)}
    <article class="contrast">
      {foreach from=$errors item=e}<p style="color:#c00;">{$e nofilter}</p>{/foreach}
    </article>
  {/if}
  {if $success && count($success)}
    <article class="contrast">
      {foreach from=$success item=s}<p style="color:#0a0;">{$s}</p>{/foreach}
    </article>
  {/if}

  {if $stage eq 'form'}
    <form method="post" action="{$modulelink}">
      <input type="hidden" name="action" value="send_otp">
      <fieldset class="mw-row">
        <label>
          حالت:
          <select name="mode">
            <option value="login" {if $prefill.mode ne 'register'}selected{/if}>ورود</option>
            <option value="register" {if $prefill.mode eq 'register'}selected{/if}>ثبت‌نام</option>
          </select>
        </label>
        <label>
          موبایل:
          <input type="text" name="mobile" value="{$prefill.mobile|escape}" placeholder="مثلاً 0912xxxxxxx" required>
        </label>
      </fieldset>

      <details>
        <summary>اطلاعات تکمیلی (برای ثبت‌نام جدید)</summary>
        <div class="mw-row">
          <div>
            <label>نام:
              <input type="text" name="firstname" value="{$prefill.firstname|escape}">
            </label>
          </div>
          <div>
            <label>نام خانوادگی:
              <input type="text" name="lastname" value="{$prefill.lastname|escape}">
            </label>
          </div>
        </div>
        <label>ایمیل:
          <input type="email" name="email" value="{$prefill.email|escape}" placeholder="[email protected]">
        </label>
      </details>

      <button type="submit">ارسال کد تایید</button>
    </form>
  {else}
    <form method="post" action="{$modulelink}">
      <input type="hidden" name="action" value="verify_otp">
      <label>کد تایید:
        <input type="text" name="otp" value="" placeholder="کد ۶ رقمی" required>
      </label>
      <button type="submit">تایید و ورود</button>
    </form>
    <hr>
    <form method="post" action="{$modulelink}">
      <input type="hidden" name="action" value="send_otp">
      <input type="hidden" name="mode" value="{$prefill.mode|default:'login'}">
      <input type="hidden" name="mobile" value="{$prefill.mobile|escape}">
      <input type="hidden" name="firstname" value="{$prefill.firstname|escape}">
      <input type="hidden" name="lastname" value="{$prefill.lastname|escape}">
      <input type="hidden" name="email" value="{$prefill.email|escape}">
      <button type="submit" class="secondary">ارسال مجدد کد</button>
    </form>
  {/if}
</div>
