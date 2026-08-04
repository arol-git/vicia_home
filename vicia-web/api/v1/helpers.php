<?php
/**
 * api/v1/helpers.php
 *
 * Fonctions communes à tous les points de terminaison de l'API REST :
 * émission de réponses JSON normalisées, authentification par jeton
 * API, et lecture du corps de requête JSON.
 */

use App\Core\Database;

/**
 * Émet une réponse JSON avec le code de statut HTTP fourni, puis
 * termine l'exécution du script.
 */
function api_response(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Lit et décode le corps JSON de la requête entrante (pour les
 * méthodes POST/PUT n'utilisant pas application/x-www-form-urlencoded).
 */
function api_input(): array
{
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        return $decoded;
    }
    return $_POST;
}

/**
 * Vérifie le jeton JWT porté dans l'en-tête Authorization et retourne
 * l'utilisateur associé. Interrompt la requête avec un code 401 si le
 * jeton est absent, mal formé, expiré ou signé avec une autre clé —
 * dans tous ces cas, un message volontairement générique
 * ("jeton invalide ou expiré") pour ne pas distinguer ces situations
 * côté client, qui doit simplement appeler /auth/refresh.
 */
function api_authenticate(): array
{
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? '');

    if (!preg_match('/Bearer\s+(\S+)/', $authHeader, $matches)) {
        api_response(['success' => false, 'message' => 'Authentification requise (en-tête Authorization manquant).'], 401);
    }

    $claims = \App\Core\Jwt::decode($matches[1]);
    if (!$claims || empty($claims['sub'])) {
        api_response(['success' => false, 'message' => 'Jeton d’accès invalide ou expiré.', 'code' => 'token_expired'], 401);
    }

    $user = Database::query(
        'SELECT id, name, email, role, status FROM users WHERE id = :id LIMIT 1',
        ['id' => $claims['sub']]
    )->fetch();

    if (!$user || $user['status'] !== 'active') {
        api_response(['success' => false, 'message' => 'Compte introuvable ou suspendu.'], 401);
    }

    return $user;
}

/**
 * Résout et vérifie la maison ciblée par une requête d'API : lue
 * dans le paramètre "house_id" (query string pour GET, corps JSON
 * pour POST/PUT/DELETE), puis vérifiée contre les droits de
 * l'utilisateur authentifié. Interrompt la requête avec un code 400
 * ou 403 en cas d'absence ou d'accès refusé.
 */
function api_authorize_house(array $user, array $input = []): int
{
    $houseId = (int) ($input['house_id'] ?? $_GET['house_id'] ?? 0);

    if (!$houseId) {
        api_response(['success' => false, 'message' => 'Le paramètre « house_id » est obligatoire.'], 400);
    }

    $role = \App\Models\House::roleOfUser($houseId, $user['id'], $user['role']);
    if ($role === null) {
        api_response(['success' => false, 'message' => 'Vous n’avez pas accès à cette maison.'], 403);
    }

    return $houseId;
}
