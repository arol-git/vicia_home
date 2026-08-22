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
use App\Services\TelemetryService;
use Mqtt\MqttClient;
use Mqtt\Publisher;

$config = require __DIR__ . '/config.php';
$client = new MqttClient($config);

set_exception_handler(static function (\Throwable $exception): void {
    $message = '[MQTT Subscriber] Arrêt inattendu : ' . $exception->getMessage();
    fwrite(STDERR, $message . PHP_EOL);
    app_log($message . ' | fichier=' . $exception->getFile() . ' | ligne=' . $exception->getLine());
    exit(1);
});

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
    echo "[" . date('Y-m-d H:i:s') . "] ✓ MQTT TOPIC REÇU: $topic\n";
    echo "[" . date('Y-m-d H:i:s') . "] PAYLOAD: $payload\n";
    app_log("[MQTT Subscriber] Message reçu — topic: $topic | payload: $payload");

    Database::query(
        'INSERT INTO mqtt_logs (topic, payload, direction, created_at) VALUES (:topic, :payload, :direction, NOW())',
        ['topic' => $topic, 'payload' => $payload, 'direction' => 'in']
    );

    // --- Cas 1 : message de télémétrie d'un capteur connu ---
    // Accepte un payload numérique brut ou JSON, ex. {"value":25.4}.
    echo "[" . date('Y-m-d H:i:s') . "] → Tentative d'ingestion télémétrie...\n";
    $telemetry = TelemetryService::ingest($topic, $payload);
    if (!empty($telemetry['saved'])) {
        echo "[" . date('Y-m-d H:i:s') . "] ✓ Télémétrie sauvegardée: " . count($telemetry['saved']) . " lecture(s)\n";
        foreach ($telemetry['saved'] as $reading) {
            echo "[" . date('Y-m-d H:i:s') . "] → Évaluation règles pour capteur #" . $reading['sensor_id'] . " (valeur={$reading['value']})\n";
            evaluateSensorRules((int) $reading['sensor_id'], (float) $reading['value']);
            evaluateSensorThreshold($reading);
        }
        return;
    }

    // --- Cas 2 : événement de sécurité (intrusion, PIR déclenché...) ---
    if (str_contains(strtolower($topic), '/security/') && trim($payload) === '1') {
        echo "[" . date('Y-m-d H:i:s') . "] ⚠️  ÉVÉNEMENT SÉCURITÉ détecté sur: $topic\n";
        $houseId = resolveHouseIdFromTopic($topic);
        if ($houseId === null) {
            echo "[" . date('Y-m-d H:i:s') . "] ✗ ERREUR: Aucune maison trouvée pour le slug du topic $topic\n";
            app_log("[MQTT Subscriber] Topic $topic ignoré : aucune maison ne correspond à ce slug.");
            return;
        }
        echo "[" . date('Y-m-d H:i:s') . "] ✓ Maison trouvée #$houseId pour le topic\n";

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
 * Évalue les règles d'automatisation dont la condition porte sur le
 * capteur ayant émis la nouvelle mesure, et déclenche les actions
 * correspondantes si la condition est satisfaite.
 */
function evaluateSensorRules(int $sensorId, float $value): void
{
    echo "[" . date('Y-m-d H:i:s') . "] 🔍 Recherche règles pour capteur #$sensorId (valeur=$value)\n";
    $rules = AutomationRule::activeForSensor($sensorId);
    echo "[" . date('Y-m-d H:i:s') . "] → Trouvé " . count($rules) . " règle(s) active(s)\n";

    foreach ($rules as $rule) {
        echo "[" . date('Y-m-d H:i:s') . "] ✓ Vérification règle #" . $rule['id'] . " (" . $rule['condition_operator'] . " " . $rule['condition_value'] . ")\n";
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
            echo "[" . date('Y-m-d H:i:s') . "] ✅ CONDITION VALIDÉE! Exécution de la règle...\n";
            executeRule($rule, "Condition capteur satisfaite (valeur=$value)");
        } else {
            echo "[" . date('Y-m-d H:i:s') . "] ✗ Condition non satisfaite\n";
        }
    }
}

function evaluateSensorThreshold(array $reading): void
{
    $threshold = $reading['alert_threshold'] ?? null;
    if ($threshold === null || (float) $reading['value'] <= (float) $threshold) {
        return;
    }

    $houseId = (int) ($reading['house_id'] ?? 0);
    $sensorId = (int) ($reading['sensor_id'] ?? 0);
    if ($houseId <= 0 || $sensorId <= 0 || \App\Models\Alert::hasRecentSensorAlert($houseId, $sensorId)) {
        return;
    }

    \App\Models\Alert::create([
        'house_id' => $houseId,
        'type' => 'capteur',
        'severity' => 'warning',
        'source' => 'sensor:' . $sensorId,
        'message' => "Le capteur « {$reading['sensor_name']} » a dépassé le seuil de {$threshold} {$reading['unit']} (valeur : {$reading['value']} {$reading['unit']}).",
    ]);
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
    echo "[" . date('Y-m-d H:i:s') . "] 🎬 EXÉCUTION RÈGLE #" . $rule['id'] . ": $reason\n";
    $resultParts = [$reason];

    if ($rule['action_equipment_id'] && $rule['action_state'] !== null) {
        try {
            $equipment = \App\Models\Equipment::find((int) $rule['action_equipment_id']);
            if ($equipment) {
                echo "[" . date('Y-m-d H:i:s') . "] → Commande équipement #{$equipment['id']}...\n";
                \App\Models\Equipment::update($equipment['id'], ['state' => (int) $rule['action_state']]);
                Publisher::publish($equipment['mqtt_topic'] . '/set', (string) (int) $rule['action_state']);
                echo "[" . date('Y-m-d H:i:s') . "] ✓ Commande équipement envoyée\n";
                $resultParts[] = "Équipement « {$equipment['name']} » mis à l'état {$rule['action_state']}";
            }
        } catch (\Throwable $exception) {
            $resultParts[] = 'Commande équipement échouée';
            app_log('[Automation] Commande équipement échouée : ' . $exception->getMessage());
        }
    }

    $notificationMessage = "Règle « {$rule['name']} » déclenchée : $reason";
    \App\Services\NotificationPipeline::dispatch((int) $rule['id'], (int) $rule['house_id'], [
        'EMAIL' => static fn (): bool => !empty($rule['notify_email'])
            && \App\Helpers\Notifier::sendAlertEmail((int) $rule['house_id'], "Règle « {$rule['name']} » déclenchée", $reason),
        'TELEGRAM' => static fn (): bool => !empty($rule['notify_telegram'])
            && \App\Helpers\Notifier::sendTelegram((int) $rule['house_id'], $notificationMessage),
        'PUSH' => static fn (): bool => \App\Helpers\Notifier::sendBrowserPush((int) $rule['house_id'], 'Règle activée', $notificationMessage),
    ]);
    $resultParts[] = 'Notifications traitées dans l’ordre prévu';

    try {
        AutomationRule::logExecution((int) $rule['id'], implode(' — ', $resultParts));
        app_log("[Automation] Règle « {$rule['name']} » exécutée : " . implode(' — ', $resultParts));
        echo "[" . date('Y-m-d H:i:s') . "] ✓ Fin d'exécution règle #{$rule['id']}\n";
    } catch (\Throwable $exception) {
        app_log('[Automation] Journalisation de règle échouée : ' . $exception->getMessage());
        echo "[" . date('Y-m-d H:i:s') . "] ⚠ Journalisation de règle échouée\n";
    }
}
