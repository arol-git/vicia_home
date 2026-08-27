<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;
use App\Helpers\Notifier;
use App\Services\NotificationPipeline;

/**
 * Class Alert
 *
 * Modèle représentant une alerte générée par le système : intrusion,
 * événement réseau, dépassement de seuil capteur, événement système.
 * Rattachée à une maison (`house_id`), sauf pour de rares alertes
 * transverses à toute la plateforme.
 */
class Alert extends Model
{
    protected static string $table = 'alerts';

    public static function forHouse(int $houseId, string $orderBy = 'created_at DESC'): array
    {
        $alerts = Database::query(
            "SELECT * FROM alerts WHERE house_id = :house_id ORDER BY $orderBy",
            ['house_id' => $houseId]
        )->fetchAll();
        return array_map([self::class, 'sanitizeForDisplay'], $alerts);
    }

    public static function recent(int $houseId, int $limit = 10): array
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT * FROM alerts WHERE house_id = :house_id ORDER BY created_at DESC LIMIT :limit'
        );
        $stmt->bindValue(':house_id', $houseId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return array_map([self::class, 'sanitizeForDisplay'], $stmt->fetchAll());
    }

    public static function countUnread(int $houseId): int
    {
        $row = Database::query(
            'SELECT COUNT(*) AS c FROM alerts WHERE house_id = :house_id AND is_read = 0',
            ['house_id' => $houseId]
        )->fetch();
        return (int) ($row['c'] ?? 0);
    }

    public static function hasRecentSensorAlert(int $houseId, int $sensorId, int $minutes = 15): bool
    {
        $minutes = max(1, min($minutes, 1440));
        $row = Database::query(
            'SELECT id FROM alerts
             WHERE house_id = :house_id AND type = :type AND source = :source
               AND created_at >= DATE_SUB(NOW(), INTERVAL ' . $minutes . ' MINUTE)
             LIMIT 1',
            [
                'house_id' => $houseId,
                'type' => 'capteur',
                'source' => 'sensor:' . $sensorId,
            ]
        )->fetch();

        return (bool) $row;
    }

    public static function hasRecentIntrusionAlert(int $houseId, string $source, int $minutes = 15): bool
    {
        $minutes = max(1, min($minutes, 1440));
        $row = Database::query(
            'SELECT id FROM alerts
             WHERE house_id = :house_id AND type = :type AND source = :source
               AND created_at >= DATE_SUB(NOW(), INTERVAL ' . $minutes . ' MINUTE)
             LIMIT 1',
            [
                'house_id' => $houseId,
                'type' => 'intrusion',
                'source' => $source,
            ]
        )->fetch();

        return (bool) $row;
    }

    public static function belongsToHouse(int $alertId, int $houseId): bool
    {
        $row = Database::query(
            'SELECT id FROM alerts WHERE id = :id AND house_id = :house_id LIMIT 1',
            ['id' => $alertId, 'house_id' => $houseId]
        )->fetch();
        return (bool) $row;
    }

    public static function markAsRead(int $id): bool
    {
        return self::update($id, ['is_read' => 1]);
    }

    public static function markAllAsRead(int $houseId): void
    {
        Database::query('UPDATE alerts SET is_read = 1 WHERE house_id = :house_id AND is_read = 0', ['house_id' => $houseId]);
    }

    public static function shouldNotify(array $data): bool
    {
        $type = strtolower((string) ($data['type'] ?? ''));
        $severity = strtolower((string) ($data['severity'] ?? 'info'));

        if (in_array($type, ['test_email', 'test_telegram', 'test'], true)) {
            return false;
        }

        if (in_array($type, ['intrusion', 'reseau', 'capteur', 'systeme', 'alarm', 'alarme', 'security'], true)) {
            return true;
        }

        return $severity === 'critical';
    }

    public static function create(array $data): int
    {
        $data += ['severity' => 'info', 'is_read' => 0];
        $alertId = parent::create($data);

        if (!self::shouldNotify($data)) {
            return $alertId;
        }

        $houseId = isset($data['house_id']) ? (int) $data['house_id'] : null;
        if ($houseId === null || $houseId <= 0) {
            return $alertId;
        }

        $label = ucfirst(str_replace(['_', '-'], ' ', (string) ($data['type'] ?? 'systeme')));
        $message = self::sanitizeText((string) ($data['message'] ?? ''));
        $text = "Alerte {$label} ({$data['severity']})\n" . ($message !== '' ? $message : 'Déclenchée sur la maison #' . $houseId);

        NotificationPipeline::dispatch($alertId, $houseId, [
            'EMAIL' => static fn (): bool => Notifier::sendAlertEmail($houseId, "Alerte Vicia Home : {$label}", $text),
            'TELEGRAM' => static fn (): bool => self::notifyBot($houseId, [
                'id'       => $alertId,
                'severity' => (string) ($data['severity'] ?? 'info'),
                'message'  => $message,
            ]),
            'PUSH' => static fn (): bool => Notifier::sendBrowserPush($houseId, "Alerte {$label}", $message !== '' ? $message : 'Nouvelle alerte sur Vicia Home'),
        ]);

        return $alertId;
    }

    private static function notifyBot(int $houseId, array $alert): bool
    {
        $secret = trim((string) getenv('VICIA_ALERT_WEBHOOK_SECRET'));
        if ($secret === '' || !function_exists('curl_init')) {
            app_log('[Alert] Notification Telegram du bot indisponible : webhook ou extension cURL non configuré.');
            return false;
        }

        $payload = json_encode([
            'house_id' => $houseId,
            'alert'    => $alert,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            app_log('[Alert] Impossible de préparer la notification Telegram du bot.');
            return false;
        }

        $url = rtrim((string) config('base_url'), '/') . '/vicia-bot/public/webhook-alert.php';
        $signature = hash_hmac('sha256', $payload, $secret);
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'X-Vicia-Signature: ' . $signature,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT        => 5,
        ]);

        $response = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($response === false || $status < 200 || $status >= 300) {
            app_log('[Alert] Échec de transmission au bot Telegram' . ($error !== '' ? ' : ' . $error : " (HTTP $status)"));
            return false;
        }

        return true;
    }

    private static function sanitizeForDisplay(array $alert): array
    {
        if (isset($alert['message'])) {
            $alert['message'] = self::sanitizeText((string) $alert['message']);
        }
        if (isset($alert['source'])) {
            $alert['source'] = self::sanitizeText((string) $alert['source']);
        }
        return $alert;
    }

    private static function sanitizeText(string $text): string
    {
        return trim((string) preg_replace(
            '~(?:mqtts?://|home/)[^\s,;()\[\]{}<>]+~i',
            'source technique masquée',
            $text
        ));
    }
}
