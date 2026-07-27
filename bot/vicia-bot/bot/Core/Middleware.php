<?php

namespace Bot\Core;

/**
 * Interface Middleware
 *
 * Contrat commun à tous les intergiciels de sécurité (voir
 * Bot\Middlewares) : liste blanche/noire, limitation de débit,
 * protection anti-rejeu, validation des entrées. Chaque intergiciel
 * reçoit la requête courante et le prochain maillon de la chaîne ;
 * il lui revient de l'appeler (ou non) pour poursuivre le traitement.
 *
 * handle() lève une Bot\Core\Exceptions\BotException (ou une
 * sous-classe) pour interrompre la chaîne — il ne retourne jamais un
 * "faux" pour signaler un refus, afin que le message d'erreur
 * approprié soit systématiquement porté par l'exception elle-même.
 */
interface Middleware
{
    /**
     * @param Request  $request
     * @param callable $next Appelle le maillon suivant de la chaîne : function(Request $request): void
     */
    public function handle(Request $request, callable $next): void;
}
