<?php

namespace App\Core;

/**
 * Class Validator
 *
 * Petit moteur de validation serveur, exécuté systématiquement en
 * complément de la validation JavaScript côté client (qui n'a qu'un
 * rôle de confort d'usage et ne constitue jamais une garantie de
 * sécurité).
 */
class Validator
{
    private array $data;
    private array $errors = [];

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Applique un ensemble de règles de validation.
     *
     * Exemple : $v->rules(['email' => 'required|email', 'name' => 'required|min:3']);
     */
    public function rules(array $rules): self
    {
        foreach ($rules as $field => $ruleString) {
            $value = $this->data[$field] ?? null;
            foreach (explode('|', $ruleString) as $rule) {
                $this->applyRule($field, $value, $rule);
            }
        }

        return $this;
    }

    private function applyRule(string $field, $value, string $rule): void
    {
        [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);

        switch ($name) {
            case 'required':
                if ($value === null || trim((string) $value) === '') {
                    $this->addError($field, 'Ce champ est obligatoire.');
                }
                break;

            case 'email':
                if ($value && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, 'Adresse e-mail invalide.');
                }
                break;

            case 'min':
                if ($value && strlen((string) $value) < (int) $param) {
                    $this->addError($field, "Ce champ doit contenir au moins $param caractères.");
                }
                break;

            case 'max':
                if ($value && strlen((string) $value) > (int) $param) {
                    $this->addError($field, "Ce champ ne doit pas dépasser $param caractères.");
                }
                break;

            case 'numeric':
                if ($value !== null && $value !== '' && !is_numeric($value)) {
                    $this->addError($field, 'Ce champ doit être numérique.');
                }
                break;

            case 'in':
                $allowed = explode(',', (string) $param);
                if ($value !== null && $value !== '' && !in_array($value, $allowed, true)) {
                    $this->addError($field, 'Valeur non autorisée pour ce champ.');
                }
                break;

            case 'confirmed':
                $confirmField = $field . '_confirmation';
                if (($this->data[$confirmField] ?? null) !== $value) {
                    $this->addError($field, 'La confirmation ne correspond pas.');
                }
                break;
        }
    }

    private function addError(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }

    public function fails(): bool
    {
        return count($this->errors) > 0;
    }

    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Retourne le premier message d'erreur, toutes règles confondues,
     * utile pour un affichage rapide en réponse AJAX.
     */
    public function firstError(): ?string
    {
        foreach ($this->errors as $fieldErrors) {
            return $fieldErrors[0];
        }
        return null;
    }
}
