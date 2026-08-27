<?php

namespace App\Services;

use App\Models\Equipment;
use App\Models\Room;

/**
 * Class VoiceCommandService
 *
 * Analyse les commandes vocales en français et les exécute.
 * Supporte les commandes simples ET les commandes batch (ex: "éteint toutes les lumières").
 *
 * Optimisé pour la performance :
 * - Cache des chambres en mémoire
 * - Bulk lookup d'équipements
 * - Support multi-commandes dans une seule phrase
 */
class VoiceCommandService
{
    private const INTENT_PATTERNS = [
        'on'     => ['allume', 'allumer', 'active', 'activer', 'ouvre', 'ouvrir', 'lance', 'lancer', 'démarre', 'démarrer', 'mets'],
        'off'    => ['éteins', 'éteindre', 'désactive', 'désactiver', 'ferme', 'fermer', 'coupe', 'couper', 'stoppe', 'arrête', 'arrêter', 'éteins-moi', 'etéins'],
        'toggle' => ['bascule', 'basculer', 'inverse', 'inverser'],
    ];

    private const TYPE_PATTERNS = [
        'led'        => ['lampe', 'lampes', 'lumière', 'lumières', 'lumiere', 'lumieres', 'éclairage', 'eclairage', 'light', 'lights'],
        'porte'      => ['porte', 'portes', 'portail', 'gate', 'door', 'doors'],
        'fenetre'    => ['fenêtre', 'fenêtres', 'fenetre', 'fenetres', 'window', 'windows'],
        'ventilateur' => ['ventilateur', 'ventilateurs', 'climatisation', 'clim', 'climate', 'fan', 'fans'],
        'pompe'      => ['pompe', 'arrosage', 'irrigation', 'pump', 'sprinkler'],
            'pompe'      => ['pompe', 'arrosage', 'irrigation', 'pump', 'sprinkler', 'eau', 'water', 'alimentation'],
        'sirene'     => ['alarme', 'sirène', 'sirene', 'alerte', 'alarm', 'siren'],
        'relais'     => ['relais', 'prise', 'prises', 'relay', 'plug', 'outlet'],
        'servo'      => ['servo', 'moteur', 'motor'],
    ];

    /**
     * Parse une commande vocale. Peut retourner UNE commande ou PLUSIEURS.
     *
     * @return array{success: bool, message: string, commands: array}
     *         où commands est un tableau de [equipment_id, intent, room_name]
     *         (vide si success=false)
     */
    public static function parse(string $command, int $houseId): array
    {
        $normalized = self::normalize($command);

        if (empty($normalized)) {
            return [
                'success' => false,
                'message' => 'Commande vide ou non valide.',
                'commands' => [],
            ];
        }

        $intent = self::detectIntent($normalized);
        if (!$intent) {
            return [
                'success' => false,
                'message' => 'Intention non reconnue (allume, éteins, bascule).',
                'commands' => [],
            ];
        }

        $isBatch = self::isBatchCommand($normalized);
        $roomName = self::detectRoom($normalized, $houseId);

        $directEquipment = self::findEquipmentByName($houseId, $normalized, $roomName);
        if ($directEquipment) {
            $targetType = $directEquipment['type'];
            $roomName = $directEquipment['room_name'];
            $equipments = [$directEquipment];
        } else {
            $targetType = self::detectType($normalized);

            if (!$targetType && $roomName) {
                $targetType = 'all';
            }

            if (!$targetType && self::containsAny($normalized, ['appareil', 'appareils', 'équipement', 'équipements', 'equipement', 'equipements', 'totalité', 'totalite'])) {
                $targetType = 'all';
                $isBatch = true;
            }

            if (!$targetType) {
                return [
                    'success' => false,
                    'message' => 'Type d\'équipement non reconnu (lampe, porte, etc.).',
                    'commands' => [],
                ];
            }

            $equipments = self::findMatchingEquipments($houseId, $targetType, $roomName, $isBatch);
        }

        if (empty($equipments)) {
            return [
                'success' => false,
                'message' => $roomName
                    ? "Aucun {$targetType} trouvé dans {$roomName}."
                    : "Aucun {$targetType} trouvé.",
                'commands' => [],
            ];
        }

        $commands = [];
        foreach ($equipments as $eq) {
            $commands[] = [
                'equipment_id' => (int) $eq['id'],
                'intent' => $intent,
                'room_name' => $eq['room_name'],
                'equipment_name' => $eq['name'],
            ];
        }

        $summary = count($commands) > 1
            ? "Commande envoyée à " . count($commands) . " équipements."
            : "Commande envoyée à 1 équipement.";

        return [
            'success' => true,
            'message' => $summary,
            'commands' => $commands,
        ];
    }

