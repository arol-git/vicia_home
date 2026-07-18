<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Equipment;

/**
 * Class ConsumptionController
 *
 * Module de suivi de la consommation électrique de la maison
 * actuellement sélectionnée.
 */
class ConsumptionController extends Controller
{
    private const POWER_WATTS = [
        'led' => 9, 'relais' => 5, 'ventilateur' => 45, 'pompe' => 60,
        'servo' => 3, 'porte' => 3, 'fenetre' => 3, 'sirene' => 4, 'camera' => 6,
    ];

    public function index(): void
    {
        $houseId = Auth::requireHouseRole(['admin', 'owner', 'resident', 'technician']);

        $equipments = Equipment::allWithRoom($houseId);

        $totalActiveWatts = 0;
        foreach ($equipments as $eq) {
            if ((int) $eq['state'] === 1) {
                $totalActiveWatts += self::POWER_WATTS[$eq['type']] ?? 10;
            }
        }

        $byType = [];
        foreach ($equipments as $eq) {
            $byType[$eq['type']] = ($byType[$eq['type']] ?? 0) + ((int) $eq['state'] === 1 ? (self::POWER_WATTS[$eq['type']] ?? 10) : 0);
        }

        $this->render('consumption/index', [
            'title'             => 'Consommation électrique',
            'totalActiveWatts'  => $totalActiveWatts,
            'estimatedDailyKwh' => round(($totalActiveWatts * 24) / 1000, 2),
            'byType'            => $byType,
            'equipments'        => $equipments,
        ]);
    }
}
