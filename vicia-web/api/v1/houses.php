<?php
/**
 * api/v1/houses.php
 *
 * Ressource REST /api/v1/houses — maisons accessibles à l'utilisateur
 * authentifié, et changement de mode de la maison.
 *
 *   GET  /api/v1/houses                Maisons de l'utilisateur (toutes pour un admin)
 *   PUT  /api/v1/houses/{id}/mode      Change le mode (confort/nuit/absence/urgence)
 *                                       { "mode": "nuit" }
 */

use App\Models\House;

function handle_houses(string $method, ?string $id, ?string $subaction): void
{
    $user = api_authenticate();

    if ($method === 'GET' && !$id) {
        api_response(['success' => true, 'data' => House::forUser($user['id'], $user['role'])]);
    }

    if ($id && $subaction === 'mode' && $method === 'PUT') {
        $houseId = (int) $id;
        $role = House::roleOfUser($houseId, $user['id'], $user['role']);
        if ($role === null) {
            api_response(['success' => false, 'message' => 'Vous n’avez pas accès à cette maison.'], 403);
        }

        $input = api_input();
        $mode = (string) ($input['mode'] ?? '');
        if (!in_array($mode, ['confort', 'nuit', 'absence', 'urgence'], true)) {
            api_response(['success' => false, 'message' => 'Mode invalide. Valeurs acceptées : confort, nuit, absence, urgence.'], 422);
        }

        House::update($houseId, ['mode' => $mode]);
        api_response(['success' => true, 'message' => 'Mode mis à jour.', 'data' => ['mode' => $mode]]);
    }

    api_response(['success' => false, 'message' => 'Route ou méthode inconnue.'], 404);
}
