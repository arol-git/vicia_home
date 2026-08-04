<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Response;
use App\Core\Validator;
use App\Models\ActivityLog;
use App\Models\User;

/**
 * Class ProfileController
 *
 * Permet à l'utilisateur connecté de consulter et modifier ses
 * propres informations de profil ainsi que son mot de passe.
 */
class ProfileController extends Controller
{
    public function show(): void
    {
        Auth::requireLogin();
        $this->render('profile/index', ['title' => 'Mon profil', 'user' => Auth::user()]);
    }

    public function update(): void
    {
        Auth::requireLogin();
        $this->verifyCsrf();

        $data = [
            'name'  => trim((string) $this->request->input('name')),
            'phone' => trim((string) $this->request->input('phone', '')),
        ];

        $validator = new Validator($data);
        $validator->rules(['name' => 'required|min:2|max:100']);

        if ($validator->fails()) {
            Response::error($validator->firstError(), 422, ['errors' => $validator->errors()]);
            return;
        }

        User::update(Auth::id(), $data);
        ActivityLog::record(Auth::id(), 'modification_profil', 'Mise à jour des informations de profil', $this->request->ip());

        Response::success('Profil mis à jour avec succès.');
    }

    /**
     * Change le mot de passe de l'utilisateur connecté, après
     * vérification de son mot de passe actuel.
     */
    public function updatePassword(): void
    {
        Auth::requireLogin();
        $this->verifyCsrf();

        $currentPassword = (string) $this->request->input('current_password');
        $newPassword      = (string) $this->request->input('password');

        $user = User::find(Auth::id());

        if (!password_verify($currentPassword, $user['password_hash'])) {
            Response::error('Le mot de passe actuel est incorrect.', 422);
            return;
        }

        $validator = new Validator([
            'password'              => $newPassword,
            'password_confirmation' => $this->request->input('password_confirmation'),
        ]);
        $validator->rules(['password' => 'required|min:8|confirmed']);

        if ($validator->fails()) {
            Response::error($validator->firstError(), 422, ['errors' => $validator->errors()]);
            return;
        }

        User::updatePassword(Auth::id(), $newPassword);
        ActivityLog::record(Auth::id(), 'changement_mdp', 'Changement du mot de passe depuis le profil', $this->request->ip());

        Response::success('Mot de passe modifié avec succès.');
    }
}
