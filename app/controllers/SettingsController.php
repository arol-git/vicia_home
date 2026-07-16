<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Response;
use App\Models\ActivityLog;

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
        Auth::requireRole(['admin']);
        $rows = Database::query('SELECT setting_key, setting_value FROM settings')->fetchAll();
        $settings = array_column($rows, 'setting_value', 'setting_key');

        $this->render('settings/index', ['title' => 'Paramètres', 'settings' => $settings]);
    }

    public function update(): void
    {
        Auth::requireRole(['admin']);
        $this->verifyCsrf();

        $editable = ['site_name', 'theme_mode', 'telegram_bot_token', 'telegram_chat_id', 'smtp_host', 'smtp_from'];

        foreach ($editable as $key) {
            if ($this->request->input($key) !== null) {
                Database::query(
                    'INSERT INTO settings (setting_key, setting_value) VALUES (:key, :value)
                     ON DUPLICATE KEY UPDATE setting_value = :value',
                    ['key' => $key, 'value' => (string) $this->request->input($key)]
                );
            }
        }

        ActivityLog::record(Auth::id(), 'modification_parametres', 'Mise à jour des paramètres généraux de la plateforme', $this->request->ip());

        Response::success('Paramètres enregistrés avec succès.');
    }
}
