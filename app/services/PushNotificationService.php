<?php

namespace App\Services;

use App\Models\PushSubscription;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class PushNotificationService
{
    public static function sendToHouse(int $houseId, string $title, string $body, ?array $data = null): bool
    {
        $config = config('web_push') ?? [];
        $publicKey = trim((string) ($config['public_key'] ?? getenv('VAPID_PUBLIC_KEY') ?: ''));
        $privateKey = trim((string) ($config['private_key'] ?? getenv('VAPID_PRIVATE_KEY') ?: ''));
        if ($publicKey === '' || $privateKey === '') {
            app_log('[Push] Clés VAPID absentes.');
            return false;
        }

        $subscriptions = PushSubscription::forHouse($houseId);
        if (!$subscriptions) return false;

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => (string) ($config['subject'] ?? 'mailto:admin@vicia-home.local'),
                'publicKey' => $publicKey,
                'privateKey' => $privateKey,
            ],
        ]);
        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'tag' => 'vicia-alert-' . $houseId,
            'requireInteraction' => true,
            'data' => $data ?? ['house_id' => $houseId, 'url' => '/alerts'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $sent = false;
        foreach ($subscriptions as $subscription) {
            try {
                $report = $webPush->sendOneNotification(
                    Subscription::create([
                        'endpoint' => $subscription['endpoint'],
                        'keys' => [
                            'p256dh' => $subscription['p256dh'],
                            'auth' => $subscription['auth'],
                        ],
                    ]),
                    $payload
                );
                if ($report->isSuccess()) {
                    $sent = true;
                } elseif (in_array($report->getResponse()?->getStatusCode(), [404, 410], true)) {
                    PushSubscription::deactivate((int) $subscription['id']);
                }
            } catch (\Throwable $exception) {
                app_log('[Push] Envoi échoué: ' . $exception->getMessage());
            }
        }

        return $sent;
    }
}
