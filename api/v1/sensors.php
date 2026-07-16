<?php
/**
 * api/v1/sensors.php
 *
 * Ressource REST /api/v1/sensors — gestion des capteurs et de leurs
 * mesures.
 *
 *   GET    /api/v1/sensors                Liste des capteurs
 *   GET    /api/v1/sensors/{id}           Détail d'un capteur
 *   GET    /api/v1/sensors/{id}/history   Historique des mesures
 *   POST   /api/v1/sensors                Création d'un capteur
 *   POST   /api/v1/sensors/{id}/readings  Enregistrement d'une mesure
 *                                          (utilisé par une passerelle
 *                                          HTTP alternative à MQTT)
 *   DELETE /api/v1/sensors/{id}           Suppression d'un capteur
 */

use App\Models\Sensor;

function handle_sensors(string $method, ?string $id, ?string $subaction): void
{
    api_authenticate();

    if ($id && $subaction === 'history' && $method === 'GET') {
        $sensor = Sensor::find((int) $id);
        if (!$sensor) {
            api_response(['success' => false, 'message' => 'Capteur introuvable.'], 404);
        }
        $hours = isset($_GET['hours']) ? (int) $_GET['hours'] : 24;
        api_response(['success' => true, 'data' => Sensor::history((int) $id, $hours)]);
    }

    if ($id && $subaction === 'readings' && $method === 'POST') {
        $sensor = Sensor::find((int) $id);
        if (!$sensor) {
            api_response(['success' => false, 'message' => 'Capteur introuvable.'], 404);
        }
        $input = api_input();
        if (!isset($input['value']) || !is_numeric($input['value'])) {
            api_response(['success' => false, 'message' => 'La valeur mesurée est obligatoire et doit être numérique.'], 422);
        }
        Sensor::recordReading((int) $id, (float) $input['value']);
        api_response(['success' => true, 'message' => 'Mesure enregistrée.'], 201);
    }

    switch ($method) {
        case 'GET':
            if ($id) {
                $sensor = Sensor::findWithRoom((int) $id);
                $sensor ? api_response(['success' => true, 'data' => $sensor])
                        : api_response(['success' => false, 'message' => 'Capteur introuvable.'], 404);
            }
            api_response(['success' => true, 'data' => Sensor::allWithRoom()]);
            break;

        case 'POST':
            $input = api_input();
            foreach (['room_id', 'name', 'type', 'mqtt_topic'] as $required) {
                if (empty($input[$required])) {
                    api_response(['success' => false, 'message' => "Le champ '$required' est obligatoire."], 422);
                }
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
            if (!$id || !Sensor::find((int) $id)) {
                api_response(['success' => false, 'message' => 'Capteur introuvable.'], 404);
            }
            Sensor::delete((int) $id);
            api_response(['success' => true, 'message' => 'Capteur supprimé.']);
            break;

        default:
            api_response(['success' => false, 'message' => 'Méthode non autorisée.'], 405);
    }
}
