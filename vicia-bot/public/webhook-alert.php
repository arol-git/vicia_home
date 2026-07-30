<?php
/**
 * public/webhook-alert.php
 *
 * Point d'entrée HTTPS séparé du webhook Telegram, appelé PAR LA
 * PLATEFORME VICIA HOME (voir mqtt/subscriber.php côté vicia-home,
 * qui doit être complété pour appeler cette URL après chaque
 * Alert::create() jugée notifiable — voir docs/README.md).
 *
 * Corps de requête attendu :
 *   { "house_id": 1, "alert": { "id": 42, "severity": "critical", "message": "..." } }
 *
 * Authentifié par signature HMAC-SHA256 du corps brut (en-tête
 * X-Vicia-Signature), avec le secret partagé VICIA_ALERT_WEBHOOK_SECRET
 * — identique des deux côtés. Jamais par jeton utilisateur : aucun
 * utilisateur Telegram n'est à l'origine de cet appel.
 */

require __DIR__ . '/../vendor/autoload.php';

use Bot\Config\App;
use Bot\Core\Logger;
use Bot\Services\NotificationDispatcher;

App::boot();

$rawBody = file_get_contents('php://input');
$providedSignature = $_SERVER['HTTP_X_VICIA_SIGNATURE'] ?? '';
$expectedSignature = hash_hmac('sha256', $rawBody, App::env('VICIA_ALERT_WEBHOOK_SECRET', ''));

if (!$providedSignature || !hash_equals($expectedSignature, $providedSignature)) {
    Logger::channel('security')->warning('Webhook alerte rejeté : signature absente ou incorrecte', [
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'inconnue',
    ]);
    http_response_code(403);
    exit;
}

$payload = json_decode($rawBody, true);

if (!is_array($payload) || empty($payload['house_id']) || empty($payload['alert'])) {
    Logger::channel('bot')->warning('Webhook alerte rejeté : corps de requête malformé');
    http_response_code(400);
    exit;
}

try {
    $sent = NotificationDispatcher::dispatchAlert((int) $payload['house_id'], $payload['alert']);
    Logger::channel('bot')->info("Alerte diffusée à {$sent} destinataire(s) pour la maison #{$payload['house_id']}");
} catch (\Throwable $e) {
    Logger::channel('bot')->error('Échec de diffusion de notification : ' . $e->getMessage());
    http_response_code(500);
    exit;
}

http_response_code(200);
echo json_encode(['success' => true, 'notified' => $sent]);
