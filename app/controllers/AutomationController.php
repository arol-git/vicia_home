<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Response;
use App\Core\Validator;
use App\Models\ActivityLog;
use App\Models\AutomationRule;
use App\Models\Equipment;
use App\Models\Sensor;

/**
 * Class AutomationController
 *
 * Gère le moteur de règles d'automatisation : permet à un
 * administrateur ou technicien de créer des règles du type
 * « SI <condition> ALORS <action> » sans modifier le code source.
 *
 * Deux types de conditions sont pris en charge :
 *   - "sensor" : comparaison de la dernière valeur d'un capteur à un seuil
 *   - "event"  : déclenchement sur un événement système (ex. intrusion)
 *
 * L'exécution effective des règles est assurée par le démon
 * mqtt/subscriber.php, qui interroge ce même modèle AutomationRule
 * à chaque réception de message MQTT.
 */
class AutomationController extends Controller
{
    public function index(): void
    {
        Auth::requireLogin();

        $rules      = AutomationRule::allWithLabels();
        $sensors    = Sensor::allWithRoom();
        $equipments = Equipment::allWithRoom();
        $logs       = AutomationRule::recentLogs(20);

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
        Auth::requireRole(['admin', 'technicien']);
        $this->verifyCsrf();

        $conditionSource = (string) $this->request->input('condition_source', 'sensor');

        $data = [
            'name'                => trim((string) $this->request->input('name')),
            'condition_source'    => $conditionSource,
            'condition_sensor_id' => $conditionSource === 'sensor' ? (int) $this->request->input('condition_sensor_id') : null,
            'condition_operator'  => $conditionSource === 'sensor' ? (string) $this->request->input('condition_operator') : null,
            'condition_value'     => $conditionSource === 'sensor' ? (string) $this->request->input('condition_value') : null,
            'condition_event'     => $conditionSource === 'event' ? (string) $this->request->input('condition_event') : null,
            'action_equipment_id' => $this->request->input('action_equipment_id') ?: null,
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
        ActivityLog::record(Auth::id(), 'creation_regle', "Création de la règle d’automatisation « {$data['name']} »", $this->request->ip());

        Response::success('Règle d’automatisation créée avec succès.', ['id' => $id]);
    }

    public function toggle(int $id): void
    {
        Auth::requireRole(['admin', 'technicien']);
        $this->verifyCsrf();

        $rule = AutomationRule::find($id);
        if (!$rule) {
            Response::error('Règle introuvable.', 404);
            return;
        }

        $newStatus = $rule['is_active'] ? 0 : 1;
        AutomationRule::update($id, ['is_active' => $newStatus]);
        ActivityLog::record(Auth::id(), 'bascule_regle', "Règle « {$rule['name']} » " . ($newStatus ? 'activée' : 'désactivée'), $this->request->ip());

        Response::success('État de la règle mis à jour.', ['is_active' => $newStatus]);
    }

    public function destroy(int $id): void
    {
        Auth::requireRole(['admin']);
        $this->verifyCsrf();

        $rule = AutomationRule::find($id);
        if (!$rule) {
            Response::error('Règle introuvable.', 404);
            return;
        }

        AutomationRule::delete($id);
        ActivityLog::record(Auth::id(), 'suppression_regle', "Suppression de la règle « {$rule['name']} »", $this->request->ip());

        Response::success('Règle supprimée avec succès.');
    }
}
