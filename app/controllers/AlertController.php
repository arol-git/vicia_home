<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Response;
use App\Models\Alert;

/**
 * Class AlertController
 *
 * Gère le module "Alertes & notifications" : liste des alertes
 * générées par le système, marquage comme lues.
 */
class AlertController extends Controller
{
    public function index(): void
    {
        Auth::requireLogin();
        $alerts = Alert::all('created_at DESC');
        $this->render('alerts/index', ['title' => 'Alertes & notifications', 'alerts' => $alerts]);
    }

    public function markAsRead(int $id): void
    {
        Auth::requireLogin();
        $this->verifyCsrf();

        if (!Alert::find($id)) {
            Response::error('Alerte introuvable.', 404);
            return;
        }

        Alert::markAsRead($id);
        Response::success('Alerte marquée comme lue.');
    }

    public function markAllAsRead(): void
    {
        Auth::requireLogin();
        $this->verifyCsrf();

        Alert::markAllAsRead();
        Response::success('Toutes les alertes ont été marquées comme lues.');
    }

    /**
     * Point d'entrée AJAX interrogé périodiquement par le tableau de
     * bord pour rafraîchir le compteur de notifications non lues.
     */
    public function unreadCount(): void
    {
        Auth::requireLogin();
        Response::json(['success' => true, 'count' => Alert::countUnread()]);
    }
}
