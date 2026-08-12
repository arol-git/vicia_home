<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Response;
use App\Core\Validator;
use App\Models\ActivityLog;
use App\Models\Device;
use App\Models\Equipment;
use App\Models\House;
use App\Models\Room;
use Mqtt\Publisher;

/**
 * Class EquipmentController
 *
 * Gère le module "Équipements" (actionneurs) pour la maison
 * actuellement sélectionnée. Toute bascule d'état déclenche la
 * publication d'une commande MQTT vers le module ESP32 concerné, sur
 * le topic propre à cette maison.
 */
class EquipmentController extends Controller
{
    private array $allowedTypes = ['led', 'relais', 'ventilateur', 'pompe', 'servo', 'porte', 'fenetre', 'sirene', 'camera'];

    public function index(): void
    {
        $houseId = Auth::requireHouseRole(['admin', 'owner', 'resident', 'technician']);
        $equipments = Equipment::allWithRoom($houseId);
        $rooms      = Room::forHouse($houseId);
        $devices    = Device::forHouse($houseId);
        $this->render('equipments/index', [
            'title'      => 'Équipements',
            'equipments' => $equipments,
            'rooms'      => $rooms,
            'devices'    => $devices,
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

        $nameSlug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-') ?: 'equipment';
        $defaultTopic = Device::generateTopic($house, $type, $zone ?: 'zone', $deviceId . '-' . $nameSlug);
        $mqttTopic = trim((string) $this->request->input('mqtt_topic', '')) ?: $defaultTopic;

        if (Device::topicExists($mqttTopic)) {
            Response::error('Ce topic MQTT est déjà utilisé.', 422);
            return;
        }

        $data = [
            'room_id'    => $roomId,
            'device_id'  => $deviceId,
            'name'       => $name,
            'type'       => $type,
            'icon'       => (string) $this->request->input('icon', equipment_icon($type)),
            'mqtt_topic' => $mqttTopic,
            'state'      => 0,
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

        $id = Equipment::create($data);
        ActivityLog::record(Auth::id(), 'creation_equipement', "Ajout de l’équipement « {$data['name']} »", $this->request->ip(), $houseId);

        Response::success('Équipement ajouté avec succès.', ['id' => $id, 'mqtt_topic' => $mqttTopic]);
    }

    public function update(int $id): void
    {
        $houseId = Auth::requireHouseRole(['admin', 'owner', 'technician']);
        $this->verifyCsrf();

        if (!Equipment::belongsToHouse($id, $houseId)) {
            Response::error('Équipement introuvable.', 404);
            return;
        }

        $equipment = Equipment::find($id);
        $roomId = (int) $this->request->input('room_id');
        if (!Room::belongsToHouse($roomId, $houseId)) {
            Response::error('Pièce invalide pour cette maison.', 422);
            return;
        }

        $data = [
            'room_id' => $roomId,
            'name'    => trim((string) $this->request->input('name')),
            'type'    => (string) $this->request->input('type'),
        ];

        $mqttTopic = trim((string) $this->request->input('mqtt_topic', ''));
        if ($mqttTopic !== '') {
            $data['mqtt_topic'] = $mqttTopic;
        }

        $validator = new Validator($data);
        $validator->rules([
            'room_id' => 'required|numeric',
            'name'    => 'required|min:2|max:100',
            'type'    => 'required|in:' . implode(',', $this->allowedTypes),
        ]);

        if ($validator->fails()) {
            Response::error($validator->firstError(), 422, ['errors' => $validator->errors()]);
            return;
        }

        Equipment::update($id, $data);
        ActivityLog::record(Auth::id(), 'modification_equipement', "Modification de l’équipement « {$data['name']} »", $this->request->ip(), $houseId);

        Response::success('Équipement mis à jour avec succès.');
    }

    public function destroy(int $id): void
    {
        $houseId = Auth::requireHouseRole(['admin', 'owner']);
        $this->verifyCsrf();

        if (!Equipment::belongsToHouse($id, $houseId)) {
            Response::error('Équipement introuvable.', 404);
            return;
        }
        $equipment = Equipment::find($id);

        Equipment::delete($id);
        ActivityLog::record(Auth::id(), 'suppression_equipement', "Suppression de l’équipement « {$equipment['name']} »", $this->request->ip(), $houseId);

        Response::success('Équipement supprimé avec succès.');
    }

    /**
     * Bascule l'état (marche/arrêt, ouvert/fermé) d'un équipement et
     * publie la commande correspondante sur le broker MQTT. Accessible
     * à tout membre de la maison (y compris un simple résident).
     */
    public function toggle(int $id): void
    {
        $houseId = Auth::requireHouseRole(['admin', 'owner', 'resident', 'technician']);
        $this->verifyCsrf();

        if (!Equipment::belongsToHouse($id, $houseId)) {
            Response::error('Équipement introuvable.', 404);
            return;
        }
        $equipment = Equipment::find($id);

        if (!$equipment['is_active']) {
            Response::error('Cet équipement est désactivé et ne peut pas être piloté.', 409);
            return;
        }

        $newState = Equipment::toggleState($id);

        // Publication de la commande vers le module ESP32 concerné, sur
        // le topic propre à cette maison (isolation garantie par le
        // préfixe home/<slug-maison>/... défini à la création du topic).
        $topic = Publisher::topicForEquipment($equipment['mqtt_topic'] ?? null, $houseId, (int) $equipment['id'], 'set');
        Publisher::publish($topic, $newState ? '1' : '0');

        ActivityLog::record(
            Auth::id(),
            'commande_equipement',
            "Équipement « {$equipment['name']} » basculé à l’état " . ($newState ? 'ON' : 'OFF'),
            $this->request->ip(),
            $houseId
        );

        Response::success('Commande envoyée avec succès.', ['state' => $newState]);
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

        $nameSlug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-') ?: 'equipment';
        $topic = Device::generateUniqueTopic($house, $type, $zone ?: 'zone', $deviceId . '-' . $nameSlug);

        Response::json(['success' => true, 'mqtt_topic' => $topic]);
    }
}
