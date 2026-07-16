<?php
/**
 * api/v1/auth.php
 *
 * Point de terminaison d'authentification de l'API :
 *   POST /api/v1/auth/login  { "email": "...", "password": "..." }
 *     -> { success, token, user }
 *
 * Le jeton retourné doit être transmis dans l'en-tête
 * "Authorization: Bearer <token>" pour toutes les requêtes suivantes.
 */

use App\Models\User;

function handle_auth(string $method, ?string $id, ?string $subaction): void
{
    if ($method === 'POST' && $id === 'login') {
        $input    = api_input();
        $email    = trim((string) ($input['email'] ?? ''));
        $password = (string) ($input['password'] ?? '');

        $user = User::findByEmail($email);

        if (!$user || $user['status'] !== 'active' || !password_verify($password, $user['password_hash'])) {
            api_response(['success' => false, 'message' => 'Identifiants incorrects.'], 401);
        }

        $token = bin2hex(random_bytes(32));
        User::setRememberToken($user['id'], hash('sha256', $token));

        api_response([
            'success' => true,
            'token'   => $token,
            'user'    => [
                'id'    => $user['id'],
                'name'  => $user['name'],
                'email' => $user['email'],
                'role'  => $user['role'],
            ],
        ]);
    }

    api_response(['success' => false, 'message' => 'Route d’authentification inconnue.'], 404);
}
