<?php

namespace App\Core;

/**
 * Class Jwt
 *
 * Implémentation minimaliste de jetons JWT (JSON Web Token, RFC 7519)
 * en HS256, sans dépendance externe — dans la continuité du reste du
 * projet (client MQTT, routeur... écrits sans bibliothèque tierce).
 *
 * Utilisée par l'API REST (api/v1/auth.php) pour émettre des jetons
 * d'accès à courte durée de vie, remplaçant le jeton porteur statique
 * initial. Le secret de signature est la clé applicative
 * (`config('app_key')`), qui doit être une valeur longue et aléatoire
 * en production — jamais la valeur par défaut du dépôt.
 */
class Jwt
{
    /**
     * Encode un tableau de revendications (claims) en un jeton signé,
     * avec une expiration exprimée en secondes à partir de maintenant.
     */
    public static function encode(array $claims, int $ttlSeconds): string
    {
        $header = ['typ' => 'JWT', 'alg' => 'HS256'];
        $claims['iat'] = time();
        $claims['exp'] = time() + $ttlSeconds;

        $segments = [
            self::base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES)),
            self::base64UrlEncode(json_encode($claims, JSON_UNESCAPED_SLASHES)),
        ];

        $signature = self::sign(implode('.', $segments));
        $segments[] = self::base64UrlEncode($signature);

        return implode('.', $segments);
    }

    /**
     * Décode et vérifie un jeton : signature, format, et expiration.
     * Retourne les revendications en cas de succès, ou null si le
     * jeton est invalide, malformé ou expiré — sans jamais lever
     * d'exception, pour laisser l'appelant décider de la réponse HTTP
     * (401 générique, sans détail exploitable par un attaquant).
     */
    public static function decode(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$encodedHeader, $encodedClaims, $encodedSignature] = $parts;

        $expectedSignature = self::sign($encodedHeader . '.' . $encodedClaims);
        $providedSignature = self::base64UrlDecode($encodedSignature);

        if (!hash_equals($expectedSignature, $providedSignature)) {
            return null;
        }

        $claims = json_decode(self::base64UrlDecode($encodedClaims), true);
        if (!is_array($claims) || !isset($claims['exp'])) {
            return null;
        }

        if (time() >= $claims['exp']) {
            return null;
        }

        return $claims;
    }

    private static function sign(string $data): string
    {
        return hash_hmac('sha256', $data, config('app_key'), true);
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string
    {
        $padded = str_pad($data, strlen($data) % 4 === 0 ? strlen($data) : strlen($data) + (4 - strlen($data) % 4), '=');
        return base64_decode(strtr($padded, '-_', '+/'));
    }
}
