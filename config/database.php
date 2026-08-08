<?php
/**
 * config/database.php
 *
 * Paramètres de connexion à la base de données MySQL.
 * En production, ces valeurs doivent être surchargées par des
 * variables d'environnement (voir .env.example).
 */
/*
return [
    'driver'   => 'mysql',
    'host'     => getenv('DB_HOST') ?: 'https://viciahome-production.up.railway.app',
    'port'     => getenv('DB_PORT') ?: '3306',
    'database' => getenv('DB_NAME') ?: 'vicia_home2',
    'username' => getenv('DB_USER') ?: 'root',
    'password' => getenv('DB_PASS') ?: (getenv('DB_PASSWORD') ?: ''),
    'charset'  => 'utf8mb4',
];
*/
return [
    'driver'   => 'mysql',
    'host'     => getenv('MYSQL_HOST') ?: 'mysql.railway.internal',
    'port'     => getenv('MYSQL_PORT') ?: '3306',
    'database' => getenv('MYSQL_DATABASE') ?: 'railway',
    'username' => getenv('MYSQL_USER') ?: 'root',
    'password' => getenv('MYSQL_PASSWORD') ?: 'PGBFnQFGojJuptRsIZOLDKTiYfIbRjOI',
    'charset'  => 'utf8mb4',
];