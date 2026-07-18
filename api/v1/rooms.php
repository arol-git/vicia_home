<?php
/**
 * api/v1/rooms.php
 *
 * Ressource REST /api/v1/rooms — gestion des pièces D'UNE MAISON.
 * Toute requête doit préciser "house_id" (query string en GET, corps
 * JSON pour les autres méthodes), vérifié contre les droits de
 * l'utilisateur authentifié via api_authorize_house().
 *
 *   GET    /api/v1/rooms?house_id=1        Liste des pièces de la maison 1
 *   GET    /api/v1/rooms/{id}?house_id=1   Détail d'une pièce
 *   POST   /api/v1/rooms                   Création (house_id dans le corps)
 *   PUT    /api/v1/rooms/{id}              Mise à jour
 *   DELETE /api/v1/rooms/{id}?house_id=1   Suppression
 */

use App\Models\Room;

function handle_rooms(string $method, ?string $id, ?string $subaction): void
{
    $user = api_authenticate();
    $input = api_input();
    $houseId = api_authorize_house($user, $input);

    switch ($method) {
        case 'GET':
            if ($id) {
                $room = Room::find((int) $id);
                if (!$room || (int) $room['house_id'] !== $houseId) {
                    api_response(['success' => false, 'message' => 'Pièce introuvable.'], 404);
                }
                api_response(['success' => true, 'data' => $room]);
            }
            api_response(['success' => true, 'data' => Room::allWithCounts($houseId)]);
            break;

        case 'POST':
            if (empty($input['name'])) {
                api_response(['success' => false, 'message' => 'Le nom de la pièce est obligatoire.'], 422);
            }
            $newId = Room::create([
                'house_id'    => $houseId,
                'name'        => $input['name'],
                'type'        => $input['type'] ?? 'autre',
                'floor'       => $input['floor'] ?? null,
                'icon'        => $input['icon'] ?? 'fa-door-open',
                'description' => $input['description'] ?? null,
            ]);
            api_response(['success' => true, 'message' => 'Pièce créée.', 'data' => Room::find($newId)], 201);
            break;

        case 'PUT':
            if (!$id || !Room::belongsToHouse((int) $id, $houseId)) {
                api_response(['success' => false, 'message' => 'Pièce introuvable.'], 404);
            }
            Room::update((int) $id, array_filter([
                'name'        => $input['name'] ?? null,
                'type'        => $input['type'] ?? null,
                'floor'       => $input['floor'] ?? null,
                'description' => $input['description'] ?? null,
            ], fn($v) => $v !== null));
            api_response(['success' => true, 'message' => 'Pièce mise à jour.', 'data' => Room::find((int) $id)]);
            break;

        case 'DELETE':
            if (!$id || !Room::belongsToHouse((int) $id, $houseId)) {
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
