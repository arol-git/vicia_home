<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\Conversation;
use App\Models\Equipment;
use App\Models\House;
use App\Models\NetworkDevice;
use App\Models\Sensor;

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
        app_log('[AIService] Nouveau message IA reçu. user=' . $userId . ' house=' . ($houseId ?? 'null') . ' message=' . substr($message, 0, 240));

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
                "Je suis désolé, le module IA rencontre un problème interne. Essaie de nouveau plus tard.",
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
            return self::respond($conversation['id'], "Je n'ai pas compris : confirmez-vous ? (oui / non)\n\n" . $pending['question'], null, 'command');
        }

        ConversationMemory::clearPendingAction($conversation['id']);

        if ($decision === 'confirmation_no') {
            return self::respond($conversation['id'], "D'accord, action annulée.", null, 'command');
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
        $context = self::resolveFactualContext($intent['topic'], $houseId);
        $recent = Conversation::recentMessages($conversation['id'], 10);

        $reply = LLMService::generateReply($message, $context, $recent);

        return self::respond($conversation['id'], $reply, $context, 'question');
    }

    private static function handleAnalysis(array $conversation, array $intent, int $houseId, string $message): array
    {
        $context = match ($intent['topic']) {
            'energy'   => RecommendationEngine::energyAnalysis($houseId),
            'security', 'network' => ['suggestions' => RecommendationEngine::suggestions($houseId)],
            'alerts'   => ['recent_alerts' => array_slice(Alert::forHouse($houseId), 0, 5)],
            default    => array_merge(RecommendationEngine::dailySummary($houseId), ['suggestions' => RecommendationEngine::suggestions($houseId)]),
        };

        $recent = Conversation::recentMessages($conversation['id'], 10);
        $reply = LLMService::generateReply($message, $context, $recent);

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
    private static function resolveFactualContext(string $topic, int $houseId): array
    {
        return match ($topic) {
            'temperature' => self::sensorSummary($houseId, 'dht22_temp'),
            'humidity'    => self::sensorSummary($houseId, 'dht22_hum'),
            'energy'      => RecommendationEngine::energyAnalysis($houseId),
            'doors'       => self::equipmentStateSummary($houseId, ['porte', 'fenetre']),
            'lights'      => self::equipmentStateSummary($houseId, ['led', 'relais']),
            'security'    => ['unread_critical_alerts' => count(array_filter(Alert::forHouse($houseId), fn($a) => $a['severity'] === 'critical' && !$a['is_read']))],
            'network'     => ['unknown_devices' => NetworkDevice::countUnknown($houseId), 'devices' => NetworkDevice::forHouse($houseId)],
            'devices'     => ['offline_or_unknown' => array_values(array_filter(NetworkDevice::forHouse($houseId), fn($d) => $d['list_status'] === 'unknown'))],
            default       => self::houseStateSummary($houseId),
        };
    }

    private static function sensorSummary(int $houseId, string $type): array
    {
        $sensors = array_values(array_filter(Sensor::allWithRoom($houseId), fn($s) => $s['type'] === $type));
        return ['sensors' => array_map(fn($s) => ['name' => $s['name'], 'room' => $s['room_name'], 'value' => $s['latest_value'], 'unit' => $s['unit']], $sensors)];
    }

    private static function equipmentStateSummary(int $houseId, array $types): array
    {
        $equipments = array_values(array_filter(Equipment::allWithRoom($houseId), fn($e) => in_array($e['type'], $types, true)));
        return ['equipments' => array_map(fn($e) => ['name' => $e['name'], 'room' => $e['room_name'], 'state' => (int) $e['state'] ? 'ouvert/allumé' : 'fermé/éteint'], $equipments)];
    }

    private static function houseStateSummary(int $houseId): array
    {
        $house = House::find($houseId);
        return array_merge(
            ['mode' => $house['mode'] ?? 'confort'],
            RecommendationEngine::dailySummary($houseId)
        );
    }

    private static function confirmationQuestionFor(array $intent): string
    {
        if ($intent['action'] === 'set_mode' && $intent['mode'] !== 'urgence') {
            return "Confirmez-vous vouloir désactiver le mode urgence et repasser en mode « {$intent['mode']} » ?";
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
            return "Mode « {$result['mode']} » activé.";
        }

        if (empty($result['affected'])) {
            return "C'était déjà dans l'état demandé, aucune action n'était nécessaire.";
        }

        $verb = (int) $result['state'] === 1 ? 'activé(s)/ouvert(s)' : 'désactivé(s)/fermé(s)';
        return implode(', ', $result['affected']) . " $verb.";
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
