<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

/**
 * Class LoginLog
 *
 * Journal des tentatives de connexion (réussies et échouées),
 * utilisé pour la traçabilité de sécurité et la détection de
 * tentatives de force brute.
 */
class LoginLog extends Model
{
    protected static string $table = 'login_logs';

    public static function record(?int $userId, string $email, string $ip, string $userAgent, string $status): int
    {
        return self::create([
            'user_id'    => $userId,
            'email_used' => $email,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'status'     => $status,
        ]);
    }

    /**
     * Compte les échecs de connexion récents pour une adresse IP,
     * utilisé pour limiter les tentatives de force brute sur le
     * formulaire de connexion.
     */
    public static function recentFailures(string $ip, int $minutes = 15): int
    {
        $sql = "SELECT COUNT(*) AS c FROM login_logs
                WHERE ip_address = :ip AND status = 'failed'
                AND created_at >= (NOW() - INTERVAL :minutes MINUTE)";
        $stmt = Database::getInstance()->prepare($sql);
        $stmt->bindValue(':ip', $ip);
        $stmt->bindValue(':minutes', $minutes, \PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetch()['c'];
    }

    public static function recent(int $limit = 20): array
    {
        $sql = "SELECT ll.*, u.name AS user_name
                FROM login_logs ll
                LEFT JOIN users u ON u.id = ll.user_id
                ORDER BY ll.created_at DESC LIMIT :limit";
        $stmt = Database::getInstance()->prepare($sql);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
