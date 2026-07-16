<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

/**
 * Class NetworkDevice
 *
 * Modèle représentant un appareil détecté sur le réseau domestique.
 * Cœur du module de cybersécurité : surveillance réseau, listes
 * blanche/noire, détection d'appareils inconnus.
 */
class NetworkDevice extends Model
{
    protected static string $table = 'network_devices';

    public static function findByMac(string $mac): ?array
    {
        $row = Database::query('SELECT * FROM network_devices WHERE mac_address = :mac LIMIT 1', ['mac' => $mac])->fetch();
        return $row ?: null;
    }

    /**
     * Enregistre ou met à jour la présence d'un appareil sur le réseau.
     * Appelée par la sonde réseau (ou simulée depuis l'interface
     * d'administration) à chaque détection.
     */
    public static function upsertSighting(string $mac, ?string $ip, ?string $hostname, ?string $vendor = null): array
    {
        $existing = self::findByMac($mac);

        if ($existing) {
            self::update($existing['id'], [
                'ip_address' => $ip,
                'hostname'   => $hostname,
                'last_seen'  => date('Y-m-d H:i:s'),
            ]);
            return self::find($existing['id']);
        }

        $id = self::create([
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

    public static function countUnknown(): int
    {
        $row = Database::query("SELECT COUNT(*) AS c FROM network_devices WHERE list_status = 'unknown'")->fetch();
        return (int) ($row['c'] ?? 0);
    }
}
