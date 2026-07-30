<?php

namespace Bot\Controllers;

use Bot\Core\Controller;
use Bot\Services\HouseContext;
use Bot\Services\ViciaApiClient;

/**
 * Class NetworkController
 *
 * Module "📡 Réseau" : appareils détectés sur le réseau de la maison
 * active, mise en liste blanche/noire.
 */
class NetworkController extends Controller
{
    public function index(): void
    {
        $telegramId = $this->request->telegramUserId();
        $houseId = HouseContext::currentHouseId($telegramId);
        $devices = ViciaApiClient::forTelegramUser($telegramId)->get('network', ['house_id' => $houseId])['data'] ?? [];

        if (empty($devices)) {
            $this->respond("📡 Aucun appareil détecté sur le réseau de cette maison.");
            return;
        }

        $statusLabel = ['unknown' => '❓ inconnu', 'whitelisted' => '✅ autorisé', 'blacklisted' => '⛔ bloqué'];
        $keyboard = [];
        $lines = ["📡 <b>Appareils réseau</b>", ""];
        foreach ($devices as $d) {
            $lines[] = "• <code>{$d['mac_address']}</code> — " . ($statusLabel[$d['list_status']] ?? $d['list_status']);
            if ($d['list_status'] !== 'whitelisted') {
                $keyboard[] = [
                    ['text' => "✅ Autoriser {$d['mac_address']}", 'callback_data' => "net:whitelist:{$d['id']}"],
                    ['text' => "⛔ Bloquer", 'callback_data' => "net:blacklist:{$d['id']}"],
                ];
            }
        }

        $this->respond(implode("\n", $lines), $keyboard ?: null);
    }

    public function whitelist(string $id): void
    {
        $this->act($id, 'whitelist', '✅ Appareil autorisé');
    }

    public function blacklist(string $id): void
    {
        $this->act($id, 'blacklist', '⛔ Appareil bloqué');
    }

    private function act(string $id, string $action, string $confirmation): void
    {
        $telegramId = $this->request->telegramUserId();
        $houseId = HouseContext::currentHouseId($telegramId);

        ViciaApiClient::forTelegramUser($telegramId)->post("network/{$id}/{$action}", ['house_id' => $houseId]);

        $this->response->answerCallback($confirmation);
        $this->index();
    }
}
