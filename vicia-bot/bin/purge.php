<?php
/**
 * bin/purge.php
 *
 * Tâche de maintenance à exécuter périodiquement via cron (voir
 * docs/README.md, section Déploiement) : purge les données
 * transitoires qui n'ont pas vocation à s'accumuler indéfiniment.
 *
 * Exemple de crontab (toutes les heures) :
 *   0 * * * * php /chemin/vers/vicia-bot/bin/purge.php >> /chemin/vers/vicia-bot/logs/purge.log 2>&1
 */

require __DIR__ . '/../vendor/autoload.php';

use Bot\Config\App;
use Bot\Models\BotSession;
use Bot\Models\ProcessedUpdate;
use Bot\Models\RateLimitHit;

App::boot();

$sessions = BotSession::purgeExpired();
$rateLimits = RateLimitHit::purgeOlderThan(3600); // 1 heure
$replays = ProcessedUpdate::purgeOlderThan(7); // 7 jours

echo date('Y-m-d H:i:s') . " — Purge : {$sessions} session(s) expirée(s), {$rateLimits} entrée(s) de débit, {$replays} update(s) anti-rejeu\n";
