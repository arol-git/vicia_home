<?php

namespace Bot\Config;

use Dotenv\Dotenv;

/**
 * Class App
 *
 * Point d'amorçage de la configuration applicative du bot. Charge le
 * fichier .env (via vlucas/phpdotenv) une seule fois par requête et
 * expose un accès typé aux variables d'environnement, avec valeurs
 * par défaut sûres pour l'environnement de développement.
 *
 * Le bot est un client de plus de l'API Vicia Home : cette classe ne
 * contient donc aucune configuration de connexion à `vicia_home`,
 * uniquement celle de sa propre base `vicia_bot` et de ses intégrations
 * externes (Telegram, API Vicia Home).
 */
class App
{
    private static bool $booted = false;

    public const ROOT_PATH = __DIR__ . '/../..';

    /**
     * Charge le fichier .env et fige les réglages critiques (fuseau
     * horaire, affichage des erreurs). Idempotent : un second appel
     * ne recharge rien.
     */
    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }

        $dotenv = Dotenv::createImmutable(self::ROOT_PATH);
        $dotenv->safeLoad();

        $dotenv->required(['TELEGRAM_BOT_TOKEN', 'VICIA_API_BASE_URL', 'MYSQL_DATABASE', 'MYSQL_USER', 'APP_KEY'])
            ->notEmpty();

        date_default_timezone_set(self::env('APP_TIMEZONE', 'UTC'));

        if (self::env('APP_ENV', 'production') === 'development') {
            ini_set('display_errors', '1');
            error_reporting(E_ALL);
        } else {
            ini_set('display_errors', '0');
        }

        self::$booted = true;
    }

    /**
     * Lit une variable d'environnement, avec valeur par défaut.
     * Ne journalise et n'expose jamais les clés sensibles
     * (APP_KEY, TELEGRAM_BOT_TOKEN, *_SECRET, MYSQL_PASSWORD) telles quelles :
     * à l'appelant de rester prudent avec ce qu'il en fait.
     */
    public static function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false || $value === null) {
            return $default;
        }

        return match (strtolower((string) $value)) {
            'true', '(true)'   => true,
            'false', '(false)' => false,
            'null', '(null)'   => null,
            'empty', '(empty)' => '',
            default            => $value,
        };
    }

    public static function isDevelopment(): bool
    {
        return self::env('APP_ENV', 'production') === 'development';
    }

    public static function path(string $relative = ''): string
    {
        return rtrim(self::ROOT_PATH, '/') . ($relative ? '/' . ltrim($relative, '/') : '');
    }
}
