<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Models\Alert;
use App\Models\ActivityLog;
use App\Models\Equipment;
use App\Models\NetworkDevice;
use App\Models\Room;
use App\Models\Sensor;

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
            'activityTrend'  => $activityTrend,
        ]);
    }
}
