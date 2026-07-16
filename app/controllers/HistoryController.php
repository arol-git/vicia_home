<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\ActivityLog;
use App\Models\LoginLog;

/**
 * Class HistoryController
 *
 * Affiche l'historique complet des activités de la plateforme
 * (journal d'audit) et des connexions, avec pagination simple.
 */
class HistoryController extends Controller
{
    public function index(): void
    {
        Auth::requireLogin();

        $page   = max(1, (int) $this->request->query('page', 1));
        $limit  = 20;
        $offset = ($page - 1) * $limit;

        $activities = ActivityLog::paginated($limit, $offset);
        $totalCount = ActivityLog::count();
        $totalPages = (int) ceil($totalCount / $limit);

        $logins = LoginLog::recent(15);

        $this->render('history/index', [
            'title'      => 'Historique',
            'activities' => $activities,
            'logins'     => $logins,
            'page'       => $page,
            'totalPages' => $totalPages,
        ]);
    }
}
