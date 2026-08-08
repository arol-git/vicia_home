<?php

namespace App\Core;

/**
 * Class Request
 *
 * Encapsule la requête HTTP entrante : méthode, URI, paramètres
 * GET/POST, en-têtes et détection AJAX. Toutes les entrées lues via
 * cette classe sont accessibles brutes ; l'échappement en sortie
 * (affichage HTML) reste à la charge des vues via htmlspecialchars().
 */
class Request
{
    public string $method;
    public string $uri;
    private array $get;
    private array $post;

    public function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->uri    = $this->normalizeUri(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
        $this->get    = $_GET;
        $this->post   = $_POST;

        // Prise en charge des requêtes JSON envoyées en AJAX (fetch API)
        if ($this->isJson()) {
            $decoded = json_decode(file_get_contents('php://input'), true);
            if (is_array($decoded)) {
                $this->post = array_merge($this->post, $decoded);
            }
        }
    }

    /**
     * Ramene l'URI demandee au chemin attendu par le routeur, meme si
     * l'application est installee dans un sous-dossier comme /vicia-home/public.
     */
    private function normalizeUri(string $uri): string
    {
        $path = '/' . ltrim($uri, '/');
        $prefixes = [];

        if (preg_match('#^/public(/|$)#', $path)) {
            $path = '/' . ltrim(substr($path, strlen('/public')), '/');
        }

        if (function_exists('config')) {
            $basePath = parse_url(config('base_url') ?? '', PHP_URL_PATH);
            if ($basePath) {
                $prefixes[] = rtrim($basePath, '/');
                if (basename($basePath) === 'public') {
                    $prefixes[] = rtrim(dirname($basePath), '/');
                }
            }
        }

        $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
        if ($scriptDir && $scriptDir !== '.') {
            $prefixes[] = $scriptDir;
            if (basename($scriptDir) === 'public') {
                $prefixes[] = rtrim(dirname($scriptDir), '/');
            }
        }

        $prefixes = array_unique(array_filter($prefixes, fn($prefix) => $prefix !== ''));
        usort($prefixes, fn($a, $b) => strlen($b) <=> strlen($a));

        foreach ($prefixes as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                $path = substr($path, strlen($prefix)) ?: '/';
                break;
            }
        }

        return '/' . trim($path, '/');
    }

    public function input(string $key, $default = null)
    {
        return $this->post[$key] ?? $this->get[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->get, $this->post);
    }

    public function query(string $key, $default = null)
    {
        return $this->get[$key] ?? $default;
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function isJson(): bool
    {
        return isset($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'application/json');
    }

    /**
     * Détecte si la requête provient d'un appel AJAX (fetch/XHR),
     * utilisé pour décider si la réponse doit être du JSON ou une vue HTML.
     */
    public function isAjax(): bool
    {
        return (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || $this->isJson();
    }

    public function method(): string
    {
        return $this->method;
    }

    public function ip(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public function userAgent(): string
    {
        return substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
    }
}
