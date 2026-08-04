<?php
/**
 * api/v1/devices.php
 *
 * Ressource REST /api/v1/devices — cartes ESP32 appairées à UNE
 * MAISON (paramètre "house_id" requis). Utile notamment pour un
 * diagnostic « appareil hors ligne » côté bot Telegram (colonne
 * `last_seen`, mise à jour par mqtt/subscriber.php à chaque message
 * de télémétrie reçu d'un capteur de cette carte).
 *
 *   GET  /api/v1/devices?house_id=1               Liste des cartes de la maison
 *   POST /api/v1/devices                           Appairage (house_id dans le corps)
 *   POST /api/v1/devices/{id}/revoke                Révocation (house_id dans le corps)
 */

use App\Models\Device;

function handle_devices(string $method, ?string $id, ?string $subaction): void
{
    $user = api_authenticate();
    $input = api_input();
    $houseId = api_authorize_house($user, $input);
    $houseRole = \App\Models\House::roleOfUser($houseId, $user['id'], $user['role']);

    if ($id && $subaction === 'revoke' && $method === 'POST') {
        if (!in_array($houseRole, ['admin', 'owner', 'technician'], true)) {
            api_response(['success' => false, 'message' => 'Privilèges insuffisants sur cette maison.'], 403);
        }
        if (!Device::belongsToHouse((int) $id, $houseId)) {
            api_response(['success' => false, 'message' => 'Carte introuvable.'], 404);
        }
        Device::revoke((int) $id);
        api_response(['success' => true, 'message' => 'Carte révoquée.']);
    }

    switch ($method) {
        case 'GET':
            api_response(['success' => true, 'data' => Device::forHouse($houseId)]);
            break;

        case 'POST':
            if (!in_array($houseRole, ['admin', 'owner', 'technician'], true)) {
                api_response(['success' => false, 'message' => 'Privilèges insuffisants sur cette maison.'], 403);
            }
            $chipId = strtoupper(trim((string) ($input['chip_id'] ?? '')));
            $label  = trim((string) ($input['label'] ?? ''));
            if (!$chipId || !$label) {
                api_response(['success' => false, 'message' => 'Les champs « chip_id » et « label » sont obligatoires.'], 422);
            }
            if (Device::findByChipId($chipId)) {
                api_response(['success' => false, 'message' => 'Cette carte est déjà appairée à une maison.'], 409);
            }
            $newId = Device::pair($houseId, $chipId, $label);
            api_response(['success' => true, 'message' => 'Carte appairée.', 'data' => Device::find($newId)], 201);
            break;

        default:
            api_response(['success' => false, 'message' => 'Méthode non autorisée.'], 405);
    }
}
