<?php

namespace App\Models;

use App\Core\Database;

/**
 * Paramètres simples stockés sous forme clé/valeur.
 */
class Setting
{
    public static function all(): array
    {
        self::ensureTable();

        $rows = Database::query('SELECT setting_key, setting_value FROM settings')->fetchAll();
        return array_column($rows, 'setting_value', 'setting_key');
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        self::ensureTable();

        $row = Database::query(
            'SELECT setting_value FROM settings WHERE setting_key = :key LIMIT 1',
            ['key' => $key]
        )->fetch();

        return $row['setting_value'] ?? $default;
    }

    public static function set(string $key, string $value): void
    {
        self::ensureTable();

        Database::query(
            'INSERT INTO settings (setting_key, setting_value) VALUES (:key, :value)
             ON DUPLICATE KEY UPDATE setting_value = :value',
            ['key' => $key, 'value' => $value]
        );
    }

    private static function ensureTable(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }

        Database::query(
            'CREATE TABLE IF NOT EXISTS settings (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(100) NOT NULL,
                setting_value TEXT NULL,
                UNIQUE KEY uq_settings_key (setting_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $index = Database::query(
            "SELECT COUNT(*) AS count
             FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'settings'
               AND INDEX_NAME = 'uq_settings_key'"
        )->fetch();

        if ((int) ($index['count'] ?? 0) === 0) {
            Database::query(
                'DELETE s1 FROM settings s1
                 INNER JOIN settings s2
                    ON s1.setting_key = s2.setting_key
                   AND s1.id < s2.id'
            );
            Database::query('ALTER TABLE settings ADD UNIQUE KEY uq_settings_key (setting_key)');
        }

        $checked = true;
    }
}
