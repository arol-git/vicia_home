<?php
/**
 * app/views/houses/members.php
 *
 * Gestion des membres d'une maison (propriétaire, résidents,
 * techniciens). Accessible au propriétaire de la maison ou à un
 * administrateur de plateforme (voir HouseController::requireOwnerOrAdmin).
 */
$pageScripts = ['houses.js'];
$roleLabels = ['owner' => 'Propriétaire', 'resident' => 'Résident', 'technician' => 'Technicien'];
?>

<div class="page-header">
    <div>
        <div class="page-header__title">Membres — <?= e($house['name']) ?></div>
        <div class="page-header__subtitle">Gérez qui a accès à cette maison et avec quel rôle</div>
    </div>
    <button type="button" class="btn btn-primary" data-open-modal="modal-add-member">
        <i class="fa-solid fa-user-plus"></i> Ajouter un membre
    </button>
</div>

<div class="table-wrapper">
    <table class="data-table">
        <thead><tr><th>Nom</th><th>E-mail</th><th>Rôle sur cette maison</th><th>Membre depuis</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($members as $member): ?>
            <tr>
                <td>
                    <div class="flex items-center flex-gap-3">
                        <div class="avatar-circle"><?= e(strtoupper(substr($member['name'], 0, 1))) ?></div>
                        <strong><?= e($member['name']) ?></strong>
                    </div>
                </td>
                <td class="text-sm text-muted"><?= e($member['email']) ?></td>
                <td><span class="badge badge-info"><?= e($roleLabels[$member['role_in_house']] ?? $member['role_in_house']) ?></span></td>
                <td class="text-xs text-muted"><?= e(format_date($member['joined_at'])) ?></td>
                <td>
                    <button type="button" class="btn btn-icon btn-secondary" title="Retirer"
                            data-remove-member data-house-id="<?= (int) $house['id'] ?>" data-user-id="<?= (int) $member['id'] ?>" data-name="<?= e($member['name']) ?>">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="modal-overlay" id="modal-add-member">
    <div class="modal">
        <div class="modal__header">
            <div class="modal__title">Ajouter un membre</div>
            <button type="button" class="modal__close" data-close-modal><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form id="member-add-form" data-house-id="<?= (int) $house['id'] ?>">
            <div class="modal__body">
                <div class="form-group">
                    <label class="form-label">Adresse e-mail du compte existant</label>
                    <input type="email" name="email" class="form-control" placeholder="utilisateur@exemple.com" required>
                    <div class="form-hint">La personne doit déjà posséder un compte sur Vicia Home.</div>
                </div>
                <div class="form-group">
                    <label class="form-label">Rôle sur cette maison</label>
                    <select name="role_in_house" class="form-control">
                        <option value="resident">Résident</option>
                        <option value="technician">Technicien</option>
                        <option value="owner">Propriétaire</option>
                    </select>
                </div>
            </div>
            <div class="modal__footer">
                <button type="button" class="btn btn-secondary" data-close-modal>Annuler</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Ajouter</button>
            </div>
        </form>
    </div>
</div>
