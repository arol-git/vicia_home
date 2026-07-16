<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Response;
use App\Core\Validator;
use App\Models\ActivityLog;
use App\Models\Room;
use App\Models\Sensor;

/**
 * Class SensorController
 *
 * Gère le module "Capteurs" : PIR, DHT22 (température/humidité),
 * MQ-2, MQ-135, LDR, RFID, humidité du sol. Fournit également le
 * point d'entrée AJAX utilisé par Chart.js pour tracer l'historique
 * des mesures d'un capteur.
 */
class SensorController extends Controller
{
    private array $allowedTypes = ['pir', 'dht22_temp', 'dht22_hum', 'mq2', 'mq135', 'ldr', 'rfid', 'humidite_sol'];

    public function index(): void
    {
        Auth::requireLogin();
        $sensors = Sensor::allWithRoom();
        $rooms   = Room::all('name ASC');
        $this->render('sensors/index', [
            'title'   => 'Capteurs',
            'sensors' => $sensors,
            'rooms'   => $rooms,
        ]);
    }

    public function store(): void
    {
        Auth::requireRole(['admin', 'technicien']);
        $this->verifyCsrf();

        $data = [
            'room_id'         => (int) $this->request->input('room_id'),
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

        $id = Sensor::create($data);
        ActivityLog::record(Auth::id(), 'creation_capteur', "Ajout du capteur « {$data['name']} »", $this->request->ip());

        Response::success('Capteur ajouté avec succès.', ['id' => $id]);
    }

    public function update(int $id): void
    {
        Auth::requireRole(['admin', 'technicien']);
        $this->verifyCsrf();

        $sensor = Sensor::find($id);
        if (!$sensor) {
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

        Sensor::update($id, $data);
        ActivityLog::record(Auth::id(), 'modification_capteur', "Modification du capteur « {$data['name']} »", $this->request->ip());

        Response::success('Capteur mis à jour avec succès.');
    }

    public function destroy(int $id): void
    {
        Auth::requireRole(['admin']);
        $this->verifyCsrf();

        $sensor = Sensor::find($id);
        if (!$sensor) {
            Response::error('Capteur introuvable.', 404);
            return;
        }

        Sensor::delete($id);
        ActivityLog::record(Auth::id(), 'suppression_capteur', "Suppression du capteur « {$sensor['name']} »", $this->request->ip());

        Response::success('Capteur supprimé avec succès.');
    }

    /**
     * Retourne, au format JSON, l'historique des mesures d'un capteur
     * sur les dernières 24 heures (consommé par Chart.js en AJAX).
     */
    public function history(int $id): void
    {
        Auth::requireLogin();
        $sensor = Sensor::find($id);
        if (!$sensor) {
            Response::error('Capteur introuvable.', 404);
            return;
        }

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
