<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

/**
 * Class NetworkDevice
 *
 * Modèle représentant un appareil détecté sur le réseau domestique
 * D'UNE MAISON PRÉCISE. Chaque maison a son propre réseau et son
 * propre VLAN IoT : les appareils ne sont donc jamais partagés entre
 * maisons (voir contrainte UNIQUE(house_id, mac_address) en base).
 */
class NetworkDevice extends Model
{
    protected static string $table = 'network_devices';

    public static function forHouse(int $houseId): array
    {
        return Database::query(
            'SELECT * FROM network_devices WHERE house_id = :house_id ORDER BY last_seen DESC',
            ['house_id' => $houseId]
        )->fetchAll();
    }

    public static function belongsToHouse(int $deviceId, int $houseId): bool
    {
        $row = Database::query(
            'SELECT id FROM network_devices WHERE id = :id AND house_id = :house_id LIMIT 1',
            ['id' => $deviceId, 'house_id' => $houseId]
        )->fetch();
        return (bool) $row;
    }

    public static function findByMac(int $houseId, string $mac): ?array
    {
        $row = Database::query(
            'SELECT * FROM network_devices WHERE house_id = :house_id AND mac_address = :mac LIMIT 1',
            ['house_id' => $houseId, 'mac' => $mac]
        )->fetch();
        return $row ?: null;
    }

    /**
     * Enregistre ou met à jour la présence d'un appareil sur le
     * réseau d'une maison précise.
     */
    public static function upsertSighting(int $houseId, string $mac, ?string $ip, ?string $hostname, ?string $vendor = null): array
    {
        $existing = self::findByMac($houseId, $mac);

        if ($existing) {
            self::update($existing['id'], [
                'ip_address' => $ip,
                'hostname'   => $hostname,
                'last_seen'  => date('Y-m-d H:i:s'),
            ]);
            return self::find($existing['id']);
        }

        $id = self::create([
            'house_id'    => $houseId,
            'mac_address' => $mac,
            'ip_address'  => $ip,
            'hostname'    => $hostname,
            'vendor'      => $vendor,
            'list_status' => 'unknown',
        ]);

        return self::find($id);
    }

    public static function setListStatus(int $id, string $status): bool
    {
        return self::update($id, ['list_status' => $status]);
    }

    public static function setBlocked(int $id, bool $blocked): bool
    {
        return self::update($id, ['is_blocked' => $blocked ? 1 : 0]);
    }

    public static function countUnknown(int $houseId): int
    {
        $row = Database::query(
            "SELECT COUNT(*) AS c FROM network_devices WHERE house_id = :house_id AND list_status = 'unknown'",
            ['house_id' => $houseId]
        )->fetch();
        return (int) ($row['c'] ?? 0);
    }
}
