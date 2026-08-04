<?php

namespace App\Core;

/**
 * Class Router
 *
 * Routeur minimaliste associant une méthode HTTP et un motif d'URI à
 * un couple [Contrôleur, méthode]. Prend en charge les paramètres
 * dynamiques dans l'URI, notés {param}.
 */
class Router
{
    private array $routes = [];

    public function get(string $uri, string $handler): void
    {
        $this->add('GET', $uri, $handler);
    }

    public function post(string $uri, string $handler): void
    {
        $this->add('POST', $uri, $handler);
    }

    public function put(string $uri, string $handler): void
    {
        $this->add('PUT', $uri, $handler);
    }

    public function delete(string $uri, string $handler): void
    {
        $this->add('DELETE', $uri, $handler);
    }

    private function add(string $method, string $uri, string $handler): void
    {
        $pattern = preg_replace('#\{[a-zA-Z_]+\}#', '([^/]+)', trim($uri, '/'));
        $this->routes[] = [
            'method'  => $method,
            'pattern' => '#^' . $pattern . '$#',
            'handler' => $handler,
        ];
    }

    /**
     * Résout la requête courante et invoque le contrôleur correspondant.
     * Prend en charge la surcharge de méthode via le champ caché
     * "_method" (utilisé par les formulaires HTML qui ne connaissent
     * que GET/POST nativement).
     */
    public function dispatch(Request $request): void
    {
        $method = $request->method();
        if ($method === 'POST' && $request->input('_method')) {
            $method = strtoupper($request->input('_method'));
        }

        $uri = trim($request->uri, '/');

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (preg_match($route['pattern'], $uri, $matches)) {
                array_shift($matches);
                [$controllerName, $action] = explode('@', $route['handler']);
                $controllerClass = 'App\\Controllers\\' . $controllerName;

                if (!class_exists($controllerClass)) {
                    $this->abort($request, 500, "Contrôleur introuvable : $controllerClass");
                }

                $controller = new $controllerClass();

                if (!method_exists($controller, $action)) {
                    $this->abort($request, 500, "Action introuvable : $controllerClass::$action");
                }

                call_user_func_array([$controller, $action], $matches);
                return;
            }
        }

        $this->abort($request, 404, 'Page introuvable');
    }

    private function abort(Request $request, int $code, string $message): void
    {
        if ($request->isAjax()) {
            Response::error($message, $code);
            return;
        }

        http_response_code($code);
        $viewFile = __DIR__ . '/../views/errors/' . $code . '.php';
        if (file_exists($viewFile)) {
            require $viewFile;
        } else {
            echo "<h1>$code</h1><p>" . htmlspecialchars($message) . '</p>';
        }
        exit;
    }
}
