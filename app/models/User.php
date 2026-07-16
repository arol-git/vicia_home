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
        return self::create([
            'name'          => $name,
            'email'         => $email,
            'password_hash' => password_hash($password, PASSWORD_BCRYPT),
            'role'          => $role,
        ]);
    }

    public static function updatePassword(int $id, string $newPassword): bool
    {
        return self::update($id, [
            'password_hash' => password_hash($newPassword, PASSWORD_BCRYPT),
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
