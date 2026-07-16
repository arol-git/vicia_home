<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Models\Equipment;

/**
 * Class ConsumptionController
 *
 * Module de suivi de la consommation électrique. Estime la
 * consommation à partir du temps de fonctionnement cumulé des
 * équipements actifs (en l'absence de compteur intelligent dédié,
 * cette estimation peut être remplacée par une mesure réelle
 * remontée via MQTT sur le topic home/power/#).
 */
class ConsumptionController extends Controller
{
    // Puissance nominale indicative par type d'équipement, en watts.
    private const POWER_WATTS = [
        'led' => 9, 'relais' => 5, 'ventilateur' => 45, 'pompe' => 60,
        'servo' => 3, 'porte' => 3, 'fenetre' => 3, 'sirene' => 4, 'camera' => 6,
    ];

    public function index(): void
    {
        Auth::requireLogin();

        $equipments = Equipment::allWithRoom();

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
            'title'            => 'Consommation électrique',
            'totalActiveWatts' => $totalActiveWatts,
            'estimatedDailyKwh' => round(($totalActiveWatts * 24) / 1000, 2),
            'byType'           => $byType,
            'equipments'       => $equipments,
        ]);
    }
}