    private static function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text, " \t\n\r\0\x0B?!.,");
    }

    private static function detectIntent(string $normalized): ?string
    {
        foreach (self::INTENT_PATTERNS as $intent => $verbs) {
            foreach ($verbs as $verb) {
                if (self::containsWordOrTypo($normalized, $verb)) {
                    return $intent;
                }
            }
        }
        return null;
    }

    private static function detectType(string $normalized): ?string
    {
        foreach (self::TYPE_PATTERNS as $type => $keywords) {
            foreach ($keywords as $keyword) {
                if (self::containsWordOrTypo($normalized, $keyword)) {
                    return $type;
                }
            }
        }
        return null;
    }

    private static function isBatchCommand(string $normalized): bool
    {
        return (bool) preg_match('/\b(tous?|toutes?|partout|partous|totalité|totalite)\b/iu', $normalized);
    }

    private static function containsAny(string $text, array $words): bool
    {
        foreach ($words as $word) {
            if (self::containsWordOrTypo($text, $word)) {
                return true;
            }
        }

        return false;
    }

    private static function containsWordOrTypo(string $text, string $word): bool
    {
        $text = self::comparisonText($text);
        $word = self::comparisonText($word);
        if (str_contains($text, $word)) {
            return true;
        }

        foreach (preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $token) {
            if (strlen($word) >= 5 && levenshtein($token, $word) <= 1) {
                return true;
            }
        }

        return false;
    }

    private static function comparisonText(string $text): string
    {
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
        return strtolower($text);
    }

    private static function detectRoom(string $normalized, int $houseId): ?string
    {
        $rooms = Room::forHouse($houseId);
        foreach ($rooms as $room) {
            $roomNormalized = self::normalize($room['name']);
            $aliases = [$roomNormalized, self::normalize(str_replace([' ', '-'], '', $room['name']))];
            if (in_array($room['type'] ?? '', ['jardin', 'terrasse'], true)) {
                $aliases = array_merge($aliases, ['dehors', 'extérieur', 'exterieur', 'à l’extérieur', 'a l exterieur']);
            }
            foreach ($aliases as $alias) {
                if ($alias !== '' && str_contains($normalized, self::normalize($alias))) {
                    return $room['name'];
                }
            }
        }
        return null;
    }

    private static function findEquipmentByName(int $houseId, string $normalized, ?string $roomName): ?array
    {
        $roomFilter = $roomName ? ' AND r.name = :room_name' : '';
        $params = ['house_id' => $houseId];

        if ($roomName) {
            $params['room_name'] = $roomName;
        }

        $sql = "SELECT e.id, e.name, e.type, r.name AS room_name, e.mqtt_topic
                FROM equipments e
                INNER JOIN rooms r ON r.id = e.room_id
                WHERE r.house_id = :house_id AND e.is_active = 1" . $roomFilter . "
                ORDER BY LENGTH(e.name) DESC, e.name ASC";

        $rows = \App\Core\Database::query($sql, $params)->fetchAll();

        foreach ($rows as $equipment) {
            $candidate = self::normalize($equipment['name']);
            if ($candidate !== '' && str_contains($normalized, $candidate)) {
                return $equipment;
            }

            $aliases = [self::normalize($equipment['name']), self::normalize(str_replace([' ', '-'], '', $equipment['name']))];
            foreach ($aliases as $alias) {
                if ($alias !== '' && str_contains($normalized, $alias)) {
                    return $equipment;
                }
            }
        }

        return null;
    }

    private static function findMatchingEquipments(int $houseId, string $type, ?string $roomName, bool $isBatch): array
    {
        $sql = "SELECT e.id, e.name, e.type, r.name AS room_name
                FROM equipments e
                INNER JOIN rooms r ON r.id = e.room_id
                WHERE r.house_id = :house_id AND e.is_active = 1";

            $params = ['house_id' => $houseId];

            if ($type !== 'all') {
                $sql .= ' AND e.type = :type';
                $params['type'] = $type;
            }

        if ($roomName && !$isBatch) {
            $sql .= " AND r.name = :room_name";
            $params['room_name'] = $roomName;
        }

        $sql .= " ORDER BY r.name, e.name";

        $stmt = \App\Core\Database::query($sql, $params);
        return $stmt->fetchAll();
    }
}
