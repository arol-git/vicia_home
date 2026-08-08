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
    'host'     => getenv('MYSQL_HOST') ?: '127.0.0.1',
    'port'     => getenv('MYSQL_PORT') ?: '3306',
    'database' => getenv('MYSQL_DATABASE') ?: 'vicia_home',
    'username' => getenv('MYSQL_USER') ?: 'vicia_user',
    'password' => getenv('MYSQL_PASSWORD') ?: 'change_me',
    'charset'  => 'utf8mb4',
];
