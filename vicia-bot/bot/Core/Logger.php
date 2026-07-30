<?php

namespace Bot\Core;

use Bot\Config\App;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;
use Monolog\Logger as Monolog;

/**
 * Class Logger
 *
 * Fabrique de journaux Monolog. Deux canaux distincts sont utilisés
 * dans tout le projet :
 *
 *   - "bot"      : journal applicatif général (requêtes traitées,
 *                  appels API, erreurs) — logs/bot-YYYY-MM-DD.log
 *   - "security" : événements de sécurité (refus de liste blanche,
 *                  dépassement de rate limit, replay détecté,
 *                  échecs d'authentification) — logs/security-YYYY-MM-DD.log,
 *                  conservé séparément pour faciliter l'audit
 *
 * Chaque canal fait une rotation quotidienne avec une rétention de
 * 14 jours (configurable), au format texte simple exploitable par les
 * outils Unix classiques (grep, tail -f) sans dépendance à un
 * agrégateur de logs externe.
 */
class Logger
{
    /** @var array<string, Monolog> */
    private static array $channels = [];

    public static function channel(string $name = 'bot'): Monolog
    {
        if (!isset(self::$channels[$name])) {
            self::$channels[$name] = self::build($name);
        }

        return self::$channels[$name];
    }

    private static function build(string $name): Monolog
    {
        $logger = new Monolog($name);

        $handler = new RotatingFileHandler(
            App::path("logs/{$name}.log"),
            14,
            self::resolveLevel(App::env('LOG_LEVEL', 'info'))
        );

        $handler->setFormatter(new LineFormatter(
            "[%datetime%] %channel%.%level_name%: %message% %context%\n",
            'Y-m-d H:i:s',
            true,
            true
        ));

        $logger->pushHandler($handler);

        return $logger;
    }

    private static function resolveLevel(string $level): Level
    {
        return Level::fromName(ucfirst(strtolower($level)));
    }
}
