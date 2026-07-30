<?php

namespace Bot\Controllers;

use Bot\Core\Controller;

/**
 * Class HelpController
 *
 * /aide — liste des commandes disponibles. Mise à jour au fil des
 * modules livrés (ajouter une ligne ici à chaque nouvelle commande).
 */
class HelpController extends Controller
{
    public function index(): void
    {
        $lines = [
            "❓ <b>Commandes disponibles</b>",
            "",
            "/start — Lier ou retrouver votre compte Vicia Home",
            "/menu — Ouvrir le menu principal",
            "/maisons — Changer de maison active",
            "/moncompte — Aperçu de votre compte",
            "/delier — Délier votre compte Telegram",
            "/historique — Derniers événements de la maison",
            "/rapport — Recevoir un rapport PDF de la maison",
            "/parametres — Paramètres du compte",
            "/statut — Vérifier que le bot répond",
            "/aide — Afficher ce message",
        ];

        $this->respond(implode("\n", $lines));
    }
}
