<?php
/**
 * app/views/users/index.php
 *
 * Gestion des comptes utilisateurs et de leurs rôles. Accessible aux
 * seuls administrateurs (contrôle appliqué dans UserController).
 */
$pageScripts = [];
?>

<div class="page-header">
    <div>
        <div class="page-header__title">Utilisateurs</div>
        <div class="page-header__subtitle"><?= count($users) ?> compte(s) enregistré(s)</div>
    </div>
    <button type="button" class="btn btn-primary" data-open-modal="modal-add-user">
        <i class="fa-solid fa-user-plus"></i> Ajouter un utilisateur
    </button>
</div>

<div class="table-wrapper">
    <table class="data-table">
        <thead>
            <tr><th>Nom</th><th>E-mail</th><th>Rôle</th><th>Statut</th><th>Dernière connexion</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($users as $user): ?>
            <tr>
                <td>
                    <div class="flex items-center flex-gap-3">
                        <div class="avatar-circle"><?= e(strtoupper(substr($user['name'], 0, 1))) ?></div>
                        <strong><?= e($user['name']) ?></strong>
                    </div>
                </td>
                <td class="text-sm text-muted"><?= e($user['email']) ?></td>
                <td><span class="badge badge-info"><?= e(role_label($user['role'])) ?></span></td>
                <td>
                    <?php if ($user['status'] === 'active'): ?>
                        <span class="badge badge-success">Actif</span>
                    <?php else: ?>
                        <span class="badge badge-critical">Suspendu</span>
                    <?php endif; ?>
                </td>
                <td class="text-xs text-muted"><?= e(time_ago($user['last_login_at'])) ?></td>
                <td>
                    <div class="table-actions">
                        <button type="button" class="btn btn-icon btn-secondary" title="Modifier"
                                data-edit-user
                                data-id="<?= (int) $user['id'] ?>"
                                data-name="<?= e($user['name']) ?>"
                                data-role="<?= e($user['role']) ?>"
                                data-status="<?= e($user['status']) ?>">
                            <i class="fa-solid fa-pen"></i>
                        </button>
                        <?php if ($user['id'] !== $currentUser['id']): ?>
                        <button type="button" class="btn btn-icon btn-secondary" title="Supprimer"
                                data-delete-user data-id="<?= (int) $user['id'] ?>" data-name="<?= e($user['name']) ?>">
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

<div class="modal-overlay" id="modal-add-user">
    <div class="modal">
        <div class="modal__header">
            <div class="modal__title">Ajouter un utilisateur</div>
            <button type="button" class="modal__close" data-close-modal><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form id="user-create-form">
            <div class="modal__body">
                <div class="form-group"><label class="form-label">Nom complet</label><input type="text" name="name" class="form-control" required></div>
                <div class="form-group"><label class="form-label">Adresse e-mail</label><input type="email" name="email" class="form-control" required></div>
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Mot de passe</label><input type="password" name="password" class="form-control" minlength="8" required></div>
                    <div class="form-group">
                        <label class="form-label">Rôle</label>
                        <select name="role" class="form-control">
                            <option value="user">Utilisateur</option>
                            <option value="technicien">Technicien</option>
                            <option value="admin">Administrateur</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal__footer">
                <button type="button" class="btn btn-secondary" data-close-modal>Annuler</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Créer le compte</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="modal-edit-user">
    <div class="modal">
        <div class="modal__header">
            <div class="modal__title">Modifier l'utilisateur</div>
            <button type="button" class="modal__close" data-close-modal><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form id="user-edit-form">
            <div class="modal__body">
                <div class="form-group"><label class="form-label">Nom complet</label><input type="text" name="name" class="form-control" required></div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Rôle</label>
                        <select name="role" class="form-control">
                            <option value="user">Utilisateur</option>
                            <option value="technicien">Technicien</option>
                            <option value="admin">Administrateur</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Statut</label>
                        <select name="status" class="form-control">
                            <option value="active">Actif</option>
                            <option value="suspended">Suspendu</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal__footer">
                <button type="button" class="btn btn-secondary" data-close-modal>Annuler</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('user-create-form')?.addEventListener('submit', (e) => {
        e.preventDefault();
        ViciaAjax.post('/users', new FormData(e.target))
            .then((res) => { ViciaApp.toast(res.message, 'success'); setTimeout(() => location.reload(), 600); })
            .catch((err) => ViciaApp.toast(err.message || 'Erreur lors de la création.', 'error'));
    });

    document.querySelectorAll('[data-edit-user]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const form = document.getElementById('user-edit-form');
            form.setAttribute('data-user-id', btn.dataset.id);
            form.querySelector('[name="name"]').value = btn.dataset.name;
            form.querySelector('[name="role"]').value = btn.dataset.role;
            form.querySelector('[name="status"]').value = btn.dataset.status;
            ViciaApp.openModal('modal-edit-user');
        });
    });

    document.getElementById('user-edit-form')?.addEventListener('submit', (e) => {
        e.preventDefault();
        const id = e.target.getAttribute('data-user-id');
        ViciaAjax.put(`/users/${id}`, new FormData(e.target))
            .then((res) => { ViciaApp.toast(res.message, 'success'); setTimeout(() => location.reload(), 600); })
            .catch((err) => ViciaApp.toast(err.message || 'Erreur lors de la mise à jour.', 'error'));
    });

    document.querySelectorAll('[data-delete-user]').forEach((btn) => {
        btn.addEventListener('click', () => {
            if (!confirm(`Supprimer le compte « ${btn.dataset.name} » ?`)) return;
            ViciaAjax.del(`/users/${btn.dataset.id}`)
                .then((res) => { ViciaApp.toast(res.message, 'success'); btn.closest('tr').remove(); })
                .catch((err) => ViciaApp.toast(err.message || 'Suppression impossible.', 'error'));
        });
    });
});
</script>
