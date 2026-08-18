<?php
// public/dump_env.php - temporary debug only. Remove after use.
header('Content-Type: text/plain');

$keys = [  //
    'APP_URL','SITE_URL','MYSQL_HOST','MYSQL_DATABASE','MYSQL_USER','MYSQL_PASSWORD',
    'MQTT_HOST','MQTT_PORT','MQTT_TLS','MQTT_USER','MQTT_PASS',
    'AI_LLM_API_KEY','AI_LLM_PROVIDER','TELEMETRY_API_KEY',
    'VAPID_SUBJECT','VAPID_PUBLIC_KEY','VAPID_PRIVATE_KEY'
];

echo "=== getenv() values ===\n";
foreach ($keys as $k) {
    $v = getenv($k);
    if ($v === false || $v === null || $v === '') {
        $v = '<unset>';
    }
    echo "$k=$v\n";
}

echo "\n=== \\$_ENV (partial) ===\n";
foreach ($keys as $k) {
    echo "$k=" . (isset($_ENV[$k]) ? $_ENV[$k] : '<unset>') . "\n";
}

echo "\n=== \\$_SERVER (selected) ===\n";
$serverKeys = ['SERVER_SOFTWARE','HTTP_HOST','SERVER_NAME','DOCUMENT_ROOT','REMOTE_ADDR','SCRIPT_NAME','SCRIPT_FILENAME','REQUEST_URI','PHP_SELF'];
foreach ($serverKeys as $k) {
    echo "$k=" . (isset($_SERVER[$k]) ? $_SERVER[$k] : '<unset>') . "\n";
}

echo "\n=== apache_getenv() (if available) ===\n";
if (function_exists('apache_getenv')) {
    foreach ($keys as $k) {
        $v = apache_getenv($k);
        echo "$k=" . ($v !== false ? ($v === '' ? '<empty>' : $v) : '<no>') . "\n";
    }
} else {
    echo "apache_getenv not available\n";
}

echo "\n=== phpinfo (disabled) ===\n";
echo "(if you need full phpinfo, request it explicitly)\n";

// End
