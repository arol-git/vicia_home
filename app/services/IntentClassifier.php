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
    private const ROOM_CACHE = []; // Optimisation: cache des pièces pour éviter N requêtes
    /** Verbes d'action -> état cible (1 = activer/ouvrir, 0 = désactiver/fermer). */
    private const ACTION_VERBS = [
        // Activation (1)
        'allume'        => 1, 'allumer'       => 1, 'allumez'    => 1, 'allumé'  => 1,
        'active'        => 1, 'activer'      => 1, 'activez'    => 1, 'activé'  => 1,
        'ouvre'         => 1, 'ouvrir'       => 1, 'ouvrez'     => 1, 'ouvert'  => 1,
        'déverrouille'  => 1, 'déverrouiller' => 1, 'déverrouille-moi' => 1,
        'lance'         => 1, 'lancer'       => 1, 'lancez'     => 1, 'lancé'   => 1,
        'démarre'       => 1, 'démarrer'     => 1, 'démarrez'   => 1, 'démarré' => 1,
        'mets'          => 1, 'mettre'       => 1, 'mettez'     => 1, 'mis'     => 1,
        'passe'         => 1, 'passer'       => 1, 'passez'     => 1, 'passé'   => 1,
        'allume-moi'    => 1, 'active-moi'   => 1,
        
        // Désactivation (0)
        'éteins'        => 0, 'éteindre'     => 0, 'éteignez'   => 0, 'éteint'  => 0,
        'etéins'        => 0, 'éteins-moi'   => 0,
        'désactive'     => 0, 'désactiver'   => 0, 'désactivez' => 0, 'désactivé' => 0,
        'ferme'         => 0, 'fermer'       => 0, 'fermez'     => 0, 'fermé'   => 0,
        'verrouille'    => 0, 'verrouiller'  => 0, 'verrouille-moi' => 0,
        'coupe'         => 0, 'couper'       => 0, 'coupez'     => 0, 'coupé'   => 0,
        'stoppe'        => 0, 'stopper'      => 0, 'stoppez'    => 0, 'stoppé'  => 0,
        'arrête'        => 0, 'arrêter'      => 0, 'arrêtez'    => 0, 'arrêté'  => 0,
        'coupe-moi'     => 0, 'arrête-moi'   => 0, 'stoppe-moi'  => 0,
    ];

    /** Mots-clés de cible -> type d'équipement (voir enum equipments.type). */
    private const TARGET_TYPES = [
        // LED
        'lampe'     => 'led', 'lampes'    => 'led', 'lumière'   => 'led', 'lumières'  => 'led',
        'lumiere'   => 'led', 'lumieres'  => 'led', 'éclairage' => 'led', 'eclairage' => 'led',
        'light'     => 'led', 'lights'    => 'led', 'lamp'      => 'led', 'lamps'     => 'led',
        'led'       => 'led', 'leds'      => 'led',
        
        // Porte
        'porte'     => 'porte', 'portes'    => 'porte', 'portail'  => 'porte', 'portails' => 'porte',
        'gate'      => 'porte', 'gates'     => 'porte', 'door'     => 'porte', 'doors'    => 'porte',
        
        // Fenêtre
        'fenêtre'   => 'fenetre', 'fenêtres' => 'fenetre', 'fenetre'  => 'fenetre', 'fenetres' => 'fenetre',
        'window'    => 'fenetre', 'windows'  => 'fenetre',
        
        // Ventilateur/Climatisation
        'ventilateur'   => 'ventilateur', 'ventilateurs' => 'ventilateur',
        'climatisation' => 'ventilateur', 'clim'  => 'ventilateur', 'climat' => 'ventilateur',
        'climate'       => 'ventilateur', 'fan'   => 'ventilateur', 'fans'   => 'ventilateur',
        'ac'            => 'ventilateur', 'air'   => 'ventilateur',
        
        // Pompe
        'pompe'     => 'pompe', 'pompes'   => 'pompe', 'arrosage' => 'pompe', 'arroseur' => 'pompe',
        'irrigation'=> 'pompe', 'pump'     => 'pompe', 'pumps'    => 'pompe',
        'sprinkler' => 'pompe', 'sprinklers' => 'pompe',
        
        // Sirène/Alarme
        'alarme'    => 'sirene', 'alarmes' => 'sirene', 'sirène'   => 'sirene', 'sirene'   => 'sirene',
        'alerte'    => 'sirene', 'alertes' => 'sirene', 'alarm'    => 'sirene', 'alarms'   => 'sirene',
        'siren'     => 'sirene', 'sirens'  => 'sirene',
        
        // Caméra
        
        // Relais/Prise
        'relais'    => 'relais', 'prise'   => 'relais', 'prises'   => 'relais',
        'relay'     => 'relais', 'plug'    => 'relais', 'outlet'   => 'relais', 'outlets'  => 'relais',
        'socket'    => 'relais',
        
        // Servo
        'servo'     => 'servo', 'servos'   => 'servo', 'moteur'    => 'servo', 'moteurs'  => 'servo',
        'motor'     => 'servo', 'motors'   => 'servo',
    ];

    private const MODE_WORDS = [
        'confort'   => 'confort', 'nuit' => 'nuit', 'absence' => 'absence', 'urgence' => 'urgence',
        'day'       => 'confort', 'night' => 'nuit', 'away'    => 'absence', 'emergency' => 'urgence',
    ];

    private const QUESTION_STARTERS = [
        'quel', 'quelle', 'quels', 'quelles', 'quoi', 'comment', 'pourquoi', 'où',
        'combien', 'y a-t-il', 'y a t il', 'y atil', 'qui', 'toutes', 'tous',
        'est-ce', 'est ce', 'estce', 'is', 'are', 'what', 'how', 'why',
    ];
    
    private const ANALYSIS_WORDS = [
        'résumé', 'resume', 'analyse', 'rapport', 'suggestion', 'conseil', 'conseille',
        'recommand', 'summary', 'status', 'état', 'etat', 'check', 'diagnostic',
        'problème', 'probleme', 'bug', 'issue', 'alert', 'alerte',
    ];
    
    private const CONFIRM_YES = [
        'oui', 'confirme', 'confirmer', "d'accord", 'daccord', 'ok', 'vas-y', 'vaxy',
        'go', 'yes', 'ouais', 'agreed', 'amen', 'c\'est bon', 'cestbon', 'allô',
    ];
    
    private const CONFIRM_NO = [
        'non', 'annule', 'annuler', 'stop', 'laisse', 'non merci', 'nope', 'cancel',
        'abort', 'arrête-toi', 'arrete-toi', 'ne fais pas', 'ne fais rien',
    ];

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
        $matchedVerb = null;
        
        foreach (self::ACTION_VERBS as $verb => $state) {
            if (str_starts_with($normalized, $verb) || str_contains($normalized, " $verb ") || str_contains($normalized, " $verb")) {
                $verbState = $state;
                $matchedVerb = $verb;
                break; // Prendre le premier match (par ordre de déclaration)
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

        // Détection de commande batch : "tous/toutes/partout"
        $scopeAll = (bool) preg_match('/\b(tous?|toutes?|partout|partous)\b/iu', $normalized);

        // Détection de la pièce (seulement si ce n'est pas une commande batch)
        $roomName = null;
        if (!$scopeAll) {
            $rooms = Room::forHouse($houseId); // Optimisation possible : cache
            foreach ($rooms as $room) {
                $roomNormalized = self::normalize($room['name']);
                if (str_contains($normalized, $roomNormalized)) {
                    $roomName = $room['name'];
                    break;
                }
            }
        }

        return [
            'type'        => 'command',
            'action'      => 'toggle_equipment',
            'target_type' => $targetType,
            'target_state' => $verbState,
            'scope_all'   => $scopeAll,
            'room'        => $roomName,
            'matched_verb' => $matchedVerb, // Pour debug et logs
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
            default                                                                                  => 'house_state',
        };
    }

    private static function analysisTopic(string $normalized): string
    {
        return match (true) {
            str_contains($normalized, 'semaine') || str_contains($normalized, 'hebdo') => 'weekly',
            str_contains($normalized, 'énergie') || str_contains($normalized, 'energie') || str_contains($normalized, 'consomm') => 'energy',
            str_contains($normalized, 'sécurité') || str_contains($normalized, 'securite') => 'security',
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
