<?php

namespace Bot\Services;

use Bot\Config\App;
use Bot\Core\Logger;
use Bot\Models\NotificationLog;
use Bot\Models\TelegramUser;
use Telegram\Bot\Api;
use Telegram\Bot\Exceptions\TelegramSDKException;

/**
 * Class NotificationDispatcher
 *
 * Diffuse une notification poussée (intrusion, fumée, gaz, appareil
 * inconnu, ESP32 hors ligne...) à tous les utilisateurs Telegram
 * ayant actuellement sélectionné la maison concernée. Appelée par
 * Bot\Controllers\AlertWebhookController, jamais directement par un
 * contrôleur de commande (qui répond, lui, à un update Telegram).
 */
class NotificationDispatcher
{
    private const SEVERITY_EMOJI = ['info' => 'ℹ️', 'warning' => '⚠️', 'critical' => '🚨'];

    /**
     * @return int Nombre d'envois effectivement réalisés (hors doublons idempotents)
     */
    public static function dispatchAlert(int $houseId, array $alert): int
    {
        $telegram = new Api(App::env('TELEGRAM_BOT_TOKEN'));
        $recipients = TelegramUser::allForHouse($houseId);
        $sent = 0;

        foreach ($recipients as $user) {
            if (NotificationLog::alreadySent($alert['id'] ?? null, $user['telegram_id'])) {
                continue;
            }

            $emoji = self::SEVERITY_EMOJI[$alert['severity']] ?? '🔔';
            $text = "$emoji <b>Alerte Vicia Home</b>\n\n{$alert['message']}";

            $status = 'failed';
            try {
                $telegram->sendMessage([
                    'chat_id'      => $user['telegram_id'],
                    'text'         => $text,
                    'parse_mode'   => 'HTML',
                    'reply_markup' => json_encode(['inline_keyboard' => [[
                        ['text' => '✅ Marquer comme lue', 'callback_data' => "alert:read:{$alert['id']}"],
                    ]]]),
                ]);
                $status = 'sent';
                $sent++;
            } catch (TelegramSDKException $e) {
                Logger::channel('bot')->warning("Échec d'envoi de notification à telegram_id={$user['telegram_id']} : " . $e->getMessage());
            }

            NotificationLog::record($houseId, $alert['id'] ?? null, $user['telegram_id'], $status);
        }

        return $sent;
    }
}
