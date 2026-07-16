<?php
/**
 * app/views/automation/index.php
 *
 * Interface du moteur de règles d'automatisation : « SI <condition>
 * ALORS <action> », configurable sans écrire de code.
 */
$pageScripts = ['automation.js'];
$canManage = in_array($currentUser['role'], ['admin', 'technicien'], true);

$operatorLabels = ['>' => 'supérieur à', '<' => 'inférieur à', '>=' => 'supérieur ou égal à', '<=' => 'inférieur ou égal à', '=' => 'égal à', '!=' => 'différent de'];
$eventLabels = ['intrusion' => 'Intrusion détectée', 'appareil_inconnu' => 'Appareil inconnu détecté'];
?>

<div class="page-header">
    <div>
        <div class="page-header__title">Automatisation</div>
        <div class="page-header__subtitle">Créez des règles « SI... ALORS... » sans écrire une seule ligne de code</div>
    </div>
    <?php if ($canManage): ?>
        <button type="button" class="btn btn-primary" data-open-modal="modal-add-rule">
            <i class="fa-solid fa-plus"></i> Nouvelle règle
        </button>
    <?php endif; ?>
</div>

<div class="card mb-4">
    <div class="card__header"><div class="card__title">Règles configurées</div></div>
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Règle</th>
                    <th>Condition</th>
                    <th>Action</th>
                    <th>Notifications</th>
                    <th>Active</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($rules)): ?>
                <tr><td colspan="6"><div class="empty-state"><i class="fa-solid fa-diagram-project"></i><p>Aucune règle d'automatisation définie.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($rules as $rule): ?>
                <tr>
                    <td><strong><?= e($rule['name']) ?></strong></td>
                    <td class="text-sm">
                        <?php if ($rule['condition_source'] === 'sensor'): ?>
                            SI <strong><?= e($rule['sensor_name'] ?? '—') ?></strong>
                            <?= e($operatorLabels[$rule['condition_operator']] ?? $rule['condition_operator']) ?>
                            <strong><?= e($rule['condition_value']) ?> <?= e($rule['sensor_unit']) ?></strong>
                        <?php else: ?>
                            SI <strong><?= e($eventLabels[$rule['condition_event']] ?? $rule['condition_event']) ?></strong>
                        <?php endif; ?>
                    </td>
                    <td class="text-sm">
                        <?= $rule['equipment_name'] ? 'Régler « ' . e($rule['equipment_name']) . ' » à ' . ($rule['action_state'] ? 'ON' : 'OFF') : '—' ?>
                    </td>
                    <td class="text-sm">
                        <?php if ($rule['notify_telegram']): ?><span class="badge badge-info"><i class="fa-brands fa-telegram"></i> Telegram</span><?php endif; ?>
                        <?php if ($rule['notify_email']): ?><span class="badge badge-neutral"><i class="fa-solid fa-envelope"></i> E-mail</span><?php endif; ?>
                        <?php if (!$rule['notify_telegram'] && !$rule['notify_email']): ?>—<?php endif; ?>
                    </td>
                    <td>
                        <label class="switch">
                            <input type="checkbox" data-toggle-rule data-id="<?= (int) $rule['id'] ?>" <?= $rule['is_active'] ? 'checked' : '' ?> <?= $canManage ? '' : 'disabled' ?>>
                            <span class="switch__track"></span>
                        </label>
                    </td>
                    <td>
                        <?php if ($currentUser['role'] === 'admin'): ?>
                        <button type="button" class="btn btn-icon btn-secondary" title="Supprimer"
                                data-delete-rule data-id="<?= (int) $rule['id'] ?>" data-name="<?= e($rule['name']) ?>">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card__header"><div class="card__title">Journal d'exécution des règles</div></div>
    <?php if (empty($logs)): ?>
        <div class="empty-state"><i class="fa-solid fa-list-check"></i><p>Aucune règle n'a encore été déclenchée.</p></div>
    <?php else: ?>
        <div class="activity-feed">
            <?php foreach ($logs as $log): ?>
                <div class="activity-item">
                    <div class="activity-item__icon"><i class="fa-solid fa-bolt"></i></div>
                    <div class="activity-item__body">
                        <div class="activity-item__title"><?= e($log['rule_name']) ?></div>
                        <div class="activity-item__meta"><?= e($log['result']) ?> · <?= e(time_ago($log['triggered_at'])) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="modal-overlay" id="modal-add-rule">
    <div class="modal">
        <div class="modal__header">
            <div class="modal__title">Nouvelle règle d'automatisation</div>
            <button type="button" class="modal__close" data-close-modal><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form id="automation-create-form">
            <div class="modal__body">
                <div class="form-group">
                    <label class="form-label">Nom de la règle</label>
                    <input type="text" name="name" class="form-control" placeholder="Ex. Alerte gaz cuisine" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Type de condition</label>
                    <select name="condition_source" id="condition-source-select" class="form-control">
                        <option value="sensor">Mesure d'un capteur</option>
                        <option value="event">Événement système</option>
                    </select>
                </div>

                <div id="condition-fields-sensor">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Capteur</label>
                            <select name="condition_sensor_id" class="form-control">
                                <?php foreach ($sensors as $s): ?>
                                    <option value="<?= (int) $s['id'] ?>"><?= e($s['name']) ?> (<?= e($s['room_name']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Opérateur</label>
                            <select name="condition_operator" class="form-control">
                                <?php foreach ($operatorLabels as $op => $label): ?>
                                    <option value="<?= $op ?>"><?= $op ?> (<?= $label ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Valeur seuil</label>
                            <input type="number" step="0.01" name="condition_value" class="form-control" placeholder="Ex. 30">
                        </div>
                    </div>
                </div>

                <div id="condition-fields-event" style="display:none;">
                    <div class="form-group">
                        <label class="form-label">Événement déclencheur</label>
                        <select name="condition_event" class="form-control">
                            <?php foreach ($eventLabels as $key => $label): ?>
                                <option value="<?= $key ?>"><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Équipement à commander (optionnel)</label>
                        <select name="action_equipment_id" class="form-control">
                            <option value="">Aucun</option>
                            <?php foreach ($equipments as $eq): ?>
                                <option value="<?= (int) $eq['id'] ?>"><?= e($eq['name']) ?> (<?= e($eq['room_name']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">État à appliquer</label>
                        <select name="action_state" class="form-control">
                            <option value="1">Activer (ON)</option>
                            <option value="0">Désactiver (OFF)</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Notifications</label>
                    <label class="checkbox-row mb-4"><input type="checkbox" name="notify_telegram" value="1"> Envoyer une notification Telegram</label>
                    <label class="checkbox-row"><input type="checkbox" name="notify_email" value="1"> Envoyer une notification par e-mail</label>
                </div>
            </div>
            <div class="modal__footer">
                <button type="button" class="btn btn-secondary" data-close-modal>Annuler</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Créer la règle</button>
            </div>
        </form>
    </div>
</div>
