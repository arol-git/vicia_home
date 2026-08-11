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

// Chaque topic est de la forme home/<slug-maison>/<domaine>/<zone>/<grandeur>
// (voir database/sql/schema.sql) : le segment <slug-maison> isole les
// messages de chaque maison sur le broker partagé.
$baseTopic = $config['base_topic'];
$client->subscribe([
    "$baseTopic/+/+/+/+",        // télémétrie des capteurs
    "$baseTopic/+/security/#",   // événements de sécurité
    "$baseTopic/+/network/#",    // événements réseau (module cybersécurité)
]);

echo "Abonnement actif. En attente de messages...\n";

/**
 * Résout la maison propriétaire d'un message à partir du deuxième
 * segment de son topic (le slug), avec mise en cache mémoire pour
 * éviter une requête base de données par message reçu.
 */
function resolveHouseIdFromTopic(string $topic): ?int
{
    static $cache = [];
    $segments = explode('/', $topic);
    $slug = $segments[1] ?? null;
    if (!$slug) {
        return null;
    }
    if (!array_key_exists($slug, $cache)) {
        $house = Database::query('SELECT id FROM houses WHERE slug = :slug LIMIT 1', ['slug' => $slug])->fetch();
        $cache[$slug] = $house ? (int) $house['id'] : null;
    }
    return $cache[$slug];
}

$client->loop(function (string $topic, string $payload) {
    app_log("[MQTT Subscriber] Message reçu — topic: $topic | payload: $payload");

    Database::query(
        'INSERT INTO mqtt_logs (topic, payload, direction, created_at) VALUES (:topic, :payload, :direction, NOW())',
        ['topic' => $topic, 'payload' => $payload, 'direction' => 'in']
    );

    // --- Cas 1 : message de télémétrie d'un capteur connu ---
    // Le capteur est retrouvé par son topic complet, ce qui détermine
    // sans ambiguïté sa maison par transitivité (capteur -> pièce -> maison).
    // On vérifie en outre que la carte ESP32 qui l'héberge est bien
    // appairée et non révoquée, avant d'accepter la mesure.
    $sensor = Sensor::findByTopic($topic);
    if ($sensor && is_numeric($payload)) {
        if ($sensor['device_id'] && !deviceIsPaired((int) $sensor['device_id'])) {
            app_log("[MQTT Subscriber] Message ignoré : la carte associée au capteur #{$sensor['id']} n'est pas (ou plus) appairée.");
            return;
        }
        if ($sensor['device_id']) {
            \App\Models\Device::touchLastSeen((int) $sensor['device_id']);
        }
        Sensor::recordReading((int) $sensor['id'], (float) $payload);
        evaluateSensorRules((int) $sensor['id'], (float) $payload);
        return;
    }

    // --- Cas 2 : événement de sécurité (intrusion, PIR déclenché...) ---
    if (str_contains($topic, '/security/') && $payload === '1') {
        $houseId = resolveHouseIdFromTopic($topic);
        if ($houseId === null) {
            app_log("[MQTT Subscriber] Topic $topic ignoré : aucune maison ne correspond à ce slug.");
            return;
        }

        \App\Models\Alert::create([
            'house_id' => $houseId,
            'type'     => 'intrusion',
            'severity' => 'critical',
            'source'   => $topic,
            'message'  => "Détection de mouvement / intrusion sur le topic $topic",
        ]);
        evaluateEventRules($houseId, 'intrusion');
    }
}, 0);

/**
 * Vérifie qu'une carte ESP32 a le statut "paired" (appairée et non
 * révoquée), avec mise en cache mémoire pour la durée du processus.
 */
function deviceIsPaired(int $deviceId): bool
{
    static $cache = [];
    if (!array_key_exists($deviceId, $cache)) {
        $row = Database::query('SELECT status FROM devices WHERE id = :id LIMIT 1', ['id' => $deviceId])->fetch();
        $cache[$deviceId] = $row && $row['status'] === 'paired';
    }
    return $cache[$deviceId];
}

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
 * Évalue les règles d'automatisation d'UNE maison déclenchées par un
 * événement système (ex. intrusion, appareil réseau inconnu).
 */
function evaluateEventRules(int $houseId, string $event): void
{
    $rules = AutomationRule::activeForEvent($houseId, $event);
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
                Equipment::update($equipment['id'], ['state' => (int) $rule['action_state']]);
                $topic = Publisher::topicForEquipment($equipment['mqtt_topic'] ?? null, $rule['house_id'] ?? null, (int) $equipment['id'], 'set');
                Publisher::publish($topic, (string) (int) $rule['action_state']);
                $resultParts[] = "Équipement « {$equipment['name']} » mis à l'état {$rule['action_state']}";
            }
    }

    if ($rule['notify_telegram']) {
        \App\Helpers\Notifier::sendTelegram((int) $rule['house_id'], "Règle « {$rule['name']} » déclenchée : $reason");
        $resultParts[] = 'Notification Telegram envoyée';
    }

    if ($rule['notify_email']) {
        \App\Helpers\Notifier::sendAlertEmail((int) $rule['house_id'], "Règle « {$rule['name']} » déclenchée", $reason);
        $resultParts[] = 'Notification e-mail envoyée';
    }

    AutomationRule::logExecution((int) $rule['id'], implode(' — ', $resultParts));
    app_log("[Automation] Règle « {$rule['name']} » exécutée : " . implode(' — ', $resultParts));
}
