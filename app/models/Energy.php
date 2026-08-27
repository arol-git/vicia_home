<?php

namespace App\Models;

use App\Core\Database;

/**
 * Lecture de l'unique compteur énergétique global d'une maison.
 * Les équipements ne sont jamais utilisés pour calculer l'énergie.
 */
class Energy
{
    private const SENSOR_TYPES = ['energy_power', 'energy_kwh', 'energy_consumption'];
    private const MAX_POWER_GAP_SECONDS = 21600;

    public static function globalSensor(int $houseId): ?array
    {
        $sql = "SELECT s.*, r.name AS room_name
                FROM sensors s
                INNER JOIN rooms r ON r.id = s.room_id
                WHERE r.house_id = :house_id
                  AND s.is_active = 1
                  AND s.type IN ('energy_power', 'energy_kwh', 'energy_consumption')
                ORDER BY FIELD(s.type, 'energy_kwh', 'energy_consumption', 'energy_power'), s.id
                LIMIT 1";
        $sensor = Database::query($sql, ['house_id' => $houseId])->fetch();
        return $sensor ?: null;
    }

    public static function month(int $houseId, string $month): array
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }

        $start = $month . '-01 00:00:00';
        $end = date('Y-m-d H:i:s', strtotime($start . ' +1 month'));
        $sensor = self::globalSensor($houseId);
        $empty = [
            'month' => $month,
            'sensor' => $sensor,
            'unit_mode' => null,
            'total_kwh' => null,
            'daily' => [],
            'has_data' => false,
        ];

        if (!$sensor) {
            return $empty;
        }

        $rows = Database::query(
            'SELECT value, recorded_at FROM sensor_readings
             WHERE sensor_id = :sensor_id AND recorded_at >= :start AND recorded_at < :end
             ORDER BY recorded_at ASC',
            ['sensor_id' => $sensor['id'], 'start' => $start, 'end' => $end]
        )->fetchAll();

        $rows = array_values(array_filter($rows, static fn(array $row): bool => is_numeric($row['value']) && (float) $row['value'] >= 0));

        $mode = self::unitMode($sensor);
        $daily = [];
        foreach ($rows as $row) {
            $day = substr((string) $row['recorded_at'], 0, 10);
            $daily[$day] = $daily[$day] ?? 0.0;
        }

        $calculationRows = $rows;
        if ($mode === 'cumulative') {
            if ($rows !== []) {
                $previous = Database::query(
                    'SELECT value, recorded_at FROM sensor_readings
                     WHERE sensor_id = :sensor_id AND recorded_at < :start
                     ORDER BY recorded_at DESC LIMIT 1',
                    ['sensor_id' => $sensor['id'], 'start' => $start]
                )->fetch();
                if ($previous) {
                    array_unshift($calculationRows, $previous);
                }
            }

            $total = self::cumulativeTotal($calculationRows, $sensor['unit']);
            $previous = null;
            foreach ($calculationRows as $row) {
                $value = (float) $row['value'];
                if ($previous !== null && $value >= $previous) {
                    $day = substr((string) $row['recorded_at'], 0, 10);
                    $daily[$day] += self::toKwh($value - $previous, $sensor['unit']);
                }
                $previous = $value;
            }
        } elseif ($mode === 'power') {
            $total = 0.0;
            $previous = null;
            foreach ($rows as $row) {
                $value = (float) $row['value'];
                if ($previous !== null) {
                    $seconds = strtotime((string) $row['recorded_at']) - strtotime((string) $previous['recorded_at']);
                    if ($seconds > 0 && $seconds <= self::MAX_POWER_GAP_SECONDS) {
                        $energy = (($value + (float) $previous['value']) / 2) * $seconds / 3600000;
                        $day = substr((string) $row['recorded_at'], 0, 10);
                        $daily[$day] += $energy;
                        $total += $energy;
                    }
                }
                $previous = $row;
            }
        } else {
            return $empty;
        }

        if (count($calculationRows) < 2) {
            $daily = [];
        }

        $daily = array_map(static fn(float $value): float => round($value, 3), $daily);
        return [
            'month' => $month,
            'sensor' => $sensor,
            'unit_mode' => $mode,
            'total_kwh' => (($mode === 'cumulative' && count($calculationRows) < 2) || ($mode === 'power' && count($rows) < 2)) ? null : round($total, 3),
            'daily' => $daily,
            'has_data' => $rows !== [],
        ];
    }

    public static function history(int $houseId, int $months = 12): array
    {
        $months = max(1, min($months, 24));
        $items = [];
        $cursor = new \DateTimeImmutable('first day of this month');
        for ($index = 0; $index < $months; $index++) {
            $month = $cursor->modify('-' . $index . ' months')->format('Y-m');
            $data = self::month($houseId, $month);
            $items[] = [
                'month' => $month,
                'total_kwh' => $data['total_kwh'],
                'has_data' => $data['has_data'],
            ];
        }
        return $items;
    }

    private static function unitMode(array $sensor): ?string
    {
        $unit = strtolower(trim((string) ($sensor['unit'] ?? '')));
        if ((string) $sensor['type'] === 'energy_power' || str_contains($unit, 'watt') || $unit === 'w') {
            return 'power';
        }
        if ((string) $sensor['type'] === 'energy_kwh' || str_contains($unit, 'kwh') || str_contains($unit, 'wh')) {
            return 'cumulative';
        }
        return null;
    }

    private static function cumulativeTotal(array $rows, string $unit): float
    {
        if (count($rows) < 2) {
            return 0.0;
        }
        $first = (float) $rows[0]['value'];
        $last = (float) $rows[count($rows) - 1]['value'];
        return $last >= $first ? self::toKwh($last - $first, $unit) : 0.0;
    }

    private static function toKwh(float $value, string $unit): float
    {
        return str_contains(strtolower($unit), 'kwh') ? $value : $value / 1000;
    }
}
