<?php

namespace Bot\Controllers;

use Bot\Core\Controller;
use Bot\Services\HouseContext;
use Bot\Services\ViciaApiClient;

/**
 * Class HistoryController
 *
 * /historique — dernières alertes de la maison active (l'API REST
 * expose /alerts ; le journal d'audit détaillé reste consultable
 * depuis l'interface Web).
 */
class HistoryController extends Controller
{
    public function index(): void
    {
        $telegramId = $this->request->telegramUserId();
        $houseId = HouseContext::currentHouseId($telegramId);
        $alerts = ViciaApiClient::forTelegramUser($telegramId)->get('alerts', ['house_id' => $houseId])['data'] ?? [];
        $alerts = array_slice($alerts, 0, 10);

        if (empty($alerts)) {
            $this->respond("🕒 Aucun événement récent pour cette maison.");
            return;
        }

        $emoji = ['info' => 'ℹ️', 'warning' => '⚠️', 'critical' => '🚨'];
        $lines = array_map(fn($a) => ($emoji[$a['severity']] ?? '•') . " {$a['message']}", $alerts);

        $this->respond("🕒 <b>Historique récent</b>\n\n" . implode("\n", $lines));
    }
}
