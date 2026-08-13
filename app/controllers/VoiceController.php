<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Response;
use App\Models\ActivityLog;
use App\Models\Equipment;
use App\Services\VoiceCommandService;
use Mqtt\Publisher;

class VoiceController extends Controller
{
    public function command(): void
    {
        $houseId = Auth::requireHouseRole(['admin', 'owner', 'resident', 'technician']);
        $this->verifyCsrf();

        $command = trim((string) $this->request->input('command', ''));
        if ($command === '') {
            Response::error('Commande vide.', 400);
            return;
        }

        if (mb_strlen($command) > 500) {
            Response::error('Commande trop longue.', 400);
            return;
        }

        $parsed = VoiceCommandService::parse($command, $houseId);
        if (!$parsed['success']) {
            Response::error($parsed['message'] ?? 'Commande non reconnue.', 400);
            return;
        }

        $equipment = Equipment::find((int) $parsed['equipment_id']);
        if (!$equipment || !Equipment::belongsToHouse((int) $equipment['id'], $houseId)) {
            Response::error('Équipement introuvable.', 404);
            return;
        }

        if (isset($equipment['is_active']) && !(int) $equipment['is_active']) {
            Response::error('Cet équipement est désactivé et ne peut pas être piloté.', 409);
            return;
        }

        $newState = match ($parsed['intent']) {
            'on' => 1,
            'off' => 0,
            'toggle' => (int) $equipment['state'] ? 0 : 1,
            default => null,
        };

        if ($newState === null) {
            Response::error('Intention non supportée.', 400);
            return;
        }

        Equipment::setState((int) $equipment['id'], $newState);
        $published = Publisher::publish($equipment['mqtt_topic'] . '/set', $newState ? '1' : '0');

        ActivityLog::record(
            Auth::id(),
            'commande_vocale',
            "Commande vocale « {$command} » exécutée sur « {$equipment['name']} »",
            $this->request->ip(),
            $houseId
        );

        Response::success($parsed['message'] ?? 'Commande exécutée.', [
            'equipment_id' => (int) $equipment['id'],
            'equipment_name' => $equipment['name'],
            'room_name' => $parsed['room_name'],
            'new_state' => $newState,
            'mqtt_published' => $published,
        ]);
    }
}
