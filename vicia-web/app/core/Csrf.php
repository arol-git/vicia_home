<?php

namespace App\Core;

/**
 * Class Csrf
 *
 * Protection contre les attaques CSRF (Cross-Site Request Forgery).
 * Un jeton unique est généré par session et doit être renvoyé par
 * tout formulaire ou requête AJAX effectuant une action de modification
 * (POST, PUT, DELETE).
 */
class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    /**
     * Génère (si nécessaire) et retourne le jeton CSRF courant.
     */
    public static function token(): string
    {
        if (!Session::has(self::SESSION_KEY)) {
            Session::set(self::SESSION_KEY, bin2hex(random_bytes(32)));
        }

        return Session::get(self::SESSION_KEY);
    }

    /**
     * Génère le champ HTML caché à insérer dans les formulaires.
     */
    public static function field(): string
    {
        $token = htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8');

        return '<input type="hidden" name="csrf_token" value="' . $token . '">';
    }

    /**
     * Vérifie la validité d'un jeton CSRF soumis par le client.
     * Utilise hash_equals() afin d'éviter les attaques temporelles.
     */
    public static function verify(?string $submittedToken): bool
    {
        if (!$submittedToken || !Session::has(self::SESSION_KEY)) {
            return false;
        }

        return hash_equals(Session::get(self::SESSION_KEY), $submittedToken);
    }
}
