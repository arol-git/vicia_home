<?php
/**
 * api/v1/equipments.php
 *
 * Ressource REST /api/v1/equipments — gestion et pilotage des
 * équipements D'UNE MAISON (paramètre "house_id" requis, vérifié via
 * api_authorize_house()).
 *
 *   GET    /api/v1/equipments?house_id=1            Liste
 *   GET    /api/v1/equipments/{id}?house_id=1        Détail
 *   POST   /api/v1/equipments                        Création (house_id dans le corps)
 *   PUT    /api/v1/equipments/{id}                   Mise à jour
 *   DELETE /api/v1/equipments/{id}?house_id=1         Suppression
 *   POST   /api/v1/equipments/{id}/toggle             Bascule d'état (house_id dans le corps)
 */

use App\Models\Equipment;
use App\Models\Room;
use Mqtt\Publisher;

function handle_equipments(string $method, ?string $id, ?string $subaction): void
{
    $user = api_authenticate();
    $input = api_input();
    $houseId = api_authorize_house($user, $input);

    if ($id && $subaction === 'toggle' && $method === 'POST') {
        if (!Equipment::belongsToHouse((int) $id, $houseId)) {
            api_response(['success' => false, 'message' => 'Équipement introuvable.'], 404);
        }
        $equipment = Equipment::find((int) $id);
        $newState = Equipment::toggleState((int) $id);
        Publisher::publish($equipment['mqtt_topic'] . '/set', $newState ? '1' : '0');
        api_response(['success' => true, 'message' => 'Commande envoyée.', 'data' => ['state' => $newState]]);
    }

    switch ($method) {
        case 'GET':
            if ($id) {
                if (!Equipment::belongsToHouse((int) $id, $houseId)) {
                    api_response(['success' => false, 'message' => 'Équipement introuvable.'], 404);
                }
                api_response(['success' => true, 'data' => api_hide_mqtt_topics(Equipment::findWithRoom((int) $id), $user, $houseId)]);
            }
            api_response(['success' => true, 'data' => api_hide_mqtt_topics(Equipment::allWithRoom($houseId), $user, $houseId)]);
            break;

        case 'POST':
            api_require_house_admin($user, $houseId);
            foreach (['room_id', 'name', 'type', 'mqtt_topic'] as $required) {
                if (empty($input[$required])) {
                    api_response(['success' => false, 'message' => "Le champ '$required' est obligatoire."], 422);
                }
            }
            if (!Room::belongsToHouse((int) $input['room_id'], $houseId)) {
                api_response(['success' => false, 'message' => 'Pièce invalide pour cette maison.'], 422);
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
            api_require_house_admin($user, $houseId);
            if (!$id || !Equipment::belongsToHouse((int) $id, $houseId)) {
                api_response(['success' => false, 'message' => 'Équipement introuvable.'], 404);
            }
            Equipment::update((int) $id, array_filter([
                'name'       => $input['name'] ?? null,
                'mqtt_topic' => $input['mqtt_topic'] ?? null,
                'is_active'  => isset($input['is_active']) ? (int) (bool) $input['is_active'] : null,
            ], fn($v) => $v !== null));
            api_response(['success' => true, 'message' => 'Équipement mis à jour.', 'data' => Equipment::findWithRoom((int) $id)]);
            break;

        case 'DELETE':
            api_require_house_admin($user, $houseId);
            if (!$id || !Equipment::belongsToHouse((int) $id, $houseId)) {
                api_response(['success' => false, 'message' => 'Équipement introuvable.'], 404);
            }
            Equipment::delete((int) $id);
            api_response(['success' => true, 'message' => 'Équipement supprimé.']);
            break;

        default:
            api_response(['success' => false, 'message' => 'Méthode non autorisée.'], 405);
    }
}
