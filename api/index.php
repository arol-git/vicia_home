<?php
/**
 * api/index.php
 *
 * Point d'entrée de l'API REST de Vicia Home. Cette API est destinée
 * à l'intégration avec des systèmes tiers ainsi qu'à une éventuelle
 * application mobile future ; elle est distincte du contrôleur Web
 * public/index.php mais réutilise le même socle applicatif (modèles,
 * base de données, authentification).
 *
 * Authentification : jeton porteur transmis dans l'en-tête
 * "Authorization: Bearer <token>". Pour cette version du produit, le
 * jeton correspond à une clé API statique par utilisateur stockée en
 * base (voir api/v1/auth.php) ; une évolution vers des jetons JWT à
 * expiration est prévue en roadmap (voir docs/README.md).
 *
 * Toutes les réponses sont au format JSON.
 */

require __DIR__ . '/../app/core/bootstrap.php';
require __DIR__ . '/v1/helpers.php';

\App\Core\Session::start();
\App\Core\Auth::restoreFromRememberCookie();

header('Content-Type: application/json; charset=utf-8');

$uri    = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$segments = explode('/', $uri);


// On attend une URI de la forme api/v1/<ressource>[/<id>][/<action>]
$apiIndex = array_search('api', $segments, true);
$segments = $apiIndex !== false ? array_slice($segments, $apiIndex + 1) : $segments;

// Remove any stray 'index.php' segments that may appear when
// the server exposes the API through a front file (e.g. /api/index.php/v1/...)
$segments = array_values(array_filter($segments, function ($s) {
    return $s !== 'index.php' && $s !== '';
}));

$version   = $segments[0] ?? 'v1';
$resource  = $segments[1] ?? null;
$id        = $segments[2] ?? null;
$subaction = $segments[3] ?? null;
$method    = $_SERVER['REQUEST_METHOD'];

if ($version !== 'v1' || !$resource) {
    api_response(['success' => false, 'message' => 'Ressource API inconnue.'], 404);
}

$routeFile = __DIR__ . '/v1/' . $resource . '.php';

if (!file_exists($routeFile)) {
    api_response(['success' => false, 'message' => "Ressource '$resource' inexistante."], 404);
}

// Chaque fichier de ressource définit une fonction handle_<resource>()
// prenant en charge le routage interne selon la méthode HTTP.
require $routeFile;

$handlerFunction = 'handle_' . $resource;

if (!function_exists($handlerFunction)) {
    api_response(['success' => false, 'message' => 'Gestionnaire de ressource introuvable.'], 500);
}

$handlerFunction($method, $id, $subaction);
