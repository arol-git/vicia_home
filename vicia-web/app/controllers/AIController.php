<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Response;
use App\Core\Validator;
use App\Models\Conversation;
use App\Services\AIService;

/**
 * Class AIController
 *
 * Contrôleur du module "Vicia Home AI" : affiche l'interface de
 * conversation et traite chaque message envoyé (texte ou transcrit
 * depuis la reconnaissance vocale du navigateur — le serveur ne fait
 * aucune distinction entre les deux, le texte lui arrive déjà
 * transcrit). Réutilise Auth::requireHouseRole() exactement comme les
 * autres contrôleurs de la plateforme : l'assistant n'a jamais plus
 * de droits que l'utilisateur qui lui parle.
 */
class AIController extends Controller
{
    public function index(): void
    {
        Auth::requireLogin();
        $houseId = Auth::currentHouseId();

        $conversation = $houseId ? Conversation::currentFor(Auth::id(), $houseId) : null;
        $history = $conversation ? Conversation::recentMessages($conversation['id'], 30) : [];

        $this->render('ai/index', [
            'title'   => 'Vicia Home AI',
            'history' => $history,
        ]);
    }

    /**
     * Point d'entrée AJAX : reçoit un message utilisateur, retourne
     * la réponse de l'assistant au format JSON.
     */
    public function send(): void
    {
        Auth::requireLogin();
        $this->verifyCsrf();

        $message = trim((string) $this->request->input('message'));

        $validator = new Validator(['message' => $message]);
        if ($validator->rules(['message' => 'required|min:1|max:2000'])->fails()) {
            Response::error('Message invalide.', 422);
            return;
        }

        $houseId = Auth::currentHouseId();

        $result = AIService::handle(Auth::id(), $houseId, $message);

        Response::json(array_merge(['success' => true], $result));
    }

    /**
     * Efface la conversation active (nouveau départ), sans toucher à
     * la mémoire longue (App\Services\ConversationMemory), qui reste
     * volontairement persistante d'une conversation à l'autre.
     */
    public function reset(): void
    {
        Auth::requireLogin();
        $this->verifyCsrf();

        $houseId = Auth::currentHouseId();
        $conversation = Conversation::currentFor(Auth::id(), $houseId);
        Conversation::update($conversation['id'], ['status' => 'archived']);

        Response::success('Nouvelle conversation démarrée.');
    }
}
