<?php

namespace Bot\Models;

use Bot\Core\Model;

/**
 * Class NotificationLog
 *
 * Traçabilité des notifications poussées envoyées (voir
 * Bot\Services\NotificationDispatcher). La contrainte d'unicité
 * (alert_id, telegram_id) en base garantit l'idempotence : un webhook
 * rejoué par la plateforme ne renvoie jamais deux fois la même alerte
 * au même utilisateur.
 */
class NotificationLog extends Model
{
    protected static string $table = 'notification_log';

    public static function alreadySent(?int $alertId, int $telegramId): bool
    {
        if ($alertId === null) {
            return false; // notifications sans alerte associée (rapport à la demande) : jamais idempotentes
        }
        $stmt = self::db()->prepare('SELECT id FROM notification_log WHERE alert_id = :aid AND telegram_id = :tid LIMIT 1');
        $stmt->execute(['aid' => $alertId, 'tid' => $telegramId]);
        return (bool) $stmt->fetch();
    }

    public static function record(int $houseId, ?int $alertId, int $telegramId, string $status): void
    {
        try {
            self::create(['house_id' => $houseId, 'alert_id' => $alertId, 'telegram_id' => $telegramId, 'status' => $status]);
        } catch (\PDOException $e) {
            if ($e->getCode() !== '23000') { // doublon (alert_id, telegram_id) : silencieusement ignoré, cas nominal
                throw $e;
            }
        }
    }
}
