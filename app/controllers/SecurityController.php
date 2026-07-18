<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Response;
use App\Models\ActivityLog;
use App\Models\Alert;
use App\Models\NetworkDevice;

/**
 * Class SecurityController
 *
 * Module de cybersécurité, scopé à la maison actuellement
 * sélectionnée : chaque maison a son propre réseau domestique et son
 * propre VLAN IoT, les appareils détectés ne sont donc jamais
 * partagés entre maisons.
 */
class SecurityController extends Controller
{
    public function index(): void
    {
        $houseId = Auth::requireHouseRole(['admin', 'owner', 'resident', 'technician']);

        $devices = NetworkDevice::forHouse($houseId);
        $logs    = Database::query(
            "SELECT nl.*, nd.mac_address, nd.hostname
             FROM network_logs nl
             INNER JOIN network_devices nd ON nd.id = nl.device_id
             WHERE nd.house_id = :house_id
             ORDER BY nl.created_at DESC LIMIT 30",
            ['house_id' => $houseId]
        )->fetchAll();

        $stats = [
            'total_devices'   => count($devices),
            'unknown_devices' => NetworkDevice::countUnknown($houseId),
            'blocked_devices' => count(array_filter($devices, fn($d) => (int) $d['is_blocked'] === 1)),
            'whitelisted'     => count(array_filter($devices, fn($d) => $d['list_status'] === 'whitelisted')),
        ];

        $this->render('security/index', [
            'title'   => 'Cybersécurité & réseau',
            'devices' => $devices,
            'logs'    => $logs,
            'stats'   => $stats,
        ]);
    }

    public function whitelist(int $id): void
    {
        $houseId = Auth::requireHouseRole(['admin', 'owner', 'technician']);
        $this->verifyCsrf();

        if (!NetworkDevice::belongsToHouse($id, $houseId)) {
            Response::error('Appareil introuvable.', 404);
            return;
        }
        $device = NetworkDevice::find($id);

        NetworkDevice::setListStatus($id, 'whitelisted');
        NetworkDevice::setBlocked($id, false);
        $this->logNetworkEvent($id, 'whitelist', "Appareil {$device['mac_address']} ajouté à la liste blanche");
        ActivityLog::record(Auth::id(), 'securite_whitelist', "Appareil {$device['mac_address']} placé en liste blanche", $this->request->ip(), $houseId);

        Response::success('Appareil ajouté à la liste blanche.');
    }

    public function blacklist(int $id): void
    {
        $houseId = Auth::requireHouseRole(['admin', 'owner', 'technician']);
        $this->verifyCsrf();

        if (!NetworkDevice::belongsToHouse($id, $houseId)) {
            Response::error('Appareil introuvable.', 404);
            return;
        }
        $device = NetworkDevice::find($id);

        NetworkDevice::setListStatus($id, 'blacklisted');
        NetworkDevice::setBlocked($id, true);
        $this->logNetworkEvent($id, 'blacklist', "Appareil {$device['mac_address']} bloqué et ajouté à la liste noire");

        Alert::create([
            'house_id' => $houseId,
            'type'     => 'reseau',
            'severity' => 'critical',
            'source'   => $device['mac_address'],
            'message'  => "Appareil {$device['mac_address']} bloqué suite à une action administrateur",
        ]);

        ActivityLog::record(Auth::id(), 'securite_blacklist', "Appareil {$device['mac_address']} bloqué (liste noire)", $this->request->ip(), $houseId);

        Response::success('Appareil bloqué et ajouté à la liste noire.');
    }

    /**
     * Simule la détection d'un nouvel appareil sur le réseau de la
     * maison sélectionnée (démonstration ; en production, la
     * détection provient de la sonde réseau propre à chaque maison).
     */
    public function simulateScan(): void
    {
        $houseId = Auth::requireHouseRole(['admin', 'owner', 'technician']);
        $this->verifyCsrf();

        $mac = strtoupper(implode(':', str_split(bin2hex(random_bytes(6)), 2)));
        $ip  = '192.168.20.' . random_int(60, 250);

        $device = NetworkDevice::upsertSighting($houseId, $mac, $ip, 'appareil-inconnu', null);
        $this->logNetworkEvent($device['id'], 'scan', "Nouvel appareil détecté sur le réseau IoT : $mac ($ip)");

        Response::success('Scan simulé : un nouvel appareil a été détecté.', ['device' => $device]);
    }

    private function logNetworkEvent(int $deviceId, string $eventType, string $description): void
    {
        Database::query(
            'INSERT INTO network_logs (device_id, event_type, description, created_at) VALUES (:device_id, :type, :desc, NOW())',
            ['device_id' => $deviceId, 'type' => $eventType, 'desc' => $description]
        );
    }
}
