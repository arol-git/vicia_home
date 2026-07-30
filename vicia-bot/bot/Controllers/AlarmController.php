<?php

namespace Bot\Controllers;

use Bot\Core\Controller;
use Bot\Services\HouseContext;
use Bot\Services\ViciaApiClient;

/**
 * Class AlarmController
 *
 * Module "🚨 Alarmes" : armement/désarmement (mappé sur le mode de la
 * maison — voir houses.mode côté plateforme) et changement de mode
 * (Confort/Nuit/Absence/Urgence).
 */
class AlarmController extends Controller
{
    private const MODE_LABELS = [
        'confort' => '🛋 Confort', 'nuit' => '🌙 Nuit', 'absence' => '🚶 Absence', 'urgence' => '🚨 Urgence',
    ];

    public function index(): void
    {
        $telegramId = $this->request->telegramUserId();
        $houseId = HouseContext::currentHouseId($telegramId);
        $houses = ViciaApiClient::forTelegramUser($telegramId)->get('houses')['data'] ?? [];
        $current = null;
        foreach ($houses as $h) {
            if ((int) $h['id'] === $houseId) {
                $current = $h;
            }
        }
        $currentMode = $current['mode'] ?? 'confort';

        $keyboard = [];
        foreach (self::MODE_LABELS as $mode => $label) {
            $mark = $mode === $currentMode ? ' ✅' : '';
            $keyboard[] = [['text' => $label . $mark, 'callback_data' => "mode:set:{$mode}"]];
        }

        $this->respond("🚨 <b>Alarmes &amp; modes</b>\n\nMode actuel : <b>" . (self::MODE_LABELS[$currentMode] ?? $currentMode) . "</b>\n\nChoisissez un mode :", $keyboard);
    }

    public function setMode(string $mode): void
    {
        $telegramId = $this->request->telegramUserId();
        $houseId = HouseContext::currentHouseId($telegramId);

        ViciaApiClient::forTelegramUser($telegramId)->put("houses/{$houseId}/mode", ['mode' => $mode]);

        $this->response->answerCallback('Mode mis à jour : ' . (self::MODE_LABELS[$mode] ?? $mode));
        $this->index();
    }
}
