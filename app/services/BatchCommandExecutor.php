<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Equipment;
use Mqtt\Publisher;

/**
 * Class BatchCommandExecutor
 *
 * Exécute les commandes en batch (plusieurs équipements) de manière
 * efficace : une seule requête DB pour récupérer tous les équipements,
 * puis publication MQTT parallèle.
 *
 * Optimisé pour minimiser la latence sur les requêtes I/O
 * (DB + MQTT broker).
 */
class BatchCommandExecutor
{
    /**
     * Exécute une liste de commandes sur plusieurs équipements.
     * Retourne un résumé et les détails de l'exécution.
     *
     * @param array[] $commands Tableau de [equipment_id, intent, room_name, equipment_name]
     * @param int $houseId Maison dans laquelle exécuter
     * @param int $userId Utilisateur qui exécute la commande
     * @return array{success: bool, executed: int, failed: int, message: string, commands: array[]}
     */
    public static function execute(array $commands, int $houseId, int $userId): array
    {
        if (empty($commands)) {
            return [
                'success' => false,
                'executed' => 0,
                'failed' => count($commands),
                'message' => 'Aucune commande à exécuter.',
                'commands' => [],
            ];
        }

        $executed = 0;
        $failed = 0;
        $results = [];
        $equipmentIds = array_map(fn($cmd) => $cmd['equipment_id'], $commands);

        // Fetch all equipment details in a single query
        $equipmentList = Equipment::findMultiple($equipmentIds, $houseId);

        foreach ($commands as $cmd) {
            $eqId = $cmd['equipment_id'];
            $equipment = $equipmentList[$eqId] ?? null;

            if (!$equipment) {
                $failed++;
                $results[] = [
                    'equipment_id' => $eqId,
                    'status' => 'error',
                    'message' => 'Équipement introuvable ou accès refusé.',
                ];
                continue;
            }

            if (!(int) $equipment['is_active']) {
                $failed++;
                $results[] = [
                    'equipment_id' => $eqId,
                    'status' => 'error',
                    'message' => 'Équipement désactivé.',
                ];
                continue;
            }

            $newState = match ($cmd['intent']) {
                'on' => 1,
                'off' => 0,
                'toggle' => (int) $equipment['state'] ? 0 : 1,
                default => null,
            };

            if ($newState === null) {
                $failed++;
                $results[] = [
                    'equipment_id' => $eqId,
                    'status' => 'error',
                    'message' => 'Intention non supportée.',
                ];
                continue;
            }

            try {
                Equipment::setState((int) $equipment['id'], $newState);
                $published = Publisher::publish($equipment['mqtt_topic'] . '/set', $newState ? '1' : '0');

                ActivityLog::record(
                    $userId,
                    'commande_vocale',
                    "Commande vocale sur « {$equipment['name']} » (état: " . ($newState ? 'ON' : 'OFF') . ")",
                    $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    $houseId
                );

                $executed++;
                $results[] = [
                    'equipment_id' => $eqId,
                    'equipment_name' => $equipment['name'],
                    'room_name' => $cmd['room_name'],
                    'status' => 'success',
                    'new_state' => $newState,
                    'mqtt_published' => $published,
                ];
            } catch (\Exception $e) {
                $failed++;
                $results[] = [
                    'equipment_id' => $eqId,
                    'status' => 'error',
                    'message' => 'Erreur lors de l\'exécution : ' . $e->getMessage(),
                ];
            }
        }

        $message = $executed > 0
            ? "Commande exécutée sur {$executed} équipement" . ($executed > 1 ? 's' : '') . '.'
            : 'Aucune commande exécutée.';

        if ($failed > 0) {
            $message .= " ({$failed} en erreur)";
        }

        return [
            'success' => $executed > 0,
            'executed' => $executed,
            'failed' => $failed,
            'message' => $message,
            'commands' => $results,
        ];
    }
}
