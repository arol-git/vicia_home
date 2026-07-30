<?php

namespace Bot\Models;

use Bot\Core\Model;
use Bot\Services\TokenVault;

/**
 * Class TelegramUser
 *
 * Liaison entre un compte Telegram et un compte Vicia Home. Porte
 * également la persistance chiffrée des jetons API (voir
 * Bot\Services\TokenVault) et la maison actuellement sélectionnée.
 *
 * Le contrôleur de liaison de compte à proprement parler
 * (StartController, formulaire e-mail/mot de passe) est traité au
 * module suivant : ce modèle n'est que la couche de persistance,
 * nécessaire dès ce module-ci puisque Bot\Services\ViciaApiClient en
 * dépend pour la gestion automatique du rafraîchissement de jeton.
 */
class TelegramUser extends Model
{
    protected static string $table = 'telegram_users';

    public static function findByTelegramId(int $telegramId): ?array
    {
        return self::findOneBy('telegram_id', $telegramId);
    }

    /**
     * Crée ou met à jour la liaison d'un utilisateur Telegram, en
     * chiffrant les jetons avant stockage.
     */
    public static function link(int $telegramId, ?string $username, int $viciaUserId, string $accessToken, string $refreshToken, int $expiresIn): int
    {
        $existing = self::findByTelegramId($telegramId);

        $data = [
            'telegram_username'       => $username,
            'vicia_user_id'           => $viciaUserId,
            'access_token_encrypted'  => TokenVault::encrypt($accessToken),
            'refresh_token_encrypted' => TokenVault::encrypt($refreshToken),
            'token_expires_at'        => date('Y-m-d H:i:s', time() + $expiresIn),
        ];

        if ($existing) {
            self::update($existing['id'], $data);
            return $existing['id'];
        }

        $data['telegram_id'] = $telegramId;
        return self::create($data);
    }

    /**
     * Met à jour uniquement les jetons (appelé après un
     * rafraîchissement réussi par ViciaApiClient), sans toucher au
     * reste de la liaison de compte.
     */
    public static function updateTokens(int $telegramId, string $accessToken, string $refreshToken, int $expiresIn): void
    {
        $user = self::findByTelegramId($telegramId);
        if (!$user) {
            return;
        }

        self::update($user['id'], [
            'access_token_encrypted'  => TokenVault::encrypt($accessToken),
            'refresh_token_encrypted' => TokenVault::encrypt($refreshToken),
            'token_expires_at'        => date('Y-m-d H:i:s', time() + $expiresIn),
        ]);
    }

    public static function getAccessToken(array $telegramUser): ?string
    {
        return $telegramUser['access_token_encrypted'] ? TokenVault::decrypt($telegramUser['access_token_encrypted']) : null;
    }

    public static function getRefreshToken(array $telegramUser): ?string
    {
        return $telegramUser['refresh_token_encrypted'] ? TokenVault::decrypt($telegramUser['refresh_token_encrypted']) : null;
    }

    /**
     * Indique si le jeton d'accès est expiré (ou expire dans moins de
     * 30 secondes — marge de sécurité pour éviter qu'il n'expire
     * pendant le trajet réseau de la requête elle-même).
     */
    public static function isAccessTokenExpired(array $telegramUser): bool
    {
        if (!$telegramUser['token_expires_at']) {
            return true;
        }
        return strtotime($telegramUser['token_expires_at']) - 30 <= time();
    }

    public static function setCurrentHouse(int $telegramId, int $houseId): void
    {
        $user = self::findByTelegramId($telegramId);
        if ($user) {
            self::update($user['id'], ['current_house_id' => $houseId]);
        }
    }

    /**
     * Délie un compte Telegram (UC-02) : supprime la liaison et les
     * jetons associés. Ne touche à rien côté vicia_home.
     */
    public static function unlink(int $telegramId): void
    {
        self::deleteWhere('telegram_id', $telegramId);
    }

    /**
     * Retourne tous les utilisateurs Telegram ayant actuellement
     * sélectionné la maison donnée — utilisé pour diffuser une
     * notification poussée à tous les résidents concernés (voir
     * Bot\Services\NotificationDispatcher).
     */
    public static function allForHouse(int $houseId): array
    {
        return self::where('current_house_id', $houseId);
    }
}
