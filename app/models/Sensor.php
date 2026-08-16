<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

/**
 * Class Sensor
 *
 * Modèle représentant un capteur installé dans une pièce (PIR,
 * DHT22, MQ-2, MQ-135, LDR, RFID, humidité du sol, énergie). Comme pour les
 * équipements, un capteur est scopé à une maison par transitivité
 * via sa pièce.
 */
class Sensor extends Model
{
    protected static string $table = 'sensors';

    public static function allWithRoom(int $houseId): array
    {
        $sql = "SELECT s.*, r.name AS room_name,
                       (SELECT sr.value FROM sensor_readings sr WHERE sr.sensor_id = s.id ORDER BY sr.recorded_at DESC LIMIT 1) AS latest_value,
                       (SELECT sr.recorded_at FROM sensor_readings sr WHERE sr.sensor_id = s.id ORDER BY sr.recorded_at DESC LIMIT 1) AS last_recorded_at
                FROM sensors s
                INNER JOIN rooms r ON r.id = s.room_id
                WHERE r.house_id = :house_id
                ORDER BY r.name ASC, s.name ASC";
        return Database::query($sql, ['house_id' => $houseId])->fetchAll();
    }

    public static function activeForHouse(int $houseId): array
    {
        $sql = "SELECT s.*
                FROM sensors s
                INNER JOIN rooms r ON r.id = s.room_id
                WHERE r.house_id = :house_id
                ORDER BY s.name ASC";
        return Database::query($sql, ['house_id' => $houseId])->fetchAll();
    }

    public static function findWithRoom(int $id): ?array
    {
        $sql = "SELECT s.*, r.name AS room_name, r.house_id
                FROM sensors s INNER JOIN rooms r ON r.id = s.room_id
                WHERE s.id = :id LIMIT 1";
        $row = Database::query($sql, ['id' => $id])->fetch();
        return $row ?: null;
    }

    /**
     * Vérifie qu'un capteur appartient bien à la maison donnée.
     */
    public static function belongsToHouse(int $sensorId, int $houseId): bool
    {
        $sql = "SELECT s.id FROM sensors s
                INNER JOIN rooms r ON r.id = s.room_id
                WHERE s.id = :id AND r.house_id = :house_id LIMIT 1";
        return (bool) Database::query($sql, ['id' => $sensorId, 'house_id' => $houseId])->fetch();
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
        Database::query(
            'INSERT INTO sensor_readings (sensor_id, value, recorded_at) VALUES (:sensor_id, :value, NOW())',
            ['sensor_id' => $sensorId, 'value' => $value]
        );
        return (int) Database::lastInsertId();
    }

    public static function setActive(int $id, bool $active): void
    {
        self::update($id, ['is_active' => $active ? 1 : 0]);
    }

    /**
     * Retrouve un capteur à partir de son topic MQTT complet
     * (home/<slug-maison>/...), ce qui identifie sans ambiguïté à la
     * fois le capteur ET la maison dont il dépend.
     */
    public static function findByTopic(string $topic): ?array
    {
        $normalized = self::normalizeTopic($topic);
        $rows = Database::query('SELECT * FROM sensors')->fetchAll();
        foreach ($rows as $row) {
            if (self::normalizeTopic((string) $row['mqtt_topic']) === $normalized) {
                return $row;
            }
        }

        return null;
    }

    public static function findByTopicWithRoom(string $topic): ?array
    {
        $normalized = self::normalizeTopic($topic);
        $sql = "SELECT s.*, r.name AS room_name, r.house_id
                FROM sensors s
                INNER JOIN rooms r ON r.id = s.room_id";
        $rows = Database::query($sql)->fetchAll();
        foreach ($rows as $row) {
            if (self::normalizeTopic((string) $row['mqtt_topic']) === $normalized) {
                return $row;
            }
        }

        return null;
    }

    private static function normalizeTopic(string $topic): string
    {
        $normalized = preg_replace('#/+#', '/', trim($topic));
        $normalized = strtolower((string) $normalized);
        $normalized = str_replace(['é', 'è', 'ê', 'à', 'â', 'ç', 'ù', 'û'], ['e', 'e', 'e', 'a', 'a', 'c', 'u', 'u'], $normalized);
        $normalized = rtrim($normalized, '/');

        $segments = array_values(array_filter(explode('/', $normalized), fn ($segment) => $segment !== ''));
        if (count($segments) >= 2 && $segments[0] === 'home' && $segments[1] === 'home') {
            array_shift($segments);
        }

        foreach ($segments as $index => $segment) {
            $segments[$index] = match ($segment) {
                'consumption', 'consommation' => 'consumption',
                'energie', 'energy' => 'energy',
                'temperature', 'temperaturec', 'temperature_c', 'température', 'temp' => 'temp',
                'humidity', 'humidite', 'humidity_pct', 'hum' => 'hum',
                'power', 'watts', 'watt' => 'power',
                'kwh', 'kilowattheure', 'kwatthour' => 'kwh',
                default => $segment,
            };
        }

        return implode('/', $segments);
    }
}
