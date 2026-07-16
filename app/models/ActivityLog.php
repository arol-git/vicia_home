<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

/**
 * Class ActivityLog
 *
 * Journal général des actions effectuées par les utilisateurs
 * (audit applicatif), utilisé par le module "Historique".
 */
class ActivityLog extends Model
{
    protected static string $table = 'activity_logs';

    /**
     * Enregistre une action utilisateur. Appelée depuis les
     * contrôleurs après toute opération de création, modification ou
     * suppression significative.
     */
    public static function record(?int $userId, string $action, string $description, string $ip): int
    {
        return self::create([
            'user_id'     => $userId,
            'action'      => $action,
            'description' => $description,
            'ip_address'  => $ip,
        ]);
    }

    public static function recent(int $limit = 15): array
    {
        $sql = "SELECT al.*, u.name AS user_name
                FROM activity_logs al
                LEFT JOIN users u ON u.id = al.user_id
                ORDER BY al.created_at DESC LIMIT :limit";
        $stmt = Database::getInstance()->prepare($sql);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function paginated(int $limit, int $offset): array
    {
        $sql = "SELECT al.*, u.name AS user_name
                FROM activity_logs al
                LEFT JOIN users u ON u.id = al.user_id
                ORDER BY al.created_at DESC LIMIT :limit OFFSET :offset";
        $stmt = Database::getInstance()->prepare($sql);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
