<?php
// Minimal autoloader for MSGWAY WHMCS addon (no Composer)

$MW_BASE = dirname(__DIR__);

spl_autoload_register(function ($class) use ($MW_BASE) {
    $prefixes = array(
        'Msgway\\'     => $MW_BASE . '/includes/',
        'MessageWay\\' => $MW_BASE . '/MessageWayPHP/src/',
    );

    foreach ($prefixes as $prefix => $baseDir) {
        $len = strlen($prefix);
        if (strncmp($class, $prefix, $len) !== 0) {
            continue;
        }
        $relative = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
        if (is_file($file)) {
            require $file;
            return true;
        }
    }

    // Fallback for single-file SDK layout (without Composer)
    if ($class === 'MessageWay\\Api\\MessageWayAPI') {
        $sdkSingle = $MW_BASE . '/MessageWayPHP/src/MessageWayAPI.php';
        if (is_file($sdkSingle)) {
            require $sdkSingle;
            return class_exists($class, false);
        }
    }
    return false;
});
