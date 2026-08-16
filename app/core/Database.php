<?php

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;

/**
 * Class Database
 *
 * Encapsule la connexion PDO à la base de données MySQL selon le
 * patron de conception Singleton, afin de garantir une connexion
 * unique et réutilisée pendant toute la durée de la requête HTTP.
 *
 * Toutes les requêtes passent par des instructions préparées afin
 * de prévenir les injections SQL.
 */
class Database
{
    /** @var Database|null Instance unique de la classe */
    private static ?Database $instance = null;

    /** @var PDO Connexion PDO active */
    private PDO $pdo;

    /**
     * Constructeur privé : établit la connexion PDO à partir de la
     * configuration définie dans config/database.php.
     */
    private function __construct()
    {
        $config = require __DIR__ . '/../../config/database.php';

        $dsn = sprintf(
            '%s:host=%s;port=%s;dbname=%s;charset=%s',
            $config['driver'],
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset']
        );

        try {
            $this->pdo = new PDO($dsn, $config['username'], $config['password'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // On ne renvoie jamais le message d'erreur brut au client :
            // il pourrait révéler des informations sensibles (identifiants, hôte...).
            error_log('[Database] Connexion échouée : ' . $e->getMessage());
            http_response_code(500);
            die('Erreur interne : la connexion à la base de données a échoué.');
        }
    }

    /**
     * Retourne l'instance unique de connexion PDO.
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance->pdo;
    }

    /**
     * Alias pour getInstance() pour compatibilité avec le code existant.
     */
    public static function getConnection(): PDO
    {
        return self::getInstance();
    }

    /**
     * Exécute une requête préparée et retourne le PDOStatement associé.
     *
     * @param string $sql    Requête SQL avec marqueurs nommés ou positionnels
     * @param array  $params Paramètres à lier à la requête
     */
    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);

        return $stmt;
    }

    /**
     * Retourne l'identifiant auto-incrémenté de la dernière insertion.
     */
    public static function lastInsertId(): string
    {
        return self::getInstance()->lastInsertId();
    }

    // Empêche le clonage et la désérialisation de l'instance singleton.
    private function __clone() {}
    public function __wakeup()
    {
        throw new \Exception('La désérialisation de Database est interdite.');
    }
}
