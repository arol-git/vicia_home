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

// === ULTRA VERBOSE LOGGING FOR DEBUGGING ===
$logfile = __DIR__ . '/../storage/logs/api-voice.log';
@file_put_contents($logfile, "\n=== REQUEST " . date('Y-m-d H:i:s') . " ===\n", FILE_APPEND);
@file_put_contents($logfile, "METHOD: {$_SERVER['REQUEST_METHOD']}\n", FILE_APPEND);
@file_put_contents($logfile, "REQUEST_URI: {$_SERVER['REQUEST_URI']}\n", FILE_APPEND);
@file_put_contents($logfile, "PHP_SELF: {$_SERVER['PHP_SELF']}\n", FILE_APPEND);
@file_put_contents($logfile, "SCRIPT_FILENAME: {$_SERVER['SCRIPT_FILENAME']}\n", FILE_APPEND);

require __DIR__ . '/../app/core/bootstrap.php';
require __DIR__ . '/v1/helpers.php';

\App\Core\Session::start();
\App\Core\Auth::restoreFromRememberCookie();

header('Content-Type: application/json; charset=utf-8');

$uri    = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$segments = explode('/', $uri);

@file_put_contents($logfile, "PARSED URI: $uri\n", FILE_APPEND);
@file_put_contents($logfile, "SEGMENTS (before filter): " . json_encode($segments) . "\n", FILE_APPEND);

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

@file_put_contents($logfile, "PARSED: version=$version, resource=$resource, id=$id, subaction=$subaction, method=$method\n", FILE_APPEND);

if ($version !== 'v1' || !$resource) {
    @file_put_contents($logfile, "ERROR: Invalid version or missing resource\n", FILE_APPEND);
    api_response(['success' => false, 'message' => 'Ressource API inconnue.'], 404);
}

$routeFile = __DIR__ . '/v1/' . $resource . '.php';
@file_put_contents($logfile, "ROUTE_FILE: $routeFile (exists: " . (file_exists($routeFile) ? 'YES' : 'NO') . ")\n", FILE_APPEND);

if (!file_exists($routeFile)) {
    @file_put_contents($logfile, "ERROR: Route file does not exist\n", FILE_APPEND);
    api_response(['success' => false, 'message' => "Ressource '$resource' inexistante."], 404);
}

// Chaque fichier de ressource définit une fonction handle_<resource>()
// prenant en charge le routage interne selon la méthode HTTP.
@file_put_contents($logfile, "Including route file...\n", FILE_APPEND);
require $routeFile;

$handlerFunction = 'handle_' . $resource;
@file_put_contents($logfile, "HANDLER_FUNCTION: $handlerFunction (exists: " . (function_exists($handlerFunction) ? 'YES' : 'NO') . ")\n", FILE_APPEND);

if (!function_exists($handlerFunction)) {
    @file_put_contents($logfile, "ERROR: Handler function does not exist\n", FILE_APPEND);
    api_response(['success' => false, 'message' => 'Gestionnaire de ressource introuvable.'], 500);
}

@file_put_contents($logfile, "Calling handler: $handlerFunction\n", FILE_APPEND);
$handlerFunction($method, $id, $subaction);
@file_put_contents($logfile, "Handler returned normally\n", FILE_APPEND);
