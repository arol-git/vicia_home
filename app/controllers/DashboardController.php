<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Response;
use App\Models\Alert;
use App\Models\ActivityLog;
use App\Models\Equipment;
use App\Models\NetworkDevice;
use App\Models\Room;
use App\Models\Sensor;
use App\Models\Setting;
use Mqtt\Publisher;

/**
 * Class DashboardController
 *
 * Affiche le tableau de bord principal : indicateurs clés,
 * graphiques de tendance, dernières activités et alertes.
 */
class DashboardController extends Controller
{
    public function index(): void
    {
        Auth::requireLogin();

        // Dernière température et humidité mesurées (premier capteur
        // de chaque type trouvé, à titre de valeur "ambiante" globale)
        $temp = Database::query("SELECT sr.value FROM sensor_readings sr
                                  INNER JOIN sensors s ON s.id = sr.sensor_id
                                  WHERE s.type = 'dht22_temp' ORDER BY sr.recorded_at DESC LIMIT 1")->fetch();
        $hum  = Database::query("SELECT sr.value FROM sensor_readings sr
                                  INNER JOIN sensors s ON s.id = sr.sensor_id
                                  WHERE s.type = 'dht22_hum' ORDER BY sr.recorded_at DESC LIMIT 1")->fetch();

        $stats = [
            'rooms_count'       => Room::count(),
            'equipments_count'  => Equipment::count(),
            'equipments_active' => Equipment::countActive(),
            'temperature'       => $temp['value'] ?? null,
            'humidity'          => $hum['value'] ?? null,
            'alerts_unread'     => Alert::countUnread(),
            'devices_unknown'   => NetworkDevice::countUnknown(),
        ];

        $recentAlerts    = Alert::recent(6);
        $recentActivity  = ActivityLog::recent(8);
        $rooms           = Room::allWithCounts();
        $allSensors      = Sensor::allWithRoom();
        $currentMode     = Setting::get('dashboard_mode', 'comfort');

        // Historique agrégé des 24 dernières heures, pour le graphique
        // de consommation / activité, groupé par heure.
        $activityTrend = Database::query(
            "SELECT DATE_FORMAT(recorded_at, '%H:00') AS hour_label, COUNT(*) AS readings
             FROM sensor_readings
             WHERE recorded_at >= (NOW() - INTERVAL 24 HOUR)
             GROUP BY hour_label ORDER BY hour_label ASC"
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
        ]);
    }

    public function setMode(): void
    {
        Auth::requireLogin();
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

        $changed = $this->applyMode($mode);
        Setting::set('dashboard_mode', $mode);

        ActivityLog::record(
            Auth::id(),
            'changement_mode',
            "Activation du mode {$labels[$mode]} ({$changed} équipement(s) ajusté(s))",
            $this->request->ip()
        );

        Response::success("Mode {$labels[$mode]} activé.", [
            'mode' => $mode,
            'label' => $labels[$mode],
            'changed' => $changed,
            'equipmentsActive' => Equipment::countActive(),
            'equipmentsCount' => Equipment::count(),
        ]);
    }

    private function applyMode(string $mode): int
    {
        $targets = [
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
                'porte' => 0,
                'fenetre' => 0,
                'sirene' => 0,
                'camera' => 1,
            ],
            'away' => [
                'led' => 0,
                'relais' => 0,
                'ventilateur' => 0,
                'pompe' => 0,
                'porte' => 0,
                'fenetre' => 0,
                'sirene' => 0,
                'camera' => 1,
            ],
            'emergency' => [
                'led' => 1,
                'relais' => 1,
                'ventilateur' => 1,
                'pompe' => 0,
                'porte' => 0,
                'fenetre' => 0,
                'sirene' => 1,
                'camera' => 1,
            ],
        ];

        $changed = 0;
        foreach (Equipment::active() as $equipment) {
            $type = $equipment['type'];
            if (!array_key_exists($type, $targets[$mode])) {
                continue;
            }

            $state = (int) $targets[$mode][$type];
            if ((int) $equipment['state'] === $state) {
                continue;
            }

            Equipment::setState((int) $equipment['id'], $state);
            Publisher::publish($equipment['mqtt_topic'] . '/set', $state ? '1' : '0');
            $changed++;
        }

        return $changed;
    }
}
