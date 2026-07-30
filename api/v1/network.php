<?php
/**
 * api/v1/network.php
 *
 * Ressource REST /api/v1/network — liste et classe les appareils
 * detectes sur le reseau d'une maison.
 *
 * Debutant : le bot ne scanne pas le reseau lui-meme. Il lit les
 * appareils deja enregistres dans `network_devices` par la plateforme.
 */

use App\Models\NetworkDevice;

function handle_network(string $method, ?string $id, ?string $subaction): void
{
    $user = api_authenticate();
    $input = api_input();
    $houseId = api_authorize_house($user, $input);

    if ($method === 'GET') {
        api_response(['success' => true, 'data' => NetworkDevice::forHouse($houseId)]);
    }

    if ($method === 'POST' && $id !== null && in_array($subaction, ['whitelist', 'blacklist'], true)) {
        api_require_house_admin($user, $houseId);

        if (!NetworkDevice::belongsToHouse((int) $id, $houseId)) {
            api_response(['success' => false, 'message' => 'Appareil réseau introuvable.'], 404);
        }

        NetworkDevice::setListStatus((int) $id, $subaction);
        NetworkDevice::setBlocked((int) $id, $subaction === 'blacklist');

        api_response([
            'success' => true,
            'message' => $subaction === 'whitelist' ? 'Appareil autorisé.' : 'Appareil bloqué.',
            'data' => NetworkDevice::find((int) $id),
        ]);
    }

    api_response(['success' => false, 'message' => 'Méthode non autorisée.'], 405);
}
