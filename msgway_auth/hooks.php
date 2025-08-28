<?php
if (!defined('WHMCS')) { die('This file cannot be accessed directly'); }

add_hook('ClientAreaPrimaryNavbar', 1, function($nav) {
    if (!isset($_SESSION['uid'])) {
        $nav->addChild('login-otp', [
            'name' => 'ورود با پیامک',
            'uri' => 'index.php?m=msgway_auth',
            'order' => 5,
        ]);
    }
});
