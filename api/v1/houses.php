<?php
/**
 * api/v1/houses.php
 *
 * Ressource REST /api/v1/houses — liste les maisons accessibles a
 * l'utilisateur authentifie. Le bot Telegram utilise cette route pour
 * choisir la maison active et afficher le compte lie.
 *
 * Debutant : cette route ne cree pas de maison. Elle expose seulement
 * les maisons auxquelles l'utilisateur a deja droit dans Vicia Home.
 */

use App\Models\House;
use App\Models\Equipment;
use App\Models\Setting;
use Mqtt\Publisher;

function handle_houses(string $method, ?string $id, ?string $subaction): void
{
    $user = api_authenticate();

    if ($id !== null && $subaction === 'mode' && $method === 'PUT') {
        $houseId = (int) $id;
        if (api_house_role($user, $houseId) === null) {
            api_response(['success' => false, 'message' => 'Maison introuvable.'], 404);
        }

        $input = api_input();
        $mode = (string) ($input['mode'] ?? '');
        $internalMode = api_house_internal_mode($mode);

        if ($internalMode === null) {
            api_response(['success' => false, 'message' => 'Mode invalide.'], 422);
        }

        $changed = api_house_apply_mode($houseId, $internalMode);
        Setting::set('dashboard_mode_' . $houseId, $internalMode);

        api_response([
            'success' => true,
            'message' => 'Mode mis à jour.',
            'data' => [
                'mode' => api_house_bot_mode($internalMode),
                'changed' => $changed,
            ],
        ]);
    }

    if ($method !== 'GET') {
        api_response(['success' => false, 'message' => 'Méthode non autorisée.'], 405);
    }

    $houses = array_map('api_house_with_mode', House::forUser((int) $user['id'], (string) $user['role']));

    if ($id !== null) {
        foreach ($houses as $house) {
            if ((int) $house['id'] === (int) $id) {
                api_response(['success' => true, 'data' => $house]);
            }
        }

        api_response(['success' => false, 'message' => 'Maison introuvable.'], 404);
    }

    api_response(['success' => true, 'data' => $houses]);
}

/**
 * Ajoute le mode courant attendu par le bot.
 *
 * La table houses n'a pas encore de colonne `mode`; le dashboard
 * stocke cette information dans settings sous `dashboard_mode_ID`.
 */
function api_house_with_mode(array $house): array
{
    $internalMode = Setting::get('dashboard_mode_' . (int) $house['id'], 'comfort');
    $house['mode'] = api_house_bot_mode((string) $internalMode);

    return $house;
}

function api_house_internal_mode(string $botMode): ?string
{
    return [
        'confort' => 'comfort',
        'nuit' => 'night',
        'absence' => 'away',
        'urgence' => 'emergency',
    ][$botMode] ?? null;
}

function api_house_bot_mode(string $internalMode): string
{
    return [
        'comfort' => 'confort',
        'night' => 'nuit',
        'away' => 'absence',
        'emergency' => 'urgence',
    ][$internalMode] ?? 'confort';
}

/**
 * Applique le meme principe que le dashboard : chaque mode fixe un
 * etat cible pour les types d'equipements connus, puis publie la
 * commande MQTT vers l'ESP32.
 */
function api_house_apply_mode(int $houseId, string $mode): int
{
    $targets = [
        'comfort' => ['led' => 1, 'relais' => 1, 'ventilateur' => 1, 'pompe' => 0, 'porte' => 0, 'fenetre' => 0, 'sirene' => 0, 'camera' => 1],
        'night' => ['led' => 0, 'relais' => 0, 'ventilateur' => 0, 'pompe' => 0, 'porte' => 0, 'fenetre' => 0, 'sirene' => 0, 'camera' => 1],
        'away' => ['led' => 0, 'relais' => 0, 'ventilateur' => 0, 'pompe' => 0, 'porte' => 0, 'fenetre' => 0, 'sirene' => 0, 'camera' => 1],
        'emergency' => ['led' => 1, 'relais' => 1, 'ventilateur' => 1, 'pompe' => 0, 'porte' => 0, 'fenetre' => 0, 'sirene' => 1, 'camera' => 1],
    ];

    $changed = 0;

    foreach (Equipment::activeForHouse($houseId) as $equipment) {
        $type = $equipment['type'];
        if (!array_key_exists($type, $targets[$mode])) {
            continue;
        }

        $state = (int) $targets[$mode][$type];
        if ((int) $equipment['state'] === $state) {
            continue;
        }

        Equipment::setState((int) $equipment['id'], $state);
        Publisher::publish($equipment['mqtt_topic'] . '/set', $state ? '1' : '0');
        $changed++;
    }

    return $changed;
}
