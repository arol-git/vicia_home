<?php

namespace App\Services;

use App\Models\AIHistory;
use App\Models\Equipment;
use App\Models\House;
use App\Models\Setting;
use Mqtt\Publisher;

/**
 * Class ActionExecutor
 *
 * Traduit une intention de commande déjà classifiée
 * (App\Services\IntentClassifier) en appels réels aux modèles
 * existants — jamais de nouvelle logique métier ici, uniquement de
 * l'orchestration de ce qui existe déjà (Equipment::toggleState(),
 * House::update(), Mqtt\Publisher::publish()...), exactement comme le
 * ferait un contrôleur classique.
 *
 * Les actions sensibles ne sont JAMAIS exécutées directement : elles
 * sont détectées par isSensitive() et renvoyées à l'appelant
 * (App\Services\AIService) comme demande de confirmation, stockée via
 * App\Models\Conversation::setPendingAction().
 */
class ActionExecutor
{
    /** Types d'équipement dont la bascule est considérée sensible (nécessite confirmation). */
    private const SENSITIVE_TYPES = ['porte', 'fenetre', 'sirene'];

    public static function isSensitive(array $intent): bool
    {
        if ($intent['action'] === 'set_mode' && $intent['mode'] === 'urgence') {
            return false; // passer EN mode urgence n'est pas sensible ; en sortir (désarmer) l'est
        }

        if ($intent['action'] === 'toggle_equipment') {
            // Désactiver une porte/fenêtre/sirène (= la fermer/verrouiller) n'est pas sensible ;
            // c'est l'OUVRIR ou la DÉVERROUILLER (state = 1) qui l'est, ainsi que désarmer une sirène active.
            return in_array($intent['target_type'], self::SENSITIVE_TYPES, true) && (int) $intent['target_state'] === 1;
        }

        return false;
    }

    /**
     * Exécute une commande déjà validée (non sensible, ou sensible et
     * confirmée). Retourne un résumé destiné à être formulé en
     * langage naturel par l'appelant (jamais de texte final ici : ce
     * service ne fait qu'agir et rapporter des faits).
     */
    public static function execute(array $intent, int $houseId, ?int $userId): array
    {
        return match ($intent['action']) {
            'toggle_equipment' => self::executeToggleEquipment($intent, $houseId, $userId),
            'set_mode'          => self::executeSetMode($intent, $houseId, $userId),
            default             => ['success' => false, 'message' => "Action inconnue."],
        };
    }

    private static function executeToggleEquipment(array $intent, int $houseId, ?int $userId): array
    {
        $equipments = Equipment::allWithRoom($houseId);
        $equipments = array_values(array_filter($equipments, fn($e) => $e['type'] === $intent['target_type']));

        if ($intent['room']) {
            $equipments = array_values(array_filter($equipments, fn($e) => $e['room_name'] === $intent['room']));
        }

        if (!empty($intent['target_name'])) {
            $targetName = mb_strtolower(trim($intent['target_name']));
            $equipments = array_values(array_filter($equipments, fn($e) => mb_strtolower(trim($e['name'])) === $targetName));
        }

        if (empty($equipments)) {
            return ['success' => false, 'message' => "Aucun équipement correspondant n'a été trouvé" . ($intent['room'] ? " dans la pièce « {$intent['room']} »" : '') . "."];
        }

        // Sans précision de pièce et sans "tous/toutes" explicite, on
        // limite l'action au premier équipement trouvé plutôt que de
        // deviner une portée large — un assistant domestique ne doit
        // jamais agir plus largement que ce qui a été demandé.
        if (!$intent['room'] && !$intent['scope_all'] && count($equipments) > 1) {
            $equipments = [$equipments[0]];
        }

        $affected = [];
        foreach ($equipments as $eq) {
            if ((int) $eq['state'] === (int) $intent['target_state']) {
                continue; // déjà dans l'état demandé : rien à faire, pas d'appel MQTT inutile
            }
            Equipment::update($eq['id'], ['state' => (int) $intent['target_state']]);
            Publisher::publish($eq['mqtt_topic'] . '/set', (string) (int) $intent['target_state']);
            $affected[] = $eq['name'];
        }

        AIHistory::record($userId, $houseId, 'equipment_toggle', 'success', implode(', ', array_column($equipments, 'name')));

        return [
            'success'  => true,
            'affected' => $affected,
            'already'  => count($equipments) - count($affected),
            'state'    => (int) $intent['target_state'],
        ];
    }

    private static function executeSetMode(array $intent, int $houseId, ?int $userId): array
    {
        $dashboardMode = match ($intent['mode']) {
            'confort' => 'comfort',
            'nuit' => 'night',
            'absence' => 'away',
            'urgence' => 'emergency',
            default => $intent['mode'],
        };

        $targets = [
            'comfort' => ['led' => 1, 'relais' => 1, 'ventilateur' => 1, 'pompe' => 0, 'porte' => 0, 'fenetre' => 0, 'sirene' => 0],
            'night' => ['led' => 0, 'relais' => 0, 'ventilateur' => 0, 'pompe' => 0, 'porte' => 1, 'fenetre' => 1, 'sirene' => 0],
            'away' => ['led' => 0, 'relais' => 0, 'ventilateur' => 0, 'pompe' => 0, 'porte' => 1, 'fenetre' => 1, 'sirene' => 0],
            'emergency' => ['led' => 0, 'relais' => 0, 'ventilateur' => 0, 'pompe' => 0, 'porte' => 1, 'fenetre' => 1, 'sirene' => 1],
        ][$dashboardMode] ?? null;

        $changed = 0;
        if ($targets !== null) {
            foreach (Equipment::activeForHouse($houseId) as $equipment) {
                if (!array_key_exists($equipment['type'], $targets) || (int) $equipment['state'] === $targets[$equipment['type']]) {
                    continue;
                }
                $state = (int) $targets[$equipment['type']];
                Equipment::setState((int) $equipment['id'], $state);
                Publisher::publish($equipment['mqtt_topic'] . '/set', $state ? '1' : '0');
                $changed++;
            }
        }

        Setting::set('dashboard_mode_' . $houseId, $dashboardMode);
        AIHistory::record($userId, $houseId, 'mode_change', 'success', $intent['mode']);

        return ['success' => true, 'mode' => $intent['mode'], 'changed' => $changed];
    }
}
