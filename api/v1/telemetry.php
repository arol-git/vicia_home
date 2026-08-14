<?php
/**
 * api/v1/telemetry.php
 *
 * Endpoint d'ingestion directe des mesures ESP32.
 *
 *   POST /api/v1/telemetry
 *
 * Corps JSON :
 *   { "topic": "home/villa-yaounde/climate/salon/temp", "value": 25.4 }
 *
 * ou :
 *   { "readings": [
 *       { "topic": "home/villa-yaounde/climate/salon/temp", "value": 25.4 },
 *       { "topic": "home/villa-yaounde/energy/salon/power", "value": 73.2 }
 *   ] }
 */

use App\Services\TelemetryService;

function handle_telemetry(string $method, ?string $id, ?string $subaction): void
{
    if ($method !== 'POST') {
        api_response(['success' => false, 'message' => 'Méthode non autorisée.'], 405);
    }

    $expectedKey = config('telemetry_api_key');
    if ($expectedKey) {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $providedKey = $headers['X-Telemetry-Key'] ?? $headers['x-telemetry-key'] ?? ($_SERVER['HTTP_X_TELEMETRY_KEY'] ?? '');
        if (!hash_equals($expectedKey, (string) $providedKey)) {
            api_response(['success' => false, 'message' => 'Clé de télémétrie invalide.'], 401);
        }
    }

    $input = api_input();
    $result = TelemetryService::ingestHttp($input);

    api_response([
        'success' => $result['success'],
        'message' => $result['success'] ? 'Mesure(s) enregistrée(s).' : 'Aucune mesure enregistrée.',
        'saved' => $result['saved'],
        'errors' => $result['errors'],
    ], $result['success'] ? 201 : 422);
}
