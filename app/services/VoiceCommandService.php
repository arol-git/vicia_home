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
        '/^(allume|active|mets|met|ouvre|ouvrir)/i' => 'on',
        // Éteindre
        '/^(eteins|eteindre|desactive|coupe|ferme|arrete|arreter)/i' => 'off',
        // Bascule
        '/^(bascule|inverse|toggle)/i' => 'toggle',
    ];

    /**
     * Mots-clés ignorables dans les commandes (articles, prépositions).
     */
    private const STOP_WORDS = ['la', 'le', 'les', 'de', 'du', 'un', 'une', 'des', 'et', 'ou', 'l\''];

    private const TYPE_SYNONYMS = [
        'led' => ['lampe', 'lampes', 'lumiere', 'lumieres', 'eclairage', 'eclairages', 'led'],
        'relais' => ['prise', 'prises', 'relais'],
        'ventilateur' => ['ventilateur', 'ventilateurs', 'ventilo', 'climatisation', 'clim'],
        'pompe' => ['pompe', 'arrosage'],
        'porte' => ['porte', 'portes', 'portail', 'garage'],
        'fenetre' => ['fenetre', 'fenetres'],
        'sirene' => ['sirene', 'alarme'],
        'camera' => ['camera', 'cameras'],
        'servo' => ['servo', 'moteur'],
    ];

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

        $normalized = self::normalize($command);

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
        $detectedRoom = self::detectRoom($normalized, Room::forHouse($houseId));
        $detectedType = self::detectType($normalized);

        // Cherche la meilleure correspondance : équipement + pièce
        $bestMatch = null;
        $bestScore = 0;

        foreach ($equipments as $eq) {
            $eqName = self::normalize($eq['name']);
            $roomName = self::normalize($eq['room_name']);
            $eqType = self::normalize($eq['type']);

            if ($detectedRoom && $roomName !== self::normalize($detectedRoom['name'])) {
                continue;
            }

            if ($detectedType && $eqType !== $detectedType) {
                continue;
            }

            $score = 0.0;

            if (str_contains($normalized, $eqName)) {
                $score += 3;
            } else {
                $score += self::fuzzyMatch($eqName, $normalized);
            }

            if ($detectedType && $eqType === $detectedType) {
                $score += 2;
            }

            if ($detectedRoom && $roomName === self::normalize($detectedRoom['name'])) {
                $score += 2;
            } elseif (str_contains($normalized, $roomName)) {
                $score += 1;
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = [
                    'equipment_id' => $eq['id'],
                    'equipment_name' => $eq['name'],
                    'room_name' => $eq['room_name'],
                ];
            }
        }

        if (!$bestMatch || $bestScore < 1.0) {
            return ['success' => false, 'message' => 'Équipement non reconnu dans la commande'];
        }

        return array_merge(['success' => true], $bestMatch);
    }

    private static function detectRoom(string $normalized, array $rooms): ?array
    {
        foreach ($rooms as $room) {
            $name = self::normalize($room['name']);
            $type = self::normalize($room['type'] ?? '');

            if (($name && str_contains($normalized, $name)) || ($type && str_contains($normalized, $type))) {
                return $room;
            }
        }

        return null;
    }

    private static function detectType(string $normalized): ?string
    {
        foreach (self::TYPE_SYNONYMS as $type => $words) {
            foreach ($words as $word) {
                if (preg_match('/\b' . preg_quote($word, '/') . '\b/u', $normalized)) {
                    return $type;
                }
            }
        }

        return null;
    }

    private static function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = str_replace(['’', "'"], ' ', $text);
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        $text = $ascii ?: $text;
        $text = preg_replace('/[^a-z0-9\s]+/', ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
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
