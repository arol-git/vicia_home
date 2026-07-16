<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Response;
use App\Core\Validator;
use App\Models\ActivityLog;
use App\Models\Equipment;
use App\Models\Room;
use Mqtt\Publisher;

/**
 * Class EquipmentController
 *
 * Gère le module "Équipements" (actionneurs) : LED, relais,
 * ventilateur, pompe, servo, porte, fenêtre, sirène, caméra.
 * Toute bascule d'état déclenche la publication d'une commande MQTT
 * vers le module ESP32 concerné.
 */
class EquipmentController extends Controller
{
    private array $allowedTypes = ['led', 'relais', 'ventilateur', 'pompe', 'servo', 'porte', 'fenetre', 'sirene', 'camera'];

    public function index(): void
    {
        Auth::requireLogin();
        $equipments = Equipment::allWithRoom();
        $rooms      = Room::all('name ASC');
        $this->render('equipments/index', [
            'title'      => 'Équipements',
            'equipments' => $equipments,
            'rooms'      => $rooms,
        ]);
    }

    public function store(): void
    {
        Auth::requireRole(['admin', 'technicien']);
        $this->verifyCsrf();

        $data = [
            'room_id'    => (int) $this->request->input('room_id'),
            'name'       => trim((string) $this->request->input('name')),
            'type'       => (string) $this->request->input('type'),
            'icon'       => (string) $this->request->input('icon', equipment_icon((string) $this->request->input('type'))),
            'mqtt_topic' => trim((string) $this->request->input('mqtt_topic')),
            'state'      => 0,
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

        $id = Equipment::create($data);
        ActivityLog::record(Auth::id(), 'creation_equipement', "Ajout de l’équipement « {$data['name']} »", $this->request->ip());

        Response::success('Équipement ajouté avec succès.', ['id' => $id]);
    }

    public function update(int $id): void
    {
        Auth::requireRole(['admin', 'technicien']);
        $this->verifyCsrf();

        $equipment = Equipment::find($id);
        if (!$equipment) {
            Response::error('Équipement introuvable.', 404);
            return;
        }

        $data = [
            'room_id'    => (int) $this->request->input('room_id'),
            'name'       => trim((string) $this->request->input('name')),
            'type'       => (string) $this->request->input('type'),
            'mqtt_topic' => trim((string) $this->request->input('mqtt_topic')),
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

        Equipment::update($id, $data);
        ActivityLog::record(Auth::id(), 'modification_equipement', "Modification de l’équipement « {$data['name']} »", $this->request->ip());

        Response::success('Équipement mis à jour avec succès.');
    }

    public function destroy(int $id): void
    {
        Auth::requireRole(['admin']);
        $this->verifyCsrf();

        $equipment = Equipment::find($id);
        if (!$equipment) {
            Response::error('Équipement introuvable.', 404);
            return;
        }

        Equipment::delete($id);
        ActivityLog::record(Auth::id(), 'suppression_equipement', "Suppression de l’équipement « {$equipment['name']} »", $this->request->ip());

        Response::success('Équipement supprimé avec succès.');
    }

    /**
     * Bascule l'état (marche/arrêt, ouvert/fermé) d'un équipement et
     * publie la commande correspondante sur le broker MQTT.
     */
    public function toggle(int $id): void
    {
        Auth::requireLogin();
        $this->verifyCsrf();

        $equipment = Equipment::find($id);
        if (!$equipment) {
            Response::error('Équipement introuvable.', 404);
            return;
        }

        if (!$equipment['is_active']) {
            Response::error('Cet équipement est désactivé et ne peut pas être piloté.', 409);
            return;
        }

        $newState = Equipment::toggleState($id);

        // Publication de la commande vers le module ESP32 concerné.
        // La classe Publisher encapsule la connexion au broker Mosquitto.
        Publisher::publish($equipment['mqtt_topic'] . '/set', $newState ? '1' : '0');

        ActivityLog::record(
            Auth::id(),
            'commande_equipement',
            "Équipement « {$equipment['name']} » basculé à l’état " . ($newState ? 'ON' : 'OFF'),
            $this->request->ip()
        );

        Response::success('Commande envoyée avec succès.', ['state' => $newState]);
    }
}
