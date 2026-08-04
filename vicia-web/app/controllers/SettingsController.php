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
 * Paramètres GLOBAUX de la plateforme uniquement : nom du site,
 * thème par défaut, serveur SMTP partagé. Réservé aux administrateurs
 * de plateforme. Les notifications Telegram et l'e-mail d'alerte sont
 * désormais propres à chaque maison (voir HouseController::update et
 * la table `houses`), car chaque client dispose de son propre canal
 * de notification.
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

        $editable = ['site_name', 'theme_mode', 'smtp_host', 'smtp_from'];

        foreach ($editable as $key) {
            if ($this->request->input($key) !== null) {
                $value = (string) $this->request->input($key);
                Database::query(
                    'INSERT INTO settings (setting_key, setting_value) VALUES (:key, :value)
                     ON DUPLICATE KEY UPDATE setting_value = :value_update',
                    ['key' => $key, 'value' => $value, 'value_update' => $value]
                );
            }
        }

        ActivityLog::record(Auth::id(), 'modification_parametres', 'Mise à jour des paramètres généraux de la plateforme', $this->request->ip());

        Response::success('Paramètres enregistrés avec succès.');
    }
}
