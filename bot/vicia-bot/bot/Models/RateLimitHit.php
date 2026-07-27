<?php

namespace Bot\Models;

use Bot\Core\Model;

/**
 * Class RateLimitHit
 *
 * Fenêtre glissante de requêtes par utilisateur Telegram, consultée
 * par Bot\Middlewares\RateLimitMiddleware.
 */
class RateLimitHit extends Model
{
    protected static string $table = 'rate_limit_hits';

    /**
     * Compte les requêtes d'un utilisateur dans les $windowSeconds
     * dernières secondes.
     */
    public static function countRecent(int $telegramId, int $windowSeconds): int
    {
        $stmt = self::db()->prepare(
            'SELECT COUNT(*) AS c FROM rate_limit_hits
             WHERE telegram_id = :telegram_id AND hit_at >= (NOW() - INTERVAL :window SECOND)'
        );
        $stmt->bindValue(':telegram_id', $telegramId, \PDO::PARAM_INT);
        $stmt->bindValue(':window', $windowSeconds, \PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetch()['c'];
    }

    public static function record(int $telegramId): void
    {
        self::create(['telegram_id' => $telegramId]);
    }

    /**
     * Purge les entrées plus anciennes que $olderThanSeconds. Appelée
     * de façon probabiliste par le middleware plutôt qu'à chaque
     * requête, pour ne pas alourdir le chemin critique.
     */
    public static function purgeOlderThan(int $olderThanSeconds): int
    {
        $stmt = self::db()->prepare('DELETE FROM rate_limit_hits WHERE hit_at < (NOW() - INTERVAL :seconds SECOND)');
        $stmt->bindValue(':seconds', $olderThanSeconds, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
    }
}
