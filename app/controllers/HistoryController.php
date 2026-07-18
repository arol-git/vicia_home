<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Models\ActivityLog;

/**
 * Class HistoryController
 *
 * Affiche l'historique des activités de la maison actuellement
 * sélectionnée (journal d'audit), avec pagination simple. Les
 * connexions restent affichées au niveau du compte utilisateur
 * (elles ne sont pas rattachées à une maison en particulier).
 */
class HistoryController extends Controller
{
    public function index(): void
    {
        $houseId = Auth::requireHouseRole(['admin', 'owner', 'resident', 'technician']);

        $page   = max(1, (int) $this->request->query('page', 1));
        $limit  = 20;
        $offset = ($page - 1) * $limit;

        $activities = ActivityLog::paginatedForHouse($houseId, $limit, $offset);
        $totalCount = Database::query(
            'SELECT COUNT(*) AS c FROM activity_logs WHERE house_id = :house_id',
            ['house_id' => $houseId]
        )->fetch()['c'];
        $totalPages = (int) ceil($totalCount / $limit);

        $logins = \App\Models\LoginLog::recent(15);

        $this->render('history/index', [
            'title'      => 'Historique',
            'activities' => $activities,
            'logins'     => $logins,
            'page'       => $page,
            'totalPages' => max(1, $totalPages),
        ]);
    }
}
