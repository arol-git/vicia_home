<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

/**
 * Class Sensor
 *
 * Modèle représentant un capteur installé dans une pièce (PIR,
 * DHT22, MQ-2, MQ-135, LDR, RFID, humidité du sol).
 */
class Sensor extends Model
{
    protected static string $table = 'sensors';

    public static function allWithRoom(): array
    {
        $sql = "SELECT s.*, r.name AS room_name,
                       (SELECT sr.value FROM sensor_readings sr WHERE sr.sensor_id = s.id ORDER BY sr.recorded_at DESC LIMIT 1) AS last_value,
                       (SELECT sr.recorded_at FROM sensor_readings sr WHERE sr.sensor_id = s.id ORDER BY sr.recorded_at DESC LIMIT 1) AS last_recorded_at
                FROM sensors s
                INNER JOIN rooms r ON r.id = s.room_id
                ORDER BY r.name ASC, s.name ASC";
        return Database::query($sql)->fetchAll();
    }

    public static function findWithRoom(int $id): ?array
    {
        $sql = "SELECT s.*, r.name AS room_name
                FROM sensors s INNER JOIN rooms r ON r.id = s.room_id
                WHERE s.id = :id LIMIT 1";
        $row = Database::query($sql, ['id' => $id])->fetch();
        return $row ?: null;
    }

    /**
     * Retourne l'historique des mesures d'un capteur sur les N
     * dernières heures, utilisé pour l'alimentation des graphiques
     * Chart.js.
     */
    public static function history(int $sensorId, int $hours = 24): array
    {
        $sql = "SELECT value, recorded_at FROM sensor_readings
                WHERE sensor_id = :id AND recorded_at >= (NOW() - INTERVAL :hours HOUR)
                ORDER BY recorded_at ASC";
        $stmt = Database::getInstance()->prepare($sql);
        $stmt->bindValue(':id', $sensorId, \PDO::PARAM_INT);
        $stmt->bindValue(':hours', $hours, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Enregistre une nouvelle mesure pour un capteur (appelé par le
     * pont MQTT lors de la réception d'un message de télémétrie).
     */
    public static function recordReading(int $sensorId, float $value): int
    {
        return Database::query(
            'INSERT INTO sensor_readings (sensor_id, value, recorded_at) VALUES (:sensor_id, :value, NOW())',
            ['sensor_id' => $sensorId, 'value' => $value]
        ) ? (int) Database::lastInsertId() : 0;
    }

    public static function findByTopic(string $topic): ?array
    {
        $row = Database::query('SELECT * FROM sensors WHERE mqtt_topic = :topic LIMIT 1', ['topic' => $topic])->fetch();
        return $row ?: null;
    }
}
