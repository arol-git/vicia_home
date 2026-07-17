<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Response;
use App\Core\Validator;
use App\Models\ActivityLog;
use App\Models\House;
use App\Models\User;

/**
 * Class HouseController
 *
 * Gère les maisons de la plateforme : création (onboarding d'un
 * nouveau client, qui en devient propriétaire), modification,
 * suppression, gestion des membres, et bascule de la maison
 * actuellement sélectionnée en session.
 *
 * Contrairement aux autres contrôleurs, les vérifications de droits
 * portent ici sur la maison CIBLÉE par chaque action (passée en
 * paramètre), et non sur la maison actuellement sélectionnée en
 * session — un propriétaire doit pouvoir gérer une maison sans
 * l'avoir préalablement sélectionnée.
 */
class HouseController extends Controller
{
    /**
     * Liste les maisons accessibles à l'utilisateur connecté
     * (toutes les maisons pour un administrateur de plateforme).
     */
    public function index(): void
    {
        Auth::requireLogin();
        $user = Auth::user();
        $houses = House::forUser($user['id'], $user['role']);
        $houses = array_map(fn($h) => House::withCounts($h['id']) + $h, $houses);

        $this->render('houses/index', ['title' => 'Mes maisons', 'houses' => $houses]);
    }

    /**
     * Crée une nouvelle maison. Tout utilisateur authentifié peut en
     * créer une : il en devient automatiquement propriétaire (owner).
     * C'est le point d'entrée d'onboarding d'un nouveau client.
     */
    public function store(): void
    {
        Auth::requireLogin();
        $this->verifyCsrf();

        $data = [
            'name'    => trim((string) $this->request->input('name')),
            'address' => trim((string) $this->request->input('address', '')),
            'city'    => trim((string) $this->request->input('city', '')),
        ];

        $validator = new Validator($data);
        $validator->rules(['name' => 'required|min:2|max:150']);
        if ($validator->fails()) {
            Response::error($validator->firstError(), 422, ['errors' => $validator->errors()]);
            return;
        }

        $data['slug'] = House::generateSlug($data['name']);
        $houseId = House::create($data);

        House::addMember($houseId, Auth::id(), 'owner');
        Auth::switchHouse($houseId);

        ActivityLog::record(Auth::id(), 'creation_maison', "Création de la maison « {$data['name']} »", $this->request->ip(), $houseId);

        Response::success('Maison créée avec succès.', ['id' => $houseId, 'slug' => $data['slug']]);
    }

    public function update(int $id): void
    {
        $this->requireOwnerOrAdmin($id);
        $this->verifyCsrf();

        $data = [
            'name'               => trim((string) $this->request->input('name')),
            'address'            => trim((string) $this->request->input('address', '')),
            'city'               => trim((string) $this->request->input('city', '')),
            'telegram_bot_token' => trim((string) $this->request->input('telegram_bot_token', '')),
            'telegram_chat_id'   => trim((string) $this->request->input('telegram_chat_id', '')),
            'alert_email'        => trim((string) $this->request->input('alert_email', '')),
        ];

        $validator = new Validator($data);
        $validator->rules(['name' => 'required|min:2|max:150']);
        if ($validator->fails()) {
            Response::error($validator->firstError(), 422, ['errors' => $validator->errors()]);
            return;
        }

        House::update($id, $data);
        ActivityLog::record(Auth::id(), 'modification_maison', "Modification de la maison « {$data['name']} »", $this->request->ip(), $id);

        Response::success('Maison mise à jour avec succès.');
    }

    public function destroy(int $id): void
    {
        $this->requireOwnerOrAdmin($id);
        $this->verifyCsrf();

        $house = House::find($id);
        House::delete($id); // CASCADE en base : pièces, équipements, capteurs, etc.
        ActivityLog::record(Auth::id(), 'suppression_maison', "Suppression de la maison « {$house['name']} »", $this->request->ip());

        Response::success('Maison supprimée avec succès.');
    }

    /**
     * Change la maison actuellement sélectionnée en session.
     */
    public function switchHouse(int $id): void
    {
        Auth::requireLogin();
        $this->verifyCsrf();

        if (!Auth::switchHouse($id)) {
            Response::error('Vous n’avez pas accès à cette maison.', 403);
            return;
        }

        Response::success('Maison sélectionnée.', ['redirect' => url('/dashboard')]);
    }

    /**
     * Liste les membres d'une maison.
     */
    public function members(int $id): void
    {
        $this->requireOwnerOrAdmin($id);
        $house = House::find($id);
        $members = House::members($id);

        $this->render('houses/members', ['title' => 'Membres — ' . $house['name'], 'house' => $house, 'members' => $members]);
    }

    /**
     * Ajoute un utilisateur existant (par e-mail) comme membre d'une
     * maison, avec un rôle propre à cette maison.
     */
    public function addMember(int $id): void
    {
        $this->requireOwnerOrAdmin($id);
        $this->verifyCsrf();

        $email = trim((string) $this->request->input('email'));
        $role  = (string) $this->request->input('role_in_house', 'resident');

        $validator = new Validator(['email' => $email, 'role' => $role]);
        $validator->rules(['email' => 'required|email', 'role' => 'required|in:owner,resident,technician']);
        if ($validator->fails()) {
            Response::error($validator->firstError(), 422);
            return;
        }

        $user = User::findByEmail($email);
        if (!$user) {
            Response::error('Aucun compte utilisateur n’existe avec cette adresse e-mail.', 404);
            return;
        }

        House::addMember($id, $user['id'], $role);
        ActivityLog::record(Auth::id(), 'ajout_membre_maison', "Ajout de {$user['name']} comme {$role} sur la maison", $this->request->ip(), $id);

        Response::success('Membre ajouté avec succès.');
    }

    public function removeMember(int $id, int $userId): void
    {
        $this->requireOwnerOrAdmin($id);
        $this->verifyCsrf();

        if (Auth::id() === $userId) {
            Response::error('Vous ne pouvez pas vous retirer vous-même de la maison.', 409);
            return;
        }

        House::removeMember($id, $userId);
        ActivityLog::record(Auth::id(), 'retrait_membre_maison', "Retrait d’un membre de la maison", $this->request->ip(), $id);

        Response::success('Membre retiré avec succès.');
    }

    /**
     * Vérifie que l'utilisateur connecté est propriétaire (owner) de
     * la maison ciblée ou administrateur de plateforme ; interrompt
     * la requête avec une erreur sinon.
     */
    private function requireOwnerOrAdmin(int $houseId): void
    {
        Auth::requireLogin();
        $role = Auth::roleOnHouse($houseId);
        if (!in_array($role, ['owner', 'admin'], true)) {
            Response::error('Seul le propriétaire de cette maison peut effectuer cette action.', 403);
            exit;
        }
    }
}
