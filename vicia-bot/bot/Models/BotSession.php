<?php

namespace Bot\Models;

use Bot\Core\Model;

/**
 * Class BotSession
 *
 * Persistance brute de l'état de conversation (voir
 * Bot\Services\SessionService, qui porte la logique métier — expiration,
 * fusion de payload). Une ligne par utilisateur Telegram au maximum
 * (contrainte d'unicité sur telegram_id) : démarrer un nouveau flux
 * remplace silencieusement tout flux précédent inachevé.
 */
class BotSession extends Model
{
    protected static string $table = 'bot_sessions';

    public static function findByTelegramId(int $telegramId): ?array
    {
        return self::findOneBy('telegram_id', $telegramId);
    }

    public static function upsert(int $telegramId, string $state, array $payload, string $expiresAt): void
    {
        $existing = self::findByTelegramId($telegramId);
        $data = [
            'state'      => $state,
            'payload'    => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'expires_at' => $expiresAt,
        ];

        if ($existing) {
            self::update($existing['id'], $data);
            return;
        }

        $data['telegram_id'] = $telegramId;
        self::create($data);
    }

    public static function clearByTelegramId(int $telegramId): void
    {
        self::deleteWhere('telegram_id', $telegramId);
    }

    public static function purgeExpired(): int
    {
        $stmt = self::db()->prepare('DELETE FROM bot_sessions WHERE expires_at < NOW()');
        $stmt->execute();
        return $stmt->rowCount();
    }
}
