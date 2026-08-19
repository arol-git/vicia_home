<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Response;
use App\Models\Alert;
use App\Models\Equipment;
use App\Models\House;
use App\Models\Room;
use App\Models\Sensor;
use App\Models\Setting;
use Mqtt\Publisher;

/**
 * Affiche l'état actuel de la maison sélectionnée.
 */
class DashboardController extends Controller
{
    public function index(): void
    {
        Auth::requireLogin();

        if (Auth::currentHouseId() === null) {
            Response::redirect('/houses');
            return;
        }

        $houseId = Auth::requireHouseRole(['admin', 'owner', 'resident', 'technician']);
        $temperature = Database::query(
            "SELECT sr.value FROM sensor_readings sr
             INNER JOIN sensors s ON s.id = sr.sensor_id
             INNER JOIN rooms r ON r.id = s.room_id
             WHERE s.type = 'dht22_temp' AND r.house_id = :house_id
             ORDER BY sr.recorded_at DESC LIMIT 1",
            ['house_id' => $houseId]
        )->fetch();
        $humidity = Database::query(
            "SELECT sr.value FROM sensor_readings sr
             INNER JOIN sensors s ON s.id = sr.sensor_id
             INNER JOIN rooms r ON r.id = s.room_id
             WHERE s.type = 'dht22_hum' AND r.house_id = :house_id
             ORDER BY sr.recorded_at DESC LIMIT 1",
            ['house_id' => $houseId]
        )->fetch();

        $this->render('dashboard/index', [
            'title' => 'Tableau de bord',
            'stats' => [
                'temperature' => $temperature['value'] ?? null,
                'humidity' => $humidity['value'] ?? null,
                'alerts_unread' => Alert::countUnread($houseId),
            ],
            'recentAlerts' => Alert::recent($houseId, 6),
            'rooms' => Room::allWithCounts($houseId),
            'allEquipments' => Equipment::allWithRoom($houseId),
            'allSensors' => Sensor::allWithRoom($houseId),
            'currentHouse' => House::find($houseId),
            'currentMode' => Setting::get('dashboard_mode_' . $houseId, 'comfort'),
        ]);
    }

    public function setMode(): void
    {
        $houseId = Auth::requireHouseRole(['admin', 'owner', 'technician']);
        $this->verifyCsrf();
        $mode = (string) $this->request->input('mode', '');
        $labels = ['comfort' => 'Confort', 'night' => 'Nuit', 'away' => 'Absence'];
        if (!isset($labels[$mode])) {
            Response::error('Mode inconnu.', 422);
            return;
        }

        $targets = [
            'comfort' => ['led' => 1, 'relais' => 1, 'ventilateur' => 1, 'pompe' => 0, 'porte' => 0, 'fenetre' => 0, 'sirene' => 0],
            'night' => ['led' => 0, 'relais' => 0, 'ventilateur' => 0, 'pompe' => 0, 'porte' => 1, 'fenetre' => 1, 'sirene' => 0],
            'away' => ['led' => 0, 'relais' => 0, 'ventilateur' => 0, 'pompe' => 0, 'porte' => 1, 'fenetre' => 1, 'sirene' => 0],
        ][$mode];
        $changed = 0;
        foreach (Equipment::activeForHouse($houseId) as $equipment) {
            if (!array_key_exists($equipment['type'], $targets) || (int) $equipment['state'] === $targets[$equipment['type']]) {
                continue;
            }
            $state = (int) $targets[$equipment['type']];
            Equipment::setState((int) $equipment['id'], $state);
            if (!empty($equipment['mqtt_topic'])) {
                Publisher::publish($equipment['mqtt_topic'] . '/set', $state ? '1' : '0');
            }
            $changed++;
        }
        Setting::set('dashboard_mode_' . $houseId, $mode);
        Response::success("Mode {$labels[$mode]} activé.", [
            'mode' => $mode,
            'changed' => $changed,
            'equipmentsActive' => Equipment::countActive($houseId),
            'equipmentsCount' => Equipment::countForHouse($houseId),
        ]);
    }
}
