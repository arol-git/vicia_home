<?php

namespace App\Services;

use App\Models\AIHistory;
use App\Models\Equipment;
use App\Models\House;
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
            $topic = Publisher::topicForEquipment($eq['mqtt_topic'] ?? null, $houseId, (int) $eq['id'], 'set');
            Publisher::publish($topic, (string) (int) $intent['target_state']);
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
        House::update($houseId, ['mode' => $intent['mode']]);
        AIHistory::record($userId, $houseId, 'mode_change', 'success', $intent['mode']);

        return ['success' => true, 'mode' => $intent['mode']];
    }
}
