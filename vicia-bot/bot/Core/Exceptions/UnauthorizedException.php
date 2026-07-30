<?php

namespace Bot\Core\Exceptions;

/**
 * Class UnauthorizedException
 *
 * Levée par les intergiciels de sécurité (liste blanche/noire,
 * compte non lié, rôle insuffisant sur la maison active) et par les
 * contrôleurs lorsqu'une action est refusée. Ne doit JAMAIS détailler
 * la raison précise du refus dans le message utilisateur au-delà du
 * strict nécessaire (éviter l'énumération de comptes ou la
 * divulgation de la structure des permissions).
 */
class UnauthorizedException extends BotException
{
    public static function accountNotLinked(): self
    {
        return new self(
            "Votre compte Telegram n'est pas encore lié à un compte Vicia Home. Envoyez /start pour le lier.",
            'Tentative d’action sans compte lié'
        );
    }

    public static function blacklisted(): self
    {
        return new self(
            "Votre accès au bot Vicia Home a été suspendu. Contactez un administrateur.",
            'Utilisateur en liste noire'
        );
    }

    public static function notWhitelisted(): self
    {
        return new self(
            "L'accès au bot Vicia Home est actuellement restreint. Contactez un administrateur pour être autorisé.",
            'Mode liste blanche actif, utilisateur non répertorié'
        );
    }

    public static function insufficientRole(): self
    {
        return new self(
            "Votre rôle sur cette maison ne permet pas cette action.",
            'Rôle insuffisant sur la maison active'
        );
    }

    public static function noHouseSelected(): self
    {
        return new self(
            "Aucune maison sélectionnée. Utilisez /maisons pour en choisir une.",
            'Aucune maison active en session'
        );
    }
}
