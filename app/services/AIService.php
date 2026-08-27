<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\Conversation;
use App\Models\Equipment;
use App\Models\House;
use App\Models\Room;
use App\Models\Sensor;
use App\Models\Setting;

/**
 * Class AIService
 *
 * Point d'entrée métier du module Vicia Home AI, appelé par
 * App\Controllers\AIController. Orchestre, dans l'ordre :
 *
 *   1. Reprise/création de la conversation active (App\Models\Conversation) ;
 *   2. Gestion d'une confirmation en attente, le cas échéant (priorité absolue) ;
 *   3. Classification de l'intention (App\Services\IntentClassifier) ;
 *   4. Exécution (App\Services\ActionExecutor) ou résolution factuelle
 *      (directement via les modèles existants — jamais de nouvelle
 *      requête SQL ad hoc ici, uniquement des méthodes déjà exposées) ;
 *   5. Mise en forme naturelle de la réponse (App\Services\LLMService),
 *      avec repli sobre si aucun fournisseur n'est configuré.
 *
 * Ne contient aucun accès direct à MQTT ni de requête SQL propre :
 * cette classe orchestre des services et modèles déjà responsables de
 * leur propre domaine, conformément à SOLID (responsabilité unique).
 */
class AIService
{
    public static function handle(int $userId, ?int $houseId, string $message): array
    {
        app_log('[AIService] Nouveau message reçu. user=' . $userId . ' house=' . ($houseId ?? 'null'));

        $conversation = Conversation::currentFor($userId, $houseId);
        if (empty($conversation['id'])) {
            app_log('[AIService] Conversation introuvable pour user=' . $userId . ' house=' . ($houseId ?? 'null'));
            throw new \RuntimeException('Conversation IA introuvable.');
        }

        Conversation::appendMessage($conversation['id'], 'user', $message);

        try {
            $pending = ConversationMemory::getPendingAction($conversation['id']);

            if ($pending !== null) {
                return self::resolvePendingConfirmation($conversation, $pending, $message, $houseId, $userId);
            }

            if (!$houseId) {
                return self::respond(
                    $conversation['id'],
                    "Merci de sélectionner une maison avant de me parler d'équipements ou de capteurs — le reste, je peux déjà y répondre.",
                    null,
                    'chitchat'
                );
            }

            $intent = IntentClassifier::classify($message, $houseId);
            app_log('[AIService] Intention classifiée : ' . json_encode($intent, JSON_UNESCAPED_UNICODE));

            return match ($intent['type']) {
                'command'          => self::handleCommand($conversation, $intent, $houseId, $userId),
                'question'         => self::handleQuestion($conversation, $intent, $houseId, $message),
                'analysis'         => self::handleAnalysis($conversation, $intent, $houseId, $message),
                'confirmation_yes', 'confirmation_no' => self::respond($conversation['id'], "Il n'y a rien à confirmer pour le moment.", null, 'chitchat'),
                default            => self::handleChitchat($conversation, $message, $houseId),
            };
        } catch (\Throwable $e) {
            app_log('[AIService] Erreur interne : ' . $e->getMessage());
            return self::respond(
                $conversation['id'],
                "Je rencontre un problème technique. Vérifiez votre connexion à la maison, puis réessayez.",
                null,
                'error'
            );
        }
    }

    private static function resolvePendingConfirmation(array $conversation, array $pending, string $message, ?int $houseId, int $userId): array
    {
        $decision = IntentClassifier::classify($message, (int) $houseId)['type'] ?? null;

        if ($decision !== 'confirmation_yes' && $decision !== 'confirmation_no') {
            // Réponse ambiguë : on redemande explicitement plutôt que
            // d'interpréter un texte qui n'est ni oui ni non comme une
            // confirmation implicite — une commande sensible ne
            // souffre aucune ambiguïté.
            return self::respond($conversation['id'], "Répondez simplement oui pour continuer ou non pour annuler.\n\n" . $pending['question'], null, 'command');
        }

        ConversationMemory::clearPendingAction($conversation['id']);

        if ($decision === 'confirmation_no') {
            return self::respond($conversation['id'], "D'accord, rien n'a été changé.", null, 'command');
        }

        $result = ActionExecutor::execute($pending['intent'], (int) $houseId, $userId);
        $text = self::describeActionResult($pending['intent'], $result);

        return self::respond($conversation['id'], $text, null, 'command');
    }

