<?php

namespace Bot\Core;

use Bot\Core\Exceptions\BotException;
use Telegram\Bot\Api;

/**
 * Class Router
 *
 * Routeur du bot : associe une commande texte (ex. "/start") ou un
 * motif de callback_data (ex. "eq:toggle:(\d+)") à un contrôleur.
 * Exécute la chaîne d'intergiciels de sécurité avant tout routage
 * (voir Bot\Middlewares), puis délègue au contrôleur résolu.
 *
 * Les commandes et callbacks sont déclarés dans routes/web.php,
 * jamais codés en dur ici — cette classe ne fait que le routage.
 */
class Router
{
    /** @var array<string, array{0: class-string, 1: string}> */
    private array $commands = [];

    /** @var list<array{pattern: string, handler: array{0: class-string, 1: string}}> */
    private array $callbacks = [];

    /** @var array{0: class-string, 1: string}|null */
    private ?array $fallback = null;

    /** @var list<Middleware> */
    private array $middlewares = [];

    public function middleware(Middleware $middleware): self
    {
        $this->middlewares[] = $middleware;
        return $this;
    }

    public function command(string $command, array $handler): self
    {
        $this->commands[$command] = $handler;
        return $this;
    }

    /**
     * Enregistre un motif de callback_data. Le motif est un fragment
     * d'expression régulière SANS délimiteurs ni ancres — le Router
     * les ajoute lui-même pour imposer une correspondance complète
     * (évite qu'un motif trop permissif n'intercepte un callback
     * d'un autre module par préfixe commun).
     */
    public function callback(string $pattern, array $handler): self
    {
        $this->callbacks[] = ['pattern' => $pattern, 'handler' => $handler];
        return $this;
    }

    /**
     * Gestionnaire appelé pour tout message texte qui n'est ni une
     * commande, ni intercepté plus tôt par une session de
     * conversation en cours (saisie d'e-mail lors de la liaison de
     * compte, par exemple).
     */
    public function fallback(array $handler): self
    {
        $this->fallback = $handler;
        return $this;
    }

    /**
     * Exécute la chaîne d'intergiciels puis le routage. Toute
     * BotException levée pendant l'un ou l'autre est laissée
     * remonter à l'appelant (public/index.php), qui la transforme en
     * réponse Telegram appropriée via Bot\Core\ErrorHandler.
     */
    public function dispatch(Request $request, Api $telegram): void
    {
        $pipeline = array_reduce(
            array_reverse($this->middlewares),
            fn(callable $next, Middleware $middleware) => fn(Request $req) => $middleware->handle($req, $next),
            fn(Request $req) => $this->route($req, $telegram)
        );

        $pipeline($request);
    }

    private function route(Request $request, Api $telegram): void
    {
        if ($request->isCallbackQuery()) {
            $this->routeCallback($request, $telegram);
            return;
        }

        if ($request->isCommand()) {
            $this->routeCommand($request, $telegram);
            return;
        }

        if ($request->isMessage() && $this->fallback !== null) {
            $this->invoke($this->fallback, $request, $telegram, []);
            return;
        }

        // Update d'un type non pris en charge (sticker, message vocal,
        // membre ajouté au groupe...) : ignoré silencieusement, sans
        // erreur — Telegram envoie de nombreux types d'updates que le
        // bot n'a pas vocation à traiter.
        Logger::channel('bot')->debug('Update ignoré (type non pris en charge)');
    }

    private function routeCommand(Request $request, Api $telegram): void
    {
        $command = $request->command();
        $handler = $this->commands[$command] ?? null;

        if ($handler === null) {
            throw new BotException(
                "Commande inconnue. Envoyez /aide pour la liste des commandes disponibles.",
                "Commande inconnue reçue : $command"
            );
        }

        $this->invoke($handler, $request, $telegram, $request->commandArgs());
    }

    private function routeCallback(Request $request, Api $telegram): void
    {
        $data = $request->callbackData();

        foreach ($this->callbacks as $route) {
            if (preg_match('#^' . $route['pattern'] . '$#', $data, $matches)) {
                array_shift($matches);
                $this->invoke($route['handler'], $request, $telegram, $matches);
                return;
            }
        }

        throw new BotException(
            "Ce bouton n'est plus valide. Merci de relancer le menu avec /start.",
            "Aucune route ne correspond au callback_data : $data"
        );
    }

    /**
     * @param array{0: class-string, 1: string} $handler
     */
    private function invoke(array $handler, Request $request, Api $telegram, array $args): void
    {
        [$controllerClass, $method] = $handler;

        if (!class_exists($controllerClass)) {
            throw new BotException(
                "Une erreur interne est survenue.",
                "Contrôleur introuvable : $controllerClass"
            );
        }

        $controller = new $controllerClass($request, $telegram);

        if (!method_exists($controller, $method)) {
            throw new BotException(
                "Une erreur interne est survenue.",
                "Méthode introuvable : $controllerClass::$method"
            );
        }

        $controller->$method(...$args);
    }
}
