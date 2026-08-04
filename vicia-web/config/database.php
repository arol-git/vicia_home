<?php
/**
 * config/database.php
 *
 * Paramètres de connexion à la base de données MySQL.
 * En production, ces valeurs doivent être surchargées par des
 * variables d'environnement (voir .env.example).
 */

return [
    'driver'   => 'mysql',
    'host'     => getenv('DB_HOST') ?: '127.0.0.1',
    'port'     => getenv('DB_PORT') ?: '3306',
    'database' => getenv('DB_NAME') ?: 'vicia_home',
    'username' => getenv('DB_USER') ?: 'vicia_user',
    'password' => getenv('DB_PASS') ?: 'change_me',
    'charset'  => 'utf8mb4',
];
