<?php

namespace Bot\Core\Exceptions;

/**
 * Class ApiException
 *
 * Levée par Bot\Services\ViciaApiClient lorsqu'un appel à l'API
 * Vicia Home échoue (erreur HTTP 4xx/5xx, réponse malformée,
 * indisponibilité réseau). Conserve le code de statut HTTP d'origine
 * pour permettre aux contrôleurs d'adapter leur réaction (401 → forcer
 * un nouveau lien de compte, 403 → message de droits insuffisants,
 * 5xx → message générique de service indisponible).
 */
class ApiException extends BotException
{
    public function __construct(
        string $userMessage,
        private readonly int $httpStatus,
        string $logMessage = '',
        ?\Throwable $previous = null
    ) {
        parent::__construct($userMessage, $logMessage, $httpStatus, $previous);
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }

    public function isAuthError(): bool
    {
        return $this->httpStatus === 401;
    }

    public function isForbidden(): bool
    {
        return $this->httpStatus === 403;
    }
}
