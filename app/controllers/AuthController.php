<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Models\ActivityLog;
use App\Models\LoginLog;
use App\Models\User;

/**
 * Class AuthController
 *
 * Gère l'ensemble du cycle d'authentification : affichage du
 * formulaire de connexion, traitement de la connexion, déconnexion,
 * et procédure de mot de passe oublié / réinitialisation.
 */
class AuthController extends Controller
{
    /**
     * Affiche le formulaire de connexion. Redirige vers le tableau de
     * bord si l'utilisateur est déjà authentifié.
     */
    public function showLogin(): void
    {
        if (Auth::check()) {
            Response::redirect('/dashboard');
        }
        $this->render('auth/login', ['title' => 'Connexion'], false);
    }

    /**
     * Traite la soumission du formulaire de connexion.
     */
    public function login(): void
    {
        $this->verifyCsrf();

        $email    = trim((string) $this->request->input('email'));
        $password = (string) $this->request->input('password');
        $remember = (bool) $this->request->input('remember');
        $ip       = $this->request->ip();

        // Protection contre la force brute : blocage temporaire après
        // 5 échecs en moins de 15 minutes pour la même adresse IP.
        if (LoginLog::recentFailures($ip) >= 5) {
            $message = 'Trop de tentatives échouées. Merci de réessayer dans quelques minutes.';
            LoginLog::record(null, $email, $ip, $this->request->userAgent(), 'failed');
            $this->respondLoginError($message);
            return;
        }

        $validator = new Validator(['email' => $email, 'password' => $password]);
        $validator->rules(['email' => 'required|email', 'password' => 'required']);

        if ($validator->fails()) {
            $this->respondLoginError('Merci de renseigner un e-mail et un mot de passe valides.');
            return;
        }

        $user = Auth::attempt($email, $password, $remember);

        if (!$user) {
            LoginLog::record(null, $email, $ip, $this->request->userAgent(), 'failed');
            $this->respondLoginError('Identifiants incorrects.');
            return;
        }

        LoginLog::record($user['id'], $email, $ip, $this->request->userAgent(), 'success');
        ActivityLog::record($user['id'], 'connexion', 'Connexion réussie à la plateforme', $ip);

        if ($this->request->isAjax()) {
            Response::success('Connexion réussie.', ['redirect' => url('/dashboard')]);
        }
        Response::redirect('/dashboard');
    }

    private function respondLoginError(string $message): void
    {
        if ($this->request->isAjax()) {
            Response::error($message, 401);
        }
        Session::flash('error', $message);
        Response::redirect('/login');
    }

    /**
     * Déconnecte l'utilisateur courant.
     */
    public function logout(): void
    {
        if (Auth::check()) {
            ActivityLog::record(Auth::id(), 'deconnexion', 'Déconnexion de la plateforme', $this->request->ip());
        }
        Auth::logout();
        Response::redirect('/login');
    }

    /**
     * Affiche le formulaire de demande de réinitialisation de mot de passe.
     */
    public function showForgotPassword(): void
    {
        $this->render('auth/forgot-password', ['title' => 'Mot de passe oublié'], false);
    }

    /**
     * Génère un jeton de réinitialisation et l'envoie par e-mail
     * (l'envoi réel est délégué à App\Helpers\Mailer ; en environnement
     * de développement, le lien est simplement journalisé).
     */
    public function sendResetLink(): void
    {
        $this->verifyCsrf();
        $email = trim((string) $this->request->input('email'));

        $user = User::findByEmail($email);

        // Par mesure de sécurité, on affiche toujours le même message,
        // que l'adresse existe ou non, afin de ne pas révéler la liste
        // des comptes enregistrés (prévention de l'énumération de comptes).
        $genericMessage = "Si cette adresse est associée à un compte, un lien de réinitialisation vient de lui être envoyé.";

        if ($user) {
            $token = bin2hex(random_bytes(32));
            \App\Core\Database::query(
                'INSERT INTO password_resets (email, token, expires_at) VALUES (:email, :token, :expires)',
                [
                    'email'   => $email,
                    'token'   => hash('sha256', $token),
                    'expires' => date('Y-m-d H:i:s', time() + 3600),
                ]
            );

            $resetLink = url('/reset-password?token=' . $token . '&email=' . urlencode($email));
            \App\Helpers\Mailer::send($email, 'Réinitialisation de votre mot de passe Vicia Home',
                "Bonjour,\n\nVoici votre lien de réinitialisation (valable 1 heure) :\n$resetLink\n\nSi vous n'êtes pas à l'origine de cette demande, ignorez ce message.");
        }

        Session::flash('success', $genericMessage);
        Response::redirect('/forgot-password');
    }

    public function showResetPassword(): void
    {
        $token = $this->request->query('token', '');
        $email = $this->request->query('email', '');
        $this->render('auth/reset-password', [
            'title' => 'Réinitialisation du mot de passe',
            'token' => $token,
            'email' => $email,
        ], false);
    }

    public function resetPassword(): void
    {
        $this->verifyCsrf();

        $email    = (string) $this->request->input('email');
        $token    = (string) $this->request->input('token');
        $password = (string) $this->request->input('password');

        $validator = new Validator(['password' => $password, 'password_confirmation' => $this->request->input('password_confirmation')]);
        $validator->rules(['password' => 'required|min:8|confirmed']);

        if ($validator->fails()) {
            Session::flash('error', 'Le mot de passe doit contenir au moins 8 caractères et être confirmé correctement.');
            Response::redirect('/reset-password?token=' . urlencode($token) . '&email=' . urlencode($email));
            return;
        }

        $hashedToken = hash('sha256', $token);
        $reset = \App\Core\Database::query(
            'SELECT * FROM password_resets WHERE email = :email AND token = :token AND used = 0 AND expires_at >= NOW() ORDER BY id DESC LIMIT 1',
            ['email' => $email, 'token' => $hashedToken]
        )->fetch();

        if (!$reset) {
            Session::flash('error', 'Ce lien de réinitialisation est invalide ou a expiré.');
            Response::redirect('/forgot-password');
            return;
        }

        $user = User::findByEmail($email);
        if ($user) {
            User::updatePassword($user['id'], $password);
            \App\Core\Database::query('UPDATE password_resets SET used = 1 WHERE id = :id', ['id' => $reset['id']]);
            ActivityLog::record($user['id'], 'reinitialisation_mdp', 'Mot de passe réinitialisé via le lien reçu par e-mail', $this->request->ip());
        }

        Session::flash('success', 'Votre mot de passe a été réinitialisé avec succès. Vous pouvez maintenant vous connecter.');
        Response::redirect('/login');
    }
}
