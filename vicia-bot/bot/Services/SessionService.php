<?php

namespace Bot\Services;

use Bot\Models\BotSession;

/**
 * Class SessionService
 *
 * Le bot n'a pas d'état en mémoire entre deux updates (chaque appel
 * webhook est une requête PHP indépendante) : cette classe est le
 * SEUL endroit du projet où l'on doit lire/écrire l'état de
 * conversation en cours (bot_sessions). Utilisée principalement par
 * Bot\Controllers\StartController pour la procédure de liaison de
 * compte en plusieurs étapes (e-mail puis mot de passe), et
 * potentiellement par d'autres flux conversationnels futurs
 * (modification d'un seuil d'alerte, par exemple).
 */
class SessionService
{
    private const DEFAULT_TTL_SECONDS = 300; // 5 minutes

    /**
     * Démarre ou remplace le flux de conversation en cours pour un
     * utilisateur. Le TTL est volontairement court : une procédure de
     * liaison de compte abandonnée en cours de route ne doit pas
     * rester exploitable indéfiniment (un tiers qui reprendrait le
     * téléphone déverrouillé de l'utilisateur, par exemple).
     */
    public static function start(int $telegramId, string $state, array $payload = [], int $ttlSeconds = self::DEFAULT_TTL_SECONDS): void
    {
        BotSession::upsert($telegramId, $state, $payload, date('Y-m-d H:i:s', time() + $ttlSeconds));
    }

    /**
     * Retourne la session active d'un utilisateur, ou null s'il n'y
     * en a aucune ou qu'elle est expirée (auto-purgée au passage).
     */
    public static function current(int $telegramId): ?array
    {
        $session = BotSession::findByTelegramId($telegramId);

        if (!$session) {
            return null;
        }

        if (strtotime($session['expires_at']) < time()) {
            BotSession::clearByTelegramId($telegramId);
            return null;
        }

        $session['payload'] = json_decode($session['payload'] ?? '{}', true) ?: [];
        return $session;
    }

    public static function state(int $telegramId): ?string
    {
        return self::current($telegramId)['state'] ?? null;
    }

    /**
     * Fusionne de nouvelles données dans le payload de la session en
     * cours, en conservant son état et son expiration d'origine.
     * Utilisé pour accumuler les réponses successives d'un même flux
     * (ex. mémoriser l'e-mail saisi en attendant le mot de passe).
     */
    public static function mergePayload(int $telegramId, array $additional): void
    {
        $session = self::current($telegramId);
        if (!$session) {
            return;
        }

        BotSession::upsert(
            $telegramId,
            $session['state'],
            array_merge($session['payload'], $additional),
            $session['expires_at']
        );
    }

    /**
     * Fait avancer le flux vers un nouvel état, en conservant le
     * payload accumulé jusqu'ici.
     */
    public static function transition(int $telegramId, string $newState, int $ttlSeconds = self::DEFAULT_TTL_SECONDS): void
    {
        $session = self::current($telegramId);
        self::start($telegramId, $newState, $session['payload'] ?? [], $ttlSeconds);
    }

    public static function clear(int $telegramId): void
    {
        BotSession::clearByTelegramId($telegramId);
    }
}
