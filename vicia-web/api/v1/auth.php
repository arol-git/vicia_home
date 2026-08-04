<?php
/**
 * api/v1/auth.php
 *
 * Authentification de l'API par jetons JWT (voir App\Core\Jwt) :
 *
 *   POST /api/v1/auth/login    { "email": "...", "password": "..." }
 *     -> { success, access_token, refresh_token, expires_in, user }
 *
 *   POST /api/v1/auth/refresh  { "refresh_token": "..." }
 *     -> { success, access_token, refresh_token, expires_in }
 *
 * Le jeton d'accès (access_token) est un JWT à courte durée de vie
 * (voir config('jwt_access_ttl')), auto-porteur : sa validité ne
 * nécessite aucune consultation de la base de données, seulement une
 * vérification de signature et d'expiration (App\Core\Jwt::decode()).
 *
 * Le jeton de rafraîchissement (refresh_token) est une chaîne opaque
 * à longue durée de vie, dont seul le hachage SHA-256 est persisté
 * (colonne dédiée users.api_refresh_token — distincte de
 * remember_token, qui reste propre à la session "Se souvenir de moi"
 * de l'interface Web, pour que les deux ne s'invalident jamais l'une
 * l'autre). Chaque rafraîchissement fait tourner (rotate) le jeton :
 * l'ancien devient immédiatement inutilisable, limitant l'impact d'un
 * vol.
 */

use App\Core\Jwt;
use App\Models\User;

function handle_auth(string $method, ?string $id, ?string $subaction): void
{
    if ($method === 'POST' && $id === 'login') {
        api_auth_login();
        return;
    }

    if ($method === 'POST' && $id === 'refresh') {
        api_auth_refresh();
        return;
    }

    api_response(['success' => false, 'message' => 'Route d’authentification inconnue.'], 404);
}

function api_auth_login(): void
{
    $input    = api_input();
    $email    = trim((string) ($input['email'] ?? ''));
    $password = (string) ($input['password'] ?? '');

    $user = User::findByEmail($email);

    if (!$user || $user['status'] !== 'active' || !password_verify($password, $user['password_hash'])) {
        api_response(['success' => false, 'message' => 'Identifiants incorrects.'], 401);
    }

    api_response(array_merge(
        ['success' => true, 'user' => api_public_user($user)],
        api_issue_tokens((int) $user['id'])
    ));
}

function api_auth_refresh(): void
{
    $input = api_input();
    $refreshToken = (string) ($input['refresh_token'] ?? '');

    if (!$refreshToken) {
        api_response(['success' => false, 'message' => 'Le jeton de rafraîchissement est obligatoire.'], 422);
    }

    $user = \App\Core\Database::query(
        'SELECT * FROM users WHERE api_refresh_token = :token AND status = "active" LIMIT 1',
        ['token' => hash('sha256', $refreshToken)]
    )->fetch();

    if (!$user) {
        api_response(['success' => false, 'message' => 'Jeton de rafraîchissement invalide ou expiré.'], 401);
    }

    api_response(array_merge(['success' => true], api_issue_tokens((int) $user['id'])));
}

/**
 * Émet une nouvelle paire de jetons (accès + rafraîchissement) pour
 * un utilisateur donné, et persiste le hachage du jeton de
 * rafraîchissement (rotation systématique).
 */
function api_issue_tokens(int $userId): array
{
    $accessToken = Jwt::encode(['sub' => $userId], config('jwt_access_ttl'));

    $refreshToken = bin2hex(random_bytes(32));
    User::setApiRefreshToken($userId, hash('sha256', $refreshToken));

    return [
        'access_token'  => $accessToken,
        'refresh_token' => $refreshToken,
        'token_type'    => 'Bearer',
        'expires_in'    => config('jwt_access_ttl'),
    ];
}

function api_public_user(array $user): array
{
    return ['id' => $user['id'], 'name' => $user['name'], 'email' => $user['email'], 'role' => $user['role']];
}
