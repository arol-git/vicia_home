<?php

namespace App\Models;

use App\Core\Database;

class PushSubscription
{
    public static function forHouse(int $houseId): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT * FROM push_subscriptions WHERE house_id = :house_id AND is_active = 1 ORDER BY created_at DESC');
        $stmt->execute([':house_id' => $houseId]);
        return $stmt->fetchAll();
    }

    public static function forUser(int $userId): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT * FROM push_subscriptions WHERE user_id = :user_id AND is_active = 1 ORDER BY created_at DESC');
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public static function upsert(int $userId, ?int $houseId, string $endpoint, string $p256dh, string $auth, string $userAgent = ''): array
    {
        $db = Database::getConnection();

        $stmt = $db->prepare('SELECT id FROM push_subscriptions WHERE endpoint = :endpoint LIMIT 1');
        $stmt->execute([':endpoint' => $endpoint]);
        $existing = $stmt->fetch();

        if ($existing) {
            $stmt = $db->prepare('UPDATE push_subscriptions SET user_id = :user_id, house_id = :house_id, p256dh = :p256dh, auth = :auth, user_agent = :user_agent, is_active = 1, updated_at = NOW() WHERE id = :id');
            $stmt->execute([
                ':user_id' => $userId,
                ':house_id' => $houseId,
                ':p256dh' => $p256dh,
                ':auth' => $auth,
                ':user_agent' => $userAgent,
                ':id' => $existing['id'],
            ]);
            return self::findById((int) $existing['id']);
        }

        $stmt = $db->prepare('INSERT INTO push_subscriptions (user_id, house_id, endpoint, p256dh, auth, user_agent, is_active, created_at, updated_at) VALUES (:user_id, :house_id, :endpoint, :p256dh, :auth, :user_agent, 1, NOW(), NOW())');
        $stmt->execute([
            ':user_id' => $userId,
            ':house_id' => $houseId,
            ':endpoint' => $endpoint,
            ':p256dh' => $p256dh,
            ':auth' => $auth,
            ':user_agent' => $userAgent,
        ]);

        return self::findById((int) $db->lastInsertId());
    }

    public static function findById(int $id): ?array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT * FROM push_subscriptions WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function deleteByEndpoint(int $userId, string $endpoint): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('DELETE FROM push_subscriptions WHERE user_id = :user_id AND endpoint = :endpoint');
        return $stmt->execute([
            ':user_id' => $userId,
            ':endpoint' => $endpoint,
        ]);
    }

    public static function deactivate(int $id): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('UPDATE push_subscriptions SET is_active = 0, updated_at = NOW() WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }
}
