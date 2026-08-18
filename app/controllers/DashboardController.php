<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Response;
use App\Models\Alert;
use App\Models\ActivityLog;
use App\Models\Equipment;
use App\Models\House;
use App\Models\NetworkDevice;
use App\Models\Room;
use App\Models\Sensor;
use App\Models\Setting;
use Mqtt\Publisher;

/**
 * Class DashboardController
 *
 * Affiche le tableau de bord de la maison actuellement sélectionnée.
 */
class DashboardController extends Controller
{
    public function index(): void
    {
        Auth::requireLogin();

        if (Auth::currentHouseId() === null) {
            Response::redirect('/houses');
        }

        $houseId = Auth::requireHouseRole(['admin', 'owner', 'resident', 'technician']);

        $temp = Database::query(
            "SELECT sr.value FROM sensor_readings sr
             INNER JOIN sensors s ON s.id = sr.sensor_id
             INNER JOIN rooms r ON r.id = s.room_id
             WHERE s.type = 'dht22_temp' AND r.house_id = :house_id
             ORDER BY sr.recorded_at DESC LIMIT 1",
            ['house_id' => $houseId]
        )->fetch();
        $hum  = Database::query(
            "SELECT sr.value FROM sensor_readings sr
             INNER JOIN sensors s ON s.id = sr.sensor_id
             INNER JOIN rooms r ON r.id = s.room_id
             WHERE s.type = 'dht22_hum' AND r.house_id = :house_id
             ORDER BY sr.recorded_at DESC LIMIT 1",
            ['house_id' => $houseId]
        )->fetch();

        $stats = [
            'rooms_count'       => count(Room::forHouse($houseId)),
            'equipments_count'  => Equipment::countForHouse($houseId),
            'equipments_active' => Equipment::countActive($houseId),
            'temperature'       => $temp['value'] ?? null,
            'humidity'          => $hum['value'] ?? null,
            'alerts_unread'     => Alert::countUnread($houseId),
            'devices_unknown'   => NetworkDevice::countUnknown($houseId),
        ];

        $recentAlerts    = Alert::recent($houseId, 6);
        $recentActivity  = ActivityLog::recentForHouse($houseId, 8);
        $rooms           = Room::allWithCounts($houseId);
        $allSensors      = Sensor::allWithRoom($houseId);
        $currentHouse    = House::find($houseId);
        $currentMode     = Setting::get('dashboard_mode_' . $houseId, 'comfort');

        $activityTrend = Database::query(
            "SELECT DATE_FORMAT(sr.recorded_at, '%H:00') AS hour_label, COUNT(*) AS readings
             FROM sensor_readings sr
             INNER JOIN sensors s ON s.id = sr.sensor_id
             INNER JOIN rooms r ON r.id = s.room_id
             WHERE r.house_id = :house_id AND sr.recorded_at >= (NOW() - INTERVAL 24 HOUR)
             GROUP BY hour_label ORDER BY hour_label ASC",
            ['house_id' => $houseId]
        )->fetchAll();

        $this->render('dashboard/index', [
            'title'          => 'Tableau de bord',
            'stats'          => $stats,
            'recentAlerts'   => $recentAlerts,
            'recentActivity' => $recentActivity,
            'rooms'          => $rooms,
            'allSensors'     => $allSensors,
            'activityTrend'  => $activityTrend,
            'currentMode'    => $currentMode,
            'currentHouse'   => $currentHouse,
        ]);
    }

