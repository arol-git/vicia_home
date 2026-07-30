<?php

namespace Bot\Controllers;

use Bot\Core\Controller;
use Bot\Services\HouseContext;
use Bot\Services\PdfReportBuilder;
use Bot\Services\ViciaApiClient;

/**
 * Class ReportController
 *
 * /rapport — génère et envoie un rapport PDF (consommation + alertes
 * récentes) de la maison active.
 */
class ReportController extends Controller
{
    public function generate(): void
    {
        $telegramId = $this->request->telegramUserId();
        $houseId = HouseContext::currentHouseId($telegramId);
        $this->response->typing();

        $client = ViciaApiClient::forTelegramUser($telegramId);
        $houses = $client->get('houses')['data'] ?? [];
        $house = null;
        foreach ($houses as $h) {
            if ((int) $h['id'] === $houseId) $house = $h;
        }
        $consumption = $client->get('consumption', ['house_id' => $houseId])['data'] ?? [];
        $alerts = $client->get('alerts', ['house_id' => $houseId])['data'] ?? [];

        try {
            $path = PdfReportBuilder::build($house ?? ['name' => 'Maison'], $consumption, array_slice($alerts, 0, 10));
        } catch (\RuntimeException $e) {
            $this->reply("⚠️ La génération du rapport a échoué. Merci de réessayer plus tard.");
            $this->log()->error('Échec génération PDF : ' . $e->getMessage());
            return;
        }

        $this->response->document($path, "Rapport — " . ($house['name'] ?? ''));
        @unlink($path);
    }
}
