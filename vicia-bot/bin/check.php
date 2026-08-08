<?php

require __DIR__ . '/../vendor/autoload.php';

use Bot\Config\App;
use Bot\Config\Database;

/**
 * Verification locale du bot.
 *
 * Ce script ne contacte pas Telegram : il controle seulement que la
 * configuration, l'autoload Composer et la base interne `vicia_bot`
 * sont prets. Les secrets sont volontairement masques pour eviter de
 * les afficher dans le terminal ou dans un rapport de bug.
 */

$ok = true;

function line(string $status, string $message): void
{
    echo '[' . $status . '] ' . $message . PHP_EOL;
}

function pass(string $message): void
{
    line('OK', $message);
}

function fail(string $message): void
{
    global $ok;
    $ok = false;
    line('FAIL', $message);
}

function maskValue(mixed $value): string
{
    $text = trim((string) $value);

    if ($text === '') {
        return '(vide)';
    }

    return '(defini, ' . strlen($text) . ' caracteres)';
}

try {
    App::boot();
    pass('Configuration chargee depuis .env');
} catch (Throwable $e) {
    fail('Configuration invalide : ' . $e->getMessage());
    exit(1);
}
/*
$required = [
    'APP_KEY',
    'TELEGRAM_BOT_TOKEN',
    'TELEGRAM_WEBHOOK_SECRET',
    'VICIA_API_BASE_URL',
    'VICIA_ALERT_WEBHOOK_SECRET',
    'MYSQL_HOST',
    'MYSQL_PORT',
    'MYSQL_DATABASE',
    'MYSQL_USER',
]; */
$required = [
    'APP_KEY',
    'TELEGRAM_BOT_TOKEN',
    'TELEGRAM_WEBHOOK_SECRET',
    'VICIA_API_BASE_URL',
    'VICIA_ALERT_WEBHOOK_SECRET',
    'MYSQL_HOST',
    'MYSQL_PORT',
    'MYSQL_NAME',
    'MYSQL_USER',
];

foreach ($required as $key) {
    $value = App::env($key);

    if ($value === null || trim((string) $value) === '') {
        fail($key . ' manque dans .env');
        continue;
    }

    pass($key . ' ' . maskValue($value));
}

try {
    $pdo = Database::connection();
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $requiredTables = [
        'access_list',
        'bot_sessions',
        'notification_log',
        'processed_updates',
        'rate_limit_hits',
        'security_events',
        'telegram_users',
    ];

    foreach ($requiredTables as $table) {
        in_array($table, $tables, true)
            ? pass('Table presente : ' . $table)
            : fail('Table manquante : ' . $table);
    }
} catch (Throwable $e) {
    fail('Base vicia_bot inaccessible : ' . $e->getMessage());
}

$apiBaseUrl = rtrim((string) App::env('VICIA_API_BASE_URL', ''), '/');
if (!filter_var($apiBaseUrl, FILTER_VALIDATE_URL)) {
    fail('VICIA_API_BASE_URL n est pas une URL valide');
} else {
    pass('API Vicia Home configuree : ' . $apiBaseUrl);
}

echo PHP_EOL;
if ($ok) {
    pass('Bot pret localement. Il reste seulement a enregistrer le webhook Telegram avec une URL HTTPS publique.');
    exit(0);
}

fail('Verification incomplete. Corriger les erreurs ci-dessus puis relancer : php vicia-bot/bin/check.php');
exit(1);
