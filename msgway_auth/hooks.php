<?php
if (!defined('WHMCS')) { die('This file cannot be accessed directly'); }

add_hook('ClientAreaPageLogin', 1, function($vars){
    return [
        'MSGWAY_OTP_URL' => 'index.php?m=msgway_auth'
    ];
});
add_hook('ClientAreaPrimaryNavbar', 1, function($nav){
    if (!isset($_SESSION['uid'])) {
        $nav->addChild('login-otp', ['name'=>'ورود با پیامک','uri'=>'index.php?m=msgway_auth','order'=>10]);
    }
});
