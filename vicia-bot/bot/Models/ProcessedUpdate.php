<?php

namespace Bot\Models;

use Bot\Core\Model;
use PDOException;

/**
 * Class ProcessedUpdate
 *
 * Registre des `update_id` Telegram déjà traités, pour la protection
 * anti-rejeu (voir Bot\Middlewares\ReplayProtectionMiddleware). Un
 * `update_id` est unique et croissant côté Telegram ; le retrouver une
 * seconde fois signale soit une relivraison légitime du webhook
 * (Telegram réémet si la réponse précédente n'a pas été un 200 dans
 * les temps), soit une tentative de rejeu malveillante — dans les
 * deux cas, retraiter l'update serait incorrect.
 */
class ProcessedUpdate extends Model
{
    protected static string $table = 'processed_updates';

    /**
     * Tente d'enregistrer un update_id comme traité. Retourne true à
     * la première tentative (traitement autorisé), false si déjà
     * présent (rejeu ou relivraison — traitement à ignorer).
     */
    public static function markProcessed(int $updateId): bool
    {
        try {
            self::db()->prepare('INSERT INTO processed_updates (update_id) VALUES (:id)')->execute(['id' => $updateId]);
            return true;
        } catch (PDOException $e) {
            // Code SQLSTATE 23000 = violation de contrainte d'unicité
            // (clé primaire déjà présente) : c'est le cas nominal d'un
            // rejeu détecté, pas une erreur imprévue.
            if ($e->getCode() === '23000') {
                return false;
            }
            throw $e;
        }
    }

    /**
     * Purge les entrées de plus de $olderThanDays jours.
     */
    public static function purgeOlderThan(int $olderThanDays): int
    {
        $stmt = self::db()->prepare('DELETE FROM processed_updates WHERE processed_at < (NOW() - INTERVAL :days DAY)');
        $stmt->bindValue(':days', $olderThanDays, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
    }
}
