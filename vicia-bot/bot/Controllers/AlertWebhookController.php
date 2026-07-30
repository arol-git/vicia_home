<?php

namespace Bot\Controllers;

use Bot\Core\Controller;
use Bot\Services\ViciaApiClient;

/**
 * Class AlertWebhookController
 *
 * Gère le bouton "Marquer comme lue" reçu depuis une notification
 * poussée (voir Bot\Services\NotificationDispatcher) — un simple
 * callback_query classique, traité comme n'importe quel autre bouton
 * du bot une fois le message envoyé.
 *
 * Le TRAITEMENT ENTRANT du webhook plateforme -> bot (réception d'une
 * alerte) est géré par public/webhook-alert.php, un point d'entrée
 * HTTP séparé qui ne passe pas par cette classe ni par le Router des
 * commandes Telegram (voir ce fichier pour le détail).
 */
class AlertWebhookController extends Controller
{
    public function markRead(string $alertId): void
    {
        $telegramId = $this->request->telegramUserId();
        $houseId = \Bot\Services\HouseContext::currentHouseId($telegramId);

        ViciaApiClient::forTelegramUser($telegramId)->post("alerts/{$alertId}/read", ['house_id' => $houseId]);

        $this->response->answerCallback('Alerte marquée comme lue ✅');
        $this->response->edit("✅ Alerte marquée comme lue.");
    }
}