<?php

namespace Bot\Core;

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
            $this->response->edit($message, $keyboard);
            $this->response->answerCallback();
        } else {
            $this->response->text($message, $keyboard);
        }
    }

    protected function log(): \Monolog\Logger
    {
        return Logger::channel('bot');
    }
}
