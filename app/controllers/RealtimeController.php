<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Response;
use App\Models\Equipment;
use App\Models\House;
use App\Models\Setting;
use Mqtt\Publisher;

class RealtimeController extends Controller
{
    private const ESP32_ONLINE_TTL = 45;

    public function state(): void
    {
        $houseId = Auth::requireHouseRole(['admin', 'owner', 'resident', 'technician']);
        Response::success('État synchronisé.', $this->snapshot($houseId));
    }

    public function resync(): void
    {
        $houseId = Auth::requireHouseRole(['admin', 'owner', 'resident', 'technician']);
        $this->verifyCsrf();

        $published = $this->requestEsp32Snapshot($houseId);
        Response::success($published ? 'Resynchronisation demandée.' : 'ESP32 non joignable via MQTT.', array_merge(
            $this->snapshot($houseId),
            ['resync_requested' => $published]
        ), $published ? 200 : 503);
    }

    public function stream(): void
    {
        $houseId = Auth::requireHouseRole(['admin', 'owner', 'resident', 'technician']);

        if (function_exists('session_write_close')) {
            session_write_close();
        }

        @set_time_limit(0);
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache, no-transform');
        header('X-Accel-Buffering: no');

        $this->requestEsp32Snapshot($houseId);
        $lastVersion = null;
        $startedAt = time();

        while (!connection_aborted() && (time() - $startedAt) < 55) {
            $version = $this->version($houseId);
            if ($version !== $lastVersion) {
                $this->sendEvent('state', $this->snapshot($houseId));
                $lastVersion = $version;
            } else {
                $this->sendEvent('heartbeat', ['version' => $version, 'time' => time()]);
            }

            sleep(2);
        }
    }

    private function snapshot(int $houseId): array
    {
        $equipments = Equipment::stateSnapshot($houseId);
        // Le flux temps réel est visible dans l'onglet Réseau du
        // navigateur. On enlève donc les topics MQTT pour tous les
        // rôles sauf admin, même si la table HTML les cache déjà.
        if (!can_view_mqtt_topics(Auth::roleOnHouse($houseId))) {
            $equipments = hide_mqtt_topics($equipments);
        }
        $active = count(array_filter($equipments, static fn(array $equipment): bool => (int) $equipment['state'] === 1));

        return [
            'esp32' => $this->esp32Status($houseId),
            'equipments' => $equipments,
            'equipmentsActive' => $active,
            'equipmentsCount' => count($equipments),
            'version' => $this->version($houseId),
        ];
    }

    private function version(int $houseId): string
    {
        return implode('|', [
            Equipment::stateVersion($houseId),
            Setting::get('esp32_status_' . $houseId, 'unknown'),
            Setting::get('esp32_last_seen_' . $houseId, '0'),
        ]);
    }

    private function esp32Status(int $houseId): array
    {
        $status = Setting::get('esp32_status_' . $houseId, 'unknown') ?: 'unknown';
        $lastSeen = (int) (Setting::get('esp32_last_seen_' . $houseId, '0') ?: 0);

        if ($lastSeen > 0 && (time() - $lastSeen) > self::ESP32_ONLINE_TTL) {
            $status = 'offline';
        }

        return [
            'status' => $status,
            'online' => $status === 'online',
            'last_seen' => $lastSeen,
        ];
    }

    private function requestEsp32Snapshot(int $houseId): bool
    {
        $house = House::find($houseId);
        if (!$house || empty($house['slug'])) {
            return false;
        }

        $config = require __DIR__ . '/../../mqtt/config.php';
        $topic = rtrim($config['base_topic'], '/') . '/' . $house['slug'] . '/sync/request';

        return Publisher::publish($topic, json_encode([
            'source' => 'vicia-web',
            'action' => 'state_snapshot',
            'requested_at' => date(DATE_ATOM),
        ], JSON_UNESCAPED_SLASHES));
    }

    private function sendEvent(string $event, array $payload): void
    {
        echo "event: {$event}\n";
        echo 'data: ' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
        @ob_flush();
        flush();
    }
}
