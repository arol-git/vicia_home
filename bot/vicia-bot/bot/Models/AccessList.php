<?php

namespace Bot\Models;

use Bot\Core\Model;

/**
 * Class AccessList
 *
 * Liste blanche/noire des utilisateurs Telegram (voir
 * Bot\Middlewares\WhitelistMiddleware). Fonctionnement retenu :
 *
 *   - un `telegram_id` en liste noire est TOUJOURS refusé ;
 *   - si la table contient au moins une entrée de liste blanche, le
 *     bot bascule en mode "accès restreint" : seuls les identifiants
 *     explicitement whitelistés peuvent l'utiliser ;
 *   - si aucune entrée de liste blanche n'existe, le bot reste en
 *     accès ouvert (n'importe qui peut tenter de lier son compte),
 *     seule la liste noire s'applique.
 *
 * Ce mode "restreint seulement si peuplé" évite d'avoir à
 * pré-enregistrer manuellement chaque utilisateur légitime dès la
 * mise en service, tout en permettant de verrouiller le bot à un
 * cercle fermé dès qu'un administrateur y ajoute la première entrée.
 */
class AccessList extends Model
{
    protected static string $table = 'access_list';

    public static function isBlacklisted(int $telegramId): bool
    {
        $row = self::findOneBy('telegram_id', $telegramId);
        return $row !== null && $row['type'] === 'blacklist';
    }

    public static function isWhitelisted(int $telegramId): bool
    {
        $row = self::findOneBy('telegram_id', $telegramId);
        return $row !== null && $row['type'] === 'whitelist';
    }

    public static function whitelistModeEnabled(): bool
    {
        $stmt = self::db()->prepare("SELECT COUNT(*) AS c FROM access_list WHERE type = 'whitelist'");
        $stmt->execute();
        return (int) $stmt->fetch()['c'] > 0;
    }

    public static function addToWhitelist(int $telegramId, ?int $createdBy = null, ?string $reason = null): void
    {
        self::upsert($telegramId, 'whitelist', $createdBy, $reason);
    }

    public static function addToBlacklist(int $telegramId, ?int $createdBy = null, ?string $reason = null): void
    {
        self::upsert($telegramId, 'blacklist', $createdBy, $reason);
    }

    public static function remove(int $telegramId): void
    {
        self::deleteWhere('telegram_id', $telegramId);
    }

    private static function upsert(int $telegramId, string $type, ?int $createdBy, ?string $reason): void
    {
        $stmt = self::db()->prepare(
            'INSERT INTO access_list (telegram_id, type, created_by, reason) VALUES (:telegram_id, :type, :created_by, :reason)
             ON DUPLICATE KEY UPDATE type = :type_update, created_by = :created_by_update, reason = :reason_update'
        );
        $stmt->execute([
            'telegram_id'      => $telegramId,
            'type'             => $type,
            'created_by'       => $createdBy,
            'reason'           => $reason,
            'type_update'      => $type,
            'created_by_update' => $createdBy,
            'reason_update'    => $reason,
        ]);
    }
}
