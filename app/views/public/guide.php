<?php
/** @var string $title */
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?> — VICIA HOME</title>
    <link rel="icon" href="<?= asset('favicon.svg') ?>" type="image/svg+xml">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= asset('css/variables.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/reset.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/components.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/public-info.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/dark-mode.css') ?>">
</head>
<body class="public-info-page">
<header class="public-info__header">
    <div class="public-info__header-inner">
        <a class="public-info__brand" href="<?= url('/login') ?>">
            <span class="public-info__brand-icon"><i class="fa-solid fa-house-signal"></i></span>
            <span>VICIA HOME</span>
        </a>
        <nav class="public-info__nav" aria-label="Navigation publique">
            <a href="<?= url('/login') ?>">Se connecter</a>
            <a href="<?= url('/privacy') ?>">Confidentialité</a>
        </nav>
    </div>
</header>

<main class="public-info__content">
    <div class="public-info__intro">
        <h1>Guide d'utilisation</h1>
        <p>VICIA HOME vous aide à surveiller et contrôler votre maison intelligente simplement.</p>
    </div>

    <section class="public-info__section">
        <h2><i class="fa-solid fa-house"></i> VICIA HOME, c'est quoi ?</h2>
        <p>C'est un tableau de bord pour voir votre maison au même endroit. Vous pouvez contrôler des appareils, lire les mesures des capteurs, consulter les alertes et suivre la consommation d'énergie.</p>
        <div class="public-info__screenshot"><img src="<?= asset('img/dashboard.png') ?>" alt="Tableau de bord VICIA HOME"></div>
    </section>

    <section class="public-info__section">
        <h2><i class="fa-solid fa-right-to-bracket"></i> Se connecter</h2>
        <ol>
            <li>Ouvrez la page de connexion.</li>
            <li>Saisissez l'adresse e-mail de votre compte.</li>
            <li>Saisissez votre mot de passe.</li>
            <li>Cliquez sur <strong>Se connecter</strong>.</li>
        </ol>
        <p>La case « Se souvenir de moi » permet de garder votre session plus longtemps sur cet appareil.</p>
    </section>

    <section class="public-info__section">
        <h2><i class="fa-solid fa-plug-circle-bolt"></i> Contrôler les équipements</h2>
        <p>Ouvrez <strong>Équipements</strong> ou le <strong>Tableau de bord</strong>. Chaque équipement affiche son nom, sa pièce et son état. Utilisez le bouton d'action pour l'allumer, l'éteindre, l'ouvrir ou le fermer selon le type d'appareil.</p>
        <p>Vous pouvez aussi utiliser l'assistant : dites par exemple « Allume la lumière du salon » ou « Arrête tous les appareils ».</p>
        <div class="public-info__screenshot"><img src="<?= asset('img/equipement.png') ?>" alt="Liste des équipements de la maison"></div>
    </section>

    <section class="public-info__section">
        <h2><i class="fa-solid fa-microchip"></i> Consulter les capteurs</h2>
        <p>Ouvrez <strong>Capteurs</strong> pour voir les dernières mesures. Les valeurs peuvent concerner la température, l'humidité, l'énergie ou la sécurité selon les capteurs installés.</p>
        <div class="public-info__screenshot"><img src="<?= asset('img/capteur.png') ?>" alt="Mesures des capteurs de la maison"></div>
    </section>

    <section class="public-info__section">
        <h2><i class="fa-solid fa-bell"></i> Consulter les alertes</h2>
        <p>Ouvrez <strong>Alertes</strong> pour voir les événements importants. Le nombre affiché près du menu indique les alertes non lues. Vous pouvez marquer une alerte comme lue.</p>
    </section>

    <section class="public-info__section">
        <h2><i class="fa-solid fa-bolt"></i> Voir la consommation</h2>
        <p>Ouvrez <strong>Consommation</strong> pour voir une estimation de la puissance active et de la consommation journalière, calculée à partir des équipements connus.</p>
        <div class="public-info__screenshot"><img src="<?= asset('img/consomation.png') ?>" alt="Suivi de la consommation énergétique"></div>
    </section>

    <section class="public-info__section">
        <h2><i class="fa-solid fa-microphone"></i> Utiliser l'assistant vocal</h2>
        <ol>
            <li>Cliquez sur le bouton avec le microphone.</li>
            <li>Cliquez sur « Écouter ».</li>
            <li>Parlez clairement, par exemple : « Quelle est la température du salon ? ».</li>
            <li>Lisez la réponse dans la fenêtre. Elle reste ouverte pour les questions.</li>
        </ol>
        <p>L'assistant comprend aussi des formulations courantes comme « coupe l'eau », « allume dehors » et « arrête tous les appareils ».</p>
    </section>

    <section class="public-info__section" id="about">
        <h2><i class="fa-solid fa-circle-info"></i> À propos de VICIA HOME</h2>
        <p>VICIA HOME est une plateforme de supervision et de contrôle pour une maison équipée d'appareils et de capteurs connectés.</p>
    </section>

    <section class="public-info__section">
        <h2><i class="fa-solid fa-icons"></i> Comprendre les boutons et les icônes</h2>
        <ul>
            <li><strong>Maison</strong> : accéder au tableau de bord de la maison.</li>
            <li><strong>Prise ou éclair</strong> : gérer un équipement.</li>
            <li><strong>Puce</strong> : consulter un capteur.</li>
            <li><strong>Cloche</strong> : consulter les alertes et les notifications.</li>
            <li><strong>Microphone</strong> : parler à l'assistant vocal.</li>
            <li><strong>Engrenage</strong> : ouvrir les paramètres.</li>
        </ul>
    </section>
</main>

<footer class="public-info__footer">VICIA HOME — Gérez et contrôlez votre maison intelligente simplement.</footer>
</body>
</html>
