<?php

namespace App\Services;

use App\Models\Equipment;
use App\Models\Room;

/**
 * Class VoiceCommandService
 *
 * Service indépendant dédiée au traitement des commandes vocales.
 * Parse une chaîne de texte, identifie l'intention et l'équipement cible,
 * puis retourne une action MQTT prête à être exécutée.
 *
 * Cette classe n'a aucune dépendance avec AIService, LLMService ou
 * IntentClassifier — elle traite les commandes vocales de manière
 * directe et déterministe.
 */
class VoiceCommandService
{
    /**
     * Patterns de reconnaissance pour les commandes vocales.
     * Format : 'pattern' => 'intent'
     */
    private const INTENT_PATTERNS = [
        // Allumer
        '/^(allume|active|mets|ouvre)/i' => 'on',
        // Éteindre
        '/^(éteins|désactive|coupe|ferme|arrête)/i' => 'off',
        // Bascule
        '/^(bascule|inverse|toggle)/i' => 'toggle',
    ];

    /**
     * Mots-clés ignorables dans les commandes (articles, prépositions).
     */
    private const STOP_WORDS = ['la', 'le', 'les', 'de', 'du', 'un', 'une', 'des', 'et', 'ou', 'l\''];

    /**
     * Parse une commande vocale et retourne les détails du traitement.
     *
     * @return array{success: bool, intent: string, room_name: string|null, equipment_name: string|null, equipment_id: int|null, message: string}
     */
    public static function parse(string $command, int $houseId): array
    {
        $command = trim($command);
        if (strlen($command) === 0) {
            return ['success' => false, 'message' => 'Commande vide'];
        }

        // Normalisation : minuscules, accents préservés, suppression ponctuation
        $normalized = mb_strtolower($command);
        $normalized = preg_replace('/[.,!?]/', '', $normalized);

        // Détection de l'intention
        $intent = self::detectIntent($normalized);
        if (!$intent) {
            return ['success' => false, 'message' => 'Intention non reconnue'];
        }

        // Extraction de la pièce et de l'équipement
        $extraction = self::extractRoomAndEquipment($normalized, $houseId);
        if (!$extraction['success']) {
            return $extraction;
        }

        return [
            'success' => true,
            'intent' => $intent,
            'room_name' => $extraction['room_name'],
            'equipment_name' => $extraction['equipment_name'],
            'equipment_id' => $extraction['equipment_id'],
            'message' => self::buildFeedback($intent, $extraction['equipment_name'], $extraction['room_name']),
        ];
    }

    /**
     * Détecte l'intention parmi les patterns reconnus.
     */
    private static function detectIntent(string $normalized): ?string
    {
        foreach (self::INTENT_PATTERNS as $pattern => $intent) {
            if (preg_match($pattern, $normalized)) {
                return $intent;
            }
        }
        return null;
    }

    /**
     * Extrait la pièce et l'équipement de la commande.
     * Cherche à matcher les noms des pièces et équipements dans la base.
     *
     * @return array{success: bool, room_name: string|null, equipment_name: string|null, equipment_id: int|null, message?: string}
     */
    private static function extractRoomAndEquipment(string $normalized, int $houseId): array
    {
        // Récupérer tous les équipements de la maison
        $equipments = Equipment::allWithRoom($houseId);
        if (empty($equipments)) {
            return ['success' => false, 'message' => 'Aucun équipement trouvé dans cette maison'];
        }

        // Récupérer toutes les pièces
        $rooms = Room::forHouse($houseId);
        $roomMap = array_combine(array_map(fn($r) => mb_strtolower($r['name']), $rooms), $rooms);

        // Cherche la meilleure correspondance : équipement + pièce
        $bestMatch = null;
        $bestScore = 0;

        foreach ($equipments as $eq) {
            $eqNameLower = mb_strtolower($eq['name']);
            $roomNameLower = mb_strtolower($eq['room_name']);

            // Score : distance de Levenshtein pour l'équipement + présence de la pièce
            $eqScore = self::fuzzyMatch($eqNameLower, $normalized);
            $roomScore = self::fuzzyMatch($roomNameLower, $normalized);

            // Si l'équipement est trouvé avec une certaine confiance
            if ($eqScore > 0.6) {
                $score = $eqScore + ($roomScore > 0.6 ? 1 : 0);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestMatch = [
                        'equipment_id' => $eq['id'],
                        'equipment_name' => $eq['name'],
                        'room_name' => $eq['room_name'],
                    ];
                }
            }
        }

        if (!$bestMatch) {
            return ['success' => false, 'message' => 'Équipement non reconnu dans la commande'];
        }

        return array_merge(['success' => true], $bestMatch);
    }

    /**
     * Calcule la similarité entre deux chaînes (0.0 à 1.0).
     * Utilise une approche simple de substring + distance Levenshtein.
     */
    private static function fuzzyMatch(string $target, string $text): float
    {
        $target = trim($target);
        $text = trim($text);

        // Si le target est complètement contenu dans text
        if (strpos($text, $target) !== false) {
            return 1.0;
        }

        // Distance Levenshtein normalisée
        $distance = levenshtein($target, $text);
        $maxLen = max(strlen($target), strlen($text));
        $similarity = 1.0 - ($distance / $maxLen);

        return max(0, $similarity);
    }

    /**
     * Construit le message de feedback pour l'utilisateur.
     */
    private static function buildFeedback(string $intent, string $equipmentName, ?string $roomName = null): string
    {
        $room = $roomName ? " du/de la $roomName" : '';

        return match ($intent) {
            'on' => "Activation de $equipmentName$room",
            'off' => "Désactivation de $equipmentName$room",
            'toggle' => "Basculement de $equipmentName$room",
            default => "Commande exécutée",
        };
    }
}
