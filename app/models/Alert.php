<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

/**
 * Class Alert
 *
 * Modèle représentant une alerte générée par le système : intrusion,
 * événement réseau, dépassement de seuil capteur, événement système.
 */
class Alert extends Model
{
    protected static string $table = 'alerts';

    public static function recent(int $limit = 10): array
    {
        $stmt = Database::getInstance()->prepare('SELECT * FROM alerts ORDER BY created_at DESC LIMIT :limit');
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function countUnread(): int
    {
        $row = Database::query('SELECT COUNT(*) AS c FROM alerts WHERE is_read = 0')->fetch();
        return (int) ($row['c'] ?? 0);
    }

    public static function markAsRead(int $id): bool
    {
        return self::update($id, ['is_read' => 1]);
    }

    public static function markAllAsRead(): void
    {
        Database::query('UPDATE alerts SET is_read = 1 WHERE is_read = 0');
    }

    public static function create(array $data): int
    {
        $data += ['severity' => 'info', 'is_read' => 0];
        return parent::create($data);
    }
}
