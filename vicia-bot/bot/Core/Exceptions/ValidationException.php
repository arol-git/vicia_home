<?php

namespace Bot\Core\Exceptions;

/**
 * Class ValidationException
 *
 * Levée lorsqu'une saisie utilisateur (texte libre envoyé en réponse
 * à une invite du bot — e-mail, code, valeur de seuil...) ne respecte
 * pas le format attendu. Porte la liste des erreurs par champ, pour
 * un éventuel réaffichage détaillé du formulaire conversationnel.
 */
class ValidationException extends BotException
{
    /** @param array<string, string> $errors champ => message */
    public function __construct(private readonly array $errors, string $userMessage = 'Saisie invalide.')
    {
        parent::__construct($userMessage, 'Validation échouée : ' . json_encode($errors, JSON_UNESCAPED_UNICODE));
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?string
    {
        return array_values($this->errors)[0] ?? null;
    }
}
