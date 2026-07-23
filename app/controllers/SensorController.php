<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Response;
use App\Core\Validator;
use App\Models\ActivityLog;
use App\Models\House;
use App\Models\Room;
use App\Models\Sensor;

/**
 * Class SensorController
 *
 * Gère le module "Capteurs" pour la maison actuellement sélectionnée.
 */
class SensorController extends Controller
{
    private array $allowedTypes = ['pir', 'dht22_temp', 'dht22_hum', 'mq2', 'mq135', 'ldr', 'rfid', 'humidite_sol'];

    public function index(): void
    {
        $houseId = Auth::requireHouseRole(['admin', 'owner', 'resident', 'technician']);
        $sensors = Sensor::allWithRoom($houseId);
        $rooms   = Room::forHouse($houseId);
        $house   = House::find($houseId);
        // Les topics MQTT des capteurs indiquent où arrivent les
        // mesures. On ne les affiche qu'à l'administration.
        $canSeeMqttTopics = can_view_mqtt_topics(Auth::roleOnHouse($houseId));
        $this->render('sensors/index', [
            'title'   => 'Capteurs',
            'sensors' => $sensors,
            'rooms'   => $rooms,
            'house'   => $house,
            'canSeeMqttTopics' => $canSeeMqttTopics,
        ]);
    }

    public function store(): void
    {
        // Seule l'administration peut déclarer un capteur : cela crée
        // une nouvelle entrée technique et un topic MQTT sensible.
        $houseId = Auth::requireHouseRole(['admin']);
        $this->verifyCsrf();

        $roomId = (int) $this->request->input('room_id');
        if (!Room::belongsToHouse($roomId, $houseId)) {
            Response::error('Pièce invalide pour cette maison.', 422);
            return;
        }
        $house = House::find($houseId);

        $data = [
            'room_id'         => $roomId,
            'name'            => trim((string) $this->request->input('name')),
            'type'            => (string) $this->request->input('type'),
            'unit'            => trim((string) $this->request->input('unit', '')),
            'icon'            => sensor_icon((string) $this->request->input('type')),
            'mqtt_topic'      => trim((string) $this->request->input('mqtt_topic')),
            'alert_threshold' => $this->request->input('alert_threshold') !== '' ? $this->request->input('alert_threshold') : null,
        ];

        $validator = new Validator($data);
        $validator->rules([
            'room_id'    => 'required|numeric',
            'name'       => 'required|min:2|max:100',
            'type'       => 'required|in:' . implode(',', $this->allowedTypes),
            'mqtt_topic' => 'required|min:3|max:150',
        ]);

        if ($validator->fails()) {
            Response::error($validator->firstError(), 422, ['errors' => $validator->errors()]);
            return;
        }
        if (!str_starts_with($data['mqtt_topic'], 'home/' . $house['slug'] . '/')) {
            Response::error('Le topic MQTT doit commencer par home/' . $house['slug'] . '/.', 422);
            return;
        }

        $id = Sensor::create($data);
        ActivityLog::record(Auth::id(), 'creation_capteur', "Ajout du capteur « {$data['name']} »", $this->request->ip(), $houseId);

        Response::success('Capteur ajouté avec succès.', ['id' => $id]);
    }

    public function update(int $id): void
    {
        // Seule l'administration peut modifier un capteur, notamment
        // son topic MQTT et son seuil d'alerte.
        $houseId = Auth::requireHouseRole(['admin']);
        $this->verifyCsrf();

        if (!Sensor::belongsToHouse($id, $houseId)) {
            Response::error('Capteur introuvable.', 404);
            return;
        }

        $data = [
            'room_id'         => (int) $this->request->input('room_id'),
            'name'            => trim((string) $this->request->input('name')),
            'unit'            => trim((string) $this->request->input('unit', '')),
            'mqtt_topic'      => trim((string) $this->request->input('mqtt_topic')),
            'alert_threshold' => $this->request->input('alert_threshold') !== '' ? $this->request->input('alert_threshold') : null,
        ];

        if (!Room::belongsToHouse($data['room_id'], $houseId)) {
            Response::error('Pièce invalide pour cette maison.', 422);
            return;
        }
        $house = House::find($houseId);

        $validator = new Validator($data);
        $validator->rules([
            'room_id'    => 'required|numeric',
            'name'       => 'required|min:2|max:100',
            'mqtt_topic' => 'required|min:3|max:150',
        ]);

        if ($validator->fails()) {
            Response::error($validator->firstError(), 422, ['errors' => $validator->errors()]);
            return;
        }
        if (!str_starts_with($data['mqtt_topic'], 'home/' . $house['slug'] . '/')) {
            Response::error('Le topic MQTT doit commencer par home/' . $house['slug'] . '/.', 422);
            return;
        }

        Sensor::update($id, $data);
        ActivityLog::record(Auth::id(), 'modification_capteur', "Modification du capteur « {$data['name']} »", $this->request->ip(), $houseId);

        Response::success('Capteur mis à jour avec succès.');
    }

    public function destroy(int $id): void
    {
        // Seule l'administration peut supprimer un capteur de l'inventaire.
        $houseId = Auth::requireHouseRole(['admin']);
        $this->verifyCsrf();

        if (!Sensor::belongsToHouse($id, $houseId)) {
            Response::error('Capteur introuvable.', 404);
            return;
        }
        $sensor = Sensor::find($id);

        Sensor::delete($id);
        ActivityLog::record(Auth::id(), 'suppression_capteur', "Suppression du capteur « {$sensor['name']} »", $this->request->ip(), $houseId);

        Response::success('Capteur supprimé avec succès.');
    }

    /**
     * Retourne, au format JSON, l'historique des mesures d'un capteur
     * sur les dernières 24 heures (consommé par Chart.js en AJAX).
     */
    public function history(int $id): void
    {
        $houseId = Auth::requireHouseRole(['admin', 'owner', 'resident', 'technician']);

        if (!Sensor::belongsToHouse($id, $houseId)) {
            Response::error('Capteur introuvable.', 404);
            return;
        }
        $sensor = Sensor::find($id);

        $hours    = (int) $this->request->query('hours', 24);
        $readings = Sensor::history($id, $hours);

        Response::json([
            'success' => true,
            'labels'  => array_map(fn($r) => date('H:i', strtotime($r['recorded_at'])), $readings),
            'values'  => array_map(fn($r) => (float) $r['value'], $readings),
            'unit'    => $sensor['unit'],
        ]);
    }
}
