<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

/**
 * Class Equipment
 *
 * Modèle représentant un équipement pilotable (actionneur) : LED,
 * relais, ventilateur, pompe, servo-moteur, porte, fenêtre, sirène
 * ou caméra.
 */
class Equipment extends Model
{
    protected static string $table = 'equipments';

    /**
     * Retourne tous les équipements avec le nom de leur pièce associée.
     */
    public static function allWithRoom(): array
    {
        $sql = "SELECT eq.*, r.name AS room_name
                FROM equipments eq
                INNER JOIN rooms r ON r.id = eq.room_id
                ORDER BY r.name ASC, eq.name ASC";
        return Database::query($sql)->fetchAll();
    }

    public static function findWithRoom(int $id): ?array
    {
        $sql = "SELECT eq.*, r.name AS room_name
                FROM equipments eq
                INNER JOIN rooms r ON r.id = eq.room_id
                WHERE eq.id = :id LIMIT 1";
        $row = Database::query($sql, ['id' => $id])->fetch();
        return $row ?: null;
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
     * Compte les équipements actuellement allumés/ouverts (state = 1).
     */
    public static function countActive(): int
    {
        $row = Database::query('SELECT COUNT(*) AS c FROM equipments WHERE state = 1')->fetch();
        return (int) ($row['c'] ?? 0);
    }
}
