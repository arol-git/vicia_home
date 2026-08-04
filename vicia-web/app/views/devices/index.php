<?php
/**
 * app/views/devices/index.php
 *
 * Gestion des cartes ESP32 appairées à la maison actuellement
 * sélectionnée. C'est cet appairage (identifiant matériel unique)
 * qui garantit qu'un équipement/capteur est piloté par la carte
 * physique réellement installée, et non par un appareil homonyme.
 */
$pageScripts = ['devices.js'];
$statusLabels = ['pending' => ['label' => 'En attente', 'class' => 'badge-warning'],
                  'paired'  => ['label' => 'Appairée', 'class' => 'badge-success'],
                  'revoked' => ['label' => 'Révoquée', 'class' => 'badge-critical']];
?>

<div class="page-header">
    <div>
        <div class="page-header__title">Appareils (ESP32)</div>
        <div class="page-header__subtitle"><?= count($devices) ?> carte(s) appairée(s) à cette maison</div>
    </div>
    <button type="button" class="btn btn-primary" data-open-modal="modal-add-device">
        <i class="fa-solid fa-plus"></i> Appairer une carte
    </button>
</div>

<div class="card mb-4">
    <div class="empty-state" style="padding: var(--space-4);">
        <p class="text-sm text-muted" style="max-width:600px;margin:0 auto;">
            Chaque équipement ou capteur doit être rattaché à une carte ESP32 appairée ici.
            C'est cet identifiant matériel (chip_id) — et non le simple nom d'un topic MQTT —
            qui garantit qu'une commande atteint la bonne carte physique.
        </p>
    </div>
</div>

<div class="table-wrapper">
    <table class="data-table">
        <thead><tr><th>Libellé</th><th>Identifiant matériel</th><th>Statut</th><th>Dernière activité</th><th></th></tr></thead>
        <tbody>
        <?php if (empty($devices)): ?>
            <tr><td colspan="5"><div class="empty-state"><i class="fa-solid fa-satellite-dish"></i><p>Aucune carte appairée pour le moment.</p></div></td></tr>
        <?php endif; ?>
        <?php foreach ($devices as $device): $status = $statusLabels[$device['status']]; ?>
            <tr>
                <td><strong><?= e($device['label']) ?></strong></td>
                <td><code><?= e($device['chip_id']) ?></code></td>
                <td><span class="badge <?= $status['class'] ?>"><?= $status['label'] ?></span></td>
                <td class="text-xs text-muted"><?= e(time_ago($device['last_seen'])) ?></td>
                <td>
                    <div class="table-actions">
                        <?php if ($device['status'] === 'paired'): ?>
                        <button type="button" class="btn btn-sm btn-secondary" data-revoke-device data-id="<?= (int) $device['id'] ?>">
                            <i class="fa-solid fa-ban"></i> Révoquer
                        </button>
                        <?php endif; ?>
                        <?php if ($currentUser['role'] === 'admin'): ?>
                        <button type="button" class="btn btn-icon btn-secondary" title="Supprimer"
                                data-delete-device data-id="<?= (int) $device['id'] ?>" data-label="<?= e($device['label']) ?>">
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

<div class="modal-overlay" id="modal-add-device">
    <div class="modal">
        <div class="modal__header">
            <div class="modal__title">Appairer une carte ESP32</div>
            <button type="button" class="modal__close" data-close-modal><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form id="device-create-form">
            <div class="modal__body">
                <div class="form-group">
                    <label class="form-label">Identifiant matériel (chip_id / adresse MAC)</label>
                    <input type="text" name="chip_id" class="form-control" placeholder="Ex. ESP32-A1B2C3D4E5F6" required>
                    <div class="form-hint">Visible sur l'étiquette de la carte ou via le firmware au premier démarrage.</div>
                </div>
                <div class="form-group">
                    <label class="form-label">Libellé</label>
                    <input type="text" name="label" class="form-control" placeholder="Ex. ESP32 Salon" required>
                </div>
            </div>
            <div class="modal__footer">
                <button type="button" class="btn btn-secondary" data-close-modal>Annuler</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Appairer</button>
            </div>
        </form>
    </div>
</div>
