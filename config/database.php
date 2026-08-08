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
    'host'     => getenv('MYSQL_HOST') ?: 'https://viciahome-production.up.railway.app',
    'port'     => getenv('MYSQL_PORT') ?: '3306',
    'database' => getenv('MYSQL_DATABASE') ?: 'railway',
    'username' => getenv('MYSQL_USER') ?: 'root',
    'password' => getenv('MYSQL_PASSWORD') ?: (getenv('MYSQL_PASSWORDWORD') ?: ''),
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