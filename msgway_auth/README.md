# MSGWAY OTP Auth (راه‌پیام) — WHMCS Addon (v1.0.1)
Coded by: Fazel Ghaemi

## Install
1) Copy `msgway_auth` to `modules/addons/`.
2) Run `composer install` inside `modules/addons/msgway_auth`.
3) In WHMCS: System Settings → Addon Modules → Activate **MSGWAY OTP Auth**.
4) Configure: API Key, Country Code, OTP Template ID, Destination, etc.
5) Client URL: `/index.php?m=msgway_auth`

If `vendor/autoload.php` is missing, the client page will show a clear message instead of fatal error.
