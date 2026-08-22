<?php

namespace App\Services;

use App\Core\Database;
use App\Models\Sensor;

class TelemetryService
{
    /**
     * Enregistre une ou plusieurs mesures reçues depuis MQTT ou HTTP.
     *
     * Formats acceptés :
     * - topic + payload numérique : 25.4
     * - topic + JSON : {"value":25.4}
     * - topic + JSON multi-mesures : {"temp":25.4,"hum":61}
     * - HTTP : {"topic":"home/.../temp","value":25.4}
     * - HTTP batch : {"readings":[{"topic":"home/.../temp","value":25.4}]}
     */
    public static function ingest(string $topic, mixed $payload): array
    {
        app_log('[TelemetryService] === INGEST TELEMETRIE ===');
        app_log('[TelemetryService] Topic: ' . $topic . ' | Payload: ' . substr((string)$payload, 0, 100));
        
        $readings = self::extractReadings($topic, $payload);
        app_log('[TelemetryService] Lectures extraites: ' . count($readings));
        
        $saved = [];
        $errors = [];

        foreach ($readings as $reading) {
            app_log('[TelemetryService] -> Recherche capteur pour topic: ' . $reading['topic']);
            $sensor = Sensor::findByTopicWithRoom($reading['topic']);
            if (!$sensor) {
                $msg = 'Aucune mesure ne correspond à cette adresse MQTT.';
                app_log('[TelemetryService] ERREUR: ' . $msg);
                $errors[] = [
                    'topic' => $reading['topic'],
                    'message' => $msg,
                ];
                continue;
            }

            app_log('[TelemetryService] SUCCES: Capteur trouve #' . $sensor['id'] . ' (' . $sensor['name'] . ')');

            if (!is_numeric($reading['value'])) {
                app_log('[TelemetryService] ERREUR: Valeur non numerique: ' . var_export($reading['value'], true));
                $errors[] = [
                    'topic' => $reading['topic'],
                    'message' => 'Valeur non numérique.',
                ];
                continue;
            }

            app_log('[TelemetryService] -> Enregistrement mesure: capteur #' . $sensor['id'] . ' = ' . $reading['value']);
            $readingId = Sensor::recordReading((int) $sensor['id'], (float) $reading['value']);
            app_log('[TelemetryService] SUCCES: Mesure enregistree #' . $readingId);
            $saved[] = [
                'reading_id' => $readingId,
                'sensor_id' => (int) $sensor['id'],
                'sensor_name' => $sensor['name'],
                'house_id' => (int) $sensor['house_id'],
                'topic' => $reading['topic'],
                'value' => (float) $reading['value'],
                'unit' => $sensor['unit'],
                'alert_threshold' => $sensor['alert_threshold'] !== null ? (float) $sensor['alert_threshold'] : null,
            ];
        }

        return [
            'success' => count($saved) > 0,
            'saved' => $saved,
            'errors' => $errors,
        ];
    }

    public static function ingestHttp(array $input): array
    {
        if (!empty($input['readings']) && is_array($input['readings'])) {
            $saved = [];
            $errors = [];

            foreach ($input['readings'] as $item) {
                if (!is_array($item) || empty($item['topic']) || !array_key_exists('value', $item)) {
                    $errors[] = ['message' => 'Mesure invalide dans readings.'];
                    continue;
                }

                $result = self::ingest((string) $item['topic'], $item['value']);
                $saved = array_merge($saved, $result['saved']);
                $errors = array_merge($errors, $result['errors']);
            }

            return [
                'success' => count($saved) > 0,
                'saved' => $saved,
                'errors' => $errors,
            ];
        }

        if (empty($input['topic']) || !array_key_exists('value', $input)) {
            return [
                'success' => false,
                'saved' => [],
                'errors' => [['message' => 'Les champs adresse et valeur sont obligatoires.']],
            ];
        }

        return self::ingest((string) $input['topic'], $input['value']);
    }

    private static function extractReadings(string $topic, mixed $payload): array
    {
        if (is_numeric($payload)) {
            return [['topic' => self::normalizeTopic($topic), 'value' => $payload]];
        }

        if (is_string($payload)) {
            $trimmed = trim($payload);
            if (is_numeric($trimmed)) {
                return [['topic' => self::normalizeTopic($topic), 'value' => $trimmed]];
            }

            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                return self::extractReadingsFromArray($topic, $decoded);
            }
        }

        if (is_array($payload)) {
            return self::extractReadingsFromArray($topic, $payload);
        }

        return [];
    }

    private static function extractReadingsFromArray(string $topic, array $payload): array
    {
        if (array_key_exists('value', $payload)) {
            return [['topic' => self::normalizeTopic($topic), 'value' => $payload['value']]];
        }

        if (!empty($payload['readings']) && is_array($payload['readings'])) {
            $readings = [];
            foreach ($payload['readings'] as $item) {
                if (is_array($item) && !empty($item['topic']) && array_key_exists('value', $item)) {
                    $readings[] = ['topic' => self::normalizeTopic((string) $item['topic']), 'value' => $item['value']];
                }
            }
            return $readings;
        }

        $readings = [];
        foreach ($payload as $metric => $value) {
            if (!is_numeric($value)) {
                continue;
            }

            $metricKey = strtolower((string) $metric);
            $metricAlias = match ($metricKey) {
                'temperature', 'temp', 'temperature_c', 'celsius' => 'temp',
                'humidity', 'hum', 'humidity_pct', 'humidite' => 'hum',
                'power', 'watts', 'watt' => 'power',
                'energy', 'consumption', 'kwh', 'consommation' => 'kwh',
                default => $metricKey,
            };

            $candidateTopic = self::normalizeTopic(rtrim($topic, '/') . '/' . $metricAlias);
            $readings[] = ['topic' => $candidateTopic, 'value' => $value];
        }

        return $readings;
    }

    private static function normalizeTopic(string $topic): string
    {
        $trimmed = trim($topic);
        if ($trimmed === '') {
            return $trimmed;
        }

        $normalized = preg_replace('#/+#', '/', $trimmed);
        $normalized = strtolower(str_replace(['é', 'è', 'ê', 'à', 'â', 'ç', 'ù', 'û'], ['e', 'e', 'e', 'a', 'a', 'c', 'u', 'u'], $normalized));
        $normalized = rtrim((string) $normalized, '/');

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
