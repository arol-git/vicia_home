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
    'database' => getenv('DB_NAME') ?: 'vicia_home2',
    'username' => getenv('DB_USER') ?: 'root',
    'password' => getenv('DB_PASS') ?: '',
    'charset'  => 'utf8mb4',
];
