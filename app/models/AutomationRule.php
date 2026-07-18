<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

/**
 * Class AutomationRule
 *
 * Modèle représentant une règle du moteur d'automatisation
 * (condition -> action) au sein d'UNE maison précise, configurable
 * depuis l'interface sans modification du code source.
 */
class AutomationRule extends Model
{
    protected static string $table = 'automation_rules';

    /**
     * Retourne toutes les règles d'une maison, enrichies des noms
     * lisibles du capteur déclencheur et de l'équipement cible.
     */
    public static function allWithLabels(int $houseId): array
    {
        $sql = "SELECT ar.*, s.name AS sensor_name, s.unit AS sensor_unit, eq.name AS equipment_name
                FROM automation_rules ar
                LEFT JOIN sensors s ON s.id = ar.condition_sensor_id
                LEFT JOIN equipments eq ON eq.id = ar.action_equipment_id
                WHERE ar.house_id = :house_id
                ORDER BY ar.created_at DESC";
        return Database::query($sql, ['house_id' => $houseId])->fetchAll();
    }

    public static function belongsToHouse(int $ruleId, int $houseId): bool
    {
        $row = Database::query(
            'SELECT id FROM automation_rules WHERE id = :id AND house_id = :house_id LIMIT 1',
            ['id' => $ruleId, 'house_id' => $houseId]
        )->fetch();
        return (bool) $row;
    }

    /**
     * Retourne toutes les règles actives, TOUTES MAISONS CONFONDUES,
     * dont la condition porte sur un capteur donné (utilisé par le
     * moteur d'exécution MQTT, qui reçoit un identifiant de capteur
     * déjà unique et n'a donc pas besoin d'un filtre de maison
     * supplémentaire — le capteur détermine intrinsèquement la maison).
     */
    public static function activeForSensor(int $sensorId): array
    {
        $sql = "SELECT * FROM automation_rules
                WHERE is_active = 1 AND condition_source = 'sensor' AND condition_sensor_id = :sid";
        return Database::query($sql, ['sid' => $sensorId])->fetchAll();
    }

    /**
     * Retourne les règles actives d'UNE maison déclenchées par un
     * type d'événement donné (ex. "intrusion", "appareil_inconnu").
     */
    public static function activeForEvent(int $houseId, string $event): array
    {
        $sql = "SELECT * FROM automation_rules
                WHERE is_active = 1 AND house_id = :house_id
                  AND condition_source = 'event' AND condition_event = :event";
        return Database::query($sql, ['house_id' => $houseId, 'event' => $event])->fetchAll();
    }

    public static function logExecution(int $ruleId, string $result): void
    {
        Database::query(
            'INSERT INTO automation_logs (rule_id, triggered_at, result) VALUES (:rid, NOW(), :result)',
            ['rid' => $ruleId, 'result' => $result]
        );
    }

    public static function recentLogs(int $houseId, int $limit = 20): array
    {
        $sql = "SELECT al.*, ar.name AS rule_name
                FROM automation_logs al
                INNER JOIN automation_rules ar ON ar.id = al.rule_id
                WHERE ar.house_id = :house_id
                ORDER BY al.triggered_at DESC LIMIT :limit";
        $stmt = Database::getInstance()->prepare($sql);
        $stmt->bindValue(':house_id', $houseId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
