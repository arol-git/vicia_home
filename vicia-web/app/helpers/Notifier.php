<?php

namespace App\Helpers;

use App\Core\Database;

/**
 * Class Notifier
 *
 * Envoie les notifications déclenchées par le moteur d'automatisation
 * ou par le module de cybersécurité, POUR UNE MAISON DONNÉE : messages
 * Telegram (via l'API Bot HTTP) et e-mails d'alerte (via Mailer).
 *
 * Le jeton Telegram et l'adresse e-mail d'alerte sont propres à
 * chaque maison (colonnes `telegram_bot_token`, `telegram_chat_id`,
 * `alert_email` de la table `houses`), configurables par le
 * propriétaire depuis le module "Mes maisons".
 */
class Notifier
{
    public static function sendTelegram(int $houseId, string $message): bool
    {
        $house = self::house($houseId);
        $token  = $house['telegram_bot_token'] ?? '';
        $chatId = $house['telegram_chat_id'] ?? '';

        if (!$token || !$chatId) {
            app_log("[Notifier] Notification Telegram ignorée pour la maison #$houseId : jeton ou identifiant de discussion non configuré.");
            return false;
        }

        $url = "https://api.telegram.org/bot{$token}/sendMessage";
        $payload = http_build_query([
            'chat_id' => $chatId,
            'text'    => '🏠 ' . ($house['name'] ?? 'Vicia Home') . "\n" . $message,
        ]);

        $context = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $payload,
                'timeout' => 5,
            ],
        ]);

        $result = @file_get_contents($url, false, $context);

        if ($result === false) {
            app_log("[Notifier] Échec de l’envoi de la notification Telegram pour la maison #$houseId.");
            return false;
        }

        return true;
    }

    public static function sendAlertEmail(int $houseId, string $subject, string $message): bool
    {
        $house = self::house($houseId);
        $to = $house['alert_email'] ?? null;

        if (!$to) {
            return false;
        }

        return Mailer::send($to, "[" . ($house['name'] ?? 'Vicia Home') . "] $subject", $message);
    }

    private static function house(int $houseId): ?array
    {
        static $cache = [];
        if (!isset($cache[$houseId])) {
            $cache[$houseId] = Database::query('SELECT * FROM houses WHERE id = :id LIMIT 1', ['id' => $houseId])->fetch() ?: null;
        }
        return $cache[$houseId];
    }
}
