<?php
/**
 * api/v1/sensors.php
 *
 * Ressource REST /api/v1/sensors — capteurs D'UNE MAISON (paramètre
 * "house_id" requis, vérifié via api_authorize_house()).
 *
 *   GET    /api/v1/sensors?house_id=1                Liste
 *   GET    /api/v1/sensors/{id}?house_id=1            Détail
 *   GET    /api/v1/sensors/{id}/history?house_id=1    Historique des mesures
 *   POST   /api/v1/sensors                            Création (house_id dans le corps)
 *   POST   /api/v1/sensors/{id}/readings              Enregistrement d'une mesure (house_id dans le corps)
 *   DELETE /api/v1/sensors/{id}?house_id=1             Suppression
 */

use App\Models\Room;
use App\Models\Sensor;

function handle_sensors(string $method, ?string $id, ?string $subaction): void
{
    $user = api_authenticate();
    $input = api_input();
    $houseId = api_authorize_house($user, $input);

    if ($id && $subaction === 'history' && $method === 'GET') {
        if (!Sensor::belongsToHouse((int) $id, $houseId)) {
            api_response(['success' => false, 'message' => 'Capteur introuvable.'], 404);
        }
        $hours = isset($_GET['hours']) ? (int) $_GET['hours'] : 24;
        api_response(['success' => true, 'data' => Sensor::history((int) $id, $hours)]);
    }

    if ($id && $subaction === 'readings' && $method === 'POST') {
        if (!Sensor::belongsToHouse((int) $id, $houseId)) {
            api_response(['success' => false, 'message' => 'Capteur introuvable.'], 404);
        }
        if (!isset($input['value']) || !is_numeric($input['value'])) {
            api_response(['success' => false, 'message' => 'La valeur mesurée est obligatoire et doit être numérique.'], 422);
        }
        Sensor::recordReading((int) $id, (float) $input['value']);
        api_response(['success' => true, 'message' => 'Mesure enregistrée.'], 201);
    }

    switch ($method) {
        case 'GET':
            if ($id) {
                if (!Sensor::belongsToHouse((int) $id, $houseId)) {
                    api_response(['success' => false, 'message' => 'Capteur introuvable.'], 404);
                }
                api_response(['success' => true, 'data' => Sensor::findWithRoom((int) $id)]);
            }
            api_response(['success' => true, 'data' => Sensor::allWithRoom($houseId)]);
            break;

        case 'POST':
            foreach (['room_id', 'name', 'type', 'mqtt_topic'] as $required) {
                if (empty($input[$required])) {
                    api_response(['success' => false, 'message' => "Le champ '$required' est obligatoire."], 422);
                }
            }
            if (!Room::belongsToHouse((int) $input['room_id'], $houseId)) {
                api_response(['success' => false, 'message' => 'Pièce invalide pour cette maison.'], 422);
            }
            $newId = Sensor::create([
                'room_id'         => (int) $input['room_id'],
                'name'            => $input['name'],
                'type'            => $input['type'],
                'unit'            => $input['unit'] ?? '',
                'icon'            => sensor_icon($input['type']),
                'mqtt_topic'      => $input['mqtt_topic'],
                'alert_threshold' => $input['alert_threshold'] ?? null,
            ]);
            api_response(['success' => true, 'message' => 'Capteur créé.', 'data' => Sensor::findWithRoom($newId)], 201);
            break;

        case 'DELETE':
            if (!$id || !Sensor::belongsToHouse((int) $id, $houseId)) {
                api_response(['success' => false, 'message' => 'Capteur introuvable.'], 404);
            }
            Sensor::delete((int) $id);
            api_response(['success' => true, 'message' => 'Capteur supprimé.']);
            break;

        default:
            api_response(['success' => false, 'message' => 'Méthode non autorisée.'], 405);
    }
}
