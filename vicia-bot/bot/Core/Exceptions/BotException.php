<?php

namespace Bot\Core\Exceptions;

/**
 * Class BotException
 *
 * Exception de base de toutes les exceptions applicatives du bot.
 * Porte un message DESTINÉ À L'UTILISATEUR (renvoyé tel quel sur
 * Telegram par le gestionnaire d'erreurs), à distinguer du message
 * technique journalisé — voir Bot\Core\ErrorHandler.
 */
class BotException extends \Exception
{
    /**
     * @param string $userMessage Message affiché à l'utilisateur Telegram
     * @param string $logMessage  Détail technique journalisé (par défaut : identique à $userMessage)
     */
    public function __construct(
        private readonly string $userMessage,
        string $logMessage = '',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($logMessage !== '' ? $logMessage : $userMessage, $code, $previous);
    }

    public function userMessage(): string
    {
        return $this->userMessage;
    }
}
