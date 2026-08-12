<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Response;
use App\Core\Validator;
use App\Models\ActivityLog;
use App\Models\Device;
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
        $devices = Device::forHouse($houseId);
        $this->render('sensors/index', [
            'title'   => 'Capteurs',
            'sensors' => $sensors,
            'rooms'   => $rooms,
            'devices' => $devices,
        ]);
    }

    public function store(): void
    {
        $houseId = Auth::requireHouseRole(['admin', 'owner', 'technician']);
        $this->verifyCsrf();

        $roomId = (int) $this->request->input('room_id');
        $deviceId = (int) $this->request->input('device_id');
        $zone = trim((string) $this->request->input('zone'));

        if (!Room::belongsToHouse($roomId, $houseId)) {
            Response::error('Pièce invalide pour cette maison.', 422);
            return;
        }
        if (!Device::isPairedInHouse($deviceId, $houseId)) {
            Response::error('Carte ESP32 invalide pour cette maison : appairez-la depuis « Appareils » avant de continuer.', 422);
            return;
        }

        $type = (string) $this->request->input('type');
        $name = trim((string) $this->request->input('name'));
        $house = House::find($houseId);
        $nameSlug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-') ?: 'sensor';
        $defaultTopic = Device::generateTopic($house, $type, $zone ?: 'zone', $deviceId . '-' . $nameSlug);
        $mqttTopic = trim((string) $this->request->input('mqtt_topic', '')) ?: $defaultTopic;

        $data = [
            'room_id'         => $roomId,
            'device_id'       => $deviceId,
            'name'            => $name,
            'type'            => $type,
            'unit'            => trim((string) $this->request->input('unit', '')),
            'icon'            => sensor_icon($type),
            'mqtt_topic'      => $mqttTopic,
            'alert_threshold' => $this->request->input('alert_threshold') !== '' ? $this->request->input('alert_threshold') : null,
        ];

        $validator = new Validator($data);
        $validator->rules([
            'room_id'   => 'required|numeric',
            'device_id' => 'required|numeric',
            'name'      => 'required|min:2|max:100',
            'type'      => 'required|in:' . implode(',', $this->allowedTypes),
        ]);

        if ($validator->fails()) {
            Response::error($validator->firstError(), 422, ['errors' => $validator->errors()]);
            return;
        }

        $id = Sensor::create($data);
        ActivityLog::record(Auth::id(), 'creation_capteur', "Ajout du capteur « {$data['name']} »", $this->request->ip(), $houseId);

        Response::success('Capteur ajouté avec succès.', ['id' => $id, 'mqtt_topic' => $mqttTopic]);
    }

    public function update(int $id): void
    {
        $houseId = Auth::requireHouseRole(['admin', 'owner', 'technician']);
        $this->verifyCsrf();

        if (!Sensor::belongsToHouse($id, $houseId)) {
            Response::error('Capteur introuvable.', 404);
            return;
        }

        $sensor = Sensor::find($id);
        $data = [
            'room_id'         => (int) $this->request->input('room_id'),
            'name'            => trim((string) $this->request->input('name')),
            'unit'            => trim((string) $this->request->input('unit', '')),
            'alert_threshold' => $this->request->input('alert_threshold') !== '' ? $this->request->input('alert_threshold') : null,
        ];

        $mqttTopic = trim((string) $this->request->input('mqtt_topic', ''));
        if ($mqttTopic !== '') {
            $data['mqtt_topic'] = $mqttTopic;
        }

        if (!Room::belongsToHouse($data['room_id'], $houseId)) {
            Response::error('Pièce invalide pour cette maison.', 422);
            return;
        }

        $validator = new Validator($data);
        $validator->rules([
            'room_id' => 'required|numeric',
            'name'    => 'required|min:2|max:100',
        ]);

        if ($validator->fails()) {
            Response::error($validator->firstError(), 422, ['errors' => $validator->errors()]);
            return;
        }

        Sensor::update($id, $data);
        ActivityLog::record(Auth::id(), 'modification_capteur', "Modification du capteur « {$data['name']} »", $this->request->ip(), $houseId);

        Response::success('Capteur mis à jour avec succès.');
    }

    public function destroy(int $id): void
    {
        $houseId = Auth::requireHouseRole(['admin', 'owner']);
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
     * Génère un topic MQTT pour un capteur en fonction du type,
     * de la zone et du nom fournis. Vérifie aussi que le topic
     * n'existe pas déjà dans la base de données de cette maison.
     */
    public function generateTopic(): void
    {
        $houseId = Auth::requireHouseRole(['admin', 'owner', 'technician']);

        $type = trim((string) $this->request->input('type'));
        $name = trim((string) $this->request->input('name'));
        $zone = trim((string) $this->request->input('zone'));
        $deviceId = (int) $this->request->input('device_id', 0);

        if (!$type || !$name || !$deviceId) {
            Response::error('Paramètres incomplets : type, name et device_id sont requis.', 422);
            return;
        }

        $house = House::find($houseId);
        if (!$house) {
            Response::error('Maison introuvable.', 404);
            return;
        }

        $nameSlug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-') ?: 'sensor';
        $topic = Device::generateTopic($house, $type, $zone ?: 'zone', $deviceId . '-' . $nameSlug);

        // Vérifie que le topic n'existe pas déjà
        $exists = (bool) Sensor::where('mqtt_topic', '=', $topic)->first();

        Response::json([
            'success' => true,
            'topic' => $topic,
            'exists' => $exists,
            'message' => $exists ? 'Ce topic existe déjà.' : 'Topic généré avec succès.',
        ]);
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

    /**
     * Génère automatiquement un topic MQTT stable et unique pour
     * pré-remplir le formulaire côté client. Retourne JSON.
     */
    public function generateTopic(): void
    {
        $houseId = Auth::requireHouseRole(['admin', 'owner', 'technician']);

        $deviceId = (int) $this->request->query('device_id');
        $zone = trim((string) $this->request->query('zone'));
        $type = (string) $this->request->query('type');
        $name = trim((string) $this->request->query('name'));

        $house = House::find($houseId);
        if (!$house) {
            Response::error('Maison introuvable.', 404);
            return;
        }

        $nameSlug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-') ?: 'sensor';
        $topic = Device::generateUniqueTopic($house, $type, $zone ?: 'zone', $deviceId . '-' . $nameSlug);

        Response::json(['success' => true, 'mqtt_topic' => $topic]);
    }
}
