<?php
/**
 * api/v1/voice.php
 *
 * Ressource REST /api/v1/voice — traitement des commandes vocales.
 * Endpoint indépendant de l'IA pour l'assistante vocale "simple".
 * Accepte l'authentification par bearer token (API) ou par session (web frontend).
 *
 *   POST   /api/v1/voice/command   Traiter une commande vocale
 */

use App\Services\VoiceCommandService;
use App\Services\BatchCommandExecutor;

function handle_voice(string $method, ?string $id, ?string $subaction): void
{
    $action = $subaction ?? ($id === 'command' ? 'command' : null);

    // Authentifier via bearer token OU session HTTP
    $user = api_authenticate_or_session();
    if (!$user) {
        api_response(['success' => false, 'message' => 'Authentification requise'], 401);
    }

    $input = api_input();
    
    $houseId = api_authorize_house($user, $input);

    if ($method === 'POST' && $action === 'command') {
        handleVoiceCommand($user, $houseId, $input);
        return;
    }

    api_response(['success' => false, 'message' => 'Endpoint non reconnu.'], 400);
}

/**
 * Authentifie via bearer token (API) OU session HTTP (web frontend).
 * Retourne l'utilisateur ou null si non authentifié.
 */
function api_authenticate_or_session(): ?array
{
    // Essayer d'abord l'authentification par session (web frontend)
    if (\App\Core\Auth::check()) {
        $sessionUser = \App\Core\Auth::user();
        if ($sessionUser) {
            return [
                'id' => $sessionUser['id'],
                'name' => $sessionUser['name'],
                'email' => $sessionUser['email'],
                'role' => $sessionUser['role'] ?? 'user',
                'status' => 'active',
            ];
        }
    }

    // Sinon, essayer l'authentification par bearer token
    return api_authenticate_token();
}

/**
 * Authentifie par bearer token (pour API tierce/mobile).
 * Retourne null si pas de token valide.
 */
function api_authenticate_token(): ?array
{
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? '');

    if (!preg_match('/Bearer\s+(\S+)/', $authHeader, $matches)) {
        return null;
    }

    $token = $matches[1];

    // Le jeton d'API correspond au hachage SHA-256 du remember_token
    $user = \App\Core\Database::query(
        'SELECT id, name, email, role, status FROM users WHERE remember_token = :token LIMIT 1',
        ['token' => hash('sha256', $token)]
    )->fetch();

    if (!$user || $user['status'] !== 'active') {
        return null;
    }

    return $user;
}

/**
 * Traite une commande vocale reçue.
 */
function handleVoiceCommand(array $user, int $houseId, array $input): void
{
    $command = trim((string) ($input['command'] ?? ''));

    if (strlen($command) === 0) {
        api_response(['success' => false, 'message' => 'Commande vide'], 400);
        return;
    }

    // Limiter la longueur pour éviter les abus
    if (strlen($command) > 500) {
        api_response(['success' => false, 'message' => 'Commande trop longue'], 400);
        return;
    }

    // Parser la commande vocale
    $parsed = VoiceCommandService::parse($command, $houseId);

    if (!$parsed['success']) {
        api_response([
            'success' => false,
            'message' => $parsed['message'] ?? 'Erreur de traitement',
        ], 400);
        return;
    }

    // Exécuter toutes les commandes en batch
    $result = BatchCommandExecutor::execute(
        $parsed['commands'],
        $houseId,
        $user['id']
    );

    if (!$result['success']) {
        api_response([
            'success' => false,
            'message' => $result['message'] ?? 'Erreur lors de l\'exécution',
        ], 400);
        return;
    }

    api_response([
        'success' => true,
        'message' => $result['message'],
        'executed' => $result['executed'],
        'failed' => $result['failed'],
        'commands' => $result['commands'],
    ]);
}

