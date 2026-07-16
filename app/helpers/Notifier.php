<?php

namespace App\Helpers;

use App\Core\Database;

/**
 * Class Notifier
 *
 * Envoie les notifications déclenchées par le moteur d'automatisation
 * ou par le module de cybersécurité : messages Telegram (via l'API
 * Bot HTTP) et e-mails d'alerte (via la classe Mailer).
 *
 * Les identifiants Telegram (jeton de bot et identifiant de
 * discussion) sont lus dans la table `settings`, configurable depuis
 * le module Paramètres de la plateforme.
 */
class Notifier
{
    public static function sendTelegram(string $message): bool
    {
        $settings = self::settings();
        $token  = $settings['telegram_bot_token'] ?? '';
        $chatId = $settings['telegram_chat_id'] ?? '';

        if (!$token || !$chatId) {
            app_log('[Notifier] Notification Telegram ignorée : jeton ou identifiant de discussion non configuré.');
            return false;
        }

        $url = "https://api.telegram.org/bot{$token}/sendMessage";
        $payload = http_build_query([
            'chat_id' => $chatId,
            'text'    => "🏠 Vicia Home\n" . $message,
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
            app_log('[Notifier] Échec de l’envoi de la notification Telegram.');
            return false;
        }

        return true;
    }

    public static function sendAlertEmail(string $subject, string $message): bool
    {
        $settings = self::settings();
        $to = $settings['smtp_from'] ?? null;

        if (!$to) {
            return false;
        }

        return Mailer::send($to, "[Vicia Home] $subject", $message);
    }

    private static function settings(): array
    {
        static $cache = null;
        if ($cache === null) {
            $rows  = Database::query('SELECT setting_key, setting_value FROM settings')->fetchAll();
            $cache = array_column($rows, 'setting_value', 'setting_key');
        }
        return $cache;
    }
}
