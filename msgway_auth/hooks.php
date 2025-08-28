<?php
if (!defined('WHMCS')) { die('This file cannot be accessed directly'); }

add_hook('ClientAreaPageLogin', 1, function($vars){
    return [
        'MSGWAY_OTP_URL' => 'index.php?m=msgway_auth'
    ];
});
