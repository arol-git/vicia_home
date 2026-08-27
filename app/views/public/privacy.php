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
            <a href="<?= url('/guide') ?>">Guide d'utilisation</a>
        </nav>
    </div>
</header>

<main class="public-info__content">
    <div class="public-info__intro">
        <h1>Politique de confidentialité</h1>
        <p>Cette page explique simplement quelles informations VICIA HOME utilise dans le fonctionnement actuel du projet.</p>
    </div>

    <section class="public-info__section">
        <h2><i class="fa-solid fa-database"></i> Données utilisées</h2>
        <ul>
            <li>Les données du compte : nom, adresse e-mail, téléphone, rôle, mot de passe chiffré, état du compte et dates utiles à la session.</li>
            <li>Les données de la maison : nom, adresse éventuelle, ville, fuseau horaire et membres autorisés.</li>
            <li>Les équipements : nom, type, pièce, état, adresse MQTT et date du dernier changement d'état.</li>
            <li>Les capteurs : nom, type, pièce, adresse MQTT et mesures enregistrées avec leur date.</li>
            <li>Les alertes, les règles d'automatisation, les journaux d'activité et les journaux de connexion nécessaires au fonctionnement et au suivi de la plateforme.</li>
            <li>Les messages de conversation avec l'assistant IA, lorsqu'une conversation est utilisée.</li>
        </ul>
    </section>

    <section class="public-info__section">
        <h2><i class="fa-solid fa-bullseye"></i> Pourquoi ces données sont utilisées</h2>
        <ul>
            <li>Permettre la connexion et contrôler l'accès aux maisons.</li>
            <li>Afficher les équipements, les pièces, les capteurs, les alertes et la consommation.</li>
            <li>Exécuter les règles d'automatisation et publier les changements vers les équipements connectés.</li>
            <li>Traiter les commandes et questions envoyées à l'assistant.</li>
            <li>Garder une trace des actions, connexions et événements utiles à la sécurité.</li>
        </ul>
    </section>

    <section class="public-info__section">
        <h2><i class="fa-solid fa-bell"></i> Notifications</h2>
        <p>Selon les choix de l'utilisateur et la configuration de la maison, VICIA HOME peut utiliser une adresse e-mail, Telegram ou les notifications du navigateur. Les paramètres SMTP, le jeton du bot et les clés de notification sont des paramètres techniques de la plateforme et ne sont pas affichés aux autres utilisateurs.</p>
    </section>

    <section class="public-info__section">
        <h2><i class="fa-solid fa-shield-halved"></i> Protection des données</h2>
        <p>L'application utilise des sessions protégées, un contrôle d'accès par rôle et par maison, une protection CSRF pour les formulaires, des mots de passe stockés sous forme de hash et des vérifications d'appartenance avant l'accès aux équipements, capteurs, alertes et maisons.</p>
        <p>Les pages publiques sont en lecture seule. Elles ne donnent accès à aucun compte, équipement, capteur, journal ou secret.</p>
    </section>

    <section class="public-info__section">
        <h2><i class="fa-solid fa-clock"></i> Conservation</h2>
        <p>Le projet conserve actuellement les données dans sa base pour permettre le fonctionnement de la plateforme. Les mesures de capteurs, alertes, conversations, journaux d'activité et journaux de connexion sont conservés selon les tables et mécanismes existants. Aucune durée générale de suppression automatique n'est définie dans le code actuel.</p>
    </section>

    <section class="public-info__section">
        <h2><i class="fa-solid fa-user-check"></i> Vos droits</h2>
        <p>Pour demander l'accès, la correction ou la suppression des données liées à votre compte, contactez l'administrateur de votre plateforme VICIA HOME. Les droits d'accès et de gestion dépendent aussi du rôle attribué à votre compte et de votre appartenance à une maison.</p>
    </section>
</main>

<footer class="public-info__footer">VICIA HOME — Politique de confidentialité</footer>
</body>
</html>
