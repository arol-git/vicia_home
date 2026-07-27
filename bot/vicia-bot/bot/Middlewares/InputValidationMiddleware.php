<?php

namespace Bot\Middlewares;

use Bot\Core\Exceptions\BotException;
use Bot\Core\Logger;
use Bot\Core\Middleware;
use Bot\Core\Request;

/**
 * Class InputValidationMiddleware
 *
 * Hygiène minimale appliquée à TOUT texte entrant (message ou
 * callback_data), avant même que le routeur ne détermine quel
 * contrôleur doit le traiter :
 *
 *   - rejet des octets nuls et caractères de contrôle (hors saut de
 *     ligne/tabulation), qui n'ont aucune raison légitime d'apparaître
 *     dans un message Telegram normal ;
 *   - plafond de longueur, en défense en profondeur même si Telegram
 *     applique déjà ses propres limites côté serveur.
 *
 * Les règles de validation MÉTIER (format d'un e-mail, longueur d'un
 * mot de passe...) ne vivent pas ici mais dans Bot\Helpers\Validator,
 * appliqué explicitement par chaque contrôleur selon le champ attendu.
 */
class InputValidationMiddleware implements Middleware
{
    private const MAX_TEXT_LENGTH = 4096; // limite Telegram elle-même pour un message texte
    private const MAX_CALLBACK_LENGTH = 64; // limite Telegram elle-même pour callback_data

    public function handle(Request $request, callable $next): void
    {
        if ($request->isMessage()) {
            $this->assertClean($request->text(), self::MAX_TEXT_LENGTH, 'message');
        }

        if ($request->isCallbackQuery()) {
            $this->assertClean($request->callbackData(), self::MAX_CALLBACK_LENGTH, 'callback_data');
        }

        $next($request);
    }

    private function assertClean(string $value, int $maxLength, string $label): void
    {
        if (mb_strlen($value) > $maxLength) {
            Logger::channel('security')->warning("Entrée $label rejetée : longueur excessive (" . mb_strlen($value) . ' caractères)');
            throw new BotException(
                "Votre message est trop long.",
                "Entrée $label rejetée pour longueur excessive"
            );
        }

        // Autorise les sauts de ligne (\n, \r) et tabulations (\t),
        // rejette tout autre caractère de contrôle (0x00-0x08,
        // 0x0B-0x1F, 0x7F), qui n'a pas sa place dans une saisie
        // utilisateur légitime.
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)) {
            Logger::channel('security')->warning("Entrée $label rejetée : caractères de contrôle détectés");
            throw new BotException(
                "Votre message contient des caractères non autorisés.",
                "Entrée $label rejetée pour caractères de contrôle"
            );
        }
    }
}
