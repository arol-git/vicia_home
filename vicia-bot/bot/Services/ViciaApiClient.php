<?php

namespace Bot\Services;

use Bot\Config\App;
use Bot\Core\Exceptions\ApiException;
use Bot\Core\Logger;
use Bot\Models\TelegramUser;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Class ViciaApiClient
 *
 * Unique point de sortie HTTP du bot vers l'API REST Vicia Home (voir
 * l'architecture retenue dans l'analyse préalable : le bot ne touche
 * jamais `vicia_home` autrement que par cette voie). Gère
 * automatiquement le rafraîchissement du jeton d'accès JWT expiré, de
 * façon transparente pour l'appelant : un contrôleur n'a jamais à se
 * soucier de l'expiration d'un jeton.
 *
 * Deux modes d'utilisation :
 *   - ViciaApiClient::guest()                      pour /auth/login (aucun compte lié requis)
 *   - ViciaApiClient::forTelegramUser($telegramId)  pour tout le reste (jeton géré automatiquement)
 */
class ViciaApiClient
{
    private Client $http;

    private function __construct(private readonly ?int $telegramId, private ?array $telegramUser)
    {
        $this->http = new Client([
            'base_uri'    => rtrim(App::env('VICIA_API_BASE_URL'), '/') . '/',
            'timeout'     => 10,
            'http_errors' => false, // gestion manuelle des codes d'erreur, voir send()
        ]);
    }

    public static function guest(): self
    {
        return new self(null, null);
    }

    public static function forTelegramUser(int $telegramId): self
    {
        $telegramUser = TelegramUser::findByTelegramId($telegramId);

        if (!$telegramUser) {
            throw \Bot\Core\Exceptions\UnauthorizedException::accountNotLinked();
        }

        return new self($telegramId, $telegramUser);
    }

    // ---------------------------------------------------------------
    // Authentification
    // ---------------------------------------------------------------

    /**
     * Authentifie un utilisateur auprès de l'API Vicia Home. Ne
     * nécessite aucun compte lié — c'est justement cet appel qui en
     * crée un (voir Bot\Controllers\StartController, module suivant).
     *
     * @return array{access_token: string, refresh_token: string, expires_in: int, user: array}
     */
    public function login(string $email, string $password): array
    {
        $result = $this->send('POST', 'auth/login', [
            'json' => ['email' => $email, 'password' => $password],
        ], authenticated: false);

        // L'API Vicia Home actuelle retourne { token, user }.
        // Le bot, lui, travaille en interne avec access_token /
        // refresh_token pour rester compatible avec une future API JWT.
        // On normalise donc la réponse ici, au bord du système.
        if (!empty($result['token']) && empty($result['access_token'])) {
            $result['access_token'] = $result['token'];
            $result['refresh_token'] = '';
            $result['expires_in'] = (int) App::env('VICIA_API_TOKEN_TTL_SECONDS', 2592000);
        }

        return $result;
    }

    // ---------------------------------------------------------------
    // Requêtes authentifiées génériques
    // ---------------------------------------------------------------

    public function get(string $path, array $query = []): array
    {
        return $this->send('GET', $path, ['query' => $query]);
    }

    public function post(string $path, array $body = []): array
    {
        return $this->send('POST', $path, ['json' => $body]);
    }

    public function put(string $path, array $body = []): array
    {
        return $this->send('PUT', $path, ['json' => $body]);
    }

    public function delete(string $path, array $body = []): array
    {
        return $this->send('DELETE', $path, ['json' => $body]);
    }

    /**
     * Cœur de la logique d'appel : attache le jeton d'accès, rafraîchit
     * automatiquement une seule fois en cas de 401, puis relance
     * l'appel d'origine. Toute erreur restante est traduite en
     * ApiException avec un message adapté au contexte (voir
     * Bot\Core\ErrorHandler pour la traduction finale vers l'utilisateur).
     */
    private function send(string $method, string $path, array $options, bool $authenticated = true, bool $isRetry = false): array
    {
        if ($authenticated) {
            $options['headers']['Authorization'] = 'Bearer ' . $this->resolveAccessToken();
        }

        try {
            $response = $this->requestWithNetworkRetry($method, $path, $options);
        } catch (ConnectException $e) {
            Logger::channel('bot')->error("Connexion à l'API Vicia Home impossible : " . $e->getMessage());
            throw new ApiException(
                "La plateforme Vicia Home est momentanément injoignable.",
                503,
                'Connexion API échouée : ' . $e->getMessage()
            );
        } catch (GuzzleException $e) {
            Logger::channel('bot')->error("Erreur de requête API Vicia Home : " . $e->getMessage());
            throw new ApiException("Une erreur de communication est survenue.", 500, $e->getMessage());
        }

        $status = $response->getStatusCode();
        $body = json_decode((string) $response->getBody(), true) ?? [];

        // Jeton expiré ou invalide : on tente un rafraîchissement
        // UNE seule fois (isRetry évite toute boucle en cas de refus
        // persistant), puis on rejoue l'appel d'origine avec le
        // nouveau jeton.
        if ($status === 401 && $authenticated && !$isRetry && $this->telegramId !== null) {
            if ($this->refreshAccessToken()) {
                return $this->send($method, $path, $options, $authenticated, isRetry: true);
            }
        }

        if ($status >= 400) {
            throw new ApiException(
                $this->userMessageFor($status, $body),
                $status,
                "Réponse API {$status} sur {$method} {$path} : " . ($body['message'] ?? 'sans détail')
            );
        }

        return $body;
    }

