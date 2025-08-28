# MSGWAY OTP Auth for WHMCS — Standalone (no Composer)
Coded by: Fazel Ghaemi

Install:
1) Extract the folder `msgway_auth` to `modules/addons/` (path must be exactly modules/addons/msgway_auth).
2) Ensure correct file permissions (folders 755, files 644) and owned by web server user.
3) In WHMCS: System Settings → Addon Modules → Activate "MSGWAY OTP Auth".
4) Configure the module settings (API Key, API Key Header, Send Endpoint, Verify Endpoint, Country Code, OTP Template ID).
   - Default send endpoint: https://api.msgway.com/v1/sms/send
   - Default verify endpoint: https://api.msgway.com/v1/sms/verify
5) Test: open https://your-whmcs/index.php?m=msgway_auth and perform send/verify actions.

Notes:
- This package uses PHP cURL. Ensure the cURL extension is enabled.
- If MessageWay expects a different JSON shape, adjust payloads in includes/MsgwayHttpClient.php.
- Logs are stored in DB table `mod_msgway_auth_logs` to help debug send/verify attempts.
