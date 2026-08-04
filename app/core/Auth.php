<?php

namespace App\Core;

use App\Core\Request;
use App\Models\User;

/**
 * Class Auth
 *
 * Centralise l'authentification et le contrôle des rôles. S'appuie
 * sur la session PHP pour l'utilisateur courant et sur un cookie
 * "remember_token" pour la fonction "Se souvenir de moi".
 */
class Auth
{
    private const SESSION_USER_KEY = '_auth_user_id';
    private const SESSION_HOUSE_KEY = '_current_house_id';
    private const REMEMBER_COOKIE  = 'vicia_remember';

    /**
     * Tente d'authentifier un utilisateur à partir de son e-mail et
     * mot de passe en clair. Retourne l'utilisateur en cas de succès,
     * null sinon. Ne journalise jamais le mot de passe.
     */
    public static function attempt(string $email, string $password, bool $remember = false): ?array
    {
        $user = User::findByEmail($email);

        if (!$user || $user['status'] !== 'active') {
            return null;
        }

        if (!password_verify($password, $user['password_hash'])) {
            return null;
        }

        self::login($user, $remember);

        return $user;
    }

    /**
     * Ouvre la session applicative pour l'utilisateur donné.
     */
    public static function login(array $user, bool $remember = false): void
    {
        // Prévention des attaques de fixation de session
        Session::regenerate();
        Session::set(self::SESSION_USER_KEY, $user['id']);

        User::touchLastLogin($user['id']);

        if ($remember) {
            $config = require __DIR__ . '/../../config/config.php';
            $token  = bin2hex(random_bytes(32));
            User::setRememberToken($user['id'], hash('sha256', $token));

            setcookie(self::REMEMBER_COOKIE, $user['id'] . '|' . $token, [
                'expires'  => time() + $config['remember_me_ttl'],
                'path'     => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
    }

    /**
     * Ferme la session applicative et supprime le cookie "Se souvenir de moi".
     */
    public static function logout(): void
    {
        $userId = self::id();
        if ($userId) {
            User::setRememberToken($userId, null);
        }

        Session::destroy();
        setcookie(self::REMEMBER_COOKIE, '', time() - 3600, '/');
    }

    /**
     * Tente de restaurer une session à partir du cookie "remember me".
     * Appelée automatiquement au bootstrap si aucune session active.
     */
    public static function restoreFromRememberCookie(): void
    {
        if (self::check() || empty($_COOKIE[self::REMEMBER_COOKIE])) {
            return;
        }

        [$userId, $token] = array_pad(explode('|', $_COOKIE[self::REMEMBER_COOKIE], 2), 2, null);
        if (!$userId || !$token) {
            return;
        }

        $user = User::find((int) $userId);
        if ($user && $user['remember_token'] && hash_equals($user['remember_token'], hash('sha256', $token))) {
            self::login($user, true);
        }
    }

    public static function check(): bool
    {
        return Session::has(self::SESSION_USER_KEY);
    }

    public static function id(): ?int
    {
        return Session::get(self::SESSION_USER_KEY);
    }

    /**
     * Retourne l'utilisateur actuellement connecté (tableau associatif)
     * ou null si aucune session n'est active.
     */
    public static function user(): ?array
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        $id = self::id();
        if (!$id) {
            return null;
        }
        $cached = User::find($id);
        return $cached;
    }

    public static function role(): ?string
    {
        $user = self::user();
        return $user['role'] ?? null;
    }

    /**
     * Vérifie que l'utilisateur connecté possède l'un des rôles fournis.
     */
    public static function hasRole(array $roles): bool
    {
        return in_array(self::role(), $roles, true);
    }

    /**
     * Exige une authentification ; renvoie JSON sur AJAX ou redirige
     * vers la page de connexion si l'accès n'est pas autorisé.
     */
    public static function requireLogin(): void
    {
        if (self::check()) {
            return;
        }

        $request = new Request();
        if ($request->isAjax()) {
            Response::error('Authentification requise.', 401);
            return;
        }

        Response::redirect('/login');
    }

    /**
     * Exige un rôle particulier ; retourne une erreur 403 sinon.
     */
    public static function requireRole(array $roles): void
    {
        self::requireLogin();
        if (!self::hasRole($roles)) {
            http_response_code(403);
            echo 'Accès refusé : privilèges insuffisants.';
            exit;
        }
    }

    public static function currentHouseId(): ?int
    {
        $user = self::user();
        if (!$user) {
            return null;
        }

        $houses = \App\Models\House::forUser($user['id'], $user['role']);
        if (empty($houses)) {
            return null;
        }

        $selected = Session::get(self::SESSION_HOUSE_KEY);
        foreach ($houses as $house) {
            if ((int) $house['id'] === (int) $selected) {
                return (int) $selected;
            }
        }

        $firstId = (int) $houses[0]['id'];
        Session::set(self::SESSION_HOUSE_KEY, $firstId);
        return $firstId;
    }

    public static function switchHouse(int $houseId): bool
    {
        $user = self::user();
        if (!$user || self::roleOnHouse($houseId) === null) {
            return false;
        }

        Session::set(self::SESSION_HOUSE_KEY, $houseId);
        return true;
    }

    public static function roleOnHouse(int $houseId): ?string
    {
        $user = self::user();
        if (!$user) {
            return null;
        }

        return \App\Models\House::roleOfUser($houseId, $user['id'], $user['role']);
    }

    public static function requireHouseRole(array $roles, ?int $houseId = null): int
    {
        self::requireLogin();
        $houseId = $houseId ?? self::currentHouseId();
        $request = new Request();

        if (!$houseId) {
            if ($request->isAjax()) {
                Response::error('Aucune maison associée à ce compte.', 403);
            } else {
                http_response_code(403);
                echo 'Aucune maison associée à ce compte.';
            }
            exit;
        }

        $role = self::roleOnHouse($houseId);
        if ($role === null || !in_array($role, $roles, true)) {
            if ($request->isAjax()) {
                Response::error('Accès refusé : privilèges insuffisants sur cette maison.', 403);
            } else {
                http_response_code(403);
                echo 'Accès refusé : privilèges insuffisants sur cette maison.';
            }
            exit;
        }

        return $houseId;
    }
}
