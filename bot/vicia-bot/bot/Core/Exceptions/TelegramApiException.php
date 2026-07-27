<?php

namespace Bot\Core\Exceptions;

/**
 * Class TelegramApiException
 *
 * Levée lorsqu'un appel à l'API Telegram elle-même échoue (jeton
 * invalide, chat_id inaccessible, limite de débit Telegram atteinte,
 * message trop long...). Distincte de ApiException, qui concerne
 * l'API Vicia Home. Un envoi Telegram raté ne doit jamais faire
 * planter le traitement en cours : les contrôleurs l'attrapent et se
 * contentent de journaliser, il n'y a par définition plus personne à
 * qui répondre si Telegram lui-même est injoignable.
 */
class TelegramApiException extends BotException
{
    public function __construct(string $logMessage, ?\Throwable $previous = null)
    {
        parent::__construct('', $logMessage, 0, $previous);
    }
}
