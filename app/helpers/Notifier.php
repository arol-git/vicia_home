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
    public static function sendTelegram($houseIdOrMessage, ?string $message = null): bool
    {
        $houseId = is_int($houseIdOrMessage) ? $houseIdOrMessage : null;
        $message = $message ?? (string) $houseIdOrMessage;
        $recipients = self::telegramRecipients(self::settings(), $houseId);

        if (empty($recipients)) {
            app_log('[Notifier] Notification Telegram ignorée : aucun destinataire Telegram configuré.');
            return false;
        }

        $sent = false;

        foreach ($recipients as $recipient) {
            $url = "https://api.telegram.org/bot{$recipient['token']}/sendMessage";
            $payload = http_build_query([
                'chat_id' => $recipient['chat_id'],
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

    public static function sendAlertEmail($houseIdOrSubject, string $subjectOrMessage, ?string $message = null): bool
    {
        $houseId = is_int($houseIdOrSubject) ? $houseIdOrSubject : null;
        $subject = $message === null ? (string) $houseIdOrSubject : $subjectOrMessage;
        $body = $message ?? $subjectOrMessage;
        $recipients = self::emailRecipients($houseId);

        if (empty($recipients)) {
            app_log('[Notifier] E-mail ignoré : aucun destinataire configuré pour la maison ' . ($houseId ?? 'globale') . '.');
            return false;
        }

        $sent = false;
        foreach ($recipients as $to) {
            $sent = Mailer::send($to, "[Vicia Home] $subject", $body) || $sent;
        }

        return $sent;
    }

    public static function sendBrowserPush(int $houseId, string $title, string $body, ?array $data = null): bool
    {
        return \App\Services\PushNotificationService::sendToHouse($houseId, $title, $body, $data);
    }

    private static function settings(): array
    {
        static $cache = null;
        if ($cache === null) {
            $cache = \App\Models\Setting::all();
        }
        return $cache;
    }

    private static function telegramRecipients(array $settings, ?int $houseId = null): array
    {
        $defaultToken = $settings['telegram_bot_token'] ?? '';
        $recipients = [];

        if ($houseId === null && $defaultToken && !empty($settings['telegram_chat_id'])) {
            $recipients[] = ['token' => $defaultToken, 'chat_id' => $settings['telegram_chat_id']];
        }

        foreach (self::notificationUsers($houseId) as $user) {
            $chatId = trim((string) (($user['telegram_name'] ?? '') ?: ($settings['user_' . $user['id'] . '_telegram_name'] ?? '') ?: ($settings['user_' . $user['id'] . '_telegram_chat_id'] ?? '')));
            $token = $defaultToken;
            if ($token && $chatId) {
                $recipients[] = ['token' => $token, 'chat_id' => $chatId];
            }
        }

        if ($houseId === null) {
            foreach ($settings as $key => $value) {
                if ($defaultToken && str_ends_with($key, '_telegram_chat_id') && trim((string) $value) !== '') {
                    $recipients[] = ['token' => $defaultToken, 'chat_id' => trim((string) $value)];
                }
            }
        }

        $unique = [];
        foreach ($recipients as $recipient) {
            $unique[$recipient['token'] . '|' . $recipient['chat_id']] = $recipient;
        }

        return array_values($unique);
    }

    private static function emailRecipients(?int $houseId = null): array
    {
        $emails = [];
        $settings = self::settings();
        foreach (self::notificationUsers($houseId) as $user) {
            $email = trim((string) (($user['notification_email'] ?? '') ?: ($settings['user_' . $user['id'] . '_notification_email'] ?? '') ?: $user['email']));
            if ($email !== '') {
                $emails[] = $email;
            }
        }

        return array_values(array_unique($emails));
    }

    private static function notificationUsers(?int $houseId): array
    {
        self::ensureUserNotificationColumns();
        $select = 'u.id, u.email, u.notification_email, u.telegram_name';
        if ($houseId === null) {
            return Database::query("SELECT $select FROM users u WHERE u.status = 'active'")->fetchAll();
        }

        return Database::query(
            "SELECT $select
             FROM users u
             INNER JOIN house_user hu ON hu.user_id = u.id
             WHERE u.status = 'active' AND hu.house_id = :house_id",
            ['house_id' => $houseId]
        )->fetchAll();
    }

    private static function ensureUserNotificationColumns(): void
    {
        \App\Models\User::notificationSettings(0);
    }
}
