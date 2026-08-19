<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Response;
use App\Models\ActivityLog;
use App\Models\Setting;

/**
 * Class SettingsController
 *
 * Paramètres généraux de la plateforme : nom du site, thème par
 * défaut, configuration des notifications Telegram et e-mail.
 * Réservé aux administrateurs.
 */
class SettingsController extends Controller
{
    public function index(): void
    {
        Auth::requireLogin();
        $settings = Setting::all();

        $this->render('settings/index', ['title' => 'Paramètres', 'settings' => $settings]);
    }

    public function update(): void
    {
        Auth::requireRole(['admin']);
        $this->verifyCsrf();

        $editable = [
            'site_name',
            'theme_mode',
            'telegram_bot_token',
            'smtp_host',
            'smtp_port',
            'smtp_encryption',
            'smtp_username',
            'smtp_password',
            'smtp_from',
            'smtp_from_name',
        ];

        foreach ($editable as $key) {
            if ($this->request->input($key) !== null) {
                Setting::set($key, (string) $this->request->input($key));
            }
        }

        ActivityLog::record(Auth::id(), 'modification_parametres', 'Mise à jour des paramètres généraux de la plateforme', $this->request->ip());

        Response::success('Paramètres enregistrés avec succès.');
    }
}
