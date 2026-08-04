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
$router->get('/forgot-password', 'AuthController@showForgotPassword');
$router->post('/forgot-password', 'AuthController@sendResetLink');
$router->get('/reset-password', 'AuthController@showResetPassword');
$router->post('/reset-password', 'AuthController@resetPassword');

// ------------------------- Tableau de bord --------------------------------
$router->get('/dashboard', 'DashboardController@index');
$router->post('/dashboard/mode', 'DashboardController@setMode');

// ------------------------- Maisons -----------------------------------------
$router->get('/houses', 'HouseController@index');
$router->post('/houses', 'HouseController@store');
$router->put('/houses/{id}', 'HouseController@update');
$router->delete('/houses/{id}', 'HouseController@destroy');
$router->post('/houses/switch/{id}', 'HouseController@switchHouse');
$router->get('/houses/{id}/members', 'HouseController@members');
$router->post('/houses/{id}/members', 'HouseController@addMember');
$router->delete('/houses/{id}/members/{userId}', 'HouseController@removeMember');

// ------------------------- Pièces -----------------------------------------
$router->get('/rooms', 'RoomController@index');
$router->post('/rooms', 'RoomController@store');
$router->put('/rooms/{id}', 'RoomController@update');
$router->delete('/rooms/{id}', 'RoomController@destroy');

// ------------------------- Équipements -------------------------------------
$router->get('/equipments', 'EquipmentController@index');
$router->post('/equipments', 'EquipmentController@store');
$router->put('/equipments/{id}', 'EquipmentController@update');
$router->delete('/equipments/{id}', 'EquipmentController@destroy');
$router->post('/equipments/{id}/toggle', 'EquipmentController@toggle');

// ------------------------- Capteurs -----------------------------------------
$router->get('/sensors', 'SensorController@index');
$router->post('/sensors', 'SensorController@store');
$router->put('/sensors/{id}', 'SensorController@update');
$router->delete('/sensors/{id}', 'SensorController@destroy');
$router->get('/sensors/{id}/history', 'SensorController@history');

// ------------------------- Caméras -------------------------------------------
$router->get('/cameras', 'CameraController@index');

// ------------------------- Réseau / cybersécurité ----------------------------
$router->get('/security', 'SecurityController@index');
$router->post('/security/devices/{id}/whitelist', 'SecurityController@whitelist');
$router->post('/security/devices/{id}/blacklist', 'SecurityController@blacklist');
$router->post('/security/simulate-scan', 'SecurityController@simulateScan');

// ------------------------- Consommation ---------------------------------------
$router->get('/consumption', 'ConsumptionController@index');

// ------------------------- Automatisation -------------------------------------
$router->get('/automation', 'AutomationController@index');
$router->post('/automation', 'AutomationController@store');
$router->post('/automation/{id}/toggle', 'AutomationController@toggle');
$router->delete('/automation/{id}', 'AutomationController@destroy');

// ------------------------- Historique ------------------------------------------
$router->get('/history', 'HistoryController@index');

// ------------------------- Alertes / notifications -------------------------------
$router->get('/alerts', 'AlertController@index');
$router->get('/alerts/test-email-direct', 'AlertController@testEmailDirect');
$router->post('/alerts/test-email', 'AlertController@testEmail');
$router->post('/alerts/{id}/read', 'AlertController@markAsRead');
$router->post('/alerts/read-all', 'AlertController@markAllAsRead');
$router->get('/alerts/unread-count', 'AlertController@unreadCount');

// ------------------------- Utilisateurs ------------------------------------------
$router->get('/users', 'UserController@index');
$router->post('/users', 'UserController@store');
$router->put('/users/{id}', 'UserController@update');
$router->delete('/users/{id}', 'UserController@destroy');

// ------------------------- Paramètres ---------------------------------------------
$router->get('/settings', 'SettingsController@index');
$router->post('/settings', 'SettingsController@update');

// ------------------------- Profil ---------------------------------------------------
$router->get('/profile', 'ProfileController@show');
$router->post('/profile', 'ProfileController@update');
$router->post('/profile/notifications', 'ProfileController@updateNotifications');
$router->post('/profile/password', 'ProfileController@updatePassword');

// ------------------------- Vicia Home AI ---------------------------------------------
$router->get('/ai', 'AIController@index');
$router->post('/ai/message', 'AIController@send');
$router->post('/ai/debug', 'AIController@debug');
$router->post('/ai/reset', 'AIController@reset');

$router->dispatch(new Request());
