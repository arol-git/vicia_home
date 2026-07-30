<?php

namespace Bot\Controllers;

use Bot\Core\Controller;
use Bot\Services\HouseContext;
use Bot\Services\ViciaApiClient;

/**
 * Class AutomationController
 *
 * Module "🤖 Automatisation" : liste des règles et activation/
 * désactivation. La création d'une nouvelle règle depuis le bot
 * (formulaire conversationnel à plusieurs étapes, à l'image de la
 * liaison de compte) est une extension naturelle mais volontairement
 * hors du périmètre de cette passe — se crée pour l'instant depuis
 * l'interface Web, le bot n'en assurant ici que le suivi.
 */
class AutomationController extends Controller
{
    public function index(): void
    {
        $telegramId = $this->request->telegramUserId();
        $houseId = HouseContext::currentHouseId($telegramId);
        $rules = ViciaApiClient::forTelegramUser($telegramId)->get('automation', ['house_id' => $houseId])['data'] ?? [];

        if (empty($rules)) {
            $this->respond("🤖 Aucune règle d'automatisation pour cette maison. Créez-en une depuis l'interface Web.");
            return;
        }

        $keyboard = array_map(function ($r) {
            $emoji = $r['is_active'] ? '🟢' : '⚪';
            return [['text' => "$emoji {$r['name']}", 'callback_data' => "auto:toggle:{$r['id']}"]];
        }, $rules);

        $this->respond("🤖 <b>Règles d'automatisation</b>\n\nAppuyez pour activer/désactiver :", $keyboard);
    }

    public function toggle(string $id): void
    {
        $telegramId = $this->request->telegramUserId();
        $houseId = HouseContext::currentHouseId($telegramId);

        $result = ViciaApiClient::forTelegramUser($telegramId)->post("automation/{$id}/toggle", ['house_id' => $houseId]);

        $this->response->answerCallback(($result['data']['is_active'] ?? false) ? 'Règle activée' : 'Règle désactivée');
        $this->index();
    }
}
