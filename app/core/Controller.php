<?php

namespace App\Core;

/**
 * Class Controller
 *
 * Classe de base pour tous les contrôleurs de l'application. Fournit
 * les fonctions communes de rendu de vue et d'accès à la requête.
 */
abstract class Controller
{
    protected Request $request;

    public function __construct()
    {
        $this->request = new Request();
    }

    /**
     * Affiche une vue en l'enveloppant dans le layout principal
     * (en-tête, menu latéral, pied de page), sauf pour les pages
     * d'authentification qui utilisent leur propre mise en page.
     *
     * @param string $view   Chemin de la vue relatif à app/views (sans .php)
     * @param array  $data   Données transmises à la vue (extraites en variables)
     * @param bool   $layout Indique si le layout principal doit englober la vue
     */
    protected function render(string $view, array $data = [], bool $layout = true): void
    {
        extract($data);
        $viewFile = __DIR__ . '/../views/' . $view . '.php';

        if (!file_exists($viewFile)) {
            http_response_code(500);
            die("Erreur : la vue '$view' est introuvable.");
        }

        if ($layout) {
            $currentUser = Auth::user();
            require __DIR__ . '/../views/layout/header.php';
            require __DIR__ . '/../views/layout/sidebar.php';
            echo '<main class="main-content">';
            require $viewFile;
            echo '</main>';
            require __DIR__ . '/../views/layout/footer.php';
        } else {
            require $viewFile;
        }
    }

    /**
     * Redirige avec un message flash de succès.
     */
    protected function redirectWithSuccess(string $path, string $message): void
    {
        Session::flash('success', $message);
        Response::redirect($path);
    }

    /**
     * Redirige avec un message flash d'erreur.
     */
    protected function redirectWithError(string $path, string $message): void
    {
        Session::flash('error', $message);
        Response::redirect($path);
    }

    /**
     * Vérifie le jeton CSRF de la requête courante ; interrompt la
     * requête avec une erreur 419 en cas d'échec.
     */
    protected function verifyCsrf(): void
    {
        $token = $this->request->input('csrf_token');
        if (!Csrf::verify($token)) {
            if ($this->request->isAjax()) {
                Response::error('Jeton de sécurité invalide ou expiré. Merci de recharger la page.', 419);
            }
            http_response_code(419);
            die('Jeton de sécurité invalide ou expiré. Merci de recharger la page.');
        }
    }
}
