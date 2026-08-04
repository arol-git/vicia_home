<?php
/**
 * app/views/houses/index.php
 *
 * Liste des maisons accessibles à l'utilisateur connecté (toutes les
 * maisons pour un administrateur de plateforme). Permet la création
 * d'une nouvelle maison (l'utilisateur en devient propriétaire) et
 * l'accès à la gestion des membres pour les maisons dont il est
 * propriétaire.
 */
$pageScripts = ['houses.js'];
?>

<div class="page-header">
    <div>
        <div class="page-header__title">Mes maisons</div>
        <div class="page-header__subtitle"><?= count($houses) ?> maison(s) accessible(s) à votre compte</div>
    </div>
    <button type="button" class="btn btn-primary" data-open-modal="modal-add-house">
        <i class="fa-solid fa-plus"></i> Ajouter une maison
    </button>
</div>

<?php if (empty($houses)): ?>
    <div class="card">
        <div class="empty-state">
            <i class="fa-solid fa-house-circle-check"></i>
            <p>Vous n'êtes rattaché à aucune maison pour le moment.<br>Créez votre première maison pour commencer à la piloter.</p>
        </div>
    </div>
<?php else: ?>
<div class="grid grid-auto">
    <?php foreach ($houses as $house): ?>
        <div class="card">
            <div class="flex-between mb-4">
                <div class="room-mini-card__icon"><i class="fa-solid fa-house-signal"></i></div>
                <span class="badge badge-info"><?= e(role_label($house['role_in_house'] === 'admin' ? 'admin' : 'user')) ?><?= $house['role_in_house'] !== 'admin' ? ' · ' . e(ucfirst($house['role_in_house'])) : '' ?></span>
            </div>
            <div class="card__title"><?= e($house['name']) ?></div>
            <div class="card__subtitle mb-4"><?= e($house['city'] ?? '—') ?><?= $house['address'] ? ' · ' . e($house['address']) : '' ?></div>
            <div class="flex flex-gap-3 mb-4">
                <span class="badge badge-neutral"><i class="fa-solid fa-door-open"></i> <?= (int) $house['rooms_count'] ?> pièces</span>
                <span class="badge badge-neutral"><i class="fa-solid fa-users"></i> <?= (int) $house['members_count'] ?> membre(s)</span>
            </div>
            <div class="flex flex-gap-2">
                <button type="button" class="btn btn-sm btn-primary" data-switch-house-card data-id="<?= (int) $house['id'] ?>">
                    <i class="fa-solid fa-right-left"></i> Sélectionner
                </button>
                <?php if (in_array($house['role_in_house'], ['owner', 'admin'], true)): ?>
                <a href="<?= url('/houses/' . (int) $house['id'] . '/members') ?>" class="btn btn-sm btn-secondary">
                    <i class="fa-solid fa-user-gear"></i> Membres
                </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="modal-overlay" id="modal-add-house">
    <div class="modal">
        <div class="modal__header">
            <div class="modal__title">Ajouter une maison</div>
            <button type="button" class="modal__close" data-close-modal><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form id="house-create-form">
            <div class="modal__body">
                <div class="form-group">
                    <label class="form-label">Nom de la maison</label>
                    <input type="text" name="name" class="form-control" placeholder="Ex. Villa Yaoundé" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Ville</label>
                        <input type="text" name="city" class="form-control" placeholder="Ex. Yaoundé">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Adresse</label>
                        <input type="text" name="address" class="form-control" placeholder="Quartier, rue...">
                    </div>
                </div>
                <div class="form-hint">Vous serez automatiquement défini comme propriétaire de cette maison.</div>
            </div>
            <div class="modal__footer">
                <button type="button" class="btn btn-secondary" data-close-modal>Annuler</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Créer la maison</button>
            </div>
        </form>
    </div>
</div>
