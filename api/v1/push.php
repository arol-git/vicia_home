<?php

use App\Core\Auth;
use App\Models\PushSubscription;

function handle_push(string $method, ?string $id = null, ?string $subaction = null): void
{
    Auth::requireLogin();
    $userId = (int) Auth::id();

        if ($method === 'GET' && $id === 'public-key') {
            $config = config('web_push') ?? [];
            api_response([
                'success' => true,
                'publicKey' => trim((string) ($config['public_key'] ?? getenv('VAPID_PUBLIC_KEY') ?: '')),
            ]);
        }

        if ($method === 'GET' && ($id === 'status' || $id === null)) {
        $subscriptions = PushSubscription::forUser($userId);
        api_response([
            'success' => true,
            'enabled' => count($subscriptions) > 0,
            'count' => count($subscriptions),
        ]);
    }

    if ($method === 'POST' && $id === 'subscribe') {
        $payload = api_input();
        $subscription = $payload['subscription'] ?? $payload;
        $keys = is_array($subscription['keys'] ?? null) ? $subscription['keys'] : [];
        $endpoint = trim((string) ($subscription['endpoint'] ?? ''));
        $p256dh = trim((string) ($keys['p256dh'] ?? ''));
        $auth = trim((string) ($keys['auth'] ?? ''));
        $userAgent = substr((string) ($payload['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 255);

        if ($endpoint === '' || strlen($endpoint) > 2048 || $p256dh === '' || strlen($p256dh) > 512 || $auth === '' || strlen($auth) > 512) {
            api_response(['success' => false, 'message' => 'Abonnement push invalide.'], 422);
        }

        $houseId = Auth::currentHouseId();
        if (!empty($payload['house_id'])) {
            $requestedHouseId = (int) $payload['house_id'];
            if (Auth::roleOnHouse($requestedHouseId) === null) {
                api_response(['success' => false, 'message' => 'Accès maison refusé.'], 403);
            }
            $houseId = $requestedHouseId;
        }

        PushSubscription::upsert($userId, $houseId, $endpoint, $p256dh, $auth, $userAgent);
        api_response(['success' => true, 'enabled' => true, 'message' => 'Notifications activées.']);
    }

    if ($method === 'POST' && $id === 'unsubscribe') {
        $payload = api_input();
        $endpoint = trim((string) ($payload['endpoint'] ?? ''));
        if ($endpoint === '') {
            api_response(['success' => false, 'message' => 'Endpoint manquant.'], 422);
        }

        PushSubscription::deleteByEndpoint($userId, $endpoint);
        api_response(['success' => true, 'enabled' => count(PushSubscription::forUser($userId)) > 0]);
    }

    api_response(['success' => false, 'message' => 'Action push inconnue.'], 404);
}
