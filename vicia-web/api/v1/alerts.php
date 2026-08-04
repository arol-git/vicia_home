<?php
/**
 * api/v1/alerts.php
 *
 * Ressource REST /api/v1/alerts — alertes D'UNE MAISON (paramètre
 * "house_id" requis, vérifié via api_authorize_house()).
 *
 *   GET  /api/v1/alerts?house_id=1             Liste des alertes
 *   GET  /api/v1/alerts/{id}?house_id=1        Détail d'une alerte
 *   POST /api/v1/alerts/{id}/read              Marque une alerte comme lue (house_id dans le corps)
 */

use App\Models\Alert;

function handle_alerts(string $method, ?string $id, ?string $subaction): void
{
    $user = api_authenticate();
    $input = api_input();
    $houseId = api_authorize_house($user, $input);

    if ($id && $subaction === 'read' && $method === 'POST') {
        if (!Alert::belongsToHouse((int) $id, $houseId)) {
            api_response(['success' => false, 'message' => 'Alerte introuvable.'], 404);
        }
        Alert::markAsRead((int) $id);
        api_response(['success' => true, 'message' => 'Alerte marquée comme lue.']);
    }

    switch ($method) {
        case 'GET':
            if ($id) {
                if (!Alert::belongsToHouse((int) $id, $houseId)) {
                    api_response(['success' => false, 'message' => 'Alerte introuvable.'], 404);
                }
                api_response(['success' => true, 'data' => Alert::find((int) $id)]);
            }
            api_response(['success' => true, 'data' => Alert::forHouse($houseId)]);
            break;

        default:
            api_response(['success' => false, 'message' => 'Méthode non autorisée.'], 405);
    }
}
