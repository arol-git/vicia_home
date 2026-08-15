<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

/**
 * Class Equipment
 *
 * Modèle représentant un équipement pilotable (actionneur) : LED,
 * relais, ventilateur, pompe, servo-moteur, porte, fenêtre, sirène
 * ou caméra. Un équipement appartient à une pièce, elle-même
 * rattachée à une maison.
 */
class Equipment extends Model
{
    protected static string $table = 'equipments';

    /**
     * Retourne tous les équipements avec le nom de leur pièce associée.
     */
    public static function allWithRoom(int $houseId): array
    {
        $sql = "SELECT eq.*, r.name AS room_name
                FROM equipments eq
                INNER JOIN rooms r ON r.id = eq.room_id
                WHERE r.house_id = :house_id
                ORDER BY r.name ASC, eq.name ASC";
        return Database::query($sql, ['house_id' => $houseId])->fetchAll();
    }

    public static function activeForHouse(int $houseId): array
    {
        $sql = "SELECT eq.*
                FROM equipments eq
                INNER JOIN rooms r ON r.id = eq.room_id
                WHERE r.house_id = :house_id AND eq.is_active = 1
                ORDER BY eq.name ASC";
        return Database::query($sql, ['house_id' => $houseId])->fetchAll();
    }

    public static function findWithRoom(int $id): ?array
    {
        $sql = "SELECT eq.*, r.name AS room_name, r.house_id
                FROM equipments eq
                INNER JOIN rooms r ON r.id = eq.room_id
                WHERE eq.id = :id LIMIT 1";
        $row = Database::query($sql, ['id' => $id])->fetch();
        return $row ?: null;
    }

    /**
     * Récupère plusieurs équipements d'un coup.
     * Optimisé pour les requêtes batch : retourne un array [id => equipment]
     * et vérifie les permissions de la maison.
     *
     * @param int[] $ids IDs des équipements
     * @param int $houseId Maison pour vérification d'accès
     * @return array{int => array} id => equipment
     */
    public static function findMultiple(array $ids, int $houseId): array
    {
        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT eq.*, r.name AS room_name, r.house_id
                FROM equipments eq
                INNER JOIN rooms r ON r.id = eq.room_id
                WHERE eq.id IN ($placeholders) AND r.house_id = ?
                ORDER BY eq.id ASC";

        $params = array_merge($ids, [$houseId]);
        $rows = Database::query($sql, $params)->fetchAll();

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['id']] = $row;
        }
        return $result;
    }

    public static function belongsToHouse(int $equipmentId, int $houseId): bool
    {
        $sql = "SELECT eq.id FROM equipments eq
                INNER JOIN rooms r ON r.id = eq.room_id
                WHERE eq.id = :id AND r.house_id = :house_id LIMIT 1";
        return (bool) Database::query($sql, ['id' => $equipmentId, 'house_id' => $houseId])->fetch();
    }

    /**
     * Bascule l'état d'un équipement (0 <-> 1) et retourne le nouvel état.
     */
    public static function toggleState(int $id): int
    {
        $equipment = self::find($id);
        if (!$equipment) {
            throw new \RuntimeException('Équipement introuvable.');
        }
        $newState = $equipment['state'] ? 0 : 1;
        self::update($id, ['state' => $newState]);
        return $newState;
    }

    public static function setState(int $id, int $state): void
    {
        self::update($id, ['state' => $state ? 1 : 0]);
    }

    /**
     * Compte les équipements actuellement allumés/ouverts (state = 1).
     */
    public static function countActive(int $houseId): int
    {
        $sql = "SELECT COUNT(*) AS c FROM equipments eq
                INNER JOIN rooms r ON r.id = eq.room_id
                WHERE r.house_id = :house_id AND eq.state = 1";
        $row = Database::query($sql, ['house_id' => $houseId])->fetch();
        return (int) ($row['c'] ?? 0);
    }

    public static function countForHouse(int $houseId): int
    {
        $sql = "SELECT COUNT(*) AS c FROM equipments eq
                INNER JOIN rooms r ON r.id = eq.room_id
                WHERE r.house_id = :house_id";
        $row = Database::query($sql, ['house_id' => $houseId])->fetch();
        return (int) ($row['c'] ?? 0);
    }
}
