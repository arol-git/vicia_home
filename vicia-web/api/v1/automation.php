<?php
/**
 * api/v1/automation.php
 *
 * Ressource REST /api/v1/automation — règles d'automatisation D'UNE
 * MAISON (paramètre "house_id" requis, vérifié via
 * api_authorize_house()).
 *
 *   GET    /api/v1/automation?house_id=1             Liste des règles
 *   POST   /api/v1/automation                         Création (house_id dans le corps)
 *   POST   /api/v1/automation/{id}/toggle              Active/désactive (house_id dans le corps)
 *   DELETE /api/v1/automation/{id}?house_id=1          Suppression
 */

use App\Models\AutomationRule;

function handle_automation(string $method, ?string $id, ?string $subaction): void
{
    $user = api_authenticate();
    $input = api_input();
    $houseId = api_authorize_house($user, $input);
    $houseRole = \App\Models\House::roleOfUser($houseId, $user['id'], $user['role']);

    if ($id && $subaction === 'toggle' && $method === 'POST') {
        if (!AutomationRule::belongsToHouse((int) $id, $houseId)) {
            api_response(['success' => false, 'message' => 'Règle introuvable.'], 404);
        }
        $rule = AutomationRule::find((int) $id);
        $newStatus = $rule['is_active'] ? 0 : 1;
        AutomationRule::update((int) $id, ['is_active' => $newStatus]);
        api_response(['success' => true, 'message' => 'État de la règle mis à jour.', 'data' => ['is_active' => $newStatus]]);
    }

    switch ($method) {
        case 'GET':
            api_response(['success' => true, 'data' => AutomationRule::allWithLabels($houseId)]);
            break;

        case 'POST':
            if (!in_array($houseRole, ['admin', 'owner', 'technician'], true)) {
                api_response(['success' => false, 'message' => 'Privilèges insuffisants sur cette maison.'], 403);
            }
            if (empty($input['name']) || empty($input['condition_source'])) {
                api_response(['success' => false, 'message' => 'Les champs « name » et « condition_source » sont obligatoires.'], 422);
            }
            $newId = AutomationRule::create([
                'house_id'            => $houseId,
                'name'                => $input['name'],
                'condition_source'    => $input['condition_source'],
                'condition_sensor_id' => $input['condition_sensor_id'] ?? null,
                'condition_operator'  => $input['condition_operator'] ?? null,
                'condition_value'     => $input['condition_value'] ?? null,
                'condition_event'     => $input['condition_event'] ?? null,
                'action_equipment_id' => $input['action_equipment_id'] ?? null,
                'action_state'        => $input['action_state'] ?? null,
                'notify_telegram'     => !empty($input['notify_telegram']) ? 1 : 0,
                'notify_email'        => !empty($input['notify_email']) ? 1 : 0,
                'is_active'           => 1,
                'created_by'          => $user['id'],
            ]);
            api_response(['success' => true, 'message' => 'Règle créée.', 'data' => AutomationRule::find($newId)], 201);
            break;

        case 'DELETE':
            if (!in_array($houseRole, ['admin', 'owner'], true)) {
                api_response(['success' => false, 'message' => 'Privilèges insuffisants sur cette maison.'], 403);
            }
            if (!$id || !AutomationRule::belongsToHouse((int) $id, $houseId)) {
                api_response(['success' => false, 'message' => 'Règle introuvable.'], 404);
            }
            AutomationRule::delete((int) $id);
            api_response(['success' => true, 'message' => 'Règle supprimée.']);
            break;

        default:
            api_response(['success' => false, 'message' => 'Méthode non autorisée.'], 405);
    }
}
