<?php
/**
 * api/v1/rooms.php
 *
 * Ressource REST /api/v1/rooms — gestion des pièces.
 *
 *   GET    /api/v1/rooms            Liste des pièces
 *   GET    /api/v1/rooms/{id}       Détail d'une pièce
 *   POST   /api/v1/rooms            Création d'une pièce
 *   PUT    /api/v1/rooms/{id}       Mise à jour d'une pièce
 *   DELETE /api/v1/rooms/{id}       Suppression d'une pièce
 */

use App\Models\Room;

function handle_rooms(string $method, ?string $id, ?string $subaction): void
{
    api_authenticate();

    switch ($method) {
        case 'GET':
            if ($id) {
                $room = Room::find((int) $id);
                $room ? api_response(['success' => true, 'data' => $room])
                      : api_response(['success' => false, 'message' => 'Pièce introuvable.'], 404);
            }
            api_response(['success' => true, 'data' => Room::allWithCounts()]);
            break;

        case 'POST':
            $input = api_input();
            if (empty($input['name'])) {
                api_response(['success' => false, 'message' => 'Le nom de la pièce est obligatoire.'], 422);
            }
            $newId = Room::create([
                'name'        => $input['name'],
                'type'        => $input['type'] ?? 'autre',
                'floor'       => $input['floor'] ?? null,
                'icon'        => $input['icon'] ?? 'fa-door-open',
                'description' => $input['description'] ?? null,
            ]);
            api_response(['success' => true, 'message' => 'Pièce créée.', 'data' => Room::find($newId)], 201);
            break;

        case 'PUT':
            if (!$id || !Room::find((int) $id)) {
                api_response(['success' => false, 'message' => 'Pièce introuvable.'], 404);
            }
            $input = api_input();
            Room::update((int) $id, array_filter([
                'name'        => $input['name'] ?? null,
                'type'        => $input['type'] ?? null,
                'floor'       => $input['floor'] ?? null,
                'description' => $input['description'] ?? null,
            ], fn($v) => $v !== null));
            api_response(['success' => true, 'message' => 'Pièce mise à jour.', 'data' => Room::find((int) $id)]);
            break;

        case 'DELETE':
            if (!$id || !Room::find((int) $id)) {
                api_response(['success' => false, 'message' => 'Pièce introuvable.'], 404);
            }
            if (Room::hasDependents((int) $id)) {
                api_response(['success' => false, 'message' => 'Des équipements ou capteurs dépendent encore de cette pièce.'], 409);
            }
            Room::delete((int) $id);
            api_response(['success' => true, 'message' => 'Pièce supprimée.']);
            break;

        default:
            api_response(['success' => false, 'message' => 'Méthode non autorisée.'], 405);
    }
}
