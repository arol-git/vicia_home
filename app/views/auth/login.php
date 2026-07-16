<?php
/**
 * app/views/auth/login.php
 *
 * Formulaire de connexion. Vue autonome (sans layout principal),
 * appelée par AuthController::showLogin().
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
    <title>Connexion — Vicia Home</title>
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
            <h1>La supervision intelligente de votre habitation, en toute sécurité.</h1>
            <p>Pilotage des équipements, surveillance des capteurs et détection d'intrusion réseau, réunis dans une plateforme unique.</p>
        </div>
        <div class="auth-page__features">
            <div><i class="fa-solid fa-shield-halved"></i> Détection d'intrusion réseau en temps réel</div>
            <div><i class="fa-solid fa-bolt"></i> Suivi de la consommation énergétique</div>
            <div><i class="fa-solid fa-diagram-project"></i> Automatisation sans code, règle par règle</div>
        </div>
    </div>

    <div class="auth-page__form-side">
        <div class="auth-card">
            <h2>Bon retour parmi nous</h2>
            <p class="subtitle">Connectez-vous pour accéder à votre tableau de bord.</p>

            <?php if ($error = Session::getFlash('error')): ?>
                <div class="alert-banner is-error"><i class="fa-solid fa-circle-exclamation"></i> <?= e($error) ?></div>
            <?php endif; ?>
            <?php if ($success = Session::getFlash('success')): ?>
                <div class="alert-banner is-success"><i class="fa-solid fa-circle-check"></i> <?= e($success) ?></div>
            <?php endif; ?>

            <form id="login-form" method="POST" action="<?= url('/login') ?>" novalidate>
                <?= csrf_field() ?>

                <div class="form-group">
                    <label class="form-label" for="email">Adresse e-mail</label>
                    <div class="auth-input-icon">
                        <i class="fa-solid fa-envelope"></i>
                        <input type="email" id="email" name="email" class="form-control" placeholder="vous@exemple.com" required autofocus>
                    </div>
                    <div class="form-error"></div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Mot de passe</label>
                    <div class="auth-input-icon">
                        <i class="fa-solid fa-lock"></i>
                        <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
                        <i class="fa-solid fa-eye toggle-visibility"></i>
                    </div>
                    <div class="form-error"></div>
                </div>

                <div class="auth-options">
                    <label class="checkbox-row">
                        <input type="checkbox" name="remember" value="1"> Se souvenir de moi
                    </label>
                    <a href="<?= url('/forgot-password') ?>">Mot de passe oublié ?</a>
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fa-solid fa-right-to-bracket"></i> Se connecter
                </button>
            </form>

            <p class="auth-footer-link">Vicia Home &mdash; Plateforme de maison intelligente sécurisée</p>
        </div>
    </div>
</div>
<script src="<?= asset('js/auth.js') ?>"></script>
</body>
</html>
