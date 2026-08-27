<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Response;
use App\Models\Energy;

/**
 * Class ConsumptionController
 *
 * Module de suivi de la consommation électrique de la maison
 * actuellement sélectionnée.
 */
class ConsumptionController extends Controller
{
    public function index(): void
    {
        $houseId = Auth::requireHouseRole(['admin', 'owner', 'resident', 'technician']);
        $month = (string) $this->request->query('month', date('Y-m'));
        $selected = Energy::month($houseId, $month);
        $history = Energy::history($houseId, 24);
        $previous = null;
        foreach ($history as $index => $item) {
            if ($item['month'] === Energy::normalizeMonth($month)) {
                $previous = $history[$index + 1] ?? null;
                break;
            }
        }

        $change = null;
        if ($selected['total_kwh'] !== null && $previous && $previous['total_kwh'] !== null && (float) $previous['total_kwh'] > 0) {
            $change = round((($selected['total_kwh'] - $previous['total_kwh']) / $previous['total_kwh']) * 100, 1);
        }

        $this->render('consumption/index', [
            'title' => 'Consommation énergétique',
            'selectedMonth' => $month,
            'selectedData' => $selected,
            'history' => $history,
            'previousData' => $previous,
            'changePercent' => $change,
        ]);
    }

    public function data(): void
    {
        $houseId = Auth::requireHouseRole(['admin', 'owner', 'resident', 'technician']);
        $month = (string) $this->request->query('month', date('Y-m'));
        Response::json(['success' => true, 'data' => Energy::month($houseId, $month)]);
    }
}
