<?php

namespace Bot\Middlewares;

use Bot\Core\Exceptions\UnauthorizedException;
use Bot\Core\Logger;
use Bot\Core\Middleware;
use Bot\Core\Request;
use Bot\Models\AccessList;
use Bot\Models\SecurityEvent;

/**
 * Class WhitelistMiddleware
 *
 * Premier intergiciel de la chaîne : aucune autre logique ne doit
 * s'exécuter pour un utilisateur bloqué. Voir Bot\Models\AccessList
 * pour la sémantique exacte liste blanche/liste noire.
 */
class WhitelistMiddleware implements Middleware
{
    public function handle(Request $request, callable $next): void
    {
        $telegramId = $request->telegramUserId();

        if ($telegramId === null) {
            // Update sans utilisateur identifiable (ex. certains
            // updates de statut de canal) : rien à vérifier, on laisse
            // passer — le routeur l'ignorera de toute façon s'il n'a
            // pas de route correspondante.
            $next($request);
            return;
        }

        if (AccessList::isBlacklisted($telegramId)) {
            SecurityEvent::record('blacklist_denied', $telegramId, 'Utilisateur en liste noire');
            Logger::channel('security')->warning("Accès refusé (liste noire) : telegram_id=$telegramId");
            throw UnauthorizedException::blacklisted();
        }

        if (AccessList::whitelistModeEnabled() && !AccessList::isWhitelisted($telegramId)) {
            SecurityEvent::record('whitelist_denied', $telegramId, "Mode liste blanche actif, utilisateur non répertorié");
            Logger::channel('security')->warning("Accès refusé (hors liste blanche) : telegram_id=$telegramId");
            throw UnauthorizedException::notWhitelisted();
        }

        $next($request);
    }
}
