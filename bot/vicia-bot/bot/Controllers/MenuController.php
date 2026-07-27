<?php

namespace Bot\Controllers;

use Bot\Core\Controller;
use Bot\Models\TelegramUser;
use Bot\Services\KeyboardBuilder;

/**
 * Class MenuController
 *
 * Affiche le menu principal et route ses entrées vers les modules
 * déjà livrés (Mon compte, Aide) ou signale un module pas encore
 * disponible pour ceux à venir (Modules 8 à 14) — le menu garde ainsi
 * sa structure définitive dès cette livraison, sans attendre que
 * chaque module métier soit terminé.
 */
class MenuController extends Controller
{
    /** Entrées déjà routées vers un contrôleur réel. */
    private const READY = ['compte' => [AccountController::class, 'overview'], 'aide' => [HelpController::class, 'index']];

    public function index(): void
    {
        if (!TelegramUser::findByTelegramId($this->request->telegramUserId())) {
            $this->respond("🔒 Envoyez /start pour lier votre compte avant d'utiliser le menu.");
            return;
        }

        $this->respond("📋 <b>Menu principal</b>\n\nQue souhaitez-vous faire ?", KeyboardBuilder::mainMenu());
    }

    /**
     * Point d'entrée unique des callbacks "menu:*" (voir routes/web.php).
     */
    public function open(string $key): void
    {
        if (isset(self::READY[$key])) {
            [$class, $method] = self::READY[$key];
            (new $class($this->request, $this->telegram))->$method();
            return;
        }

        $this->response->answerCallback('Ce module arrive bientôt 🚧', showAlert: true);
    }
}
