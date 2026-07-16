<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Response;
use App\Core\Validator;
use App\Models\ActivityLog;
use App\Models\Room;

/**
 * Class RoomController
 *
 * Gère le module "Pièces" : listage, création, modification et
 * suppression des pièces de l'habitation. Les opérations d'écriture
 * sont réalisées en AJAX et répondent en JSON.
 */
class RoomController extends Controller
{
    private array $allowedTypes = ['salon', 'cuisine', 'chambre', 'garage', 'bureau', 'salle_de_bain', 'jardin', 'terrasse', 'autre'];

    public function index(): void
    {
        Auth::requireLogin();
        $rooms = Room::allWithCounts();
        $this->render('rooms/index', ['title' => 'Pièces', 'rooms' => $rooms]);
    }

    /**
     * Crée une nouvelle pièce (appelé en AJAX depuis la modale
     * "Ajouter une pièce").
     */
    public function store(): void
    {
        Auth::requireRole(['admin', 'technicien']);
        $this->verifyCsrf();

        $data = [
            'name'        => trim((string) $this->request->input('name')),
            'type'        => (string) $this->request->input('type', 'autre'),
            'floor'       => trim((string) $this->request->input('floor', '')),
            'icon'        => (string) $this->request->input('icon', 'fa-door-open'),
            'description' => trim((string) $this->request->input('description', '')),
        ];

        $validator = new Validator($data);
        $validator->rules([
            'name' => 'required|min:2|max:100',
            'type' => 'required|in:' . implode(',', $this->allowedTypes),
        ]);

        if ($validator->fails()) {
            Response::error($validator->firstError(), 422, ['errors' => $validator->errors()]);
            return;
        }

        $id = Room::create($data);
        ActivityLog::record(Auth::id(), 'creation_piece', "Création de la pièce « {$data['name']} »", $this->request->ip());

        Response::success('Pièce ajoutée avec succès.', ['id' => $id]);
    }

    public function update(int $id): void
    {
        Auth::requireRole(['admin', 'technicien']);
        $this->verifyCsrf();

        $room = Room::find($id);
        if (!$room) {
            Response::error('Pièce introuvable.', 404);
            return;
        }

        $data = [
            'name'        => trim((string) $this->request->input('name')),
            'type'        => (string) $this->request->input('type', $room['type']),
            'floor'       => trim((string) $this->request->input('floor', '')),
            'icon'        => (string) $this->request->input('icon', $room['icon']),
            'description' => trim((string) $this->request->input('description', '')),
        ];

        $validator = new Validator($data);
        $validator->rules([
            'name' => 'required|min:2|max:100',
            'type' => 'required|in:' . implode(',', $this->allowedTypes),
        ]);

        if ($validator->fails()) {
            Response::error($validator->firstError(), 422, ['errors' => $validator->errors()]);
            return;
        }

        Room::update($id, $data);
        ActivityLog::record(Auth::id(), 'modification_piece', "Modification de la pièce « {$data['name']} »", $this->request->ip());

        Response::success('Pièce mise à jour avec succès.');
    }

    public function destroy(int $id): void
    {
        Auth::requireRole(['admin']);
        $this->verifyCsrf();

        $room = Room::find($id);
        if (!$room) {
            Response::error('Pièce introuvable.', 404);
            return;
        }

        if (Room::hasDependents($id)) {
            Response::error('Impossible de supprimer cette pièce : des équipements ou capteurs y sont encore rattachés.', 409);
            return;
        }

        Room::delete($id);
        ActivityLog::record(Auth::id(), 'suppression_piece', "Suppression de la pièce « {$room['name']} »", $this->request->ip());

        Response::success('Pièce supprimée avec succès.');
    }
}
