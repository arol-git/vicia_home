<?php

namespace Bot\Controllers;

use Bot\Core\Controller;
use Bot\Models\TelegramUser;

/**
 * Class SettingsController
 *
 * Module "⚙ Paramètres" du compte lié. L'authentification à deux
 * facteurs est architecturée (colonne telegram_users.two_factor_secret,
 * déjà en place depuis le Module 3) mais volontairement non activée
 * dans cette livraison — voir l'analyse préalable, §Sécurité. Activer
 * la vérification effective d'un code TOTP à chaque /start est une
 * extension mineure de ce contrôleur le jour où elle sera priorisée,
 * sans reprise de schéma.
 */
class SettingsController extends Controller
{
    public function index(): void
    {
        $telegramId = $this->request->telegramUserId();
        $user = TelegramUser::findByTelegramId($telegramId);

        $twoFactorStatus = $user['two_factor_secret'] ? 'activée' : 'désactivée (bientôt disponible)';

        $lines = [
            "⚙ <b>Paramètres</b>",
            "",
            "Authentification à deux facteurs : <b>{$twoFactorStatus}</b>",
            "Notifications poussées : <b>activées</b> pour la maison active",
        ];

        $this->respond(implode("\n", $lines));
    }
}
