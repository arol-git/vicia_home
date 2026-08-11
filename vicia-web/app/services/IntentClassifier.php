<?php

namespace App\Services;

use App\Models\Equipment;
use App\Models\Room;

/**
 * Class IntentClassifier
 *
 * Analyse un message utilisateur en français et détermine :
 *   - sa nature (command / question / analysis / confirmation / chitchat) ;
 *   - pour une commande, l'action et la cible (type d'équipement, pièce, mode) ;
 *   - pour une question, la catégorie de la question.
 *
 * Le module reste volontairement déterministe pour les commandes
 * d'action : l'intention métier est résolue sans appel au LLM.
 */
class IntentClassifier
{
    /** Verbes d'action -> état cible (1 = activer/ouvrir, 0 = désactiver/fermer). */
    private const ACTION_VERBS = [
        'allume'      => 1, 'allumer'     => 1, 'active'   => 1, 'activer'  => 1,
        'ouvre'       => 1, 'ouvrir'      => 1, 'deverrouille' => 1, 'deverrouiller' => 1,
        'lance'       => 1, 'lancer'      => 1, 'demarre'  => 1, 'demarrer' => 1,
        'eteins'      => 0, 'eteindre'    => 0, 'desactive' => 0, 'desactiver' => 0,
        'ferme'       => 0, 'fermer'      => 0, 'verrouille' => 0, 'verrouiller' => 0,
        'coupe'       => 0, 'couper'      => 0, 'stoppe'   => 0, 'arrete'   => 0, 'arreter' => 0,
    ];

    /** Mots-clés de cible -> type d'équipement (voir enum equipments.type). */
    private const TARGET_TYPES = [
        'lampe' => 'led', 'lampes' => 'led', 'lumiere' => 'led', 'lumieres' => 'led',
        'eclairage' => 'led',
        'porte' => 'porte', 'portes' => 'porte', 'portail' => 'porte',
        'fenetre' => 'fenetre', 'fenetres' => 'fenetre',
        'ventilateur' => 'ventilateur', 'ventilateurs' => 'ventilateur', 'climatisation' => 'ventilateur',
        'pompe' => 'pompe', 'arrosage' => 'pompe',
        'alarme' => 'sirene', 'sirene' => 'sirene',
        'camera' => 'camera', 'cameras' => 'camera',
        'relais' => 'relais', 'prise' => 'relais', 'prises' => 'relais',
    ];

    private const MODE_WORDS = ['confort' => 'confort', 'nuit' => 'nuit', 'absence' => 'absence', 'urgence' => 'urgence'];

    private const QUESTION_STARTERS = ['quel', 'quelle', 'quels', 'quelles', 'combien', 'y a t il', 'y a t il', 'qui', 'toutes', 'tous', 'est ce', 'est ce'];
    private const ANALYSIS_WORDS = ['resume', 'analyse', 'rapport', 'suggestion', 'conseil', 'conseille', 'recommand'];
    private const CONFIRM_YES = ['oui', 'confirme', 'confirmer', 'd accord', 'daccord', 'ok', 'vas y', 'go'];
    private const CONFIRM_NO = ['non', 'annule', 'annuler', 'stop', 'laisse'];

    /**
     * @return array{type: string, ...} Toujours au moins la clé "type"
     *         parmi confirmation_yes, confirmation_no, command,
     *         question, analysis, chitchat.
     */
    public static function classify(string $text, int $houseId): array
    {
        $normalized = self::normalize($text);

        if (in_array($normalized, self::CONFIRM_YES, true)) {
            return ['type' => 'confirmation_yes'];
        }
        if (in_array($normalized, self::CONFIRM_NO, true)) {
            return ['type' => 'confirmation_no'];
        }

        if ($mode = self::detectMode($normalized)) {
            return ['type' => 'command', 'action' => 'set_mode', 'mode' => $mode];
        }

        if ($command = self::detectEquipmentCommand($normalized, $houseId)) {
            return $command;
        }

        foreach (self::ANALYSIS_WORDS as $word) {
            if (str_contains($normalized, $word)) {
                return ['type' => 'analysis', 'topic' => self::analysisTopic($normalized)];
            }
        }

        foreach (self::QUESTION_STARTERS as $starter) {
            if (str_starts_with($normalized, $starter) || str_contains($normalized, '?')) {
                return ['type' => 'question', 'topic' => self::questionTopic($normalized)];
            }
        }

        return ['type' => 'chitchat'];
    }

    private static function detectMode(string $normalized): ?string
    {
        if (!str_contains($normalized, 'mode') && !preg_match('/passe en|active le|activer le/', $normalized)) {
            return null;
        }
        foreach (self::MODE_WORDS as $word => $mode) {
            if (str_contains($normalized, $word)) {
                return $mode;
            }
        }
        return null;
    }

