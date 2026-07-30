<?php

namespace Bot\Controllers;

use Bot\Core\Controller;
use Bot\Core\Exceptions\ApiException;
use Bot\Core\Exceptions\UnauthorizedException;
use Bot\Models\TelegramUser;
use Bot\Services\ViciaApiClient;

/**
 * Class AccountController
 *
 * Gestion du compte lié : sélection de la maison active (UC-03),
 * aperçu du compte (UC menu "Mon compte"), et déliaison (UC-02).
 */
class AccountController extends Controller
{
    /**
     * /maisons — liste les maisons accessibles et propose de changer
     * la maison actuellement sélectionnée.
     */
    public function listHouses(): void
    {
        $telegramId = $this->requireLinkedAccount();
        if ($telegramId === null) {
            return;
        }

        $houses = ViciaApiClient::forTelegramUser($telegramId)->get('houses')['data'] ?? [];

        if (empty($houses)) {
            $this->reply("Vous n'êtes rattaché à aucune maison pour le moment. Créez-en une depuis l'interface Web Vicia Home.");
            return;
        }

        $telegramUser = TelegramUser::findByTelegramId($telegramId);
        $currentId = (int) ($telegramUser['current_house_id'] ?? 0);

        $keyboard = array_map(function ($house) use ($currentId) {
            $label = $house['name'] . ((int) $house['id'] === $currentId ? ' ✅' : '');
            return [['text' => $label, 'callback_data' => "house:select:{$house['id']}"]];
        }, $houses);

        $this->respond("🏠 Vos maisons — sélectionnez celle à piloter :", $keyboard);
    }

    /**
     * Callback "house:select:{id}" — bascule la maison active.
     */
    public function selectHouse(string $houseId): void
    {
        $telegramId = $this->requireLinkedAccount();
        if ($telegramId === null) {
            return;
        }

        $houses = ViciaApiClient::forTelegramUser($telegramId)->get('houses')['data'] ?? [];
        $house = null;
        foreach ($houses as $h) {
            if ((int) $h['id'] === (int) $houseId) {
                $house = $h;
                break;
            }
        }

        if (!$house) {
            // L'API elle-même fait foi de l'appartenance à la maison :
            // si elle n'apparaît pas dans la liste retournée, l'accès
            // n'est plus valide (retiré entre-temps, par exemple).
            $this->response->answerCallback("Vous n'avez plus accès à cette maison.", showAlert: true);
            return;
        }

        TelegramUser::setCurrentHouse($telegramId, (int) $house['id']);
        $this->respond("✅ Maison sélectionnée : <b>{$house['name']}</b>\n\nEnvoyez /aide pour découvrir les commandes disponibles.");
    }

    /**
     * "Mon compte" — aperçu du compte lié.
     */
    public function overview(): void
    {
        $telegramId = $this->requireLinkedAccount();
        if ($telegramId === null) {
            return;
        }

        $telegramUser = TelegramUser::findByTelegramId($telegramId);
        $houseName = null;

        if ($telegramUser['current_house_id']) {
            try {
                $houses = ViciaApiClient::forTelegramUser($telegramId)->get('houses')['data'] ?? [];
                foreach ($houses as $h) {
                    if ((int) $h['id'] === (int) $telegramUser['current_house_id']) {
                        $houseName = $h['name'];
                    }
                }
            } catch (ApiException) {
                // laisse $houseName à null : simple agrément d'affichage
            }
        }

        $lines = [
            "👤 <b>Mon compte</b>",
            "",
            "Telegram : @" . ($telegramUser['telegram_username'] ?: '(sans nom d’utilisateur)'),
            "Lié depuis : " . date('d/m/Y', strtotime($telegramUser['linked_at'])),
            "Maison active : " . ($houseName ?? 'aucune sélectionnée'),
        ];

        $this->respond(implode("\n", $lines), [
            [['text' => '🏠 Changer de maison', 'callback_data' => 'account:houses']],
            [['text' => '🔓 Délier mon compte', 'callback_data' => 'account:unlink_confirm']],
        ]);
    }

    public function goToHouses(): void
    {
        $this->listHouses();
    }

    /**
     * /delier — demande confirmation avant de délier le compte
     * (action irréversible sans nouvelle procédure de liaison
     * complète).
     */
    public function confirmUnlink(): void
    {
        if ($this->requireLinkedAccount() === null) {
            return;
        }

        $this->respond(
            "⚠️ Voulez-vous vraiment délier votre compte Telegram de Vicia Home ?\nVous ne recevrez plus aucune notification tant que vous ne l'aurez pas relié à nouveau.",
            [[
                ['text' => '✅ Oui, délier', 'callback_data' => 'account:unlink'],
                ['text' => '❌ Annuler', 'callback_data' => 'account:cancel'],
            ]]
        );
    }

    public function unlink(): void
    {
        $telegramId = $this->requireLinkedAccount();
        if ($telegramId === null) {
            return;
        }

        TelegramUser::unlink($telegramId);
        $this->respond("Votre compte a été délié. Envoyez /start pour en lier un nouveau à tout moment.");
    }

    public function cancelUnlink(): void
    {
        $this->respond("Annulé. Votre compte reste lié.");
    }

    /**
     * Vérifie qu'un compte est bien lié et retourne l'identifiant
     * Telegram, ou répond avec le message d'invitation à /start et
     * retourne null sinon.
     */
    private function requireLinkedAccount(): ?int
    {
        $telegramId = $this->request->telegramUserId();

        if (!TelegramUser::findByTelegramId($telegramId)) {
            $this->respond('🔒 ' . UnauthorizedException::accountNotLinked()->userMessage());
            return null;
        }

        return $telegramId;
    }
}
