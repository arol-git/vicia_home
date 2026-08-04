<?php
/**
 * app/helpers/functions.php
 *
 * Fonctions utilitaires globales disponibles dans l'ensemble de
 * l'application (contrôleurs et vues).
 */

use App\Core\Csrf;

/**
 * Échappe une chaîne pour un affichage HTML sûr (protection XSS).
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Retourne la configuration générale de l'application (mise en cache
 * statique pour éviter de relire le fichier à chaque appel).
 */
function config(string $key = null)
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../../config/config.php';
    }
    if ($key === null) {
        return $config;
    }
    return $config[$key] ?? null;
}

/**
 * Construit une URL absolue de l'application à partir d'un chemin relatif.
 */
function url(string $path = ''): string
{
    return rtrim(config('base_url'), '/') . '/' . ltrim($path, '/');
}

/**
 * Construit l'URL d'un fichier statique (CSS, JS, image).
 */
function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

/**
 * Insère le champ caché contenant le jeton CSRF courant.
 */
function csrf_field(): string
{
    return Csrf::field();
}

/**
 * Formate une date SQL (Y-m-d H:i:s) en format lisible français.
 */
function format_date(?string $date, string $format = 'd/m/Y H:i'): string
{
    if (!$date) {
        return '—';
    }
    $timestamp = strtotime($date);
    return $timestamp ? date($format, $timestamp) : '—';
}

/**
 * Retourne une expression de temps relatif ("il y a 5 minutes").
 */
function time_ago(?string $date): string
{
    if (!$date) {
        return '—';
    }
    $diff = time() - strtotime($date);
    if ($diff < 60) {
        return 'à l’instant';
    }
    if ($diff < 3600) {
        return 'il y a ' . floor($diff / 60) . ' min';
    }
    if ($diff < 86400) {
        return 'il y a ' . floor($diff / 3600) . ' h';
    }
    return 'il y a ' . floor($diff / 86400) . ' j';
}

/**
 * Traduit une sévérité d'alerte en libellé et couleur d'affichage.
 */
function severity_badge(string $severity): array
{
    return match ($severity) {
        'critical' => ['label' => 'Critique', 'class' => 'badge-critical'],
        'warning'  => ['label' => 'Avertissement', 'class' => 'badge-warning'],
        default    => ['label' => 'Information', 'class' => 'badge-info'],
    };
}

/**
 * Traduit le rôle utilisateur en libellé français lisible.
 */
function role_label(string $role): string
{
    return match ($role) {
        'admin'       => 'Administrateur',
        'technicien'  => 'Technicien',
        default       => 'Utilisateur',
    };
}

/**
 * Retourne la classe d'icône Font Awesome associée à un type
 * d'équipement.
 */
function equipment_icon(string $type): string
{
    return match ($type) {
        'led'         => 'fa-lightbulb',
        'relais'      => 'fa-toggle-on',
        'ventilateur' => 'fa-fan',
        'pompe'       => 'fa-faucet',
        'servo'       => 'fa-cog',
        'porte'       => 'fa-warehouse',
        'fenetre'     => 'fa-window-maximize',
        'sirene'      => 'fa-bell',
        'camera'      => 'fa-video',
        default       => 'fa-microchip',
    };
}

/**
 * Retourne la classe d'icône Font Awesome associée à un type de capteur.
 */
function sensor_icon(string $type): string
{
    return match ($type) {
        'pir'          => 'fa-walking',
        'dht22_temp'   => 'fa-temperature-high',
        'dht22_hum'    => 'fa-tint',
        'mq2'          => 'fa-smog',
        'mq135'        => 'fa-wind',
        'ldr'          => 'fa-sun',
        'rfid'         => 'fa-id-card',
        'humidite_sol' => 'fa-seedling',
        default        => 'fa-microchip',
    };
}

/**
 * Journalise un message applicatif dans storage/logs/app.log.
 */
function app_log(string $message): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    file_put_contents(config('log_path'), $line, FILE_APPEND);
}
