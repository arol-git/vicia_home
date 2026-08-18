<?php

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

/*
 * ============================================================
 * VICIA BOT
 * ============================================================
 */
if (str_starts_with($path, '/vicia-bot/public')) {

    $botPath = substr($path, strlen('/vicia-bot/public'));

    if ($botPath === '' || $botPath === '/') {
        $botPath = '/index.php';
    }

    /*
     * Sécurité : empêcher les chemins ../
     */
    $botPath = '/' . ltrim($botPath, '/');

    /*
     * index.php = webhook Telegram
     */
    if ($botPath === '/index.php') {
        require __DIR__ . '/../vicia-bot/public/index.php';
        exit;
    }

    /*
     * webhook-alert.php = webhook interne Vicia Home
     */
    if ($botPath === '/webhook-alert.php') {
        require __DIR__ . '/../vicia-bot/public/webhook-alert.php';
        exit;
    }

    http_response_code(404);
    echo 'Bot route not found';
    exit;
}

/*
 * ============================================================
 * VICIA HOME API
 * ============================================================
 */

// Rediriger les routes API vers le vrai fichier api/index.php
if (str_starts_with($path, '/api/')) {
    // Reconstruire la REQUEST_URI pour que api/index.php le reçoive correctement
    require __DIR__ . '/../api/index.php';
    exit;
}

/*
 * ============================================================
 * VICIA HOME
 * ============================================================
 */

$file = __DIR__ . $path;

if ($path !== '/' && is_file($file)) {
    return false;
}

require __DIR__ . '/index.php';// Force redeploy mar. 18 août 2026 17:05:54 WAT
