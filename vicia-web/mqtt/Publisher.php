<?php

namespace Mqtt;

use App\Core\Database;

/**
 * Class Publisher
 *
 * Point d'entrée simple utilisé par les contrôleurs Web pour publier
 * une commande vers le broker MQTT (par exemple, basculer l'état
 * d'un équipement). Ouvre une connexion courte, publie le message,
 * puis se déconnecte : ce choix convient au rythme de commandes
 * d'une interface utilisateur (quelques messages par seconde au
 * maximum). Pour un trafic plus soutenu, il est recommandé de faire
 * transiter les commandes par une file d'attente consommée par le
 * démon mqtt/subscriber.php plutôt que d'ouvrir une connexion MQTT
 * par requête HTTP.
 */
class Publisher
{
    public static function publish(string $topic, string $payload): bool
    {
        $config = require __DIR__ . '/config.php';

        $client = new MqttClient($config);

        if (!$client->connect()) {
            app_log("[MQTT Publisher] Échec de connexion au broker — commande non envoyée ($topic)");
            self::log($topic, $payload, 'out', false);
            return false;
        }

        $client->publish($topic, $payload);
        $client->disconnect();

        self::log($topic, $payload, 'out', true);

        return true;
    }

    private static function log(string $topic, string $payload, string $direction, bool $success): void
    {
        try {
            Database::query(
                'INSERT INTO mqtt_logs (topic, payload, direction, created_at) VALUES (:topic, :payload, :direction, NOW())',
                ['topic' => $topic, 'payload' => $payload . ($success ? '' : ' [échec envoi]'), 'direction' => $direction]
            );
        } catch (\Throwable $e) {
            // La journalisation MQTT ne doit jamais faire échouer la
            // requête utilisateur : on se contente de logger l'erreur.
            app_log('[MQTT Publisher] Erreur de journalisation : ' . $e->getMessage());
        }
    }
}
