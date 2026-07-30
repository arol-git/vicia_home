<?php

namespace Bot\Controllers;

use Bot\Core\Controller;
use Bot\Services\HouseContext;
use Bot\Services\ViciaApiClient;

/**
 * Class ConsumptionController
 *
 * Module "⚡ Énergie" : estimation de la consommation électrique de
 * la maison active.
 */
class ConsumptionController extends Controller
{
    public function index(): void
    {
        $telegramId = $this->request->telegramUserId();
        $houseId = HouseContext::currentHouseId($telegramId);
        $data = ViciaApiClient::forTelegramUser($telegramId)->get('consumption', ['house_id' => $houseId])['data'] ?? [];

        $lines = [
            "⚡ <b>Consommation électrique</b>",
            "",
            "Puissance instantanée : <b>{$data['total_active_watts']} W</b>",
            "Estimation journalière : <b>{$data['estimated_daily_kwh']} kWh</b>",
            "Équipements actifs : <b>{$data['active_equipments']}</b>",
        ];

        $this->respond(implode("\n", $lines));
    }
}
