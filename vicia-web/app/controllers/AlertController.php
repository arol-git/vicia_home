<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Response;
use App\Models\Alert;

/**
 * Class AlertController
 *
 * Gère le module "Alertes & notifications" de la maison actuellement
 * sélectionnée.
 */
class AlertController extends Controller
{
    public function index(): void
    {
        $houseId = Auth::requireHouseRole(['admin', 'owner', 'resident', 'technician']);
        $alerts = Alert::forHouse($houseId);
        $this->render('alerts/index', ['title' => 'Alertes & notifications', 'alerts' => $alerts]);
    }

    public function markAsRead(int $id): void
    {
        $houseId = Auth::requireHouseRole(['admin', 'owner', 'resident', 'technician']);
        $this->verifyCsrf();

        if (!Alert::belongsToHouse($id, $houseId)) {
            Response::error('Alerte introuvable.', 404);
            return;
        }

        Alert::markAsRead($id);
        Response::success('Alerte marquée comme lue.');
    }

    public function markAllAsRead(): void
    {
        $houseId = Auth::requireHouseRole(['admin', 'owner', 'resident', 'technician']);
        $this->verifyCsrf();

        Alert::markAllAsRead($houseId);
        Response::success('Toutes les alertes ont été marquées comme lues.');
    }

    /**
     * Point d'entrée AJAX interrogé périodiquement par le tableau de
     * bord pour rafraîchir le compteur de notifications non lues de
     * la maison actuellement sélectionnée.
     */
    public function unreadCount(): void
    {
        $houseId = Auth::requireHouseRole(['admin', 'owner', 'resident', 'technician']);
        Response::json(['success' => true, 'count' => Alert::countUnread($houseId)]);
    }
}
