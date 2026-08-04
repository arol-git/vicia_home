<?php

namespace App\Services;

use App\Core\Database;
use App\Models\Conversation;

/**
 * Class ConversationMemory
 *
 * Deux rôles distincts :
 *   1. Mémoire longue par utilisateur (`ai_memory`) : nom préféré,
 *      langue, préférences énoncées en conversation — consultée avant
 *      de reformuler une question déjà répondue par le passé.
 *   2. Action en attente de confirmation (`ai_conversations.pending_action`,
 *      via App\Models\Conversation) — le point de passage obligé de
 *      toute commande sensible (voir App\Services\ActionExecutor::isSensitive()).
 */
class ConversationMemory
{
    public static function remember(int $userId, string $key, string $value): void
    {
        Database::query(
            'INSERT INTO ai_memory (user_id, memory_key, memory_value) VALUES (:user_id, :key, :value)
             ON DUPLICATE KEY UPDATE memory_value = :value_update, updated_at = NOW()',
            ['user_id' => $userId, 'key' => $key, 'value' => $value, 'value_update' => $value]
        );
    }

    public static function recall(int $userId, string $key): ?string
    {
        $row = Database::query(
            'SELECT memory_value FROM ai_memory WHERE user_id = :user_id AND memory_key = :key LIMIT 1',
            ['user_id' => $userId, 'key' => $key]
        )->fetch();

        return $row['memory_value'] ?? null;
    }

    /**
     * Retourne toute la mémoire connue d'un utilisateur, sous une
     * forme directement injectable dans le contexte envoyé au LLM
     * (voir App\Services\LLMService).
     */
    public static function all(int $userId): array
    {
        $rows = Database::query('SELECT memory_key, memory_value FROM ai_memory WHERE user_id = :user_id', ['user_id' => $userId])->fetchAll();
        return array_column($rows, 'memory_value', 'memory_key');
    }

    /**
     * Enregistre une action sensible en attente de confirmation
     * explicite de l'utilisateur (voir cahier des charges §Sécurité :
     * désarmement, déverrouillage, suppression, reset, OTA).
     */
    public static function setPendingAction(int $conversationId, array $intent, string $confirmationQuestion): void
    {
        Conversation::setPendingAction($conversationId, [
            'intent'   => $intent,
            'question' => $confirmationQuestion,
            'set_at'   => date('Y-m-d H:i:s'),
        ]);
    }

    public static function getPendingAction(int $conversationId): ?array
    {
        return Conversation::getPendingAction($conversationId);
    }

    public static function clearPendingAction(int $conversationId): void
    {
        Conversation::setPendingAction($conversationId, null);
    }
}
