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
 * Vérifie le jeton d'API porté dans l'en-tête Authorization et
 * retourne l'utilisateur associé. Interrompt la requête avec un code
 * 401 si le jeton est absent ou invalide.
 */
function api_authenticate(): array
{
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? '');

    if (!preg_match('/Bearer\s+(\S+)/', $authHeader, $matches)) {
        api_response(['success' => false, 'message' => 'Authentification requise (en-tête Authorization manquant).'], 401);
    }

    $token = $matches[1];

    // Le jeton d'API correspond au hachage SHA-256 du remember_token
    // utilisateur, mécanisme simple et cohérent avec l'authentification
    // "Se souvenir de moi" déjà en place côté interface Web.
    $user = Database::query(
        'SELECT id, name, email, role, status FROM users WHERE remember_token = :token LIMIT 1',
        ['token' => hash('sha256', $token)]
    )->fetch();

    if (!$user || $user['status'] !== 'active') {
        api_response(['success' => false, 'message' => 'Jeton d’API invalide ou expiré.'], 401);
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

/**
 * Retourne le rôle de l'utilisateur API dans une maison.
 * Débutant : on sépare cette fonction de api_authorize_house() parce
 * que certaines routes acceptent la lecture pour tous les membres,
 * mais réservent l'écriture aux administrateurs.
 */
function api_house_role(array $user, int $houseId): ?string
{
    return \App\Models\House::roleOfUser($houseId, (int) $user['id'], (string) $user['role']);
}

/**
 * Bloque une route API si l'utilisateur n'est pas administrateur.
 */
function api_require_house_admin(array $user, int $houseId): void
{
    if (api_house_role($user, $houseId) !== 'admin') {
        api_response(['success' => false, 'message' => 'Action réservée à l’administration.'], 403);
    }
}

/**
 * Retire les topics MQTT d'une réponse API pour les non-admins.
 * Le paramètre peut être une ligne unique ou une liste de lignes.
 */
function api_hide_mqtt_topics($data, array $user, int $houseId)
{
    if (can_view_mqtt_topics(api_house_role($user, $houseId))) {
        return $data;
    }

    return hide_mqtt_topics($data);
}
