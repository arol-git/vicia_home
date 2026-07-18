<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;

/**
 * Class CameraController
 *
 * Affiche les caméras (équipements de type "camera") de la maison
 * actuellement sélectionnée.
 */
class CameraController extends Controller
{
    public function index(): void
    {
        $houseId = Auth::requireHouseRole(['admin', 'owner', 'resident', 'technician']);

        $cameras = Database::query(
            "SELECT eq.*, r.name AS room_name FROM equipments eq
             INNER JOIN rooms r ON r.id = eq.room_id
             WHERE eq.type = 'camera' AND r.house_id = :house_id
             ORDER BY r.name ASC",
            ['house_id' => $houseId]
        )->fetchAll();

        $this->render('cameras/index', ['title' => 'Caméras', 'cameras' => $cameras]);
    }
}
