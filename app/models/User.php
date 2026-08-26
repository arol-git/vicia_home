<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

/**
 * Class User
 *
 * Modèle représentant un compte utilisateur de la plateforme
 * (administrateur, technicien ou résident).
 */
class User extends Model
{
    protected static string $table = 'users';

    public static function findByEmail(string $email): ?array
    {
        $stmt = Database::query('SELECT * FROM users WHERE email = :email LIMIT 1', ['email' => $email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Crée un nouvel utilisateur en hachant son mot de passe avec BCrypt.
     */
    public static function register(string $name, string $email, string $password, string $role = 'user'): int
    {
        self::ensurePlatformRoleColumn();

        return self::create([
            'name'          => $name,
            'email'         => $email,
            'password_hash' => password_hash($password, PASSWORD_BCRYPT),
            'role'          => $role,
        ]);
    }

    public static function update(int $id, array $data): bool
    {
        self::ensurePlatformRoleColumn();

        return parent::update($id, $data);
    }

    public static function updatePassword(int $id, string $newPassword): bool
    {
        return self::update($id, [
            'password_hash' => password_hash($newPassword, PASSWORD_BCRYPT),
        ]);
    }

    public static function notificationSettings(int $id): array
    {
        self::ensureNotificationColumns();
        $user = self::find($id) ?: [];
        $telegramValue = trim((string) ((string) ($user['telegram_name'] ?? '') ?: (string) ($user['telegram_chat_id'] ?? '') ?: (string) Setting::get('user_' . $id . '_telegram_name', '') ?: (string) Setting::get('user_' . $id . '_telegram_chat_id', '')));

        return [
            'notification_email' => trim((string) (($user['notification_email'] ?? '') ?: Setting::get('user_' . $id . '_notification_email', $user['email'] ?? ''))),
            'telegram_name' => $telegramValue,
            'notify_email' => Setting::get('user_' . $id . '_notify_email', '1') === '1',
            'notify_telegram' => Setting::get('user_' . $id . '_notify_telegram', '1') === '1',
            'notify_push' => Setting::get('user_' . $id . '_notify_push', '1') === '1',
        ];
    }

    public static function updateNotificationSettings(int $id, array $data): bool
    {
        self::ensureNotificationColumns();

        return self::update($id, [
            'notification_email' => $data['notification_email'],
            'telegram_name' => $data['telegram_name'] ?? '',
            'telegram_chat_id' => $data['telegram_name'] ?? '',
        ]);
    }

    public static function touchLastLogin(int $id): void
    {
        Database::query('UPDATE users SET last_login_at = NOW() WHERE id = :id', ['id' => $id]);
    }

    public static function setRememberToken(int $id, ?string $hashedToken): void
    {
        Database::query('UPDATE users SET remember_token = :token WHERE id = :id', [
            'token' => $hashedToken,
            'id'    => $id,
        ]);
    }

    private static function ensureNotificationColumns(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }

        $columns = Database::query('SHOW COLUMNS FROM users')->fetchAll();
        $names = array_column($columns, 'Field');
        $missing = array_diff(['notification_email', 'telegram_name', 'telegram_chat_id'], $names);

        foreach ($missing as $column) {
            $definition = match ($column) {
                'notification_email' => 'VARCHAR(150) NULL',
                'telegram_chat_id' => 'VARCHAR(100) NULL',
                default => 'VARCHAR(100) NULL',
            };
            Database::query("ALTER TABLE users ADD COLUMN `$column` $definition");
        }

        $checked = true;
    }

    private static function ensurePlatformRoleColumn(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }

        $row = Database::query("SHOW COLUMNS FROM users LIKE 'role'")->fetch();
        if ($row && !str_contains((string) $row['Type'], 'technicien')) {
            Database::query("ALTER TABLE users MODIFY role ENUM('admin','user','technicien') NOT NULL DEFAULT 'user' COMMENT 'rôle de plateforme (admin = support Vicia Home)'");
        }

        $checked = true;
    }

    /**
     * Retourne les utilisateurs avec pagination simple, du plus récent
     * au plus ancien.
     */
    public static function paginated(int $limit = 20, int $offset = 0): array
    {
        // bindValue nécessaire ici pour typer explicitement LIMIT/OFFSET en entier
        $stmt = Database::getInstance()->prepare(
            'SELECT id, name, email, role, status, last_login_at, created_at FROM users ORDER BY created_at DESC LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
