<?php

namespace App\Core;

/**
 * Class Response
 *
 * Petites fonctions utilitaires pour émettre des réponses HTTP
 * homogènes (JSON pour l'API/AJAX, redirections pour les vues).
 */
class Response
{
    /**
     * Émet une réponse JSON normalisée puis termine le script.
     *
     * @param mixed $data       Données à sérialiser
     * @param int   $statusCode Code de statut HTTP
     */
    public static function json($data, int $statusCode = 200): void
    {
        if (ob_get_length() !== false) {
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }
        }

        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            app_log('[Response] Encodage JSON impossible : ' . json_last_error_msg());
            http_response_code(500);
            $json = json_encode([
                'success' => false,
                'message' => 'Réponse serveur impossible à encoder en JSON.',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        echo $json;
        exit;
    }

    public static function success(string $message, array $extra = [], int $statusCode = 200): void
    {
        self::json(array_merge(['success' => true, 'message' => $message], $extra), $statusCode);
    }

    public static function error(string $message, int $statusCode = 400, array $extra = []): void
    {
        self::json(array_merge(['success' => false, 'message' => $message], $extra), $statusCode);
    }

    /**
     * Redirige le navigateur vers une autre URL de l'application.
     */
    public static function redirect(string $path): void
    {
        // Use the `url()` helper to build redirect targets so that
        // base_url normalization and trailing slashes are handled
        // consistently across environments.
        header('Location: ' . url($path));
        exit;
    }
}
