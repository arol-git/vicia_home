<?php
/**
 * app/views/profile/index.php
 *
 * Profil de l'utilisateur connecté : informations personnelles et
 * changement de mot de passe.
 */
$pageScripts = [];
$notificationSettings = $notificationSettings ?? [];
?>

<div class="page-header">
    <div>
        <div class="page-header__title">Mon profil</div>
        <div class="page-header__subtitle">Gérez vos informations personnelles et votre sécurité</div>
    </div>
</div>

<div class="grid grid-cols-2 mb-4">
    <div class="card">
        <div class="card__header"><div class="card__title">Informations personnelles</div></div>
        <form id="profile-form">
            <div class="form-group">
                <label class="form-label">Nom complet</label>
                <input type="text" name="name" class="form-control" value="<?= e($user['name']) ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label">Adresse e-mail</label>
                <input type="email" class="form-control" value="<?= e($user['email']) ?>" disabled>
                <div class="form-hint">L'adresse e-mail ne peut pas être modifiée depuis cette page.</div>
            </div>
            <div class="form-group">
                <label class="form-label">Téléphone</label>
                <input type="text" name="phone" class="form-control" value="<?= e($user['phone'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Rôle</label>
                <input type="text" class="form-control" value="<?= e(role_label($user['role'])) ?>" disabled>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Mettre à jour</button>
        </form>
    </div>

    <div class="card">
        <div class="card__header"><div class="card__title">Changer de mot de passe</div></div>
        <form id="password-form">
            <div class="form-group">
                <label class="form-label">Mot de passe actuel</label>
                <input type="password" name="current_password" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">Nouveau mot de passe</label>
                <input type="password" name="password" class="form-control" minlength="8" required>
            </div>
            <div class="form-group">
                <label class="form-label">Confirmation</label>
                <input type="password" name="password_confirmation" class="form-control" minlength="8" required>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-lock"></i> Changer le mot de passe</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card__header">
        <div>
            <div class="card__title">Notifications</div>
            <div class="card__subtitle">Choisissez où recevoir vos alertes personnelles.</div>
        </div>
    </div>
    <form id="notifications-form">
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">E-mail de réception</label>
                <input type="email" name="notification_email" class="form-control" value="<?= e($notificationSettings['notification_email'] ?? $user['email']) ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label">Identifiant Telegram</label>
                <input type="text" name="telegram_chat_id" class="form-control" value="<?= e($notificationSettings['telegram_chat_id'] ?? '') ?>" placeholder="Ex. 123456789">
                <div class="form-hint">Le bot Telegram global doit être configuré par l'administrateur.</div>
            </div>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-bell"></i> Enregistrer les notifications</button>
    </form>
</div>

<script>
document.getElementById('profile-form').addEventListener('submit', (e) => {
    e.preventDefault();
    ViciaAjax.post('<?= url('/profile') ?>', new FormData(e.target))
        .then((res) => ViciaApp.toast(res.message, 'success'))
        .catch((err) => ViciaApp.toast(err.message || 'Erreur lors de la mise à jour.', 'error'));
});

document.getElementById('password-form').addEventListener('submit', (e) => {
    e.preventDefault();
    const form = e.target;
    if (form.password.value !== form.password_confirmation.value) {
        ViciaApp.toast('La confirmation ne correspond pas au nouveau mot de passe.', 'error');
        return;
    }
    ViciaAjax.post('<?= url('/profile/password') ?>', new FormData(form))
        .then((res) => { ViciaApp.toast(res.message, 'success'); form.reset(); })
        .catch((err) => ViciaApp.toast(err.message || 'Erreur lors du changement de mot de passe.', 'error'));
});

document.getElementById('notifications-form').addEventListener('submit', (e) => {
    e.preventDefault();
    ViciaAjax.post('<?= url('/profile/notifications') ?>', new FormData(e.target))
        .then((res) => ViciaApp.toast(res.message, 'success'))
        .catch((err) => ViciaApp.toast(err.message || 'Erreur lors de la mise à jour des notifications.', 'error'));
});
</script>
