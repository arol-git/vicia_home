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
 * Le jeton du bot Telegram reste global, tandis que les destinations
 * Telegram et e-mail peuvent être définies par chaque utilisateur
 * depuis son profil.
 */
class Notifier
{
    public static function sendTelegram(string $message): bool
    {
        $settings = self::settings();
        $token  = $settings['telegram_bot_token'] ?? '';
        $chatIds = self::telegramRecipients($settings);

        if (!$token || empty($chatIds)) {
            app_log('[Notifier] Notification Telegram ignorée : jeton ou destinataire Telegram non configuré.');
            return false;
        }

        $url = "https://api.telegram.org/bot{$token}/sendMessage";
        $sent = false;

        foreach ($chatIds as $chatId) {
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
            $sent = $sent || $result !== false;
        }

        if (!$sent) {
            app_log('[Notifier] Échec de l’envoi des notifications Telegram.');
        }

        return $sent;
    }

    public static function sendAlertEmail(string $subject, string $message): bool
    {
        $recipients = self::emailRecipients();

        if (empty($recipients)) {
            return false;
        }

        $sent = false;
        foreach ($recipients as $to) {
            $sent = Mailer::send($to, "[Vicia Home] $subject", $message) || $sent;
        }

        return $sent;
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

    private static function telegramRecipients(array $settings): array
    {
        $recipients = [];

        if (!empty($settings['telegram_chat_id'])) {
            $recipients[] = $settings['telegram_chat_id'];
        }

        foreach ($settings as $key => $value) {
            if (str_ends_with($key, '_telegram_chat_id') && trim((string) $value) !== '') {
                $recipients[] = trim((string) $value);
            }
        }

        return array_values(array_unique($recipients));
    }

    private static function emailRecipients(): array
    {
        $rows = Database::query(
            "SELECT u.email,
                    se.setting_value AS notification_email
             FROM users u
             LEFT JOIN settings se
               ON se.setting_key = CONCAT('user_', u.id, '_notification_email')
             WHERE u.status = 'active'"
        )->fetchAll();

        $emails = [];
        foreach ($rows as $row) {
            $email = trim((string) ($row['notification_email'] ?: $row['email']));
            if ($email !== '') {
                $emails[] = $email;
            }
        }

        return array_values(array_unique($emails));
    }
}
