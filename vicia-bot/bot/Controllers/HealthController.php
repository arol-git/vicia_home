<?php

namespace Bot\Controllers;

use Bot\Config\App;
use Bot\Core\Controller;

/**
 * Class HealthController
 *
 * Commande de diagnostic /statut : confirme que le bot répond et
 * affiche quelques informations utiles en exploitation (environnement,
 * heure serveur). Sert aussi de premier point de vérification de bout
 * en bout du socle (Router → Controller → Response) lors de la mise
 * en service, avant que les modules métier ne soient déployés.
 */
class HealthController extends Controller
{
    public function index(): void
    {
        $lines = [
            '✅ <b>Vicia Home Bot</b> — opérationnel',
            '',
            'Environnement : ' . (App::isDevelopment() ? 'développement' : 'production'),
            'Heure serveur : ' . date('Y-m-d H:i:s'),
        ];

        $this->respond(implode("\n", $lines));
    }
}