    private static function handleCommand(array $conversation, array $intent, int $houseId, ?int $userId): array
    {
        if (ActionExecutor::isSensitive($intent)) {
            $question = self::confirmationQuestionFor($intent);
            ConversationMemory::setPendingAction($conversation['id'], $intent, $question);
            return self::respond($conversation['id'], $question, null, 'command');
        }

        $result = ActionExecutor::execute($intent, $houseId, $userId);
        $text = self::describeActionResult($intent, $result);

        return self::respond($conversation['id'], $text, null, 'command');
    }

    private static function handleQuestion(array $conversation, array $intent, int $houseId, string $message): array
    {
        $roomName = self::extractRoomNameFromMessage($houseId, $message);
        $context = self::resolveFactualContext($intent['topic'], $houseId, $roomName);
        $recent = Conversation::recentMessages($conversation['id'], 10);

        $reply = self::directFactualReply($intent['topic'], $context, $roomName);
        if ($reply === null) {
            $reply = LLMService::generateReply($message, $context, $recent);
        }

        return self::respond($conversation['id'], $reply, $context, 'question');
    }

    private static function directFactualReply(string $topic, array $context, ?string $roomName): ?string
    {
        if (in_array($topic, ['temperature', 'humidity'], true)) {
            $sensors = $context['sensors'] ?? [];
            if ($sensors === []) {
                return $roomName
                    ? "Je n'ai pas encore de mesure disponible dans la pièce « {$roomName} »."
                    : "Je n'ai pas encore de mesure disponible pour cette information.";
            }

            $label = $topic === 'temperature' ? 'Température' : 'Humidité';
            $readings = array_map(function (array $sensor) use ($topic): string {
                $value = str_replace('.', ',', trim((string) $sensor['value']));
                $unit = $topic === 'temperature' ? 'degrés Celsius' : 'pour cent';
                return "dans {$sensor['room']}, elle est de {$value} {$unit}";
            }, $sensors);

            return $label . ' : ' . implode('; ', $readings) . '.';
        }

        if (in_array($topic, ['doors', 'lights'], true)) {
            $equipments = $context['equipments'] ?? [];
            if ($equipments === []) {
                return $roomName
                    ? "Je ne trouve pas d'équipement correspondant dans la pièce « {$roomName} »."
                    : "Je ne trouve pas d'équipement correspondant.";
            }

            $readings = array_map(fn(array $equipment): string => "{$equipment['name']} est {$equipment['state']}", $equipments);
            return implode('. ', $readings) . '.';
        }

        if ($topic === 'house_state') {
            return self::houseStateReply($context);
        }

        if ($topic === 'energy') {
            $watts = (int) ($context['total_active_watts'] ?? 0);
            $daily = str_replace('.', ',', (string) ($context['estimated_daily_kwh'] ?? 0));
            return "La puissance actuellement estimée est de {$watts} watts. La consommation estimée sur une journée est de {$daily} kilowattheures.";
        }

        return null;
    }

    private static function houseStateReply(array $context): string
    {
        $mode = self::modeLabel((string) ($context['mode'] ?? 'comfort'));
        $active = (int) ($context['equipments_active_now'] ?? 0);
        $total = (int) ($context['equipments_total'] ?? 0);
        $alerts = (int) ($context['alerts_today'] ?? 0);

        return "La maison est en mode {$mode}. {$active} équipement(s) sur {$total} sont actuellement en marche. "
            . ($alerts === 0 ? "Il n'y a aucune alerte aujourd'hui." : "Il y a {$alerts} alerte(s) aujourd'hui.");
    }

