<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

/**
 * Class House
 *
 * Modèle représentant une maison (habitation cliente) sur la
 * plateforme. Une maison est reliée à un ou plusieurs utilisateurs
 * via la table pivot `house_user`, chacun avec un rôle propre à
 * cette maison (owner / resident / technician).
 */
class House extends Model
{
    protected static string $table = 'houses';

    /**
     * Retourne toutes les maisons auxquelles un utilisateur a accès,
     * avec son rôle sur chacune. Un utilisateur de rôle plateforme
     * "admin" a accès à toutes les maisons (support Vicia Home).
     */
    public static function forUser(int $userId, string $platformRole): array
    {
        if ($platformRole === 'admin') {
            $sql = "SELECT h.*, 'admin' AS role_in_house FROM houses h ORDER BY h.name ASC";
            return Database::query($sql)->fetchAll();
        }

        $sql = "SELECT h.*, hu.role_in_house
                FROM houses h
                INNER JOIN house_user hu ON hu.house_id = h.id
                WHERE hu.user_id = :user_id
                ORDER BY h.name ASC";
        return Database::query($sql, ['user_id' => $userId])->fetchAll();
    }

    /**
     * Retourne le rôle d'un utilisateur sur une maison donnée, ou
     * null s'il n'y a aucun accès. Les administrateurs de plateforme
     * disposent implicitement du rôle "admin" sur toute maison.
     */
    public static function roleOfUser(int $houseId, int $userId, string $platformRole): ?string
    {
        if ($platformRole === 'admin') {
            return 'admin';
        }

        $row = Database::query(
            'SELECT role_in_house FROM house_user WHERE house_id = :house_id AND user_id = :user_id LIMIT 1',
            ['house_id' => $houseId, 'user_id' => $userId]
        )->fetch();

        return $row['role_in_house'] ?? null;
    }

    /**
     * Retourne les membres (utilisateurs) d'une maison avec leur rôle.
     */
    public static function members(int $houseId): array
    {
        $sql = "SELECT u.id, u.name, u.email, hu.role_in_house, hu.created_at AS joined_at
                FROM house_user hu
                INNER JOIN users u ON u.id = hu.user_id
                WHERE hu.house_id = :house_id
                ORDER BY FIELD(hu.role_in_house, 'owner', 'technician', 'resident'), u.name ASC";
        return Database::query($sql, ['house_id' => $houseId])->fetchAll();
    }

    /**
     * Ajoute un membre à une maison, ou met à jour son rôle s'il en
     * fait déjà partie.
     */
    public static function addMember(int $houseId, int $userId, string $role): void
    {
        Database::query(
            'INSERT INTO house_user (house_id, user_id, role_in_house) VALUES (:house_id, :user_id, :role)
             ON DUPLICATE KEY UPDATE role_in_house = :role_update',
            ['house_id' => $houseId, 'user_id' => $userId, 'role' => $role, 'role_update' => $role]
        );
    }

    public static function removeMember(int $houseId, int $userId): void
    {
        Database::query(
            'DELETE FROM house_user WHERE house_id = :house_id AND user_id = :user_id',
            ['house_id' => $houseId, 'user_id' => $userId]
        );
    }

    /**
     * Génère un slug unique (segment d'espace de noms MQTT) à partir
     * du nom de la maison, ex. "Villa Yaoundé" -> "villa-yaounde".
     */
    public static function generateSlug(string $name): string
    {
        $base = strtolower($name);
        $base = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $base) ?: $base;
        $base = preg_replace('/[^a-z0-9]+/', '-', $base);
        $base = trim($base, '-') ?: 'maison';

        $slug = $base;
        $suffix = 1;
        while (Database::query('SELECT id FROM houses WHERE slug = :slug', ['slug' => $slug])->fetch()) {
            $suffix++;
            $slug = $base . '-' . $suffix;
        }

        return $slug;
    }

    /**
     * Compte le nombre de pièces, équipements et capteurs d'une
     * maison (utilisé pour l'affichage de synthèse).
     */
    public static function withCounts(int $houseId): ?array
    {
        $house = self::find($houseId);
        if (!$house) {
            return null;
        }

        $rooms = Database::query('SELECT COUNT(*) AS c FROM rooms WHERE house_id = :id', ['id' => $houseId])->fetch();
        $members = Database::query('SELECT COUNT(*) AS c FROM house_user WHERE house_id = :id', ['id' => $houseId])->fetch();

        $house['rooms_count'] = (int) ($rooms['c'] ?? 0);
        $house['members_count'] = (int) ($members['c'] ?? 0);

        return $house;
    }
}
