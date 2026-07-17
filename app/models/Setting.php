<?php

namespace App\Models;

use App\Core\Database;

/**
 * Paramètres simples stockés sous forme clé/valeur.
 */
class Setting
{
    public static function get(string $key, ?string $default = null): ?string
    {
        $row = Database::query(
            'SELECT setting_value FROM settings WHERE setting_key = :key LIMIT 1',
            ['key' => $key]
        )->fetch();

        return $row['setting_value'] ?? $default;
    }

    public static function set(string $key, string $value): void
    {
        Database::query(
            'INSERT INTO settings (setting_key, setting_value) VALUES (:key, :value)
             ON DUPLICATE KEY UPDATE setting_value = :value',
            ['key' => $key, 'value' => $value]
        );
    }
}
