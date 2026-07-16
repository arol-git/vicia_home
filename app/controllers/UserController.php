<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Response;
use App\Core\Validator;
use App\Models\ActivityLog;
use App\Models\User;

/**
 * Class UserController
 *
 * Réservé au rôle administrateur : gestion des comptes utilisateurs
 * et de leurs rôles (administrateur, technicien, utilisateur).
 */
class UserController extends Controller
{
    public function index(): void
    {
        Auth::requireRole(['admin']);
        $users = User::all('created_at DESC');
        $this->render('users/index', ['title' => 'Utilisateurs', 'users' => $users]);
    }

    public function store(): void
    {
        Auth::requireRole(['admin']);
        $this->verifyCsrf();

        $data = [
            'name'     => trim((string) $this->request->input('name')),
            'email'    => trim((string) $this->request->input('email')),
            'password' => (string) $this->request->input('password'),
            'role'     => (string) $this->request->input('role', 'user'),
        ];

        $validator = new Validator($data);
        $validator->rules([
            'name'     => 'required|min:2|max:100',
            'email'    => 'required|email',
            'password' => 'required|min:8',
            'role'     => 'required|in:admin,user,technicien',
        ]);

        if ($validator->fails()) {
            Response::error($validator->firstError(), 422, ['errors' => $validator->errors()]);
            return;
        }

        if (User::findByEmail($data['email'])) {
            Response::error('Un compte existe déjà avec cette adresse e-mail.', 409);
            return;
        }

        $id = User::register($data['name'], $data['email'], $data['password'], $data['role']);
        ActivityLog::record(Auth::id(), 'creation_utilisateur', "Création du compte utilisateur « {$data['name']} » ({$data['role']})", $this->request->ip());

        Response::success('Utilisateur créé avec succès.', ['id' => $id]);
    }

    public function update(int $id): void
    {
        Auth::requireRole(['admin']);
        $this->verifyCsrf();

        $user = User::find($id);
        if (!$user) {
            Response::error('Utilisateur introuvable.', 404);
            return;
        }

        $data = [
            'name'   => trim((string) $this->request->input('name')),
            'role'   => (string) $this->request->input('role'),
            'status' => (string) $this->request->input('status', 'active'),
        ];

        $validator = new Validator($data);
        $validator->rules([
            'name'   => 'required|min:2|max:100',
            'role'   => 'required|in:admin,user,technicien',
            'status' => 'required|in:active,suspended',
        ]);

        if ($validator->fails()) {
            Response::error($validator->firstError(), 422, ['errors' => $validator->errors()]);
            return;
        }

        User::update($id, $data);
        ActivityLog::record(Auth::id(), 'modification_utilisateur', "Modification du compte « {$data['name']} »", $this->request->ip());

        Response::success('Utilisateur mis à jour avec succès.');
    }

    public function destroy(int $id): void
    {
        Auth::requireRole(['admin']);
        $this->verifyCsrf();

        if ($id === Auth::id()) {
            Response::error('Vous ne pouvez pas supprimer votre propre compte.', 409);
            return;
        }

        $user = User::find($id);
        if (!$user) {
            Response::error('Utilisateur introuvable.', 404);
            return;
        }

        User::delete($id);
        ActivityLog::record(Auth::id(), 'suppression_utilisateur', "Suppression du compte « {$user['name']} »", $this->request->ip());

        Response::success('Utilisateur supprimé avec succès.');
    }
}
