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
        if (!$subscriptions) {
            app_log('[NOTIFICATION] PUSH ÉCHEC | utilisateur=aucun abonnement pour maison ' . $houseId . ' | date=' . date('c'));
            return false;
        }

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
                    app_log('[NOTIFICATION] PUSH utilisateur=' . $subscription['user_id'] . ' SUCCÈS | date=' . date('c'));
                } elseif (in_array($report->getResponse()?->getStatusCode(), [404, 410], true)) {
                    PushSubscription::deactivate((int) $subscription['id']);
                    app_log('[NOTIFICATION] PUSH utilisateur=' . $subscription['user_id'] . ' ÉCHEC | erreur=abonnement expiré | date=' . date('c'));
                } else {
                    app_log('[NOTIFICATION] PUSH utilisateur=' . $subscription['user_id'] . ' ÉCHEC | erreur=réponse du service push invalide | date=' . date('c'));
                }
            } catch (\Throwable $exception) {
                app_log('[NOTIFICATION] PUSH utilisateur=' . $subscription['user_id'] . ' ÉCHEC | erreur=' . $exception->getMessage() . ' | date=' . date('c'));
            }
        }

        return $sent;
    }
}
