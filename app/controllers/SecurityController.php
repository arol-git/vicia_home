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
 * Module de cybersécurité : surveillance du réseau domestique,
 * détection d'appareils inconnus, gestion des listes blanche/noire,
 * journal des événements réseau et état du Wi-Fi.
 */
class SecurityController extends Controller
{
    public function index(): void
    {
        Auth::requireLogin();

        $devices = NetworkDevice::all('last_seen DESC');
        $logs    = Database::query(
            "SELECT nl.*, nd.mac_address, nd.hostname
             FROM network_logs nl
             LEFT JOIN network_devices nd ON nd.id = nl.device_id
             ORDER BY nl.created_at DESC LIMIT 30"
        )->fetchAll();

        $stats = [
            'total_devices'   => count($devices),
            'unknown_devices' => NetworkDevice::countUnknown(),
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

    /**
     * Place un appareil sur liste blanche (appareil de confiance).
     */
    public function whitelist(int $id): void
    {
        Auth::requireRole(['admin', 'technicien']);
        $this->verifyCsrf();

        $device = NetworkDevice::find($id);
        if (!$device) {
            Response::error('Appareil introuvable.', 404);
            return;
        }

        NetworkDevice::setListStatus($id, 'whitelisted');
        NetworkDevice::setBlocked($id, false);
        $this->logNetworkEvent($id, 'whitelist', "Appareil {$device['mac_address']} ajouté à la liste blanche");
        ActivityLog::record(Auth::id(), 'securite_whitelist', "Appareil {$device['mac_address']} placé en liste blanche", $this->request->ip());

        Response::success('Appareil ajouté à la liste blanche.');
    }

    /**
     * Place un appareil sur liste noire et bloque son accès réseau.
     */
    public function blacklist(int $id): void
    {
        Auth::requireRole(['admin', 'technicien']);
        $this->verifyCsrf();

        $device = NetworkDevice::find($id);
        if (!$device) {
            Response::error('Appareil introuvable.', 404);
            return;
        }

        NetworkDevice::setListStatus($id, 'blacklisted');
        NetworkDevice::setBlocked($id, true);
        $this->logNetworkEvent($id, 'blacklist', "Appareil {$device['mac_address']} bloqué et ajouté à la liste noire");

        Alert::create([
            'type'     => 'reseau',
            'severity' => 'critical',
            'source'   => $device['mac_address'],
            'message'  => "Appareil {$device['mac_address']} bloqué suite à une action administrateur",
        ]);

        ActivityLog::record(Auth::id(), 'securite_blacklist', "Appareil {$device['mac_address']} bloqué (liste noire)", $this->request->ip());

        Response::success('Appareil bloqué et ajouté à la liste noire.');
    }

    /**
     * Simule la détection d'un nouvel appareil sur le réseau (utilisé
     * à des fins de démonstration ; en production, cette détection
     * est réalisée par la sonde réseau décrite dans le cahier des
     * charges et publiée sur le topic MQTT home/network/scan).
     */
    public function simulateScan(): void
    {
        Auth::requireRole(['admin', 'technicien']);
        $this->verifyCsrf();

        $mac = strtoupper(implode(':', str_split(bin2hex(random_bytes(6)), 2)));
        $ip  = '192.168.20.' . random_int(60, 250);

        $device = NetworkDevice::upsertSighting($mac, $ip, 'appareil-inconnu', null);
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
