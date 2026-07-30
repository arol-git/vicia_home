<?php

namespace Bot\Services;

use Bot\Core\Exceptions\UnauthorizedException;
use Bot\Models\TelegramUser;

/**
 * Class HouseContext
 *
 * Résout la maison actuellement sélectionnée pour un utilisateur
 * Telegram lié, utilisée par tous les contrôleurs métier (Modules 8+)
 * pour scoper leurs appels API. Centralise le refus propre si aucune
 * maison n'est sélectionnée.
 */
class HouseContext
{
    public static function currentHouseId(int $telegramId): int
    {
        $user = TelegramUser::findByTelegramId($telegramId);

        if (!$user || !$user['current_house_id']) {
            throw UnauthorizedException::noHouseSelected();
        }

        return (int) $user['current_house_id'];
    }
}
