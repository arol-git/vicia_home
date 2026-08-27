<?php

namespace App\Controllers;

use App\Core\Controller;

/**
 * Pages d'information accessibles sans authentification.
 */
class PublicController extends Controller
{
    public function guide(): void
    {
        $this->render('public/guide', ['title' => "Guide d'utilisation"], false);
    }

    public function privacy(): void
    {
        $this->render('public/privacy', ['title' => 'Politique de confidentialité'], false);
    }
}
