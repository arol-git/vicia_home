<?php
/**
 * app/views/settings/index.php
 *
 * Paramètres généraux de la plateforme : identité du site, thème par
 * défaut, notifications Telegram, e-mail et navigateur.
 */
use App\Core\Auth;

$pageScripts = ['settings.js'];
$isAdmin = Auth::role() === 'admin';
?>

<div class="page-header">
    <div>
        <div class="page-header__title">Paramètres</div>
        <div class="page-header__subtitle">Configuration générale de la plateforme Vicia Home</div>
    </div>
</div>

<?php if ($isAdmin): ?>
<form id="settings-form">
    <div class="grid grid-cols-2">
        <div class="card">
            <div class="card__header"><div class="card__title">Identité de la plateforme</div></div>
            <div class="form-group">
                <label class="form-label">Nom du site</label>
                <input type="text" name="site_name" class="form-control" value="<?= e($settings['site_name'] ?? 'Vicia Home') ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Thème par défaut</label>
                <select name="theme_mode" class="form-control">
                    <option value="light" <?= ($settings['theme_mode'] ?? '') === 'light' ? 'selected' : '' ?>>Clair</option>
                    <option value="dark" <?= ($settings['theme_mode'] ?? '') === 'dark' ? 'selected' : '' ?>>Sombre</option>
                </select>
            </div>
        </div>

        <div class="card">
            <div class="card__header"><div class="card__title">Notifications Telegram</div></div>
            <div class="form-group">
                <label class="form-label">Jeton du bot (Bot Token)</label>
                <input type="text" name="telegram_bot_token" class="form-control" value="<?= e($settings['telegram_bot_token'] ?? '') ?>" placeholder="123456789:ABC-...">
            </div>
            <div class="form-hint">Les utilisateurs renseignent leur nom Telegram depuis leur profil.</div>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card__header"><div class="card__title">Notifications e-mail</div></div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Serveur SMTP</label>
                <input type="text" name="smtp_host" class="form-control" value="<?= e($settings['smtp_host'] ?? '') ?>" placeholder="smtp.example.com">
            </div>
            <div class="form-group">
                <label class="form-label">Port SMTP</label>
                <input type="number" name="smtp_port" class="form-control" value="<?= e($settings['smtp_port'] ?? '587') ?>" placeholder="587">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Sécurité</label>
                <select name="smtp_encryption" class="form-control">
                    <option value="tls" <?= ($settings['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>TLS</option>
                    <option value="ssl" <?= ($settings['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                    <option value="" <?= ($settings['smtp_encryption'] ?? '') === '' ? 'selected' : '' ?>>Aucune</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Nom d'expéditeur</label>
                <input type="text" name="smtp_from_name" class="form-control" value="<?= e($settings['smtp_from_name'] ?? 'Vicia Home') ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Utilisateur SMTP</label>
                <input type="text" name="smtp_username" class="form-control" value="<?= e($settings['smtp_username'] ?? '') ?>" placeholder="compte@example.com">
            </div>
            <div class="form-group">
                <label class="form-label">Mot de passe SMTP</label>
                <input type="password" name="smtp_password" class="form-control" value="<?= e($settings['smtp_password'] ?? '') ?>" autocomplete="new-password">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Adresse d'expédition</label>
                <input type="email" name="smtp_from" class="form-control" value="<?= e($settings['smtp_from'] ?? '') ?>" placeholder="no-reply@example.com">
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-primary mt-4"><i class="fa-solid fa-floppy-disk"></i> Enregistrer les paramètres</button>
</form>
<?php endif; ?>

<div class="card mt-4">
    <div class="card__header"><div class="card__title"><i class="fa-solid fa-bell"></i> Notifications de la maison</div></div>
    <p class="text-muted">Recevez les alertes importantes de votre maison, même lorsque Vicia Home n’est pas ouvert.</p>
    <div class="flex items-center flex-gap-3 mt-4">
        <span class="badge badge-neutral" data-push-status>Notifications désactivées</span>
        <button type="button" class="btn btn-primary" data-push-enable><i class="fa-solid fa-bell"></i> Activer les notifications</button>
        <button type="button" class="btn btn-secondary" data-push-disable hidden><i class="fa-solid fa-bell-slash"></i> Désactiver</button>
    </div>
</div>

<div class="card mt-4">
    <div class="card__header"><div class="card__title"><i class="fa-solid fa-mobile-screen-button"></i> Application Vicia Home</div></div>
    <p class="text-muted">Installez Vicia Home parmi les applications de votre appareil pour l’ouvrir sans barre d’adresse.</p>
    <button type="button" id="pwa-install-button" class="btn btn-secondary mt-4"><i class="fa-solid fa-download"></i> Installer Vicia Home</button>
</div>

<script>
document.getElementById('settings-form')?.addEventListener('submit', (e) => {
    e.preventDefault();
    ViciaAjax.post('/settings', new FormData(e.target))
        .then((res) => ViciaApp.toast(res.message, 'success'))
        .catch((err) => ViciaApp.toast(err.message || 'Erreur lors de l’enregistrement.', 'error'));
});
</script>
