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
 * rattachée à une maison : toutes les requêtes de listage sont donc
 * scopées par `house_id` pour garantir l'isolation entre maisons.
 */
class Equipment extends Model
{
    protected static string $table = 'equipments';

    /**
     * Retourne tous les équipements d'UNE maison, avec le nom de leur
     * pièce associée.
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
     * Vérifie qu'un équipement appartient bien à la maison donnée
     * (par transitivité via sa pièce), avant toute action dessus.
     */
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

    /**
     * Compte les équipements actuellement allumés/ouverts (state = 1)
     * pour une maison donnée.
     */
    public static function countActive(int $houseId): int
    {
        $sql = "SELECT COUNT(*) AS c FROM equipments eq
                INNER JOIN rooms r ON r.id = eq.room_id
                WHERE r.house_id = :house_id AND eq.state = 1";
        $row = Database::query($sql, ['house_id' => $houseId])->fetch();
        return (int) ($row['c'] ?? 0);
    }

    /**
     * Compte le nombre total d'équipements d'une maison.
     */
    public static function countForHouse(int $houseId): int
    {
        $sql = "SELECT COUNT(*) AS c FROM equipments eq
                INNER JOIN rooms r ON r.id = eq.room_id
                WHERE r.house_id = :house_id";
        $row = Database::query($sql, ['house_id' => $houseId])->fetch();
        return (int) ($row['c'] ?? 0);
    }
}
