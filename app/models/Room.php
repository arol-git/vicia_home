<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

/**
 * Class Room
 *
 * Modèle représentant une pièce de l'habitation (salon, cuisine,
 * chambre, garage, etc.).
 */
class Room extends Model
{
    protected static string $table = 'rooms';

    /**
     * Retourne toutes les pièces enrichies du nombre d'équipements et
     * de capteurs qui leur sont associés (utilisé pour la vue "Pièces").
     */
    public static function allWithCounts(): array
    {
        $sql = "SELECT r.*,
                       (SELECT COUNT(*) FROM equipments e WHERE e.room_id = r.id) AS equipments_count,
                       (SELECT COUNT(*) FROM sensors s WHERE s.room_id = r.id) AS sensors_count
                FROM rooms r
                ORDER BY r.name ASC";
        return Database::query($sql)->fetchAll();
    }

    /**
     * Empêche la suppression d'une pièce si elle contient encore des
     * équipements ou des capteurs (intégrité fonctionnelle, en plus
     * des contraintes de clé étrangère en base).
     */
    public static function hasDependents(int $roomId): bool
    {
        $equipments = Database::query('SELECT COUNT(*) AS c FROM equipments WHERE room_id = :id', ['id' => $roomId])->fetch();
        $sensors    = Database::query('SELECT COUNT(*) AS c FROM sensors WHERE room_id = :id', ['id' => $roomId])->fetch();

        return ($equipments['c'] ?? 0) > 0 || ($sensors['c'] ?? 0) > 0;
    }
}
