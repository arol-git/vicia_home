<?php
/**
 * api/v1/consumption.php
 *
 * Ressource REST /api/v1/consumption — estimation de la consommation
 * électrique D'UNE MAISON (paramètre "house_id" requis). Reprend la
 * même table de puissances nominales indicatives que
 * App\Controllers\ConsumptionController, côté Web.
 *
 *   GET /api/v1/consumption?house_id=1
 */

use App\Models\Equipment;

function handle_consumption(string $method, ?string $id, ?string $subaction): void
{
    $user = api_authenticate();
    $houseId = api_authorize_house($user, api_input());

    if ($method !== 'GET') {
        api_response(['success' => false, 'message' => 'Méthode non autorisée.'], 405);
    }

    $powerWatts = [
        'led' => 9, 'relais' => 5, 'ventilateur' => 45, 'pompe' => 60,
        'servo' => 3, 'porte' => 3, 'fenetre' => 3, 'sirene' => 4, 'camera' => 6,
    ];

    $equipments = Equipment::allWithRoom($houseId);

    $totalActiveWatts = 0;
    $byType = [];
    foreach ($equipments as $eq) {
        $watts = $powerWatts[$eq['type']] ?? 10;
        $byType[$eq['type']] = $byType[$eq['type']] ?? 0;
        if ((int) $eq['state'] === 1) {
            $totalActiveWatts += $watts;
            $byType[$eq['type']] += $watts;
        }
    }

    api_response([
        'success' => true,
        'data' => [
            'total_active_watts'   => $totalActiveWatts,
            'estimated_daily_kwh'  => round(($totalActiveWatts * 24) / 1000, 2),
            'by_type_watts'        => $byType,
            'active_equipments'    => count(array_filter($equipments, fn($e) => (int) $e['state'] === 1)),
        ],
    ]);
}