    private static function handleAnalysis(array $conversation, array $intent, int $houseId, string $message): array
    {
        $context = match ($intent['topic']) {
            'energy'   => RecommendationEngine::energyAnalysis($houseId),
            'security' => ['suggestions' => RecommendationEngine::suggestions($houseId)],
            'alerts'   => ['recent_alerts' => array_slice(Alert::forHouse($houseId), 0, 5)],
            default    => array_merge(RecommendationEngine::dailySummary($houseId), ['suggestions' => RecommendationEngine::suggestions($houseId)]),
        };

        $recent = Conversation::recentMessages($conversation['id'], 10);
        $reply = self::directAnalysisReply($intent['topic'], $context);
        if ($reply === null) {
            $reply = LLMService::generateReply($message, $context, $recent);
        }

        return self::respond($conversation['id'], $reply, $context, 'analysis');
    }

    private static function handleChitchat(array $conversation, string $message, ?int $houseId): array
    {
        $recent = Conversation::recentMessages($conversation['id'], 10);
        $reply = LLMService::generateReply($message, [], $recent);

        return self::respond($conversation['id'], $reply, null, 'chitchat');
    }

    /**
     * Résout un contexte factuel pour une catégorie de question,
     * exclusivement via les modèles déjà existants — c'est la SEULE
     * source de vérité transmise au LLM (voir App\Services\LLMService).
     */
    private static function extractRoomNameFromMessage(int $houseId, string $message): ?string
    {
        foreach (Room::forHouse($houseId) as $room) {
            $normalizedRoom = mb_strtolower(trim($room['name']));
            $normalizedRoom = preg_replace('/\s+/', ' ', $normalizedRoom);
            if (str_contains(mb_strtolower($message), $normalizedRoom)) {
                return $room['name'];
            }
        }

        return null;
    }

    private static function resolveFactualContext(string $topic, int $houseId, ?string $roomName = null): array
    {
        return match ($topic) {
            'temperature' => self::sensorSummary($houseId, 'dht22_temp', $roomName),
            'humidity'    => self::sensorSummary($houseId, 'dht22_hum', $roomName),
            'energy'      => RecommendationEngine::energyAnalysis($houseId),
            'doors'       => self::equipmentStateSummary($houseId, ['porte', 'fenetre'], $roomName),
            'lights'      => self::equipmentStateSummary($houseId, ['led', 'relais'], $roomName),
            'security'    => ['unread_critical_alerts' => count(array_filter(Alert::forHouse($houseId), fn($a) => $a['severity'] === 'critical' && !$a['is_read']))],
            default       => self::houseStateSummary($houseId),
        };
    }

    private static function sensorSummary(int $houseId, string $type, ?string $roomName = null): array
    {
        $sensors = array_values(array_filter(Sensor::allWithRoom($houseId), fn($s) => $s['type'] === $type && (!$roomName || $s['room_name'] === $roomName)));
        return ['sensors' => array_map(fn($s) => ['name' => $s['name'], 'room' => $s['room_name'], 'value' => $s['latest_value'], 'unit' => $s['unit']], $sensors)];
    }

    private static function equipmentStateSummary(int $houseId, array $types, ?string $roomName = null): array
    {
        $equipments = array_values(array_filter(Equipment::allWithRoom($houseId), fn($e) => in_array($e['type'], $types, true) && (!$roomName || $e['room_name'] === $roomName)));
        return ['equipments' => array_map(fn($e) => ['name' => $e['name'], 'room' => $e['room_name'], 'state' => (int) $e['state'] ? 'ouvert/allumé' : 'fermé/éteint'], $equipments)];
    }

