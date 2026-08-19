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
        ]);
    }
}
