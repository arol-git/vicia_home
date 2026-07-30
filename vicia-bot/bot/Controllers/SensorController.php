<?php

namespace Bot\Controllers;

use Bot\Core\Controller;
use Bot\Services\HouseContext;
use Bot\Services\ViciaApiClient;

/**
 * Class SensorController
 *
 * Modules "🌡 Température" et "💧 Humidité" : dernières valeurs des
 * capteurs de la maison active.
 */
class SensorController extends Controller
{
    public function temperature(): void { $this->renderByType('dht22_temp', '🌡 Température', '°C'); }
    public function humidity(): void { $this->renderByType('dht22_hum', '💧 Humidité', '%'); }

    private function renderByType(string $type, string $title, string $unit): void
    {
        $telegramId = $this->request->telegramUserId();
        $houseId = HouseContext::currentHouseId($telegramId);
        $sensors = ViciaApiClient::forTelegramUser($telegramId)->get('sensors', ['house_id' => $houseId])['data'] ?? [];
        $sensors = array_values(array_filter($sensors, fn($s) => $s['type'] === $type));

        if (empty($sensors)) {
            $this->respond("Aucun capteur de ce type pour cette maison.");
            return;
        }

        $lines = array_map(function ($s) use ($unit) {
            $value = $s['last_value'] !== null ? "{$s['last_value']} {$s['unit']}" : 'aucune mesure';
            return "• <b>{$s['name']}</b> ({$s['room_name']}) : {$value}";
        }, $sensors);

        $this->respond("$title\n\n" . implode("\n", $lines));
    }
}
