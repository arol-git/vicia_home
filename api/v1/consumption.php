<?php
/**
 * api/v1/consumption.php
 *
 * Ressource REST /api/v1/consumption — mesures du compteur global
 * de la maison. Aucun équipement individuel n'est utilisé.
 */

use App\Models\Energy;

function handle_consumption(string $method, ?string $id, ?string $subaction): void
{
    $user = api_authenticate();
    $input = api_input();
    $houseId = api_authorize_house($user, $input);

    if ($method !== 'GET') {
        api_response(['success' => false, 'message' => 'Méthode non autorisée.'], 405);
    }

    $month = (string) ($input['month'] ?? date('Y-m'));

    api_response([
        'success' => true,
        'data' => Energy::month($houseId, $month),
    ]);
}
