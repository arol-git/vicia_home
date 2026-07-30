<?php

namespace Bot\Core;

use Bot\Config\Database;

/**
 * Class Model
 *
 * Classe de base des modèles du bot, tous adossés à `vicia_bot`
 * (jamais à `vicia_home` — voir Bot\Services\ViciaApiClient pour tout
 * ce qui concerne les données de la plateforme). Fournit des
 * opérations CRUD génériques, dans la continuité du même patron déjà
 * utilisé côté plateforme Vicia Home (App\Core\Model), pour une
 * cohérence de style entre les deux projets.
 */
abstract class Model
{
    protected static string $table = '';
    protected static string $primaryKey = 'id';

    protected static function db(): \PDO
    {
        return Database::connection();
    }

    public static function find(int $id): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM `' . static::$table . '` WHERE `' . static::$primaryKey . '` = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function where(string $column, $value): array
    {
        $stmt = self::db()->prepare('SELECT * FROM `' . static::$table . '` WHERE `' . $column . '` = :value');
        $stmt->execute(['value' => $value]);
        return $stmt->fetchAll();
    }

    public static function findOneBy(string $column, $value): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM `' . static::$table . '` WHERE `' . $column . '` = :value LIMIT 1');
        $stmt->execute(['value' => $value]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_map(fn($c) => ':' . $c, $columns);

        $sql = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (%s)',
            static::$table,
            implode('`, `', $columns),
            implode(', ', $placeholders)
        );

        $stmt = self::db()->prepare($sql);
        $stmt->execute($data);

        return (int) self::db()->lastInsertId();
    }

    public static function update(int $id, array $data): bool
    {
        $assignments = implode(', ', array_map(fn($c) => "`$c` = :$c", array_keys($data)));
        $data['__id'] = $id;

        $stmt = self::db()->prepare(sprintf(
            'UPDATE `%s` SET %s WHERE `%s` = :__id',
            static::$table,
            $assignments,
            static::$primaryKey
        ));
        $stmt->execute($data);

        return $stmt->rowCount() > 0;
    }

    public static function delete(int $id): bool
    {
        $stmt = self::db()->prepare('DELETE FROM `' . static::$table . '` WHERE `' . static::$primaryKey . '` = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public static function deleteWhere(string $column, $value): int
    {
        $stmt = self::db()->prepare('DELETE FROM `' . static::$table . '` WHERE `' . $column . '` = :value');
        $stmt->execute(['value' => $value]);
        return $stmt->rowCount();
    }
}
