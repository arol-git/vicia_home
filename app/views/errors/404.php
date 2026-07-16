<?php
/**
 * app/views/errors/404.php
 *
 * Page d'erreur "ressource introuvable". Vue autonome, appelée
 * directement par App\Core\Router::abort().
 */
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
    <title>Page introuvable — Vicia Home</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= asset('css/variables.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/reset.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/components.css') ?>">
</head>
<body style="display:flex;align-items:center;justify-content:center;min-height:100vh;background:var(--color-bg);">
    <div class="card" style="text-align:center;max-width:420px;">
        <i class="fa-solid fa-satellite-dish" style="font-size:2.5rem;color:var(--color-primary);margin-bottom:16px;"></i>
        <h1 style="font-size:1.5rem;margin-bottom:8px;">404 — Page introuvable</h1>
        <p class="text-muted mb-4">La page que vous recherchez n'existe pas ou a été déplacée.</p>
        <a href="<?= url('/dashboard') ?>" class="btn btn-primary"><i class="fa-solid fa-house"></i> Retour au tableau de bord</a>
    </div>
</body>
</html>
