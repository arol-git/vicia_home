<?php
/**
 * app/views/auth/forgot-password.php
 *
 * Formulaire de demande de réinitialisation de mot de passe.
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
    <script>
        (function () {
            try {
                var theme = localStorage.getItem('vicia_theme');
                if (theme === 'dark' || theme === 'light') {
                    document.documentElement.setAttribute('data-theme', theme);
                }
            } catch (e) {}
        })();
    </script>
    <style>
        html[data-theme="dark"], html[data-theme="dark"] body { background: #0e1520; color: #e7ecf3; color-scheme: dark; }
        html[data-theme="light"] { color-scheme: light; }
    </style>
    <title>Mot de passe oublié — Vicia Home</title>
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
            <h1>Récupérez l'accès à votre maison intelligente en toute sécurité.</h1>
            <p>Un lien de réinitialisation à usage unique, valable une heure, vous sera envoyé par e-mail.</p>
        </div>
        <div></div>
    </div>

    <div class="auth-page__form-side">
        <div class="auth-card">
            <h2>Mot de passe oublié</h2>
            <p class="subtitle">Indiquez votre adresse e-mail pour recevoir un lien de réinitialisation.</p>

            <?php if ($success = Session::getFlash('success')): ?>
                <div class="alert-banner is-success"><i class="fa-solid fa-circle-check"></i> <?= e($success) ?></div>
            <?php endif; ?>
            <?php if ($error = Session::getFlash('error')): ?>
                <div class="alert-banner is-error"><i class="fa-solid fa-circle-exclamation"></i> <?= e($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="<?= url('/forgot-password') ?>">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label class="form-label" for="email">Adresse e-mail</label>
                    <div class="auth-input-icon">
                        <i class="fa-solid fa-envelope"></i>
                        <input type="email" id="email" name="email" class="form-control" placeholder="vous@exemple.com" required autofocus>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fa-solid fa-paper-plane"></i> Envoyer le lien de réinitialisation
                </button>
            </form>

            <p class="auth-footer-link"><a href="<?= url('/login') ?>"><i class="fa-solid fa-arrow-left"></i> Retour à la connexion</a></p>
        </div>
    </div>
</div>
</body>
</html>