    private function requestWithNetworkRetry(string $method, string $path, array $options): \Psr\Http\Message\ResponseInterface
    {
        try {
            return $this->http->request($method, ltrim($path, '/'), $options);
        } catch (ConnectException $firstError) {
            usleep(250000);

            try {
                return $this->http->request($method, ltrim($path, '/'), $options);
            } catch (ConnectException $secondError) {
                $publicBase = rtrim((string) App::env('RAILWAY_SERVICE_VICIA_HOME_URL', ''), '/');
                if ($publicBase !== '' && !preg_match('#^https?://#i', $publicBase)) {
                    $publicBase = 'https://' . $publicBase;
                }
                if ($publicBase === '' || !str_contains((string) App::env('VICIA_API_BASE_URL', ''), '.railway.internal')) {
                    throw $secondError;
                }

                $fallback = new Client([
                    'base_uri' => $publicBase . '/api/v1/',
                    'timeout' => 15,
                    'connect_timeout' => 8,
                    'http_errors' => false,
                ]);
                return $fallback->request($method, ltrim($path, '/'), $options);
            }
        }
    }

    private function userMessageFor(int $status, array $body): string
    {
        return match (true) {
            $status === 401 => "Votre session a expiré. Envoyez /start pour vous reconnecter.",
            $status === 403 => $body['message'] ?? "Vous n'avez pas les droits nécessaires pour cette action.",
            $status === 404 => "Ressource introuvable.",
            $status === 409 => $body['message'] ?? "Cette action n'est pas possible dans l'état actuel.",
            $status === 422 => $body['message'] ?? "Certaines informations saisies sont invalides.",
            default          => "La plateforme Vicia Home a rencontré une erreur. Merci de réessayer.",
        };
    }

    private function resolveAccessToken(): string
    {
        if (TelegramUser::isAccessTokenExpired($this->telegramUser)) {
            if (!$this->refreshAccessToken()) {
                // Le jeton d'accès est expiré ET son rafraîchissement a
                // échoué (jeton de rafraîchissement lui-même expiré ou
                // révoqué) : inutile de tenter l'appel avec un jeton
                // que l'on sait déjà mort, on demande directement une
                // nouvelle connexion complète.
                throw new ApiException(
                    "Votre session a expiré. Envoyez /start pour vous reconnecter.",
                    401,
                    "Échec du rafraîchissement proactif pour telegram_id={$this->telegramId}"
                );
            }
        }

        $token = TelegramUser::getAccessToken($this->telegramUser);

        if (!$token) {
            throw \Bot\Core\Exceptions\UnauthorizedException::accountNotLinked();
        }

        return $token;
    }

    /**
     * Échange le jeton de rafraîchissement stocké contre une nouvelle
     * paire de jetons, et les persiste aussitôt (rotation : l'ancien
     * refresh_token devient inutilisable dès cet appel côté API).
     * Retourne false si le rafraîchissement lui-même échoue (jeton de
     * rafraîchissement expiré ou révoqué) — dans ce cas, seul un
     * nouveau /start (nouvelle connexion complète) peut résoudre la
     * situation.
     */
    private function refreshAccessToken(): bool
    {
        $refreshToken = TelegramUser::getRefreshToken($this->telegramUser);
        if (!$refreshToken) {
            return false;
        }

        // L'API actuelle ne possède pas encore /auth/refresh. Si aucun
        // refresh token n'est stocké, on laisse le flux demander un
        // nouveau /start lorsque le token local est considéré expiré.
        try {
            $result = $this->send('POST', 'auth/refresh', ['json' => ['refresh_token' => $refreshToken]], authenticated: false);
        } catch (ApiException) {
            return false;
        }

        if (empty($result['access_token'])) {
            return false;
        }

        TelegramUser::updateTokens($this->telegramId, $result['access_token'], $result['refresh_token'], $result['expires_in']);
        $this->telegramUser = TelegramUser::findByTelegramId($this->telegramId);

        return true;
    }
}
