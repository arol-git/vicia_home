<?php

namespace Bot\Middlewares;

use Bot\Config\App;
use Bot\Core\Exceptions\BotException;
use Bot\Core\Logger;
use Bot\Core\Middleware;
use Bot\Core\Request;
use Bot\Models\RateLimitHit;
use Bot\Models\SecurityEvent;

/**
 * Class RateLimitMiddleware
 *
 * Limite le nombre de requêtes qu'un même utilisateur Telegram peut
 * effectuer dans une fenêtre glissante (RATE_LIMIT_MAX_REQUESTS sur
 * RATE_LIMIT_WINDOW_SECONDS, voir .env). Protège à la fois contre un
 * usage abusif et contre un client mal configuré qui bouclerait sur
 * l'envoi de messages.
 */
class RateLimitMiddleware implements Middleware
{
    /** Probabilité (1 sur N) de déclencher une purge des anciennes entrées à chaque requête. */
    private const PURGE_PROBABILITY = 50;

    public function handle(Request $request, callable $next): void
    {
        $telegramId = $request->telegramUserId();

        if ($telegramId === null) {
            $next($request);
            return;
        }

        $maxRequests = (int) App::env('RATE_LIMIT_MAX_REQUESTS', 20);
        $windowSeconds = (int) App::env('RATE_LIMIT_WINDOW_SECONDS', 60);

        $recentCount = RateLimitHit::countRecent($telegramId, $windowSeconds);

        if ($recentCount >= $maxRequests) {
            SecurityEvent::record('rate_limited', $telegramId, "Limite de $maxRequests requêtes / {$windowSeconds}s dépassée");
            Logger::channel('security')->warning("Limitation de débit appliquée : telegram_id=$telegramId ($recentCount requêtes récentes)");
            throw new BotException(
                "Vous envoyez des commandes trop rapidement. Merci de patienter quelques instants avant de réessayer.",
                "Rate limit dépassé pour telegram_id=$telegramId"
            );
        }

        RateLimitHit::record($telegramId);

        if (random_int(1, self::PURGE_PROBABILITY) === 1) {
            RateLimitHit::purgeOlderThan($windowSeconds * 2);
        }

        $next($request);
    }
}
