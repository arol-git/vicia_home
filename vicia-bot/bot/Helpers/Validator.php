<?php

namespace Bot\Helpers;

use Bot\Core\Exceptions\ValidationException;

/**
 * Class Validator
 *
 * Petit moteur de validation pour les saisies conversationnelles
 * (e-mail et mot de passe lors de la liaison de compte, valeur de
 * seuil d'alerte, etc.). Complète — sans le remplacer —
 * Bot\Middlewares\InputValidationMiddleware, qui n'assure qu'une
 * hygiène minimale sur TOUT texte entrant ; ce validateur porte les
 * règles métier spécifiques à chaque champ, appliquées explicitement
 * par les contrôleurs.
 */
class Validator
{
    private array $errors = [];

    public function __construct(private readonly array $data)
    {
    }

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

    private function applyRule(string $field, mixed $value, string $rule): void
    {
        [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);

        switch ($name) {
            case 'required':
                if ($value === null || trim((string) $value) === '') {
                    $this->errors[$field] = 'Ce champ est obligatoire.';
                }
                break;

            case 'email':
                if ($value && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->errors[$field] = 'Adresse e-mail invalide.';
                }
                break;

            case 'min':
                if ($value && mb_strlen((string) $value) < (int) $param) {
                    $this->errors[$field] = "Ce champ doit contenir au moins $param caractères.";
                }
                break;

            case 'max':
                if ($value && mb_strlen((string) $value) > (int) $param) {
                    $this->errors[$field] = "Ce champ ne doit pas dépasser $param caractères.";
                }
                break;

            case 'numeric':
                if ($value !== null && $value !== '' && !is_numeric($value)) {
                    $this->errors[$field] = 'Ce champ doit être numérique.';
                }
                break;

            case 'in':
                $allowed = explode(',', (string) $param);
                if ($value !== null && $value !== '' && !in_array($value, $allowed, true)) {
                    $this->errors[$field] = 'Valeur non autorisée pour ce champ.';
                }
                break;
        }
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
     * Retourne les données validées si aucune erreur, ou lève une
     * ValidationException portant le détail des erreurs — pratique
     * pour un usage direct dans les contrôleurs sans bloc if répété.
     */
    public function validate(): array
    {
        if ($this->fails()) {
            throw new ValidationException($this->errors, array_values($this->errors)[0]);
        }

        return $this->data;
    }
}
