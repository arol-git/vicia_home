<?php
/**
 * app/views/auth/reset-password.php
 *
 * Formulaire de saisie du nouveau mot de passe, accessible via le
 * lien reçu par e-mail (jeton + adresse e-mail en paramètres de
 * requête).
 */
use App\Core\Csrf;
use App\Core\Session;
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e(Csrf::token()) ?>">
    <title>Réinitialisation du mot de passe — Vicia Home</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= asset('css/variables.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/reset.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/components.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/auth.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/dark-mode.css') ?>">
</head>
<body>
<div class="auth-page">
    <div class="auth-page__visual">
        <div class="auth-brand">
            <div class="auth-brand-icon"><i class="fa-solid fa-house-signal"></i></div>
            Vicia Home
        </div>
        <div class="auth-page__pitch">
            <h1>Choisissez un nouveau mot de passe robuste.</h1>
            <p>Nous vous recommandons au moins 8 caractères, mêlant lettres, chiffres et symboles.</p>
        </div>
        <div></div>
    </div>

    <div class="auth-page__form-side">
        <div class="auth-card">
            <h2>Nouveau mot de passe</h2>
            <p class="subtitle">Ce lien n'est valable qu'une seule fois et expire après une heure.</p>

            <?php if ($error = Session::getFlash('error')): ?>
                <div class="alert-banner is-error"><i class="fa-solid fa-circle-exclamation"></i> <?= e($error) ?></div>
            <?php endif; ?>

            <form id="reset-password-form" method="POST" action="<?= url('/reset-password') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <input type="hidden" name="email" value="<?= e($email) ?>">

                <div class="form-group">
                    <label class="form-label" for="password">Nouveau mot de passe</label>
                    <div class="auth-input-icon">
                        <i class="fa-solid fa-lock"></i>
                        <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required minlength="8">
                        <i class="fa-solid fa-eye toggle-visibility"></i>
                    </div>
                    <div class="form-error"></div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password_confirmation">Confirmation</label>
                    <div class="auth-input-icon">
                        <i class="fa-solid fa-lock"></i>
                        <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" placeholder="••••••••" required minlength="8">
                        <i class="fa-solid fa-eye toggle-visibility"></i>
                    </div>
                    <div class="form-error"></div>
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fa-solid fa-key"></i> Réinitialiser le mot de passe
                </button>
            </form>
        </div>
    </div>
</div>
<script src="<?= asset('js/auth.js') ?>"></script>
</body>
</html>
