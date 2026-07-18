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
     * Retourne toutes les pièces d'UNE maison, enrichies du nombre
     * d'équipements et de capteurs qui leur sont associés (utilisé
     * pour la vue "Pièces").
     */
    public static function allWithCounts(int $houseId): array
    {
        $sql = "SELECT r.*,
                       (SELECT COUNT(*) FROM equipments e WHERE e.room_id = r.id) AS equipments_count,
                       (SELECT COUNT(*) FROM sensors s WHERE s.room_id = r.id) AS sensors_count
                FROM rooms r
                WHERE r.house_id = :house_id
                ORDER BY r.name ASC";
        return Database::query($sql, ['house_id' => $houseId])->fetchAll();
    }

    /**
     * Retourne toutes les pièces d'une maison (liste simple, sans
     * compteurs), utilisée pour peupler les listes déroulantes des
     * formulaires d'équipements/capteurs.
     */
    public static function forHouse(int $houseId): array
    {
        return Database::query(
            'SELECT * FROM rooms WHERE house_id = :house_id ORDER BY name ASC',
            ['house_id' => $houseId]
        )->fetchAll();
    }

    /**
     * Vérifie qu'une pièce appartient bien à la maison donnée, avant
     * toute opération de lecture/écriture — condition nécessaire pour
     * qu'un utilisateur d'une maison ne puisse jamais agir sur les
     * ressources d'une autre maison en falsifiant un identifiant.
     */
    public static function belongsToHouse(int $roomId, int $houseId): bool
    {
        $row = Database::query(
            'SELECT id FROM rooms WHERE id = :id AND house_id = :house_id LIMIT 1',
            ['id' => $roomId, 'house_id' => $houseId]
        )->fetch();

        return (bool) $row;
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
