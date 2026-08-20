<?php

namespace Bot\Services;

use Bot\Config\App;

/**
 * Class TokenVault
 *
 * Chiffre/déchiffre les jetons API Vicia Home avant leur persistance
 * en base (`telegram_users.access_token_encrypted` /
 * `refresh_token_encrypted`). Algorithme AES-256-GCM (chiffrement
 * authentifié : toute altération du texte chiffré fait échouer le
 * déchiffrement plutôt que de renvoyer un texte corrompu en silence).
 *
 * La clé de chiffrement dérive de APP_KEY (dérivation SHA-256, pour
 * garantir exactement 32 octets quelle que soit la longueur de la
 * valeur fournie dans .env) — jamais stockée telle quelle ailleurs,
 * jamais journalisée.
 */
class TokenVault
{
    private const CIPHER = 'aes-256-gcm';

    /**
     * Chiffre une chaîne en clair. Retourne une chaîne encodée en
     * base64 combinant IV, tag d'authentification et texte chiffré —
     * le format directement stockable en base.
     */
    public static function encrypt(string $plaintext): string
    {
        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        $iv = random_bytes($ivLength);
        $tag = '';

        $ciphertext = openssl_encrypt($plaintext, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag);

        if ($ciphertext === false) {
            throw new \RuntimeException('Échec du chiffrement du jeton.');
        }

        return base64_encode($iv . $tag . $ciphertext);
    }

    /**
     * Déchiffre une chaîne produite par encrypt(). Retourne null en
     * cas d'échec (donnée corrompue, altérée, ou chiffrée avec une
     * autre clé APP_KEY) plutôt que de lever une exception : à
     * l'appelant de traiter cela comme "jeton absent" et de réclamer
     * une nouvelle liaison de compte, sans faire planter le bot.
     */
    public static function decrypt(string $encoded): ?string
    {
        $raw = base64_decode($encoded, true);
        if ($raw === false) {
            return null;
        }

        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        $tagLength = 16; // taille standard du tag GCM en octets

        if (strlen($raw) < $ivLength + $tagLength) {
            return null;
        }

        $iv = substr($raw, 0, $ivLength);
        $tag = substr($raw, $ivLength, $tagLength);
        $ciphertext = substr($raw, $ivLength + $tagLength);

        $plaintext = openssl_decrypt($ciphertext, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag);

        return $plaintext === false ? null : $plaintext;
    }

    private static function key(): string
    {
        return hash('sha256', App::env('BOT_APP_KEY'), true);
    }
}
