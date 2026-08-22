<?php

namespace App\Services;

class NotificationPipeline
{
    private const ORDER = ['EMAIL', 'TELEGRAM', 'PUSH'];

    public static function dispatch(int $alertId, int $houseId, array $channels): void
    {
        app_log("[NOTIFICATION] Alerte reçue | alerte={$alertId} | maison={$houseId}");

        foreach (self::ORDER as $channel) {
            app_log("[NOTIFICATION] Tentative {$channel} | alerte={$alertId} | maison={$houseId}");

            try {
                $sent = ($channels[$channel] ?? static fn (): bool => false)();
                app_log("[NOTIFICATION] {$channel} " . ($sent ? 'SUCCÈS' : 'ÉCHEC') . " | alerte={$alertId} | maison={$houseId} | date=" . date('c'));
            } catch (\Throwable $exception) {
                app_log("[NOTIFICATION] {$channel} ÉCHEC | alerte={$alertId} | maison={$houseId} | erreur=" . $exception->getMessage() . ' | date=' . date('c'));
            }
        }

        app_log("[NOTIFICATION] FIN DU TRAITEMENT | alerte={$alertId} | maison={$houseId}");
    }
}
