<?php
// public/test_env.php (temporaire) — NE PAS garder en production
header('Content-Type: text/plain');
echo 'APP_URL=' . (getenv('APP_URL') ?: '<unset>') . PHP_EOL;
echo 'SITE_URL=' . (getenv('SITE_URL') ?: '<unset>') . PHP_EOL;
echo 'MYSQL_HOST=' . (getenv('MYSQL_HOST') ?: '<unset>') . PHP_EOL;
echo 'MQTT_HOST=' . (getenv('MQTT_HOST') ?: '<unset>') . PHP_EOL;