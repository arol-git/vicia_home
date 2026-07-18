<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

/**
 * Class Alert
 *
 * Modèle représentant une alerte générée par le système : intrusion,
 * événement réseau, dépassement de seuil capteur, événement système.
 * Rattachée à une maison (`house_id`), sauf pour de rares alertes
 * transverses à toute la plateforme.
 */
class Alert extends Model
{
    protected static string $table = 'alerts';

    public static function forHouse(int $houseId, string $orderBy = 'created_at DESC'): array
    {
        return Database::query(
            "SELECT * FROM alerts WHERE house_id = :house_id ORDER BY $orderBy",
            ['house_id' => $houseId]
        )->fetchAll();
    }

    public static function recent(int $houseId, int $limit = 10): array
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT * FROM alerts WHERE house_id = :house_id ORDER BY created_at DESC LIMIT :limit'
        );
        $stmt->bindValue(':house_id', $houseId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function countUnread(int $houseId): int
    {
        $row = Database::query(
            'SELECT COUNT(*) AS c FROM alerts WHERE house_id = :house_id AND is_read = 0',
            ['house_id' => $houseId]
        )->fetch();
        return (int) ($row['c'] ?? 0);
    }

    public static function belongsToHouse(int $alertId, int $houseId): bool
    {
        $row = Database::query(
            'SELECT id FROM alerts WHERE id = :id AND house_id = :house_id LIMIT 1',
            ['id' => $alertId, 'house_id' => $houseId]
        )->fetch();
        return (bool) $row;
    }

    public static function markAsRead(int $id): bool
    {
        return self::update($id, ['is_read' => 1]);
    }

    public static function markAllAsRead(int $houseId): void
    {
        Database::query('UPDATE alerts SET is_read = 1 WHERE house_id = :house_id AND is_read = 0', ['house_id' => $houseId]);
    }

    public static function create(array $data): int
    {
        $data += ['severity' => 'info', 'is_read' => 0];
        return parent::create($data);
    }
}
