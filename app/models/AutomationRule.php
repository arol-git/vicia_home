<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

/**
 * Class AutomationRule
 *
 * Modèle représentant une règle du moteur d'automatisation
 * (condition -> action), configurable depuis l'interface sans
 * modification du code source.
 */
class AutomationRule extends Model
{
    protected static string $table = 'automation_rules';

    /**
     * Retourne toutes les règles enrichies des noms lisibles du
     * capteur déclencheur et de l'équipement cible.
     */
    public static function allWithLabels(): array
    {
        $sql = "SELECT ar.*, s.name AS sensor_name, s.unit AS sensor_unit, eq.name AS equipment_name
                FROM automation_rules ar
                LEFT JOIN sensors s ON s.id = ar.condition_sensor_id
                LEFT JOIN equipments eq ON eq.id = ar.action_equipment_id
                ORDER BY ar.created_at DESC";
        return Database::query($sql)->fetchAll();
    }

    /**
     * Retourne toutes les règles actives dont la condition porte sur
     * un capteur donné (utilisé par le moteur d'exécution MQTT).
     */
    public static function activeForSensor(int $sensorId): array
    {
        $sql = "SELECT * FROM automation_rules
                WHERE is_active = 1 AND condition_source = 'sensor' AND condition_sensor_id = :sid";
        return Database::query($sql, ['sid' => $sensorId])->fetchAll();
    }

    /**
     * Retourne toutes les règles actives déclenchées par un type
     * d'événement donné (ex. "intrusion", "appareil_inconnu").
     */
    public static function activeForEvent(string $event): array
    {
        $sql = "SELECT * FROM automation_rules
                WHERE is_active = 1 AND condition_source = 'event' AND condition_event = :event";
        return Database::query($sql, ['event' => $event])->fetchAll();
    }

    public static function logExecution(int $ruleId, string $result): void
    {
        Database::query(
            'INSERT INTO automation_logs (rule_id, triggered_at, result) VALUES (:rid, NOW(), :result)',
            ['rid' => $ruleId, 'result' => $result]
        );
    }

    public static function recentLogs(int $limit = 20): array
    {
        $sql = "SELECT al.*, ar.name AS rule_name
                FROM automation_logs al
                INNER JOIN automation_rules ar ON ar.id = al.rule_id
                ORDER BY al.triggered_at DESC LIMIT :limit";
        $stmt = Database::getInstance()->prepare($sql);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
