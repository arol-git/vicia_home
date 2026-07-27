<?php
/**
 * routes/web.php
 *
 * Déclaration de toutes les commandes texte et de tous les motifs de
 * callback_data du bot. Ce fichier est chargé par public/index.php
 * juste avant le dispatch ; il a accès à la variable $router déjà
 * instanciée.
 *
 * Convention de nommage des callback_data : "<module>:<action>:<arg>",
 * par exemple "eq:toggle:42" (équipement #42) ou "house:switch:2"
 * (bascule vers la maison #2) — voir Bot\Services\KeyboardBuilder,
 * qui génère ces chaînes de façon centralisée pour tout le bot.
 *
 * Ce fichier se remplit au fil des modules livrés. Chaque nouveau
 * contrôleur ajoute ses routes ici, jamais ailleurs.
 */

use Bot\Controllers\HealthController;

// --- Module 2 (Core) : commande de diagnostic ---------------------------
$router->command('/statut', [HealthController::class, 'index']);

// --- Middlewares de sécurité (Module 4) ----------------------------------
// Ordre volontaire : hygiène des entrées d'abord (la donnée la plus
// brute possible), puis anti-rejeu (ne traiter un update qu'une
// fois), puis liste blanche/noire, puis limitation de débit — de
// sorte qu'un update rejeté au plus tôt ne consomme pas de quota de
// débit ni de vérification de liste.
$router->middleware(new \Bot\Middlewares\InputValidationMiddleware());
$router->middleware(new \Bot\Middlewares\ReplayProtectionMiddleware());
$router->middleware(new \Bot\Middlewares\WhitelistMiddleware());
$router->middleware(new \Bot\Middlewares\RateLimitMiddleware());

// --- Liaison de compte & session (Module 6) ------------------------------
use Bot\Controllers\AccountController;
use Bot\Controllers\StartController;

$router->command('/start', [StartController::class, 'index']);
$router->fallback([StartController::class, 'handleFreeText']);

$router->command('/maisons', [AccountController::class, 'listHouses']);
$router->command('/moncompte', [AccountController::class, 'overview']);
$router->command('/delier', [AccountController::class, 'confirmUnlink']);

$router->callback('house:select:(\d+)', [AccountController::class, 'selectHouse']);
$router->callback('account:houses', [AccountController::class, 'goToHouses']);
$router->callback('account:unlink_confirm', [AccountController::class, 'confirmUnlink']);
$router->callback('account:unlink', [AccountController::class, 'unlink']);
$router->callback('account:cancel', [AccountController::class, 'cancelUnlink']);

// --- Menu principal & clavier (Module 7) ---------------------------------
use Bot\Controllers\HelpController;
use Bot\Controllers\MenuController;

$router->command('/menu', [MenuController::class, 'index']);
$router->command('/aide', [HelpController::class, 'index']);
$router->callback('menu:([a-z]+)', [MenuController::class, 'open']);

// --- Modules suivants : à compléter livraison après livraison ------------
