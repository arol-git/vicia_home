<?php

namespace App\Core;

/**
 * Class Session
 *
 * Encapsule la gestion des sessions PHP : démarrage sécurisé,
 * lecture, écriture, et messages "flash" affichés une seule fois
 * (notifications de succès/erreur après redirection).
 */
class Session
{
    /**
     * Démarre la session avec des paramètres de cookie sécurisés.
     * Doit être appelée une seule fois, au tout début du cycle de requête.
     */
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'secure'   => self::isHttps(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_name('vicia_session');
            session_start();
        }
    }

    private static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    }

    public static function set(string $key, $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * Détruit entièrement la session courante (utilisé à la déconnexion).
     */
    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    /**
     * Enregistre un message flash, affiché une seule fois par la vue
     * puis automatiquement supprimé.
     */
    public static function flash(string $key, string $message): void
    {
        $_SESSION['_flash'][$key] = $message;
    }

    /**
     * Récupère et supprime un message flash.
     */
    public static function getFlash(string $key): ?string
    {
        $message = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);

        return $message;
    }

    /**
     * Régénère l'identifiant de session (utilisé après connexion pour
     * prévenir les attaques de fixation de session).
     */
    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }
}
