<?php
/**
 * api/v1/alerts.php
 *
 * Ressource REST /api/v1/alerts — consultation des alertes générées
 * par le système.
 *
 *   GET  /api/v1/alerts             Liste des alertes
 *   GET  /api/v1/alerts/{id}        Détail d'une alerte
 *   POST /api/v1/alerts/{id}/read   Marque une alerte comme lue
 */

use App\Models\Alert;

function handle_alerts(string $method, ?string $id, ?string $subaction): void
{
    api_authenticate();

    if ($id && $subaction === 'read' && $method === 'POST') {
        if (!Alert::find((int) $id)) {
            api_response(['success' => false, 'message' => 'Alerte introuvable.'], 404);
        }
        Alert::markAsRead((int) $id);
        api_response(['success' => true, 'message' => 'Alerte marquée comme lue.']);
    }

    switch ($method) {
        case 'GET':
            if ($id) {
                $alert = Alert::find((int) $id);
                $alert ? api_response(['success' => true, 'data' => $alert])
                       : api_response(['success' => false, 'message' => 'Alerte introuvable.'], 404);
            }
            api_response(['success' => true, 'data' => Alert::all('created_at DESC')]);
            break;

        default:
            api_response(['success' => false, 'message' => 'Méthode non autorisée.'], 405);
    }
}
