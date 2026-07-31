<?php

namespace Bot\Core;

use Bot\Services\KeyboardBuilder;
use Telegram\Bot\Api;

/**
 * Class Controller
 *
 * Classe de base de tous les contrôleurs du bot. Fournit un accès
 * uniforme à la requête courante, à l'émetteur de réponses Telegram
 * et au journal applicatif, ainsi que quelques raccourcis fréquemment
 * utilisés (réponse texte, édition, accusé de callback).
 */
abstract class Controller
{
    protected Response $response;

    public function __construct(
        protected readonly Request $request,
        protected readonly Api $telegram
    ) {
        $this->response = new Response($telegram, $request);
    }

    protected function reply(string $message, ?array $keyboard = null): void
    {
        $this->response->text($message, $keyboard);
    }

    /**
     * Répond en éditant le message existant si l'update provient d'un
     * callback_query (navigation dans un menu), ou en envoyant un
     * nouveau message sinon (commande texte) — évite à chaque
     * contrôleur de refaire ce choix.
     */
    protected function respond(string $message, ?array $keyboard = null): void
    {
        if ($this->request->isCallbackQuery()) {
            $this->response->edit($message, $this->withBackToMenu($keyboard));
            $this->response->answerCallback();
        } else {
            $this->response->text($message, $keyboard);
        }
    }

    /**
     * Ajoute un bouton "Retour au menu" en bas des ecrans ouverts
     * depuis un bouton inline. Ainsi, le meme message peut revenir au
     * menu principal sans envoyer un nouveau message dans Telegram.
     */
    private function withBackToMenu(?array $keyboard): ?array
    {
        if (!$this->request->isCallbackQuery() || $this->request->callbackData() === 'menu:main') {
            return $keyboard;
        }

        $keyboard ??= [];
        $keyboard[] = KeyboardBuilder::row(KeyboardBuilder::button('⬅ Retour au menu', 'menu:main'));

        return $keyboard;
    }

    protected function log(): \Monolog\Logger
    {
        return Logger::channel('bot');
    }
}
