<?php
/**
 * app/views/layout/header.php
 *
 * En-tête HTML commun à toutes les pages authentifiées de la
 * plateforme. Attend les variables $title (titre de la page) et
 * $currentUser (utilisateur connecté), injectées par
 * App\Core\Controller::render().
 */
use App\Core\Csrf;
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e(Csrf::token()) ?>">
    <title><?= e($title ?? 'Vicia Home') ?> — Vicia Home</title>

    <link rel="icon" type="image/png" href="<?= asset('img/favicon.png') ?>">

    <!-- Polices d'icônes -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Feuilles de styles personnalisées (sans framework externe) -->
    <link rel="stylesheet" href="<?= asset('css/variables.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/reset.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/layout.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/components.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/dashboard.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/dark-mode.css') ?>">
</head>
<body>
<div class="app-shell">
