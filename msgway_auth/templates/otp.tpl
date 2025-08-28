{* MSGWAY OTP Auth — final template (safe) *}
<style>
  .mw-card{max-width:640px;margin:2rem auto;padding:1.25rem;border:1px solid #eee;border-radius:14px;background:#fff}
  .mw-row{display:flex;gap:.75rem}
  .mw-row > div{flex:1}
  .rtl{direction:rtl;text-align:right}
  .mw-note{padding:.5rem;border-radius:8px;background:#f8f9fa;margin-bottom:.5rem}
</style>

<div class="mw-card rtl">
  <h3>ورود / ثبت‌نام با پیامک (راه‌پیام)</h3>
  <p class="mw-note"><small>{$brand|escape}</small></p>

  {if $errors}
    <div style="background:#ffecec;border:1px solid #f5c2c7;color:#842029;padding:.75rem;border-radius:10px;margin:.5rem 0">
      {foreach from=$errors item=e}<p>{$e|escape}</p>{/foreach}
    </div>
  {/if}

  {if $success}
    <div style="background:#e9f7ef;border:1px solid #b7e4c7;color:#0f5132;padding:.75rem;border-radius:10px;margin:.5rem 0">
      {foreach from=$success item=s}<p>{$s|escape}</p>{/foreach}
    </div>
  {/if}

  {if $stage eq 'form'}
    <form method="post" action="{$modulelink|escape}">
      <input type="hidden" name="action" value="send_otp">
      <fieldset class="mw-row">
        <label style="flex:0 0 160px">
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

      <details style="margin-top:.75rem">
        <summary>اطلاعات تکمیلی (برای ثبت‌نام)</summary>
        <div class="mw-row" style="margin-top:.5rem">
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

      <button type="submit" style="margin-top:1rem">ارسال کد تایید</button>
    </form>
  {else}
    <form method="post" action="{$modulelink|escape}">
      <input type="hidden" name="action" value="verify_otp">
      <label>کد تایید:
        <input type="text" name="otp" value="" placeholder="کد ۶ رقمی" required>
      </label>
      <button type="submit" style="margin-top:.75rem">تایید و ورود</button>
    </form>

    <hr>
    <form method="post" action="{$modulelink|escape}">
      <input type="hidden" name="action" value="send_otp">
      <input type="hidden" name="mode" value="{if $prefill.mode}{$prefill.mode|escape}{else}login{/if}">
      <input type="hidden" name="mobile" value="{$prefill.mobile|escape}">
      <input type="hidden" name="email" value="{$prefill.email|escape}">
      <input type="hidden" name="firstname" value="{$prefill.firstname|escape}">
      <input type="hidden" name="lastname" value="{$prefill.lastname|escape}">
      <button type="submit" class="secondary">ارسال مجدد کد</button>
    </form>
  {/if}
</div>
