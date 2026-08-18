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
    // Use APP_URL or SITE_URL as provided by the environment. Do not
    // force a `/public` suffix here because the hosting provider may
    // already serve the `public/` directory as the document root.
    'base_url'    => rtrim(getenv('APP_URL') ?: getenv('SITE_URL') ?: 'https://viciahome-production.up.railway.app', '/'),

    // Fuseau horaire de référence
    'timezone'    => 'Africa/Douala',

    // Clé secrète utilisée pour la signature des jetons (API, CSRF)
    'app_key'     => getenv('APP_KEY') ?: 'vicia-home-secret-key-change-me-in-production',

    // Durée de vie de la session "Se souvenir de moi" (en secondes)
    'remember_me_ttl' => 60 * 60 * 24 * 30, // 30 jours

    // Répertoire de téléversement des fichiers (avatars, etc.)
    'upload_path' => __DIR__ . '/../public/uploads',
    'upload_url'  => '/uploads',

     /* // Paramètres MQTT (utilisés par /mqtt et par les vues d'état MQTT)
    'mqtt' => [
        'host'       => getenv('MQTT_HOST') ?: '127.0.0.1',
        'port'       => getenv('MQTT_PORT') ?: 8883,
        'tls'        => true,
        'client_id'  => 'vicia_home_web',
        'username'   => getenv('MQTT_USER') ?: 'vicia_web',
        'password'   => getenv('MQTT_PASS') ?: 'change_me',  */

    // Paramètres MQTT (utilisés par le site, Wokwi/ESP32 et le subscriber)
/*    'mqtt' => [
        'host'       => 'broker.hivemq.com',
        'port'       => 1883,
        'tls'        => false,
        'client_id'  => 'vicia_home_web',
        'base_topic' => 'home',
    ],*/


    'mqtt' => [
        'host'       => getenv('MQTT_HOST') ?: 'f0f51473e19f4dc89f2813a0a491dcbb.s1.eu.hivemq.cloud',
        'port'       => (int) (getenv('MQTT_PORT') ?: 8883),
        'tls'        => filter_var(getenv('MQTT_TLS') ?: true, FILTER_VALIDATE_BOOLEAN),
        'tls_verify' => filter_var(getenv('MQTT_TLS_VERIFY') ?: true, FILTER_VALIDATE_BOOLEAN),
        'username'   => getenv('MQTT_USER') ?: 'viciaHome',
        'password'   => getenv('MQTT_PASS') ?: 'viciaSecure',
        'client_id'  => getenv('MQTT_CLIENT_ID') ?: 'vicia_home_web',
        'base_topic' => getenv('MQTT_BASE_TOPIC') ?: 'home',
    ],
/*
'mqtt' => [
    'host'       => getenv('MQTT_HOST'),
    'port'       => (int)(getenv('MQTT_PORT') ?: 8883),
    'tls'        => filter_var(getenv('MQTT_TLS'), FILTER_VALIDATE_BOOLEAN),
    'client_id'  => 'vicia_home_web',
    'username'   => getenv('MQTT_USER'),
    'password'   => getenv('MQTT_PASS'),
    'base_topic' => 'home',
],
*/
    // Paramètres du module Vicia Home AI.
    // Sans clé API, le module reste utilisable avec ses réponses de repli locales.
    'ai' => [
        'provider' => getenv('AI_LLM_PROVIDER') ?: 'openai',
        'api_key'  => getenv('AI_LLM_API_KEY') ?: '',
        'model'    => getenv('AI_LLM_MODEL') ?: 'gpt-4o-mini',
        'base_url' => getenv('AI_LLM_BASE_URL') ?: '',
    ],

    // Clé optionnelle pour les mesures ESP32 envoyées via POST /api/v1/telemetry.
    // Si elle est renseignée, l'ESP32 doit envoyer X-Telemetry-Key avec cette valeur.
    'telemetry_api_key' => getenv('TELEMETRY_API_KEY') ?: '',

    // Paramètres Web Push / notifications PWA.
    'web_push' => [
        'subject'    => getenv('VAPID_SUBJECT') ?: 'mailto:admin@vicia-home.local',
        'public_key' => getenv('VAPID_PUBLIC_KEY') ?: '',
        'private_key' => getenv('VAPID_PRIVATE_KEY') ?: '',
    ],

    // Journalisation applicative
    'log_path' => __DIR__ . '/../storage/logs/app.log',
];
