<?php
/**
 * api/v1/network.php
 *
 * Ressource REST /api/v1/network — appareils détectés sur le réseau
 * D'UNE MAISON (paramètre "house_id" requis, vérifié via
 * api_authorize_house()). Miroir du module Web SecurityController.
 *
 *   GET  /api/v1/network?house_id=1                    Liste des appareils
 *   POST /api/v1/network/{id}/whitelist                Ajoute en liste blanche (house_id dans le corps)
 *   POST /api/v1/network/{id}/blacklist                Bloque et ajoute en liste noire (house_id dans le corps)
 */

use App\Models\Alert;
use App\Models\NetworkDevice;

function handle_network(string $method, ?string $id, ?string $subaction): void
{
    $user = api_authenticate();
    $input = api_input();
    $houseId = api_authorize_house($user, $input);
    $houseRole = \App\Models\House::roleOfUser($houseId, $user['id'], $user['role']);

    if ($id && in_array($subaction, ['whitelist', 'blacklist'], true) && $method === 'POST') {
        if (!in_array($houseRole, ['admin', 'owner', 'technician'], true)) {
            api_response(['success' => false, 'message' => 'Privilèges insuffisants sur cette maison.'], 403);
        }
        if (!NetworkDevice::belongsToHouse((int) $id, $houseId)) {
            api_response(['success' => false, 'message' => 'Appareil introuvable.'], 404);
        }

        $device = NetworkDevice::find((int) $id);
        $status = $subaction === 'whitelist' ? 'whitelisted' : 'blacklisted';
        NetworkDevice::setListStatus((int) $id, $status);
        NetworkDevice::setBlocked((int) $id, $subaction === 'blacklist');

        \App\Core\Database::query(
            'INSERT INTO network_logs (device_id, event_type, description, created_at) VALUES (:device_id, :type, :desc, NOW())',
            [
                'device_id' => (int) $id,
                'type'      => $subaction,
                'desc'      => "Appareil {$device['mac_address']} " . ($subaction === 'whitelist' ? 'ajouté à la liste blanche' : 'bloqué et ajouté à la liste noire'),
            ]
        );

        if ($subaction === 'blacklist') {
            Alert::create([
                'house_id' => $houseId,
                'type'     => 'reseau',
                'severity' => 'critical',
                'source'   => $device['mac_address'],
                'message'  => "Appareil {$device['mac_address']} bloqué via l’API",
            ]);
        }

        api_response(['success' => true, 'message' => $subaction === 'whitelist' ? 'Appareil ajouté à la liste blanche.' : 'Appareil bloqué et ajouté à la liste noire.']);
    }

    switch ($method) {
        case 'GET':
            api_response(['success' => true, 'data' => NetworkDevice::forHouse($houseId)]);
            break;

        default:
            api_response(['success' => false, 'message' => 'Méthode non autorisée.'], 405);
    }
}
