<?php
/**
 * public/index.php
 *
 * Point d'entrée unique de l'application Web Vicia Home (patron
 * "Front Controller"). Toutes les requêtes HTTP sont réécrites vers
 * ce fichier par Apache (voir public/.htaccess), qui se charge
 * ensuite de démarrer la session, restaurer l'authentification
 * persistante puis déléguer la résolution de la route au Router.
 */

require __DIR__ . '/../app/core/bootstrap.php';

use App\Core\Auth;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;

// Lorsque l'application est servie via le serveur de développement
// intégré de PHP (php -S ...), les fichiers statiques (CSS, JS,
// images) doivent être renvoyés tels quels plutôt que d'être routés
// vers ce contrôleur frontal. Sous Apache, ce cas ne se présente
// jamais : c'est la règle RewriteCond !-f de public/.htaccess qui
// joue ce rôle en amont, avant même que PHP ne soit sollicité.
if (PHP_SAPI === 'cli-server') {
    $requestedFile = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if ($requestedFile !== __FILE__ && is_file($requestedFile)) {
        return false;
    }
}

Session::start();
Auth::restoreFromRememberCookie();

$router = new Router();

// ------------------------- Authentification -----------------------------
$router->get('/', 'AuthController@showLogin');
$router->get('/login', 'AuthController@showLogin');
$router->post('/login', 'AuthController@login');
$router->post('/logout', 'AuthController@logout');

// ------------------------- Sélection de maison ---------------------------
$router->get('/houses', 'HouseController@index');
$router->post('/houses/switch/{id}', 'HouseController@switchHouse');

// ------------------------- Module IA Vicia Home ---------------------------
$router->get('/ai', 'AIController@index');
$router->post('/ai/message', 'AIController@send');
$router->post('/ai/reset', 'AIController@reset');

$router->dispatch(new Request());