    private static function detectEquipmentCommand(string $normalized, int $houseId): ?array
    {
        $action = self::detectActionState($normalized);
        if ($action === null) {
            return null;
        }

        $targetType = self::detectTargetType($normalized);
        $roomName = self::detectRoom($normalized, $houseId);

        if ($targetType === null && $roomName !== null) {
            $targetType = self::inferDefaultTargetType($roomName, $houseId);
        }

        if ($targetType === null) {
            return null;
        }

        $scopeAll = self::detectScopeAll($normalized, $targetType, $roomName);

        return [
            'type'         => 'command',
            'action'       => 'toggle_equipment',
            'target_type'  => $targetType,
            'target_state' => $action['state'],
            'scope_all'    => $scopeAll,
            'room'         => $roomName,
        ];
    }

    private static function detectActionState(string $normalized): ?array
    {
        foreach (self::ACTION_VERBS as $verb => $state) {
            if (self::containsWord($normalized, $verb)) {
                return ['state' => $state, 'verb' => $verb];
            }
        }
        return null;
    }

    private static function detectTargetType(string $normalized): ?string
    {
        foreach (self::TARGET_TYPES as $word => $type) {
            if (self::containsWord($normalized, $word)) {
                return $type;
            }
        }
        return null;
    }

    private static function detectRoom(string $normalized, int $houseId): ?string
    {
        foreach (Room::forHouse($houseId) as $room) {
            $roomNormalized = self::normalize($room['name']);
            if (self::containsWord($normalized, $roomNormalized)) {
                return $room['name'];
            }
        }
        return null;
    }

    private static function inferDefaultTargetType(string $roomName, int $houseId): ?string
    {
        $equipments = Equipment::allWithRoom($houseId);
        $roomNameNormalized = self::normalize($roomName);
        $roomEquipments = array_filter($equipments, fn($e) => self::normalize($e['room_name']) === $roomNameNormalized);
        $types = array_values(array_unique(array_column($roomEquipments, 'type')));

        if (count($types) === 1) {
            return $types[0];
        }

        if (in_array('led', $types, true)) {
            return 'led';
        }
        if (in_array('relais', $types, true)) {
            return 'relais';
        }

        return $types[0] ?? null;
    }

    private static function detectScopeAll(string $normalized, string $targetType, ?string $roomName): bool
    {
        if (preg_match('/\b(tout|tous|toutes|ensemble|globalement|entier)\b/', $normalized)) {
            return true;
        }

        return $roomName !== null && strpos($normalized, $targetType) === false;
    }

    private static function questionTopic(string $normalized): string
    {
        return match (true) {
            str_contains($normalized, 'temperature') => 'temperature',
            str_contains($normalized, 'humidite') => 'humidity',
            str_contains($normalized, 'energie') || str_contains($normalized, 'consomm') => 'energy',
            str_contains($normalized, 'porte') => 'doors',
            str_contains($normalized, 'lampe') || str_contains($normalized, 'lumiere') => 'lights',
            str_contains($normalized, 'intrusion') => 'security',
            str_contains($normalized, 'wifi') || str_contains($normalized, 'wi fi') || str_contains($normalized, 'connecte') || str_contains($normalized, 'reseau') => 'network',
            str_contains($normalized, 'hors ligne') || str_contains($normalized, 'appareils') => 'devices',
            default => 'house_state',
        };
    }

    private static function analysisTopic(string $normalized): string
    {
        return match (true) {
            str_contains($normalized, 'semaine') || str_contains($normalized, 'hebdo') => 'weekly',
            str_contains($normalized, 'energie') || str_contains($normalized, 'consomm') => 'energy',
            str_contains($normalized, 'securite') => 'security',
            str_contains($normalized, 'reseau') => 'network',
            str_contains($normalized, 'alerte') => 'alerts',
            default => 'daily',
        };
    }

    private static function containsWord(string $haystack, string $word): bool
    {
        return preg_match('/(?:^|\s)' . preg_quote($word, '/') . '(?:$|\s)/u', $haystack) === 1;
    }

    /**
     * Normalise un texte pour la comparaison : minuscules, accents
     * retirés, ponctuation supprimée, espaces multiples réduits.
     */
    private static function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
        $text = preg_replace('/\s+/u', ' ', $text);
        $text = trim($text);
        return self::foldAccents($text);
    }

    private static function foldAccents(string $text): string
    {
        if (function_exists('transliterator_transliterate')) {
            $converted = transliterator_transliterate('Any-Latin; Latin-ASCII', $text);
            if ($converted !== false) {
                return strtolower(preg_replace('/[^a-z0-9\s]/', '', $converted));
            }
        }

        if (extension_loaded('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT', $text);
            if ($converted !== false) {
                return strtolower(preg_replace('/[^a-z0-9\s]/', '', $converted));
            }
        }

        $replacements = [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
            'æ' => 'ae', 'ç' => 'c', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ñ' => 'n',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ÿ' => 'y',
            'œ' => 'oe', 'æ' => 'ae',
        ];

        return strtolower(strtr($text, $replacements));
    }
}
