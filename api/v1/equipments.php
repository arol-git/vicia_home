<?php
/**
 * api/v1/equipments.php
 *
 * Ressource REST /api/v1/equipments — gestion et pilotage des
 * équipements.
 *
 *   GET    /api/v1/equipments               Liste des équipements
 *   GET    /api/v1/equipments/{id}           Détail d'un équipement
 *   POST   /api/v1/equipments                Création d'un équipement
 *   PUT    /api/v1/equipments/{id}           Mise à jour d'un équipement
 *   DELETE /api/v1/equipments/{id}           Suppression d'un équipement
 *   POST   /api/v1/equipments/{id}/toggle    Bascule d'état (marche/arrêt)
 */

use App\Models\Equipment;
use Mqtt\Publisher;

function handle_equipments(string $method, ?string $id, ?string $subaction): void
{
    api_authenticate();

    if ($id && $subaction === 'toggle' && $method === 'POST') {
        $equipment = Equipment::find((int) $id);
        if (!$equipment) {
            api_response(['success' => false, 'message' => 'Équipement introuvable.'], 404);
        }
        $newState = Equipment::toggleState((int) $id);
        Publisher::publish($equipment['mqtt_topic'] . '/set', $newState ? '1' : '0');
        api_response(['success' => true, 'message' => 'Commande envoyée.', 'data' => ['state' => $newState]]);
    }

    switch ($method) {
        case 'GET':
            if ($id) {
                $equipment = Equipment::findWithRoom((int) $id);
                $equipment ? api_response(['success' => true, 'data' => $equipment])
                           : api_response(['success' => false, 'message' => 'Équipement introuvable.'], 404);
            }
            api_response(['success' => true, 'data' => Equipment::allWithRoom()]);
            break;

        case 'POST':
            $input = api_input();
            foreach (['room_id', 'name', 'type', 'mqtt_topic'] as $required) {
                if (empty($input[$required])) {
                    api_response(['success' => false, 'message' => "Le champ '$required' est obligatoire."], 422);
                }
            }
            $newId = Equipment::create([
                'room_id'    => (int) $input['room_id'],
                'name'       => $input['name'],
                'type'       => $input['type'],
                'icon'       => equipment_icon($input['type']),
                'mqtt_topic' => $input['mqtt_topic'],
                'state'      => 0,
            ]);
            api_response(['success' => true, 'message' => 'Équipement créé.', 'data' => Equipment::findWithRoom($newId)], 201);
            break;

        case 'PUT':
            if (!$id || !Equipment::find((int) $id)) {
                api_response(['success' => false, 'message' => 'Équipement introuvable.'], 404);
            }
            $input = api_input();
            Equipment::update((int) $id, array_filter([
                'name'       => $input['name'] ?? null,
                'room_id'    => isset($input['room_id']) ? (int) $input['room_id'] : null,
                'mqtt_topic' => $input['mqtt_topic'] ?? null,
                'is_active'  => isset($input['is_active']) ? (int) (bool) $input['is_active'] : null,
            ], fn($v) => $v !== null));
            api_response(['success' => true, 'message' => 'Équipement mis à jour.', 'data' => Equipment::findWithRoom((int) $id)]);
            break;

        case 'DELETE':
            if (!$id || !Equipment::find((int) $id)) {
                api_response(['success' => false, 'message' => 'Équipement introuvable.'], 404);
            }
            Equipment::delete((int) $id);
            api_response(['success' => true, 'message' => 'Équipement supprimé.']);
            break;

        default:
            api_response(['success' => false, 'message' => 'Méthode non autorisée.'], 405);
    }
}
