<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Response;
use App\Models\PushSubscription;

class PushController extends Controller
{
    public function getPublicKey(): void
    {
        Auth::requireLogin();

        $config = config('web_push') ?? [];
        $publicKey = trim((string) ($config['public_key'] ?? getenv('VAPID_PUBLIC_KEY') ?: ''));

        Response::json([
            'success' => true,
            'publicKey' => $publicKey,
        ]);
    }

    public function subscribe(): void
    {
        Auth::requireLogin();
        
        // CSRF not required for push subscriptions (same-origin, credentials: same-origin)
        // but we still validate the JSON structure
        if (!$this->request->isJson()) {
            Response::error('Content-Type: application/json requis.', 415);
            return;
        }

        $payload = $this->request->all();
        $subscription = $payload['subscription'] ?? null;
        if (!is_array($subscription)) {
            Response::error('Abonnement invalide.', 422);
            return;
        }

        $houseId = null;
        if (!empty($payload['house_id'])) {
            $houseId = (int) $payload['house_id'];
            if (Auth::roleOnHouse($houseId) === null) {
                Response::error('Maison introuvable ou accès refusé.', 403);
                return;
            }
        } else {
            $houseId = Auth::currentHouseId();
        }

        $endpoint = trim((string) ($subscription['endpoint'] ?? ''));
        $keys = $subscription['keys'] ?? [];
        $p256dh = trim((string) ($keys['p256dh'] ?? ''));
        $auth = trim((string) ($keys['auth'] ?? ''));

        if ($endpoint === '' || $p256dh === '' || $auth === '') {
            Response::error('Données de souscription incomplètes.', 422);
            return;
        }

        $userId = Auth::id();
        PushSubscription::upsert($userId, $houseId, $endpoint, $p256dh, $auth, $payload['user_agent'] ?? '');

        Response::success('Notifications navigateur activées.', ['subscribed' => true]);
    }

    public function unsubscribe(): void
    {
        Auth::requireLogin();
        
        // CSRF not required for push unsubscribe (same-origin, credentials: same-origin)
        if (!$this->request->isJson()) {
            Response::error('Content-Type: application/json requis.', 415);
            return;
        }

        $payload = $this->request->all();
        $endpoint = trim((string) ($payload['endpoint'] ?? ''));
        if ($endpoint === '') {
            Response::error('Endpoint manquant.', 422);
            return;
        }

        PushSubscription::deleteByEndpoint(Auth::id(), $endpoint);
        Response::success('Abonnement supprimé.');
    }
}