    private static function houseStateSummary(int $houseId): array
    {
        $house = House::find($houseId);
        return array_merge(
            ['mode' => Setting::get('dashboard_mode_' . $houseId, 'comfort')],
            RecommendationEngine::dailySummary($houseId)
        );
    }

    private static function directAnalysisReply(string $topic, array $context): ?string
    {
        if ($topic === 'daily') {
            $active = (int) ($context['equipments_active_now'] ?? 0);
            $total = (int) ($context['equipments_total'] ?? 0);
            $alerts = (int) ($context['alerts_today'] ?? 0);
            return "La maison compte {$total} équipement(s), dont {$active} actuellement en marche. "
                . ($alerts === 0 ? "Aucune alerte n'a été enregistrée aujourd'hui." : "{$alerts} alerte(s) ont été enregistrées aujourd'hui.");
        }

        return null;
    }

    private static function confirmationQuestionFor(array $intent): string
    {
        if ($intent['action'] === 'set_mode' && $intent['mode'] !== 'urgence') {
            return "Confirmez-vous vouloir désactiver le mode urgence et repasser en mode « {$intent['mode']} » ?";
        }

        if ($intent['action'] === 'toggle_equipment' && ($intent['target_type'] ?? null) === 'all' && (int) ($intent['target_state'] ?? 0) === 0) {
            return "Attention : cette action va arrêter tous les équipements de la maison. Confirmez-vous ? (oui / non)";
        }

        $verb = match ($intent['target_type']) {
            'porte', 'fenetre' => 'ouvrir/déverrouiller',
            'sirene'           => "désactiver l'alarme sur",
            default            => 'activer',
        };

        return "Confirmez-vous vouloir $verb " . ($intent['room'] ? "l'équipement dans « {$intent['room']} »" : "cet équipement") . " ?";
    }

    private static function describeActionResult(array $intent, array $result): string
    {
        if (!$result['success']) {
            return $result['message'] ?? "Je n'ai pas pu exécuter cette action.";
        }

        if ($intent['action'] === 'set_mode') {
            return "Le mode « " . self::modeLabel((string) $result['mode']) . " » est maintenant activé. "
                . ((int) ($result['changed'] ?? 0) > 0 ? ((int) $result['changed'] . " équipement(s) ont été ajusté(s).") : "Aucun équipement n'avait besoin d'être modifié.");
        }

        if (empty($result['affected'])) {
            return "C'était déjà dans l'état demandé, aucune action n'était nécessaire.";
        }

        $labels = [];
        foreach ($result['affected'] as $equipment) {
            $labels[] = $equipment;
        }

        $verb = self::equipmentStateLabel($intent['target_type'] ?? '', (int) $result['state']);
        return implode(', ', $labels) . " : {$verb}.";
    }

    private static function modeLabel(string $mode): string
    {
        return match ($mode) {
            'comfort', 'confort' => 'confort',
            'night', 'nuit' => 'nuit',
            'away', 'absence' => 'absence',
            'emergency', 'urgence' => 'urgence',
            default => $mode,
        };
    }

    private static function equipmentStateLabel(string $type, int $state): string
    {
        return match ($type) {
            'porte', 'fenetre' => $state === 1 ? 'ouvert' : 'fermé',
            'led' => $state === 1 ? 'allumé' : 'éteint',
            'ventilateur', 'pompe', 'relais', 'servo' => $state === 1 ? 'en marche' : 'arrêté',
            'camera' => $state === 1 ? 'activée' : 'désactivée',
            'sirene' => $state === 1 ? 'activée' : 'désactivée',
            default => $state === 1 ? 'activé' : 'désactivé',
        };
    }

    private static function respond(int $conversationId, string $text, ?array $context, string $intent): array
    {
        Conversation::appendMessage($conversationId, 'assistant', $text, $intent, $context);

        return [
            'reply'        => $text,
            'spoken_text'  => VoiceService::sanitizeForSpeech($text),
            'intent'       => $intent,
            'conversation_id' => $conversationId,
        ];
    }
}
