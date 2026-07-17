<?php
/**
 * api/v1/automation.php
 *
 * Ressource REST /api/v1/automation — consultation et gestion des
 * règles du moteur d'automatisation.
 *
 *   GET    /api/v1/automation             Liste des règles
 *   POST   /api/v1/automation             Création d'une règle
 *   POST   /api/v1/automation/{id}/toggle Active/désactive une règle
 *   DELETE /api/v1/automation/{id}        Suppression d'une règle
 */

use App\Models\AutomationRule;

function handle_automation(string $method, ?string $id, ?string $subaction): void
{
    $user = api_authenticate();

    if ($id && $subaction === 'toggle' && $method === 'POST') {
        $rule = AutomationRule::find((int) $id);
        if (!$rule) {
            api_response(['success' => false, 'message' => 'Règle introuvable.'], 404);
        }
        $newStatus = $rule['is_active'] ? 0 : 1;
        AutomationRule::update((int) $id, ['is_active' => $newStatus]);
        api_response(['success' => true, 'message' => 'État de la règle mis à jour.', 'data' => ['is_active' => $newStatus]]);
    }

    switch ($method) {
        case 'GET':
            api_response(['success' => true, 'data' => AutomationRule::allWithLabels()]);
            break;

        case 'POST':
            if (!in_array($user['role'], ['admin', 'technicien'], true)) {
                api_response(['success' => false, 'message' => 'Privilèges insuffisants.'], 403);
            }
            $input = api_input();
            if (empty($input['name']) || empty($input['condition_source'])) {
                api_response(['success' => false, 'message' => 'Les champs « name » et « condition_source » sont obligatoires.'], 422);
            }
            $newId = AutomationRule::create([
                'name'                => $input['name'],
                'condition_source'    => $input['condition_source'],
                'condition_sensor_id' => $input['condition_sensor_id'] ?? null,
                'condition_operator'  => $input['condition_operator'] ?? null,
                'condition_value'     => $input['condition_value'] ?? null,
                'condition_event'     => $input['condition_event'] ?? null,
                'action_equipment_id' => $input['action_equipment_id'] ?? null,
                'action_state'        => $input['action_state'] ?? null,
                'notify_telegram'     => !empty($input['notify_telegram']) ? 1 : 0,//cette ligne est pour le bot telegram on doit y mettre le token du bot dans le fichier de config
                'notify_email'        => !empty($input['notify_email']) ? 1 : 0,
                'is_active'           => 1,
                'created_by'          => $user['id'],
            ]);
            api_response(['success' => true, 'message' => 'Règle créée.', 'data' => AutomationRule::find($newId)], 201);
            break;

        case 'DELETE':
            if (!in_array($user['role'], ['admin'], true)) {
                api_response(['success' => false, 'message' => 'Privilèges insuffisants.'], 403);
            }
            if (!$id || !AutomationRule::find((int) $id)) {
                api_response(['success' => false, 'message' => 'Règle introuvable.'], 404);
            }
            AutomationRule::delete((int) $id);
            api_response(['success' => true, 'message' => 'Règle supprimée.']);
            break;

        default:
            api_response(['success' => false, 'message' => 'Méthode non autorisée.'], 405);
    }
}
