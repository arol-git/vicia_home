<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Models\Alert;
use App\Models\ActivityLog;
use App\Models\Equipment;
use App\Models\House;
use App\Models\NetworkDevice;
use App\Models\Room;

/**
 * Class DashboardController
 *
 * Affiche le tableau de bord de la maison actuellement sélectionnée :
 * indicateurs clés, graphiques de tendance, dernières activités et
 * alertes — jamais agrégés entre plusieurs maisons.
 */
class DashboardController extends Controller
{
    public function index(): void
    {
        Auth::requireLogin();

        // Un utilisateur fraîchement inscrit peut ne posséder aucune
        // maison : on le redirige vers l'onboarding plutôt que
        // d'afficher une erreur d'accès.
        if (Auth::currentHouseId() === null) {
            \App\Core\Response::redirect('/houses');
        }

        $houseId = Auth::requireHouseRole(['admin', 'owner', 'resident', 'technician']);

        // Dernière température et humidité mesurées dans la maison
        // sélectionnée (premier capteur de chaque type trouvé).
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
        $currentHouse    = House::find($houseId);

        // Historique agrégé des 24 dernières heures pour cette maison,
        // pour le graphique d'activité, groupé par heure.
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
            'activityTrend'  => $activityTrend,
            'currentHouse'   => $currentHouse,
        ]);
    }
}
