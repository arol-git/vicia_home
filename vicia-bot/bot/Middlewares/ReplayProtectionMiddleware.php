<?php

namespace Bot\Middlewares;

use Bot\Core\Logger;
use Bot\Core\Middleware;
use Bot\Core\Request;
use Bot\Models\ProcessedUpdate;
use Bot\Models\SecurityEvent;

/**
 * Class ReplayProtectionMiddleware
 *
 * Empêche qu'un même update Telegram ne soit traité deux fois (voir
 * Bot\Models\ProcessedUpdate). Contrairement aux autres intergiciels,
 * un rejeu détecté n'est PAS une erreur à signaler à l'utilisateur :
 * le traitement est silencieusement interrompu (aucune exception),
 * puisqu'il s'agit très généralement d'une relivraison bénigne du
 * webhook par Telegram lui-même plutôt que d'une attaque.
 */
class ReplayProtectionMiddleware implements Middleware
{
    public function handle(Request $request, callable $next): void
    {
        $updateId = $request->updateId();

        if ($updateId === null) {
            $next($request);
            return;
        }

        if (!ProcessedUpdate::markProcessed($updateId)) {
            SecurityEvent::record('replay_detected', $request->telegramUserId(), "update_id=$updateId déjà traité");
            Logger::channel('security')->info("Update déjà traité, ignoré : update_id=$updateId");
            return; // interruption silencieuse de la chaîne : $next() n'est jamais appelé
        }

        $next($request);
    }
}
