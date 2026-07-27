<?php

namespace Bot\Config;

use PDO;
use PDOException;

/**
 * Class Database
 *
 * Connexion PDO à la base **propre au bot** (`vicia_bot`), distincte
 * de `vicia_home`. Le bot n'obtient et ne modifie jamais de données
 * de la plateforme autrement qu'en passant par son API REST
 * (voir Bot\Services\ViciaApiClient) — cette classe ne sert donc
 * qu'à la persistance des données internes au bot : liaison de
 * comptes, sessions de conversation, listes d'accès, journalisation.
 */
class Database
{
    private static ?PDO $instance = null;

    public static function connection(): PDO
    {
        if (self::$instance === null) {
            self::$instance = self::connect();
        }

        return self::$instance;
    }

    private static function connect(): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            App::env('DB_HOST', '127.0.0.1'),
            App::env('DB_PORT', '3306'),
            App::env('DB_NAME')
        );

        try {
            return new PDO($dsn, App::env('DB_USER'), App::env('DB_PASS'), [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // Ne jamais renvoyer le message d'erreur brut à Telegram :
            // il peut contenir hôte, utilisateur ou fragments de DSN.
            error_log('[vicia-bot] Connexion à vicia_bot échouée : ' . $e->getMessage());
            throw new PDOException('Connexion à la base de données du bot impossible.', (int) $e->getCode());
        }
    }

    private function __construct() {}
    private function __clone() {}
}
