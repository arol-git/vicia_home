<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;
use App\Models\House;

/**
 * Class Device
 *
 * Modèle représentant une carte ESP32 physiquement APPAIRÉE à une
 * maison, identifiée par son `chip_id` matériel (unique, en pratique
 * l'adresse MAC ou le chip ID Espressif). C'est cet appairage — et
 * non le simple nommage d'un topic MQTT — qui garantit qu'un
 * équipement ou capteur est piloté par la carte réellement installée
 * dans la maison, et non par un appareil homonyme mal configuré.
 */
class Device extends Model
{
    protected static string $table = 'devices';

    public static function forHouse(int $houseId): array
    {
        return Database::query(
            'SELECT * FROM devices WHERE house_id = :house_id ORDER BY created_at DESC',
            ['house_id' => $houseId]
        )->fetchAll();
    }

    public static function belongsToHouse(int $deviceId, int $houseId): bool
    {
        $row = Database::query(
            'SELECT id FROM devices WHERE id = :id AND house_id = :house_id LIMIT 1',
            ['id' => $deviceId, 'house_id' => $houseId]
        )->fetch();
        return (bool) $row;
    }

    /**
     * Vérifie qu'une carte appartient à la maison ET qu'elle est
     * effectivement appairée (statut "paired") — une carte révoquée
     * ne doit plus pouvoir se voir rattacher de nouveaux
     * équipements/capteurs.
     */
    public static function isPairedInHouse(int $deviceId, int $houseId): bool
    {
        $row = Database::query(
            "SELECT id FROM devices WHERE id = :id AND house_id = :house_id AND status = 'paired' LIMIT 1",
            ['id' => $deviceId, 'house_id' => $houseId]
        )->fetch();
        return (bool) $row;
    }

    public static function findByChipId(string $chipId): ?array
    {
        $row = Database::query('SELECT * FROM devices WHERE chip_id = :chip_id LIMIT 1', ['chip_id' => $chipId])->fetch();
        return $row ?: null;
    }

    /**
     * Appaire une nouvelle carte ESP32 à une maison. Le chip_id doit
     * être unique sur toute la plateforme : une carte ne peut être
     * appairée qu'à une seule maison à la fois.
     */
    public static function pair(int $houseId, string $chipId, string $label): int
    {
        return self::create([
            'house_id' => $houseId,
            'chip_id'  => $chipId,
            'label'    => $label,
            'status'   => 'paired',
        ]);
    }

    public static function revoke(int $id): bool
    {
        return self::update($id, ['status' => 'revoked']);
    }

    public static function touchLastSeen(int $id): void
    {
        Database::query('UPDATE devices SET last_seen = NOW() WHERE id = :id', ['id' => $id]);
    }

    /**
     * Construit le topic MQTT normalisé d'un équipement/capteur à
     * partir du slug de la maison et du domaine/zone/mesure fournis.
     * Évite la saisie manuelle et les erreurs de nommage.
     * Exemple de topic stable :
     *   home/<house-slug>/<type>/<zone>/<deviceId>-<name-slug>
     * Le suffixe de commande est ensuite ajouté séparément :
     *   home/<house-slug>/<type>/<zone>/<deviceId>-<name-slug>/set
     */
    public static function generateTopic(array $house, string $domain, string $zone, string $measure): string
    {
        $slugify = fn(string $v) => trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($v)), '-');
        $houseSlug = trim((string) ($house['slug'] ?? '')) ?: House::generateSlug($house['name'] ?? 'maison');
        return sprintf('home/%s/%s/%s/%s', $slugify($houseSlug), $slugify($domain), $slugify($zone), $slugify($measure));
    }

    /**
     * Vérifie si un topic MQTT existe déjà (équipements ou capteurs).
     */
    public static function topicExists(string $topic): bool
    {
        $row = Database::query('SELECT 1 FROM equipments WHERE mqtt_topic = :t LIMIT 1', ['t' => $topic])->fetch();
        if ($row) return true;
        $row = Database::query('SELECT 1 FROM sensors WHERE mqtt_topic = :t LIMIT 1', ['t' => $topic])->fetch();
        return (bool) $row;
    }

    /**
     * Génère un topic à partir des paramètres fournis et s'assure
     * qu'il est unique en ajoutant un suffixe numérique si besoin.
     */
    public static function generateUniqueTopic(array $house, string $domain, string $zone, string $measure): string
    {
        $base = self::generateTopic($house, $domain, $zone, $measure);
        $candidate = $base;
        $i = 2;
        while (self::topicExists($candidate)) {
            $candidate = $base . '-' . $i;
            $i++;
            // safety guard to avoid infinite loop
            if ($i > 1000) break;
        }
        return $candidate;
    }
}
