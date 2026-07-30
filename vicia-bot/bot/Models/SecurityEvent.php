<?php

namespace Bot\Models;

use Bot\Core\Model;

/**
 * Class SecurityEvent
 *
 * Journal d'audit structuré des incidents de sécurité, en complément
 * du canal "security" de Bot\Core\Logger (fichier). Le fichier reste
 * la source de vérité opérationnelle (grep, tail -f) ; cette table
 * permet des requêtes structurées (ex. nombre de refus par
 * utilisateur sur les dernières 24 heures) pour un futur tableau de
 * bord d'administration.
 */
class SecurityEvent extends Model
{
    protected static string $table = 'security_events';

    public static function record(string $eventType, ?int $telegramId, ?string $description = null, ?string $ip = null): void
    {
        self::create([
            'telegram_id' => $telegramId,
            'event_type'  => $eventType,
            'description' => $description,
            'ip_address'  => $ip,
        ]);
    }
}
