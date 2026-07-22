<?php
/**
 * app/core/bootstrap.php
 *
 * Point d'amorçage commun à tous les points d'entrée de
 * l'application : interface Web (public/index.php), API REST
 * (api/index.php) et démons CLI (mqtt/subscriber.php).
 *
 * Met en place l'autochargement des classes (PSR-4 simplifié, sans
 * dépendance à Composer), le fuseau horaire et les fonctions
 * utilitaires globales.
 */

define('ROOT_PATH', dirname(__DIR__, 2));

// Charge les dépendances Composer (PHPMailer, client MQTT Composer, etc.).
$composerAutoload = ROOT_PATH . '/vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

// --- Autochargement des classes applicatives ---------------------------
// Convention : le préfixe "App\" pointe vers /app, le préfixe "Mqtt\"
// pointe vers /mqtt. Chaque séparateur d'espace de noms correspond à
// un séparateur de répertoire, à l'image du standard PSR-4.
spl_autoload_register(function (string $class) {
    $map = [
        'App\\'  => ROOT_PATH . '/app/',
        'Mqtt\\' => ROOT_PATH . '/mqtt/',
    ];

    foreach ($map as $prefix => $baseDir) {
        if (str_starts_with($class, $prefix)) {
            $relative = substr($class, strlen($prefix));
            // Les dossiers "controllers" et "models" sont en minuscules
            // dans l'arborescence alors que les classes sont en
            // PascalCase ; on normalise ici la correspondance.
            $segments = explode('\\', $relative);
            $className = array_pop($segments);
            $subPath = '';
            foreach ($segments as $segment) {
                $subPath .= strtolower($segment) . '/';
            }
            $file = $baseDir . $subPath . $className . '.php';
            if (file_exists($file)) {
                require $file;
                return;
            }
        }
    }
});

// --- Configuration générale ---------------------------------------------
require_once __DIR__ . '/../helpers/functions.php';

date_default_timezone_set(config('timezone'));

if (config('env') !== 'production') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}
