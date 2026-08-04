<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

/**
 * Class Conversation
 *
 * Modèle représentant un fil de discussion avec l'assistant Vicia
 * Home AI (`ai_conversations`) et ses messages (`ai_messages`). Une
 * conversation appartient à un utilisateur et, le cas échéant, à une
 * maison (celle active au moment où la conversation a commencé).
 */
class Conversation extends Model
{
    protected static string $table = 'ai_conversations';

    /**
     * Retourne (ou crée) la conversation active de l'utilisateur pour
     * la maison donnée. Une seule conversation "active" à la fois par
     * couple utilisateur/maison : la reprendre évite de perdre le
     * contexte entre deux échanges successifs.
     */
    public static function currentFor(int $userId, ?int $houseId): array
    {
        $sql = "SELECT * FROM ai_conversations
                WHERE user_id = :user_id AND status = 'active'
                AND " . ($houseId !== null ? "house_id = :house_id" : "house_id IS NULL") . "
                ORDER BY updated_at DESC LIMIT 1";

        $params = ['user_id' => $userId];
        if ($houseId !== null) {
            $params['house_id'] = $houseId;
        }

        $existing = Database::query($sql, $params)->fetch();
        if ($existing) {
            return $existing;
        }

        $id = self::create(['user_id' => $userId, 'house_id' => $houseId]);
        return self::find($id);
    }

    /**
     * Ajoute un message à la conversation et met à jour son horodatage
     * (utilisé pour retrouver la conversation la plus récemment
     * active, voir currentFor()).
     */
    public static function appendMessage(int $conversationId, string $role, string $content, ?string $intent = null, ?array $metadata = null): int
    {
        $id = Database::query(
            'INSERT INTO ai_messages (conversation_id, role, content, intent, metadata) VALUES (:cid, :role, :content, :intent, :metadata)',
            [
                'cid'      => $conversationId,
                'role'     => $role,
                'content'  => $content,
                'intent'   => $intent,
                'metadata' => $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null,
            ]
        ) ? (int) Database::lastInsertId() : 0;

        Database::query('UPDATE ai_conversations SET updated_at = NOW() WHERE id = :id', ['id' => $conversationId]);

        return $id;
    }

    /**
     * Retourne les N derniers messages d'une conversation, dans
     * l'ordre chronologique (le plus ancien en premier) — c'est le
     * format attendu par App\Services\LLMService pour construire le
     * contexte envoyé au modèle de langage.
     */
    public static function recentMessages(int $conversationId, int $limit = 20): array
    {
        $sql = "SELECT * FROM (
                    SELECT * FROM ai_messages WHERE conversation_id = :cid ORDER BY created_at DESC LIMIT :limit
                ) recent ORDER BY created_at ASC";
        $stmt = Database::getInstance()->prepare($sql);
        $stmt->bindValue(':cid', $conversationId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Enregistre une action sensible en attente de confirmation
     * (voir App\Services\ConversationMemory) — ou l'efface si $action
     * est null, une fois confirmée ou annulée.
     */
    public static function setPendingAction(int $conversationId, ?array $action): void
    {
        Database::query(
            'UPDATE ai_conversations SET pending_action = :action WHERE id = :id',
            ['action' => $action ? json_encode($action, JSON_UNESCAPED_UNICODE) : null, 'id' => $conversationId]
        );
    }

    public static function getPendingAction(int $conversationId): ?array
    {
        $row = self::find($conversationId);
        return $row && $row['pending_action'] ? json_decode($row['pending_action'], true) : null;
    }
}
