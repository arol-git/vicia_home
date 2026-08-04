<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Response;
use App\Core\Validator;
use App\Models\ActivityLog;
use App\Models\AutomationRule;
use App\Models\Equipment;
use App\Models\Room;
use App\Models\Sensor;

/**
 * Class AutomationController
 *
 * Gère le moteur de règles d'automatisation de la maison actuellement
 * sélectionnée. Un capteur ou un équipement choisi comme condition ou
 * action est systématiquement vérifié comme appartenant à cette même
 * maison, afin qu'une règle ne puisse jamais agir sur les ressources
 * d'une autre maison.
 */
class AutomationController extends Controller
{
    public function index(): void
    {
        $houseId = Auth::requireHouseRole(['admin', 'owner', 'resident', 'technician']);

        $rules      = AutomationRule::allWithLabels($houseId);
        $sensors    = Sensor::allWithRoom($houseId);
        $equipments = Equipment::allWithRoom($houseId);
        $logs       = AutomationRule::recentLogs($houseId, 20);

        $this->render('automation/index', [
            'title'      => 'Automatisation',
            'rules'      => $rules,
            'sensors'    => $sensors,
            'equipments' => $equipments,
            'logs'       => $logs,
        ]);
    }

    public function store(): void
    {
        $houseId = Auth::requireHouseRole(['admin', 'owner', 'technician']);
        $this->verifyCsrf();

        $conditionSource = (string) $this->request->input('condition_source', 'sensor');
        $sensorId = $conditionSource === 'sensor' ? (int) $this->request->input('condition_sensor_id') : null;
        $equipmentId = $this->request->input('action_equipment_id') ?: null;

        if ($sensorId && !Sensor::belongsToHouse($sensorId, $houseId)) {
            Response::error('Capteur invalide pour cette maison.', 422);
            return;
        }
        if ($equipmentId && !Equipment::belongsToHouse((int) $equipmentId, $houseId)) {
            Response::error('Équipement invalide pour cette maison.', 422);
            return;
        }

        $data = [
            'house_id'            => $houseId,
            'name'                => trim((string) $this->request->input('name')),
            'condition_source'    => $conditionSource,
            'condition_sensor_id' => $sensorId,
            'condition_operator'  => $conditionSource === 'sensor' ? (string) $this->request->input('condition_operator') : null,
            'condition_value'     => $conditionSource === 'sensor' ? (string) $this->request->input('condition_value') : null,
            'condition_event'     => $conditionSource === 'event' ? (string) $this->request->input('condition_event') : null,
            'action_equipment_id' => $equipmentId,
            'action_state'        => $this->request->input('action_state') !== '' ? (int) $this->request->input('action_state') : null,
            'notify_telegram'     => $this->request->input('notify_telegram') ? 1 : 0,
            'notify_email'        => $this->request->input('notify_email') ? 1 : 0,
            'is_active'           => 1,
            'created_by'          => Auth::id(),
        ];

        $validator = new Validator($data);
        $rules = ['name' => 'required|min:3|max:150', 'condition_source' => 'required|in:sensor,event,time'];
        if ($conditionSource === 'sensor') {
            $rules['condition_sensor_id'] = 'required|numeric';
            $rules['condition_operator']  = 'required|in:>,<,>=,<=,=,!=';
            $rules['condition_value']     = 'required|numeric';
        }
        $validator->rules($rules);

        if ($validator->fails()) {
            Response::error($validator->firstError(), 422, ['errors' => $validator->errors()]);
            return;
        }

        $id = AutomationRule::create($data);
        ActivityLog::record(Auth::id(), 'creation_regle', "Création de la règle d’automatisation « {$data['name']} »", $this->request->ip(), $houseId);

        Response::success('Règle d’automatisation créée avec succès.', ['id' => $id]);
    }

    public function toggle(int $id): void
    {
        $houseId = Auth::requireHouseRole(['admin', 'owner', 'technician']);
        $this->verifyCsrf();

        if (!AutomationRule::belongsToHouse($id, $houseId)) {
            Response::error('Règle introuvable.', 404);
            return;
        }
        $rule = AutomationRule::find($id);

        $newStatus = $rule['is_active'] ? 0 : 1;
        AutomationRule::update($id, ['is_active' => $newStatus]);
        ActivityLog::record(Auth::id(), 'bascule_regle', "Règle « {$rule['name']} » " . ($newStatus ? 'activée' : 'désactivée'), $this->request->ip(), $houseId);

        Response::success('État de la règle mis à jour.', ['is_active' => $newStatus]);
    }

    public function destroy(int $id): void
    {
        $houseId = Auth::requireHouseRole(['admin', 'owner']);
        $this->verifyCsrf();

        if (!AutomationRule::belongsToHouse($id, $houseId)) {
            Response::error('Règle introuvable.', 404);
            return;
        }
        $rule = AutomationRule::find($id);

        AutomationRule::delete($id);
        ActivityLog::record(Auth::id(), 'suppression_regle', "Suppression de la règle « {$rule['name']} »", $this->request->ip(), $houseId);

        Response::success('Règle supprimée avec succès.');
    }
}
