<?php

namespace Bot\Controllers;

use Bot\Core\Controller;
use Bot\Services\HouseContext;
use Bot\Services\ViciaApiClient;

/**
 * Class CameraController
 *
 * Module "📹 Caméras" : liste des caméras déclarées et leur statut.
 * L'intégration d'un flux vidéo réel (RTSP/HLS envoyé en sendVideo ou
 * lien de visionnage) dépend du modèle de caméra effectivement
 * déployé — hors périmètre de ce module, qui se limite à la liste et
 * au statut, comme son équivalent côté interface Web.
 */
class CameraController extends Controller
{
    public function index(): void
    {
        $telegramId = $this->request->telegramUserId();
        $houseId = HouseContext::currentHouseId($telegramId);
        $equipments = ViciaApiClient::forTelegramUser($telegramId)->get('equipments', ['house_id' => $houseId])['data'] ?? [];
        $cameras = array_values(array_filter($equipments, fn($e) => $e['type'] === 'camera'));

        if (empty($cameras)) {
            $this->respond("📹 Aucune caméra déclarée pour cette maison.");
            return;
        }

        $lines = array_map(fn($c) => "• <b>{$c['name']}</b> ({$c['room_name']}) : " . ($c['state'] ? '🟢 en ligne' : '⚪ hors ligne'), $cameras);
        $this->respond("📹 <b>Caméras</b>\n\n" . implode("\n", $lines));
    }
}
