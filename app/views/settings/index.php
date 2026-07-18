<?php
/**
 * app/views/settings/index.php
 *
 * Paramètres généraux de la plateforme : identité du site, thème par
 * défaut, notifications Telegram et e-mail.
 */
$pageScripts = [];
?>

<div class="page-header">
    <div>
        <div class="page-header__title">Paramètres</div>
        <div class="page-header__subtitle">Configuration générale de la plateforme Vicia Home</div>
    </div>
</div>

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
                <label class="form-label">Adresse d'expédition</label>
                <input type="email" name="smtp_from" class="form-control" value="<?= e($settings['smtp_from'] ?? '') ?>">
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-primary mt-4"><i class="fa-solid fa-floppy-disk"></i> Enregistrer les paramètres</button>
</form>

<script>
document.getElementById('settings-form').addEventListener('submit', (e) => {
    e.preventDefault();
    ViciaAjax.post('/settings', new FormData(e.target))
        .then((res) => ViciaApp.toast(res.message, 'success'))
        .catch((err) => ViciaApp.toast(err.message || 'Erreur lors de l’enregistrement.', 'error'));
});
</script>
