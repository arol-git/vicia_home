<?php
/**
 * app/views/rooms/index.php
 *
 * Gestion des pièces de l'habitation : listage sous forme de cartes,
 * création, modification et suppression via modales AJAX.
 */
$pageScripts = ['rooms.js'];

$roomTypes = [
    'salon' => 'Salon', 'cuisine' => 'Cuisine', 'chambre' => 'Chambre', 'garage' => 'Garage',
    'bureau' => 'Bureau', 'salle_de_bain' => 'Salle de bain', 'jardin' => 'Jardin',
    'terrasse' => 'Terrasse', 'autre' => 'Autre',
];
$roomIcons = [
    'salon' => 'fa-couch', 'cuisine' => 'fa-utensils', 'chambre' => 'fa-bed', 'garage' => 'fa-warehouse',
    'bureau' => 'fa-briefcase', 'salle_de_bain' => 'fa-bath', 'jardin' => 'fa-leaf',
    'terrasse' => 'fa-umbrella-beach', 'autre' => 'fa-door-open',
];
$canManage = in_array($currentUser['role'], ['admin', 'technicien'], true);
?>

<div class="page-header">
    <div>
        <div class="page-header__title">Pièces</div>
        <div class="page-header__subtitle"><?= count($rooms) ?> pièce(s) enregistrée(s) dans votre habitation</div>
    </div>
    <?php if ($canManage): ?>
        <button type="button" class="btn btn-primary" data-open-modal="modal-add-room">
            <i class="fa-solid fa-plus"></i> Ajouter une pièce
        </button>
    <?php endif; ?>
</div>

<div class="grid grid-auto">
    <?php foreach ($rooms as $room): ?>
        <div class="card room-card">
            <div class="flex-between mb-4">
                <div class="room-mini-card__icon"><i class="fa-solid <?= e($room['icon']) ?>"></i></div>
                <?php if ($canManage): ?>
                <div class="table-actions">
                    <button type="button" class="btn btn-icon btn-secondary" title="Modifier"
                            data-edit-room
                            data-id="<?= (int) $room['id'] ?>"
                            data-name="<?= e($room['name']) ?>"
                            data-type="<?= e($room['type']) ?>"
                            data-floor="<?= e($room['floor']) ?>"
                            data-description="<?= e($room['description']) ?>">
                        <i class="fa-solid fa-pen"></i>
                    </button>
                    <?php if ($currentUser['role'] === 'admin'): ?>
                    <button type="button" class="btn btn-icon btn-secondary" title="Supprimer"
                            data-delete-room data-id="<?= (int) $room['id'] ?>" data-name="<?= e($room['name']) ?>">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="card__title"><?= e($room['name']) ?></div>
            <div class="card__subtitle mb-4"><?= e($roomTypes[$room['type']] ?? 'Autre') ?><?= $room['floor'] ? ' · ' . e($room['floor']) : '' ?></div>
            <div class="flex flex-gap-3">
                <span class="badge badge-info"><i class="fa-solid fa-plug-circle-bolt"></i> <?= (int) $room['equipments_count'] ?> équipements</span>
                <span class="badge badge-neutral"><i class="fa-solid fa-microchip"></i> <?= (int) $room['sensors_count'] ?> capteurs</span>
            </div>
            <?php if ($room['description']): ?>
                <p class="text-sm text-muted mt-4"><?= e($room['description']) ?></p>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<!-- Modale d'ajout -->
<div class="modal-overlay" id="modal-add-room">
    <div class="modal">
        <div class="modal__header">
            <div class="modal__title">Ajouter une pièce</div>
            <button type="button" class="modal__close" data-close-modal><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form id="room-create-form">
            <div class="modal__body">
                <div class="form-group">
                    <label class="form-label">Nom de la pièce</label>
                    <input type="text" name="name" class="form-control" placeholder="Ex. Chambre parentale" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Type</label>
                        <select name="type" class="form-control">
                            <?php foreach ($roomTypes as $key => $label): ?>
                                <option value="<?= $key ?>" data-icon="<?= $roomIcons[$key] ?>"><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Étage / emplacement</label>
                        <input type="text" name="floor" class="form-control" placeholder="Ex. Rez-de-chaussée">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Description (optionnelle)</label>
                    <textarea name="description" class="form-control" placeholder="Description libre de la pièce"></textarea>
                </div>
                <input type="hidden" name="icon" value="fa-door-open">
            </div>
            <div class="modal__footer">
                <button type="button" class="btn btn-secondary" data-close-modal>Annuler</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Créer la pièce</button>
            </div>
        </form>
    </div>
</div>

<!-- Modale de modification -->
<div class="modal-overlay" id="modal-edit-room">
    <div class="modal">
        <div class="modal__header">
            <div class="modal__title">Modifier la pièce</div>
            <button type="button" class="modal__close" data-close-modal><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form id="room-edit-form">
            <div class="modal__body">
                <div class="form-group">
                    <label class="form-label">Nom de la pièce</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Type</label>
                        <select name="type" class="form-control">
                            <?php foreach ($roomTypes as $key => $label): ?>
                                <option value="<?= $key ?>"><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Étage / emplacement</label>
                        <input type="text" name="floor" class="form-control">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control"></textarea>
                </div>
            </div>
            <div class="modal__footer">
                <button type="button" class="btn btn-secondary" data-close-modal>Annuler</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Enregistrer</button>
            </div>
        </form>
    </div>
</div>
