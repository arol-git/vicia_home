<?php

namespace App\Core;

/**
 * Class Model
 *
 * Classe de base abstraite pour tous les modèles de l'application.
 * Fournit des opérations CRUD génériques s'appuyant sur la classe
 * Database. Chaque modèle concret définit son nom de table et sa
 * clé primaire.
 */
abstract class Model
{
    /** @var string Nom de la table associée au modèle */
    protected static string $table = '';

    /** @var string Nom de la clé primaire */
    protected static string $primaryKey = 'id';

    /**
     * Retourne tous les enregistrements de la table, triés par défaut
     * par identifiant décroissant.
     */
    public static function all(string $orderBy = null): array
    {
        $order = $orderBy ?: static::$primaryKey . ' DESC';
        $stmt  = Database::query('SELECT * FROM `' . static::$table . '` ORDER BY ' . $order);

        return $stmt->fetchAll();
    }

    /**
     * Recherche un enregistrement par sa clé primaire.
     */
    public static function find(int $id): ?array
    {
        $stmt = Database::query(
            'SELECT * FROM `' . static::$table . '` WHERE `' . static::$primaryKey . '` = :id LIMIT 1',
            ['id' => $id]
        );
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * Recherche des enregistrements selon une condition simple
     * colonne = valeur.
     */
    public static function where(string $column, $value, string $orderBy = null): array
    {
        $order = $orderBy ? ' ORDER BY ' . $orderBy : '';
        $stmt  = Database::query(
            'SELECT * FROM `' . static::$table . '` WHERE `' . $column . '` = :value' . $order,
            ['value' => $value]
        );

        return $stmt->fetchAll();
    }

    /**
     * Insère un nouvel enregistrement et retourne son identifiant.
     *
     * @param array $data Tableau associatif colonne => valeur
     */
    public static function create(array $data): int
    {
        $columns      = array_keys($data);
        $placeholders = array_map(fn($c) => ':' . $c, $columns);

        $sql = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (%s)',
            static::$table,
            implode('`, `', $columns),
            implode(', ', $placeholders)
        );

        Database::query($sql, $data);

        return (int) Database::lastInsertId();
    }

    /**
     * Met à jour un enregistrement identifié par sa clé primaire.
     */
    public static function update(int $id, array $data): bool
    {
        $assignments = implode(', ', array_map(fn($c) => "`$c` = :$c", array_keys($data)));
        $data['__id'] = $id;

        $sql = sprintf(
            'UPDATE `%s` SET %s WHERE `%s` = :__id',
            static::$table,
            $assignments,
            static::$primaryKey
        );

        $stmt = Database::query($sql, $data);

        return $stmt->rowCount() > 0;
    }

    /**
     * Supprime un enregistrement par sa clé primaire.
     */
    public static function delete(int $id): bool
    {
        $stmt = Database::query(
            'DELETE FROM `' . static::$table . '` WHERE `' . static::$primaryKey . '` = :id',
            ['id' => $id]
        );

        return $stmt->rowCount() > 0;
    }

    /**
     * Compte le nombre total d'enregistrements de la table.
     */
    public static function count(): int
    {
        $stmt = Database::query('SELECT COUNT(*) AS total FROM `' . static::$table . '`');

        return (int) $stmt->fetch()['total'];
    }
}
