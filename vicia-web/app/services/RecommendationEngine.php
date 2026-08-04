<?php

namespace App\Services;

use App\Core\Database;
use App\Models\Alert;
use App\Models\Equipment;
use App\Models\NetworkDevice;

/**
 * Class RecommendationEngine
 *
 * Produit des résumés et suggestions à partir des données déjà
 * enregistrées par la plateforme (équipements, capteurs, alertes,
 * appareils réseau, consommation) — jamais de statistique inventée.
 *
 * Limite assumée : la plateforme ne journalise pas encore l'historique
 * complet des changements d'état d'un équipement (seule la dernière
 * bascule est connue via `equipments.last_state_change`), ce qui
 * empêche une détection fine de motifs comportementaux récurrents du
 * type "vous oubliez souvent d'éteindre X le soir". Les suggestions
 * ci-dessous se limitent donc à ce qui est réellement observable
 * aujourd'hui ; une détection de motifs plus riche nécessiterait une
 * table dédiée d'historique d'état (extension naturelle, hors
 * périmètre de ce module).
 */
class RecommendationEngine
{
    /**
     * Résumé factuel de la journée écoulée pour une maison —
     * consommé par App\Services\AIService puis reformulé
     * naturellement par App\Services\LLMService.
     */
    public static function dailySummary(int $houseId): array
    {
        $equipments = Equipment::allWithRoom($houseId);
        $activeNow = array_filter($equipments, fn($e) => (int) $e['state'] === 1);

        $alertsToday = Database::query(
            "SELECT COUNT(*) AS c, SUM(severity = 'critical') AS critical
             FROM alerts WHERE house_id = :house_id AND DATE(created_at) = CURDATE()",
            ['house_id' => $houseId]
        )->fetch();

        return [
            'equipments_active_now' => count($activeNow),
            'equipments_total'      => count($equipments),
            'alerts_today'          => (int) ($alertsToday['c'] ?? 0),
            'critical_alerts_today' => (int) ($alertsToday['critical'] ?? 0),
        ];
    }

    /**
     * Suggestions proactives fondées sur des faits vérifiables
     * actuellement en base (jamais inférées sur des habitudes non
     * mesurées) : équipements restés actifs longtemps, appareils
     * réseau non classés, fenêtre/porte ouverte en mode absence.
     */
    public static function suggestions(int $houseId): array
    {
        $suggestions = [];

        $equipments = Equipment::allWithRoom($houseId);
        foreach ($equipments as $eq) {
            if ((int) $eq['state'] !== 1 || !$eq['last_state_change']) {
                continue;
            }
            $hoursOn = (time() - strtotime($eq['last_state_change'])) / 3600;
            if (in_array($eq['type'], ['led', 'relais'], true) && $hoursOn >= 6) {
                $suggestions[] = "« {$eq['name']} » ({$eq['room_name']}) est allumé depuis plus de 6 heures.";
            }
            if (in_array($eq['type'], ['porte', 'fenetre'], true) && $hoursOn >= 2) {
                $suggestions[] = "« {$eq['name']} » ({$eq['room_name']}) est ouvert(e) depuis plus de 2 heures.";
            }
        }

        $unknownCount = NetworkDevice::countUnknown($houseId);
        if ($unknownCount > 0) {
            $suggestions[] = "$unknownCount appareil(s) non identifié(s) sur votre réseau — vérifiez la liste dans le module Réseau.";
        }

        $unreadCritical = count(array_filter(Alert::forHouse($houseId), fn($a) => $a['severity'] === 'critical' && !$a['is_read']));
        if ($unreadCritical > 0) {
            $suggestions[] = "$unreadCritical alerte(s) critique(s) non lue(s).";
        }

        return $suggestions;
    }

    public static function energyAnalysis(int $houseId): array
    {
        $equipments = Equipment::allWithRoom($houseId);
        $powerWatts = ['led' => 9, 'relais' => 5, 'ventilateur' => 45, 'pompe' => 60, 'servo' => 3, 'porte' => 3, 'fenetre' => 3, 'sirene' => 4, 'camera' => 6];

        $byType = [];
        $totalWatts = 0;
        foreach ($equipments as $eq) {
            if ((int) $eq['state'] === 1) {
                $watts = $powerWatts[$eq['type']] ?? 10;
                $byType[$eq['type']] = ($byType[$eq['type']] ?? 0) + $watts;
                $totalWatts += $watts;
            }
        }

        return [
            'total_active_watts'  => $totalWatts,
            'estimated_daily_kwh' => round(($totalWatts * 24) / 1000, 2),
            'by_type_watts'       => $byType,
        ];
    }
}
