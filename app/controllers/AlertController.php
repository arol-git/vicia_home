<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Response;
use App\Helpers\Notifier;
use App\Helpers\Mailer;
use App\Models\Alert;
use App\Models\User;

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

    public function testEmail(): void
    {
        app_log('[AlertController] Requête reçue sur /alerts/test-email.');

        $houseId = Auth::requireHouseRole(['admin', 'owner', 'resident', 'technician']);
        $this->verifyCsrf();
        $this->runEmailTest($houseId);
    }

    public function testEmailDirect(): void
    {
        app_log('[AlertController] Requête reçue sur /alerts/test-email-direct.');

        $houseId = Auth::requireHouseRole(['admin', 'owner', 'resident', 'technician']);
        $this->runEmailTest($houseId);
    }

    private function runEmailTest(int $houseId): void
    {
        app_log("[AlertController] Test e-mail demandé pour la maison $houseId.");

        $message = 'Alerte de test e-mail générée depuis Vicia Home.';
        Alert::create([
            'house_id' => $houseId,
            'type' => 'test_email',
            'severity' => 'warning',
            'source' => 'manual_test',
            'message' => $message,
        ]);

        $user = Auth::user() ?? [];
        $notificationSettings = User::notificationSettings((int) Auth::id());
        $recipient = trim((string) ($notificationSettings['notification_email'] ?? $user['email'] ?? ''));
        $sent = $recipient !== '' && Mailer::send($recipient, '[Vicia Home] Alerte de test', $message);
        app_log('[AlertController] Résultat test e-mail : ' . ($sent ? 'envoyé' : 'non envoyé') . '.');

        if (!$sent) {
            Response::success('Alerte créée, mais aucun e-mail n’a été envoyé. Vérifiez SMTP et votre e-mail de réception.', ['sent' => false]);
            return;
        }

        Response::success('Alerte créée et e-mail de test envoyé.', ['sent' => true]);
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
