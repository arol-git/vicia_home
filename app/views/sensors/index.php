<?php
/**
 * app/views/sensors/index.php
 *
 * Gestion des capteurs : listage avec dernière valeur mesurée,
 * consultation de l'historique (modale + Chart.js), création et
 * suppression.
 */
$pageScripts = ['sensors.js'];

$sensorTypes = [
    'pir' => 'PIR (mouvement)', 'dht22_temp' => 'DHT22 — Température', 'dht22_hum' => 'DHT22 — Humidité',
    'mq2' => 'MQ-2 (gaz/fumée)', 'mq135' => 'MQ-135 (qualité air)', 'ldr' => 'LDR (luminosité)',
    'rfid' => 'RFID', 'humidite_sol' => 'Humidité du sol',
];
$canManage = in_array($currentUser['role'], ['admin', 'technicien'], true);
?>

<div class="page-header">
    <div>
        <div class="page-header__title">Capteurs</div>
        <div class="page-header__subtitle"><?= count($sensors) ?> capteur(s) installé(s)</div>
    </div>
    <?php if ($canManage): ?>
        <button type="button" class="btn btn-primary" data-open-modal="modal-add-sensor">
            <i class="fa-solid fa-plus"></i> Ajouter un capteur
        </button>
    <?php endif; ?>
</div>

<div class="table-wrapper">
    <table class="data-table">
        <thead>
            <tr>
                <th>Capteur</th>
                <th>Type</th>
                <th>Pièce</th>
                <th>Dernière valeur</th>
                <th>Relevé</th>
                <th>Seuil d'alerte</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($sensors)): ?>
            <tr><td colspan="7"><div class="empty-state"><i class="fa-solid fa-microchip"></i><p>Aucun capteur enregistré.</p></div></td></tr>
        <?php endif; ?>
        <?php foreach ($sensors as $sensor): ?>
            <tr>
                <td>
                    <div class="flex items-center flex-gap-3">
                        <div class="room-mini-card__icon" style="width:36px;height:36px;font-size:0.95rem;">
                            <i class="fa-solid <?= e($sensor['icon']) ?>"></i>
                        </div>
                        <strong><?= e($sensor['name']) ?></strong>
                    </div>
                </td>
                <td><span class="badge badge-neutral"><?= e($sensorTypes[$sensor['type']] ?? $sensor['type']) ?></span></td>
                <td><?= e($sensor['room_name']) ?></td>
                <td><strong><?= $sensor['last_value'] !== null ? e($sensor['last_value']) . ' ' . e($sensor['unit']) : '—' ?></strong></td>
                <td class="text-xs text-muted"><?= e(time_ago($sensor['last_recorded_at'])) ?></td>
                <td class="text-xs text-muted"><?= $sensor['alert_threshold'] !== null ? e($sensor['alert_threshold']) . ' ' . e($sensor['unit']) : '—' ?></td>
                <td>
                    <div class="table-actions">
                        <button type="button" class="btn btn-icon btn-secondary" title="Historique"
                                data-view-history data-id="<?= (int) $sensor['id'] ?>" data-name="<?= e($sensor['name']) ?>">
                            <i class="fa-solid fa-chart-line"></i>
                        </button>
                        <?php if ($currentUser['role'] === 'admin'): ?>
                        <button type="button" class="btn btn-icon btn-secondary" title="Supprimer"
                                data-delete-sensor data-id="<?= (int) $sensor['id'] ?>" data-name="<?= e($sensor['name']) ?>">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="modal-overlay" id="modal-add-sensor">
    <div class="modal">
        <div class="modal__header">
            <div class="modal__title">Ajouter un capteur</div>
            <button type="button" class="modal__close" data-close-modal><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form id="sensor-create-form">
            <div class="modal__body">
                <div class="form-group">
                    <label class="form-label">Nom du capteur</label>
                    <input type="text" name="name" class="form-control" placeholder="Ex. Température bureau" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Type</label>
                        <select name="type" class="form-control">
                            <?php foreach ($sensorTypes as $key => $label): ?>
                                <option value="<?= $key ?>"><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Pièce</label>
                        <select name="room_id" class="form-control">
                            <?php foreach ($rooms as $room): ?>
                                <option value="<?= (int) $room['id'] ?>"><?= e($room['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Unité de mesure</label>
                        <input type="text" name="unit" class="form-control" placeholder="°C, %, ppm, lux...">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Seuil d'alerte (optionnel)</label>
                        <input type="number" step="0.01" name="alert_threshold" class="form-control" placeholder="Ex. 400">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Topic MQTT</label>
                    <input type="text" name="mqtt_topic" class="form-control" placeholder="home/climate/bureau/temp" required>
                </div>
            </div>
            <div class="modal__footer">
                <button type="button" class="btn btn-secondary" data-close-modal>Annuler</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Créer le capteur</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="modal-sensor-history">
    <div class="modal" style="max-width: 680px;">
        <div class="modal__header">
            <div class="modal__title" id="history-modal-title">Historique du capteur</div>
            <button type="button" class="modal__close" data-close-modal><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal__body">
            <div class="chart-card__canvas-wrap" style="height:300px;">
                <canvas id="sensor-history-chart"></canvas>
            </div>
            <div class="empty-state" id="history-empty" style="display:none;">
                <i class="fa-solid fa-chart-line"></i><p>Aucune mesure disponible sur les dernières 24 heures.</p>
            </div>
        </div>
    </div>
</div>
