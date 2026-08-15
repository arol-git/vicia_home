<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Response;
use App\Models\ActivityLog;
use App\Models\Equipment;
use App\Services\VoiceCommandService;
use App\Services\BatchCommandExecutor;
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

        // Parse la commande et récupère la liste de toutes les commandes à exécuter
        $parsed = VoiceCommandService::parse($command, $houseId);
        if (!$parsed['success']) {
            Response::error($parsed['message'] ?? 'Commande non reconnue.', 400);
            return;
        }

        // Exécuter toutes les commandes en batch
        $result = BatchCommandExecutor::execute(
            $parsed['commands'],
            $houseId,
            Auth::id()
        );

        if (!$result['success']) {
            Response::error($result['message'] ?? 'Erreur lors de l\'exécution.', 400);
            return;
        }

        Response::success($result['message'], $result);
    }
}