    public function setMode(): void
    {
        $houseId = Auth::requireHouseRole(['admin', 'owner', 'technician']);
        $this->verifyCsrf();

        $mode = (string) $this->request->input('mode', '');
        $labels = [
            'comfort' => 'Confort',
            'away' => 'Absence',
            'night' => 'Nuit',
            'emergency' => 'Urgence',
        ];

        if (!isset($labels[$mode])) {
            Response::error('Mode inconnu.', 422);
            return;
        }

        $changed = $this->applyMode($houseId, $mode);
        Setting::set('dashboard_mode_' . $houseId, $mode);

        ActivityLog::record(
            Auth::id(),
            'changement_mode',
            "Activation du mode {$labels[$mode]} ({$changed} équipement(s) ajusté(s))",
            $this->request->ip(),
            $houseId
        );

        Response::success("Mode {$labels[$mode]} activé.", [
            'mode' => $mode,
            'label' => $labels[$mode],
            'changed' => $changed,
            'equipmentsActive' => Equipment::countActive($houseId),
            'equipmentsCount' => Equipment::countForHouse($houseId),
        ]);
    }

    private function applyMode(int $houseId, string $mode): int
    {
        $equipmentTargets = [
            'comfort' => [
                'led' => 1,
                'relais' => 1,
                'ventilateur' => 1,
                'pompe' => 0,
                'porte' => 0,
                'fenetre' => 0,
                'sirene' => 0,
                'camera' => 1,
            ],
            'night' => [
                'led' => 0,
                'relais' => 0,
                'ventilateur' => 0,
                'pompe' => 0,
                'porte' => 1,
                'fenetre' => 1,
                'sirene' => 0,
                'camera' => 1,
            ],
            'away' => [
                'led' => 0,
                'relais' => 0,
                'ventilateur' => 0,
                'pompe' => 0,
                'porte' => 1,
                'fenetre' => 1,
                'sirene' => 0,
                'camera' => 1,
            ],
            'emergency' => [
                'led' => 1,
                'relais' => 1,
                'ventilateur' => 1,
                'pompe' => 0,
                'porte' => 1,
                'fenetre' => 1,
                'sirene' => 1,
                'camera' => 1,
            ],
        ];

        $sensorTargets = [
            'comfort' => [
                'pir' => 1,
                'mq2' => 1,
                'mq135' => 1,
                'rfid' => 1,
                'ldr' => 1,
                'dht22_temp' => 1,
                'dht22_hum' => 1,
                'humidite_sol' => 1,
                'energy_power' => 1,
                'energy_kwh' => 1,
                'energy_consumption' => 1,
            ],
            'night' => [
                'pir' => 1,
                'mq2' => 1,
                'mq135' => 1,
                'rfid' => 1,
                'ldr' => 1,
                'dht22_temp' => 1,
                'dht22_hum' => 1,
            ],
            'away' => [
                'pir' => 1,
                'mq2' => 1,
                'mq135' => 1,
                'rfid' => 1,
                'ldr' => 1,
                'dht22_temp' => 1,
                'dht22_hum' => 1,
            ],
            'emergency' => [
                'pir' => 1,
                'mq2' => 1,
                'mq135' => 1,
                'rfid' => 1,
                'ldr' => 1,
                'dht22_temp' => 1,
                'dht22_hum' => 1,
            ],
        ];

        $changed = 0;
        foreach (Equipment::activeForHouse($houseId) as $equipment) {
            $type = $equipment['type'];
            if (!array_key_exists($type, $equipmentTargets[$mode])) {
                continue;
            }

            $state = (int) $equipmentTargets[$mode][$type];
            if ((int) $equipment['state'] === $state) {
                continue;
            }

            Equipment::setState((int) $equipment['id'], $state);
            if (!empty($equipment['mqtt_topic'])) {
                Publisher::publish($equipment['mqtt_topic'] . '/set', $state ? '1' : '0');  
            }
            $changed++;
        }

        foreach (Sensor::activeForHouse($houseId) as $sensor) {
            $type = $sensor['type'];
            if (!array_key_exists($type, $sensorTargets[$mode])) {
                continue;
            }

            $isActive = (int) $sensorTargets[$mode][$type];
            if ((int) $sensor['is_active'] === $isActive) {
                continue;
            }

            Sensor::setActive((int) $sensor['id'], (bool) $isActive);
            $changed++;
        }

        return $changed;
    }
}
