<?php
/**
 * api/v1/consumption.php
 *
 * Ressource REST /api/v1/consumption — estimation simple de la
 * consommation electrique d'une maison.
 *
 * Debutant : la base actuelle ne stocke pas encore la puissance
 * reelle de chaque equipement. On utilise donc une table d'estimation
 * par type, suffisante pour afficher une synthese dans le bot.
 */

use App\Models\Equipment;

function handle_consumption(string $method, ?string $id, ?string $subaction): void
{
    $user = api_authenticate();
    $input = api_input();
    $houseId = api_authorize_house($user, $input);

    if ($method !== 'GET') {
        api_response(['success' => false, 'message' => 'Méthode non autorisée.'], 405);
    }

    $wattsByType = [
        'led' => 8,
        'relais' => 60,
        'ventilateur' => 45,
        'pompe' => 80,
        'servo' => 5,
        'porte' => 5,
        'fenetre' => 5,
        'sirene' => 15,
    ];

    $totalWatts = 0;
    $activeEquipments = 0;

    foreach (Equipment::allWithRoom($houseId) as $equipment) {
        if ((int) $equipment['state'] !== 1) {
            continue;
        }

        $activeEquipments++;
        $totalWatts += $wattsByType[$equipment['type']] ?? 0;
    }

    api_response([
        'success' => true,
        'data' => [
            'total_active_watts' => $totalWatts,
            'estimated_daily_kwh' => round(($totalWatts * 24) / 1000, 2),
            'active_equipments' => $activeEquipments,
        ],
    ]);
}
