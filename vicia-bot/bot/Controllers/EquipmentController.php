<?php

namespace Bot\Controllers;

use Bot\Core\Controller;
use Bot\Services\HouseContext;
use Bot\Services\ViciaApiClient;

/**
 * Class EquipmentController
 *
 * Modules "🏠 Maison", "💡 Éclairage", "🚪 Portes" : listage et
 * pilotage des équipements de la maison active. Le groupe (light/door)
 * est encodé dans callback_data afin que toggle() sache quelle liste
 * redessiner après la bascule.
 */
class EquipmentController extends Controller
{
    private const GROUPS = [
        'light' => ['led', 'relais'],
        'door'  => ['porte', 'fenetre', 'servo'],
    ];

    public function house(): void
    {
        $telegramId = $this->request->telegramUserId();
        $houseId = HouseContext::currentHouseId($telegramId);
        $rooms = ViciaApiClient::forTelegramUser($telegramId)->get('rooms', ['house_id' => $houseId])['data'] ?? [];

        if (empty($rooms)) {
            $this->respond("Aucune pièce enregistrée pour cette maison.");
            return;
        }

        $lines = array_map(fn($r) => "• <b>{$r['name']}</b> — {$r['equipments_count']} équip., {$r['sensors_count']} capteurs", $rooms);
        $this->respond("🏠 <b>Pièces de la maison</b>\n\n" . implode("\n", $lines));
    }

    public function lighting(): void
    {
        $this->renderGroup('light', '💡 Éclairage');
    }

    public function doors(): void
    {
        $this->renderGroup('door', '🚪 Portes & ouvertures');
    }

    private function renderGroup(string $group, string $title): void
    {
        $telegramId = $this->request->telegramUserId();
        $houseId = HouseContext::currentHouseId($telegramId);
        $equipments = ViciaApiClient::forTelegramUser($telegramId)->get('equipments', ['house_id' => $houseId])['data'] ?? [];
        $equipments = array_values(array_filter($equipments, fn($e) => in_array($e['type'], self::GROUPS[$group], true)));

        if (empty($equipments)) {
            $this->respond("Aucun équipement de ce type pour cette maison.");
            return;
        }

        $keyboard = array_map(function ($e) use ($group) {
            $emoji = $e['state'] ? '🟢' : '⚪';
            return [['text' => "$emoji {$e['name']} ({$e['room_name']})", 'callback_data' => "eq:toggle:{$group}:{$e['id']}"]];
        }, $equipments);

        $this->respond("$title\n\nAppuyez sur un équipement pour basculer son état :", $keyboard);
    }

    public function toggle(string $group, string $id): void
    {
        $telegramId = $this->request->telegramUserId();
        $houseId = HouseContext::currentHouseId($telegramId);

        $result = ViciaApiClient::forTelegramUser($telegramId)->post("equipments/{$id}/toggle", ['house_id' => $houseId]);
        $state = $result['data']['state'] ?? 0;

        $this->response->answerCallback($state ? '✅ Activé' : '⭕ Désactivé');
        $this->renderGroup($group, $group === 'light' ? '💡 Éclairage' : '🚪 Portes & ouvertures');
    }
}
