<?php

namespace App\Services;

use App\Models\Room;

/**
 * Class IntentClassifier
 *
 * Analyse un message utilisateur en français et détermine :
 *   - sa nature (command / question / analysis / confirmation / chitchat) ;
 *   - pour une commande, l'action et la cible (type d'équipement, pièce, mode) ;
 *   - pour une question, la catégorie de la question.
 *
 * Volontairement déterministe (expressions régulières et
 * dictionnaires de synonymes) plutôt que déléguée au LLM : plus
 * rapide, gratuit, prévisible, et suffisant pour un domaine fermé
 * (piloter une maison). Le LLM (App\Services\LLMService) n'intervient
 * jamais dans cette étape — il ne sert qu'à formuler la réponse une
 * fois l'intention déjà résolue avec certitude.
 */
class IntentClassifier
{
    /** Verbes d'action -> état cible (1 = activer/ouvrir, 0 = désactiver/fermer). */
    private const ACTION_VERBS = [
        'allume'      => 1, 'allumer'     => 1, 'active'   => 1, 'activer'  => 1,
        'ouvre'       => 1, 'ouvrir'      => 1, 'déverrouille' => 1, 'déverrouiller' => 1,
        'lance'       => 1, 'lancer'      => 1, 'démarre'  => 1, 'démarrer' => 1,
        'éteins'      => 0, 'éteindre'    => 0, 'désactive' => 0, 'désactiver' => 0,
        'ferme'       => 0, 'fermer'      => 0, 'verrouille' => 0, 'verrouiller' => 0,
        'coupe'       => 0, 'couper'      => 0, 'stoppe'   => 0, 'arrête'   => 0, 'arrêter' => 0,
    ];

    /** Mots-clés de cible -> type d'équipement (voir enum equipments.type). */
    private const TARGET_TYPES = [
        'lampe' => 'led', 'lampes' => 'led', 'lumière' => 'led', 'lumières' => 'led', 'lumiere' => 'led', 'lumieres' => 'led',
        'éclairage' => 'led', 'eclairage' => 'led',
        'porte' => 'porte', 'portes' => 'porte', 'portail' => 'porte',
        'fenêtre' => 'fenetre', 'fenêtres' => 'fenetre', 'fenetre' => 'fenetre', 'fenetres' => 'fenetre',
        'ventilateur' => 'ventilateur', 'ventilateurs' => 'ventilateur', 'climatisation' => 'ventilateur',
        'pompe' => 'pompe', 'arrosage' => 'pompe',
        'alarme' => 'sirene', 'sirène' => 'sirene', 'sirene' => 'sirene',
        'caméra' => 'camera', 'camera' => 'camera', 'caméras' => 'camera',
        'relais' => 'relais', 'prise' => 'relais', 'prises' => 'relais',
    ];

    private const MODE_WORDS = ['confort' => 'confort', 'nuit' => 'nuit', 'absence' => 'absence', 'urgence' => 'urgence'];

    private const QUESTION_STARTERS = ['quel', 'quelle', 'quels', 'quelles', 'combien', 'y a-t-il', 'y a t il', 'qui', 'toutes', 'tous', 'est-ce', 'est ce'];
    private const ANALYSIS_WORDS = ['résumé', 'resume', 'analyse', 'rapport', 'suggestion', 'conseil', 'conseille', 'recommand'];
    private const CONFIRM_YES = ['oui', 'confirme', 'confirmer', "d'accord", 'daccord', 'ok', 'vas-y', 'go'];
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
        $verbState = null;
        foreach (self::ACTION_VERBS as $verb => $state) {
            if (str_starts_with($normalized, $verb) || str_contains($normalized, " $verb ") || str_contains($normalized, " $verb")) {
                $verbState = $state;
                $matchedVerb = $verb;
                break;
            }
        }
        if ($verbState === null) {
            return null;
        }

        $targetType = null;
        foreach (self::TARGET_TYPES as $word => $type) {
            if (str_contains($normalized, $word)) {
                $targetType = $type;
                break;
            }
        }
        if ($targetType === null) {
            return null;
        }

        $scopeAll = (bool) preg_match('/\btout(es)?\b/', $normalized);

        $roomName = null;
        foreach (Room::forHouse($houseId) as $room) {
            $roomNormalized = self::normalize($room['name']);
            if (str_contains($normalized, $roomNormalized)) {
                $roomName = $room['name'];
                break;
            }
        }

        return [
            'type'        => 'command',
            'action'      => 'toggle_equipment',
            'target_type' => $targetType,
            'target_state' => $verbState,
            'scope_all'   => $scopeAll,
            'room'        => $roomName,
        ];
    }

    private static function questionTopic(string $normalized): string
    {
        return match (true) {
            str_contains($normalized, 'température') || str_contains($normalized, 'temperature') => 'temperature',
            str_contains($normalized, 'humidité') || str_contains($normalized, 'humidite')         => 'humidity',
            str_contains($normalized, 'énergie') || str_contains($normalized, 'energie') || str_contains($normalized, 'consomm') => 'energy',
            str_contains($normalized, 'porte')                                                     => 'doors',
            str_contains($normalized, 'lampe') || str_contains($normalized, 'lumiere') || str_contains($normalized, 'lumière') => 'lights',
            str_contains($normalized, 'intrusion')                                                 => 'security',
            str_contains($normalized, 'wifi') || str_contains($normalized, 'wi-fi') || str_contains($normalized, 'connecté') || str_contains($normalized, 'reseau') || str_contains($normalized, 'réseau') => 'network',
            str_contains($normalized, 'hors ligne') || str_contains($normalized, 'appareils')       => 'devices',
            default                                                                                  => 'house_state',
        };
    }

    private static function analysisTopic(string $normalized): string
    {
        return match (true) {
            str_contains($normalized, 'semaine') || str_contains($normalized, 'hebdo') => 'weekly',
            str_contains($normalized, 'énergie') || str_contains($normalized, 'energie') || str_contains($normalized, 'consomm') => 'energy',
            str_contains($normalized, 'sécurité') || str_contains($normalized, 'securite') => 'security',
            str_contains($normalized, 'réseau') || str_contains($normalized, 'reseau') => 'network',
            str_contains($normalized, 'alerte') => 'alerts',
            default => 'daily',
        };
    }

    /**
     * Normalise un texte pour la comparaison : minuscules, accents
     * conservés (le français en dépend sémantiquement), espaces
     * multiples réduits, ponctuation de fin retirée.
     */
    private static function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = preg_replace('/\s+/u', ' ', $text);
        return trim($text, " \t\n\r\0\x0B?!.");
    }
}
