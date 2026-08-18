<?php
require __DIR__ . '/app/core/bootstrap.php';

$config = require __DIR__ . '/mqtt/config.php';
$client = new Mqtt\MqttClient($config);

if (!$client->connect()) {
    echo "ERREUR: impossible de se connecter au broker MQTT\n";
    exit(1);
}

$topic = 'home/villa-douala/climate/chambre/temp';
$value = '25.4';
$client->publish($topic, $value);
echo "PUBLISH OK -> $topic = $value\n";
$client->disconnect();
