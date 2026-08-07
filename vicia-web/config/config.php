<?php
/**
 * config/config.php
 *
 * Configuration générale de l'application Vicia Home.
 */

return [
    // Nom de l'application, affiché dans les vues et les e-mails
    'app_name'    => 'Vicia Home',

    // Environnement d'exécution : "production" ou "development"
    'env'         => getenv('APP_ENV') ?: 'development',

    // URL de base de l'application (sans slash final)
    'base_url'    => getenv('APP_URL') ?: 'https://viciahome-production.up.railway.app',

    // Fuseau horaire de référence
    'timezone'    => 'Africa/Douala',

    // Clé secrète utilisée pour la signature des jetons (API, CSRF)
    'app_key'     => getenv('APP_KEY') ?: 'vicia-home-secret-key-change-me-in-production',

    // Durée de vie de la session "Se souvenir de moi" (en secondes)
    'remember_me_ttl' => 60 * 60 * 24 * 30, // 30 jours

    // Durées de vie des jetons JWT de l'API REST (voir App\Core\Jwt)
    'jwt_access_ttl'  => 60 * 15,          // 15 minutes
    'jwt_refresh_ttl' => 60 * 60 * 24 * 30, // 30 jours

    // Répertoire de téléversement des fichiers (avatars, etc.)
    'upload_path' => __DIR__ . '/../public/uploads',
    'upload_url'  => '/uploads',

    // Paramètres MQTT (utilisés par /mqtt et par les vues d'état MQTT)
    'mqtt' => [
        'host'       => getenv('MQTT_HOST') ?: '127.0.0.1',
        'port'       => getenv('MQTT_PORT') ?: 8883,
        'tls'        => true,
        'client_id'  => 'vicia_home_web',
        'username'   => getenv('MQTT_USER') ?: 'vicia_web',
        'password'   => getenv('MQTT_PASS') ?: 'change_me',
        'base_topic' => 'home',
    ],

    // Journalisation applicative
    'log_path' => __DIR__ . '/../storage/logs/app.log',
];
