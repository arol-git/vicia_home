<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

/**
 * Class AIHistory
 *
 * Journal d'audit des actions effectivement exécutées par
 * l'assistant (`ai_logs`) — distinct de l'historique conversationnel
 * (Conversation) : ne contient que les actions ayant un effet réel
 * (bascule d'équipement, changement de mode...), jamais le simple
 * bavardage.
 */
class AIHistory extends Model
{
    protected static string $table = 'ai_logs';

    public static function record(?int $userId, ?int $houseId, string $action, string $status, ?string $detail = null): int
    {
        return self::create([
            'user_id'  => $userId,
            'house_id' => $houseId,
            'action'   => $action,
            'status'   => $status,
            'detail'   => $detail,
        ]);
    }

    public static function recentForHouse(int $houseId, int $limit = 20): array
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT * FROM ai_logs WHERE house_id = :house_id ORDER BY created_at DESC LIMIT :limit'
        );
        $stmt->bindValue(':house_id', $houseId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
