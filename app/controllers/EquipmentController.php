<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Response;
use App\Core\Validator;
use App\Models\ActivityLog;
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
    private array $allowedTypes = ['led', 'relais', 'ventilateur', 'pompe', 'servo', 'porte', 'fenetre', 'sirene'];

    public function index(): void
    {
        $houseId = Auth::requireHouseRole(['admin', 'owner', 'resident', 'technician']);
        $equipments = Equipment::allWithRoom($houseId);
        $rooms      = Room::forHouse($houseId);
        $house      = House::find($houseId);
        // Seuls les administrateurs plateforme voient les topics MQTT.
        // Un topic donne l'adresse technique de commande d'un appareil :
        // on le traite donc comme une information sensible.
        $canSeeMqttTopics = can_view_mqtt_topics(Auth::roleOnHouse($houseId));
        $this->render('equipments/index', [
            'title'      => 'Équipements',
            'equipments' => $equipments,
            'rooms'      => $rooms,
            'house'      => $house,
            'canSeeMqttTopics' => $canSeeMqttTopics,
        ]);
    }

    public function store(): void
    {
        // Création réservée à l'administration : ajouter un équipement
        // expose un nouveau topic MQTT et peut donner un accès physique
        // à la maison. On vérifie ce droit côté serveur, pas seulement
        // dans l'interface.
        $houseId = Auth::requireHouseRole(['admin']);
        $this->verifyCsrf();

        $roomId = (int) $this->request->input('room_id');
        if (!Room::belongsToHouse($roomId, $houseId)) {
            Response::error('Pièce invalide pour cette maison.', 422);
            return;
        }
        $house = House::find($houseId);

        $data = [
            'room_id'    => $roomId,
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
        if (!str_starts_with($data['mqtt_topic'], 'home/' . $house['slug'] . '/')) {
            Response::error('Le topic MQTT doit commencer par home/' . $house['slug'] . '/.', 422);
            return;
        }

        $id = Equipment::create($data);
        ActivityLog::record(Auth::id(), 'creation_equipement', "Ajout de l’équipement « {$data['name']} »", $this->request->ip(), $houseId);

        Response::success('Équipement ajouté avec succès.', ['id' => $id]);
    }

    public function update(int $id): void
    {
        // Modification réservée à l'administration pour éviter qu'un
        // utilisateur change le topic MQTT d'un équipement existant.
        $houseId = Auth::requireHouseRole(['admin']);
        $this->verifyCsrf();

        if (!Equipment::belongsToHouse($id, $houseId)) {
            Response::error('Équipement introuvable.', 404);
            return;
        }

        $roomId = (int) $this->request->input('room_id');
        if (!Room::belongsToHouse($roomId, $houseId)) {
            Response::error('Pièce invalide pour cette maison.', 422);
            return;
        }
        $house = House::find($houseId);

        $data = [
            'room_id'    => $roomId,
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
        if (!str_starts_with($data['mqtt_topic'], 'home/' . $house['slug'] . '/')) {
            Response::error('Le topic MQTT doit commencer par home/' . $house['slug'] . '/.', 422);
            return;
        }

        Equipment::update($id, $data);
        ActivityLog::record(Auth::id(), 'modification_equipement', "Modification de l’équipement « {$data['name']} »", $this->request->ip(), $houseId);

        Response::success('Équipement mis à jour avec succès.');
    }

    public function destroy(int $id): void
    {
        // Suppression réservée à l'administration : retirer un équipement
        // modifie l'inventaire matériel de la maison.
        $houseId = Auth::requireHouseRole(['admin']);
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
        Publisher::publish($equipment['mqtt_topic'] . '/set', $newState ? '1' : '0');

        ActivityLog::record(
            Auth::id(),
            'commande_equipement',
            "Équipement « {$equipment['name']} » basculé à l’état " . ($newState ? 'ON' : 'OFF'),
            $this->request->ip(),
            $houseId
        );

        Response::success('Commande envoyée avec succès.', ['state' => $newState]);
    }
}
