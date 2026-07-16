<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;

/**
 * Class CameraController
 *
 * Affiche les caméras déclarées dans le système (équipements de
 * type "camera"). L'intégration du flux vidéo temps réel (RTSP/HLS)
 * est prévue au niveau de la vue et dépend du modèle de caméra
 * effectivement déployé sur le produit final.
 */
class CameraController extends Controller
{
    public function index(): void
    {
        Auth::requireLogin();

        $cameras = Database::query(
            "SELECT eq.*, r.name AS room_name FROM equipments eq
             INNER JOIN rooms r ON r.id = eq.room_id
             WHERE eq.type = 'camera' ORDER BY r.name ASC"
        )->fetchAll();

        $this->render('cameras/index', ['title' => 'Caméras', 'cameras' => $cameras]);
    }
}
