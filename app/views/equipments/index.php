<?php
/**
 * app/views/equipments/index.php
 *
 * Gestion des équipements (actionneurs) : listage tabulaire avec
 * interrupteur d'état en direct, création et suppression.
 */
use App\Core\Auth;

$pageScripts = ['equipments.js'];
$house = $house ?? [];

$equipmentTypes = [
    'led' => 'LED', 'relais' => 'Relais', 'ventilateur' => 'Ventilateur', 'pompe' => 'Pompe',
    'servo' => 'Servo-moteur', 'porte' => 'Porte', 'fenetre' => 'Fenêtre', 'sirene' => 'Sirène',
];
$houseRole = Auth::roleOnHouse(Auth::currentHouseId() ?? 0);
// Les opérations d'inventaire matériel sont réservées aux admins.
// Un résident ou technicien peut consulter/piloter si son rôle l'autorise,
// mais il ne doit pas pouvoir créer, supprimer ou voir les topics MQTT.
$canManage = can_manage_hardware_inventory($houseRole);
$canDelete = can_manage_hardware_inventory($houseRole);
$canSeeMqttTopics = $canSeeMqttTopics ?? can_view_mqtt_topics($houseRole);
$columnsCount = $canSeeMqttTopics ? 7 : 6;
?>

<div class="page-header" data-current-house-slug="<?= e($house['slug'] ?? '') ?>">
    <div>
        <div class="page-header__title">Équipements</div>
        <div class="page-header__subtitle"><?= count($equipments) ?> équipement(s) — <?= count(array_filter($equipments, fn($e) => $e['state'])) ?> actif(s)</div>
    </div>
    <?php if ($canManage): ?>
        <button type="button" class="btn btn-primary" data-open-modal="modal-add-equipment">
            <i class="fa-solid fa-plus"></i> Ajouter un équipement
        </button>
    <?php endif; ?>
</div>

<div class="table-wrapper">
    <table class="data-table">
        <thead>
            <tr>
                <th>Équipement</th>
                <th>Type</th>
                <th>Pièce</th>
                <?php if ($canSeeMqttTopics): ?>
                <th>Topic MQTT</th>
                <?php endif; ?>
                <th>Dernier changement</th>
                <th>État</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($equipments)): ?>
            <tr><td colspan="<?= (int) $columnsCount ?>"><div class="empty-state"><i class="fa-solid fa-plug-circle-bolt"></i><p>Aucun équipement enregistré.</p></div></td></tr>
        <?php endif; ?>
        <?php foreach ($equipments as $eq): ?>
            <tr>
                <td data-label="Équipement">
                    <div class="flex items-center flex-gap-3">
                        <div class="room-mini-card__icon" style="width:36px;height:36px;font-size:0.95rem;">
                            <i class="fa-solid <?= e($eq['icon']) ?>"></i>
                        </div>
                        <strong><?= e($eq['name']) ?></strong>
                    </div>
                </td>
                <td data-label="Type"><span class="badge badge-neutral"><?= e($equipmentTypes[$eq['type']] ?? $eq['type']) ?></span></td>
                <td data-label="Pièce"><?= e($eq['room_name']) ?></td>
                <?php if ($canSeeMqttTopics): ?>
                <td data-label="Adresse technique" class="text-xs text-muted"><?= e($eq['mqtt_topic']) ?></td>
                <?php endif; ?>
                <td data-label="Dernier changement" class="text-xs text-muted"><?= e(time_ago($eq['last_state_change'])) ?></td>
                <td data-label="État">
                    <label class="switch">
                        <input type="checkbox" data-toggle-equipment data-id="<?= (int) $eq['id'] ?>" <?= $eq['state'] ? 'checked' : '' ?> <?= $eq['is_active'] ? '' : 'disabled' ?>>
                        <span class="switch__track"></span>
                    </label>
                </td>
                <td data-label="Actions">
                    <?php if ($canDelete): ?>
                    <div class="table-actions">
                        <button type="button" class="btn btn-icon btn-secondary" title="Supprimer"
                                data-delete-equipment data-id="<?= (int) $eq['id'] ?>" data-name="<?= e($eq['name']) ?>">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($canManage): ?>
<div class="modal-overlay" id="modal-add-equipment">
    <div class="modal">
        <div class="modal__header">
            <div class="modal__title">Ajouter un équipement</div>
            <button type="button" class="modal__close" data-close-modal><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form id="equipment-create-form" data-house-slug="<?= e($house['slug'] ?? '') ?>">
            <div class="modal__body">
                <div class="form-group">
                    <label class="form-label">Nom de l'équipement</label>
                    <input type="text" name="name" class="form-control" placeholder="Ex. Éclairage terrasse" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Type</label>
                        <select name="type" class="form-control">
                            <?php foreach ($equipmentTypes as $key => $label): ?>
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
                <div class="form-group">
                    <label class="form-label">Topic MQTT</label>
                    <input type="text" name="mqtt_topic" class="form-control" placeholder="home/<?= e($house['slug'] ?? 'maison') ?>/lighting/salon/led1" required>
                    <div class="form-hint">Suggéré automatiquement à partir du type et de la pièce ; modifiable.</div>
                </div>
            </div>
            <div class="modal__footer">
                <button type="button" class="btn btn-secondary" data-close-modal>Annuler</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Créer l'équipement</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
