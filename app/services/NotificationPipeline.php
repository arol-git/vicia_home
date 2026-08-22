<?php

namespace App\Services;

class NotificationPipeline
{
    private const ORDER = ['EMAIL', 'TELEGRAM', 'PUSH'];

    public static function dispatch(int $alertId, int $houseId, array $channels): void
    {
        self::log("[NOTIFICATION] Alerte reçue | alerte={$alertId} | maison={$houseId}");

        foreach (self::ORDER as $channel) {
            self::log("[NOTIFICATION] Tentative {$channel} | alerte={$alertId} | maison={$houseId}");

            try {
                $sent = ($channels[$channel] ?? static fn (): bool => false)();
                self::log("[NOTIFICATION] {$channel} " . ($sent ? 'SUCCÈS' : 'ÉCHEC') . " | alerte={$alertId} | maison={$houseId} | date=" . date('c'));
            } catch (\Throwable $exception) {
                self::log("[NOTIFICATION] {$channel} ÉCHEC | alerte={$alertId} | maison={$houseId} | erreur=" . $exception->getMessage() . ' | date=' . date('c'));
            }
        }

        self::log("[NOTIFICATION] FIN DU TRAITEMENT | alerte={$alertId} | maison={$houseId}");
    }

    private static function log(string $message): void
    {
        echo $message . PHP_EOL;
        app_log($message);
    }
}
