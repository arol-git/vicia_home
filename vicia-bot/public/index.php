<?php
/**
 * public/index.php
 *
 * Point d'entrée unique du webhook Telegram. Reçoit chaque Update
 * envoyé par Telegram, vérifie l'origine de la requête, construit le
 * contexte applicatif (Request, client API Telegram) puis délègue
 * l'ensemble du traitement au Router.
 *
 * Ce fichier reste volontairement mince : toute logique métier vit
 * dans bot/Controllers, toute déclaration de route dans
 * routes/web.php. Configurer l'URL de ce fichier comme webhook auprès
 * de Telegram (voir docs/README.md, section Déploiement) avec le
 * paramètre secret_token positionné à la valeur de
 * TELEGRAM_WEBHOOK_SECRET.
 */

require __DIR__ . '/vendor/autoload.php';

use Bot\Config\App;
use Bot\Core\ErrorHandler;
use Bot\Core\Logger;
use Bot\Core\Request;
use Bot\Core\Router;
use Telegram\Bot\Api;

App::boot();
ErrorHandler::registerGlobal();

// Vérification de l'origine de la requête : Telegram joint l'en-tête
// X-Telegram-Bot-Api-Secret-Token, dont la valeur doit correspondre
// exactement à celle fournie lors de l'enregistrement du webhook
// (setWebhook, paramètre secret_token). Toute requête sans ce jeton,
// ou avec un jeton incorrect, est rejetée avant même d'être analysée
// — elle ne provient pas de Telegram.
$providedSecret = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
if (!hash_equals(App::env('TELEGRAM_WEBHOOK_SECRET', ''), $providedSecret)) {
    Logger::channel('security')->warning('Requête webhook rejetée : jeton secret absent ou incorrect', [
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'inconnue',
    ]);
    http_response_code(403);
    exit;
}

$telegram = new Api(App::env('TELEGRAM_BOT_TOKEN'));

try {
    $update = $telegram->getWebhookUpdate();
} catch (\Throwable $e) {
    // Corps de requête absent ou JSON malformé : ne peut pas provenir
    // d'un usage normal de Telegram. Journalisé et ignoré.
    Logger::channel('security')->warning('Update webhook illisible : ' . $e->getMessage());
    http_response_code(200);
    exit;
}

$request = new Request($update);
$router  = new Router();

// Enregistrement des intergiciels de sécurité et des routes. Le
// fichier routes/web.php se remplit au fil des modules livrés — il
// est actuellement volontairement minimal (voir Module 2).
require __DIR__ . '/../routes/web.php';

try {
    $router->dispatch($request, $telegram);
} catch (\Throwable $e) {
    ErrorHandler::handle($e, $request, $telegram);
}

http_response_code(200);
