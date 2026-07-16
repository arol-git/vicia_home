<?php
/**
 * mqtt/subscriber.php
 *
 * Démon CLI destiné à tourner en arrière-plan (via systemd, voir
 * docs/README.md) : se connecte au broker Mosquitto, s'abonne aux
 * topics de télémétrie et d'événements publiés par les modules
 * ESP32, puis :
 *   1. enregistre chaque mesure de capteur en base de données ;
 *   2. évalue les règles d'automatisation actives concernées ;
 *   3. déclenche les actions et notifications correspondantes.
 *
 * Lancement manuel (test) : php mqtt/subscriber.php
 */

require __DIR__ . '/../app/core/bootstrap.php';

use App\Core\Database;
use App\Models\AutomationRule;
use App\Models\Sensor;
use Mqtt\MqttClient;
use Mqtt\Publisher;

$config = require __DIR__ . '/config.php';
$client = new MqttClient($config);

echo "[Vicia Home] Connexion au broker MQTT {$config['host']}:{$config['port']}...\n";

if (!$client->connect()) {
    fwrite(STDERR, "Impossible de se connecter au broker MQTT. Arrêt du démon.\n");
    exit(1);
}

$baseTopic = $config['base_topic'];
$client->subscribe([
    "$baseTopic/+/+/+",        // télémétrie des capteurs (home/<domaine>/<zone>/<grandeur>)
    "$baseTopic/security/#",   // événements de sécurité
    "$baseTopic/network/#",    // événements réseau (module cybersécurité)
]);

echo "Abonnement actif. En attente de messages...\n";

$client->loop(function (string $topic, string $payload) {
    app_log("[MQTT Subscriber] Message reçu — topic: $topic | payload: $payload");

    Database::query(
        'INSERT INTO mqtt_logs (topic, payload, direction, created_at) VALUES (:topic, :payload, :direction, NOW())',
        ['topic' => $topic, 'payload' => $payload, 'direction' => 'in']
    );

    // --- Cas 1 : message de télémétrie d'un capteur connu ---
    $sensor = Sensor::findByTopic($topic);
    if ($sensor && is_numeric($payload)) {
        Sensor::recordReading((int) $sensor['id'], (float) $payload);
        evaluateSensorRules((int) $sensor['id'], (float) $payload);
        return;
    }

    // --- Cas 2 : événement de sécurité (intrusion, PIR déclenché...) ---
    if (str_contains($topic, '/security/') && $payload === '1') {
        \App\Models\Alert::create([
            'type'     => 'intrusion',
            'severity' => 'critical',
            'source'   => $topic,
            'message'  => "Détection de mouvement / intrusion sur le topic $topic",
        ]);
        evaluateEventRules('intrusion');
    }
}, 0);

/**
 * Évalue les règles d'automatisation dont la condition porte sur le
 * capteur ayant émis la nouvelle mesure, et déclenche les actions
 * correspondantes si la condition est satisfaite.
 */
function evaluateSensorRules(int $sensorId, float $value): void
{
    $rules = AutomationRule::activeForSensor($sensorId);

    foreach ($rules as $rule) {
        $threshold = (float) $rule['condition_value'];
        $matched = match ($rule['condition_operator']) {
            '>'  => $value > $threshold,
            '<'  => $value < $threshold,
            '>=' => $value >= $threshold,
            '<=' => $value <= $threshold,
            '='  => abs($value - $threshold) < 0.0001,
            '!=' => abs($value - $threshold) >= 0.0001,
            default => false,
        };

        if ($matched) {
            executeRule($rule, "Condition capteur satisfaite (valeur=$value)");
        }
    }
}

/**
 * Évalue les règles d'automatisation déclenchées par un événement
 * système (ex. intrusion, appareil réseau inconnu).
 */
function evaluateEventRules(string $event): void
{
    $rules = AutomationRule::activeForEvent($event);
    foreach ($rules as $rule) {
        executeRule($rule, "Événement « $event » détecté");
    }
}

/**
 * Exécute l'action associée à une règle : commande d'équipement et/ou
 * notifications Telegram / e-mail.
 */
function executeRule(array $rule, string $reason): void
{
    $resultParts = [$reason];

    if ($rule['action_equipment_id'] && $rule['action_state'] !== null) {
        $equipment = \App\Models\Equipment::find((int) $rule['action_equipment_id']);
        if ($equipment) {
            \App\Models\Equipment::update($equipment['id'], ['state' => (int) $rule['action_state']]);
            Publisher::publish($equipment['mqtt_topic'] . '/set', (string) (int) $rule['action_state']);
            $resultParts[] = "Équipement « {$equipment['name']} » mis à l'état {$rule['action_state']}";
        }
    }

    if ($rule['notify_telegram']) {
        \App\Helpers\Notifier::sendTelegram("Règle « {$rule['name']} » déclenchée : $reason");
        $resultParts[] = 'Notification Telegram envoyée';
    }

    if ($rule['notify_email']) {
        \App\Helpers\Notifier::sendAlertEmail("Règle « {$rule['name']} » déclenchée", $reason);
        $resultParts[] = 'Notification e-mail envoyée';
    }

    AutomationRule::logExecution((int) $rule['id'], implode(' — ', $resultParts));
    app_log("[Automation] Règle « {$rule['name']} » exécutée : " . implode(' — ', $resultParts));
}
