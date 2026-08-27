<?php

require_once __DIR__ . '/../app/models/Energy.php';
require_once __DIR__ . '/../app/services/TelemetryService.php';

$passed = 0;
$failed = 0;

function checkEnergy(bool $condition, string $message): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "[OK] $message\n";
    } else {
        $failed++;
        echo "[FAIL] $message\n";
    }
}

$reflection = new ReflectionClass('App\\Models\\Energy');
$unitMode = $reflection->getMethod('unitMode');
$unitMode->setAccessible(true);
$toKwh = $reflection->getMethod('toKwh');
$toKwh->setAccessible(true);
$telemetryReflection = new ReflectionClass('App\\Services\\TelemetryService');
$extract = $telemetryReflection->getMethod('extractReadings');
$extract->setAccessible(true);

checkEnergy($unitMode->invoke(null, ['type' => 'energy_power', 'unit' => 'W']) === 'power', 'Un capteur en watts est traité comme une puissance instantanée');
checkEnergy($unitMode->invoke(null, ['type' => 'energy_kwh', 'unit' => 'kWh']) === 'cumulative', 'Un capteur en kWh est traité comme un compteur cumulé');
checkEnergy($unitMode->invoke(null, ['type' => 'energy_consumption', 'unit' => 'Wh']) === 'cumulative', 'Un capteur en Wh est traité comme un compteur cumulé');
checkEnergy($unitMode->invoke(null, ['type' => 'energy_consumption', 'unit' => '']) === null, 'Une unité énergétique inconnue reste indisponible');
checkEnergy($toKwh->invoke(null, 2500.0, 'Wh') === 2.5, 'Une différence de 2500 Wh devient 2,5 kWh');
checkEnergy($extract->invoke(null, 'home/maison/energy/power', '125.5')[0]['topic'] === 'home/maison/energy/power', 'Un relevé MQTT de puissance globale conserve son topic');
checkEnergy($extract->invoke(null, 'home/maison/energy', ['kwh' => 42.25])[0]['topic'] === 'home/maison/energy/kwh', 'Un relevé MQTT énergétique kWh est normalisé');
checkEnergy($extract->invoke(null, 'home/maison/energy/power', 'inconnu') === [], 'Une valeur MQTT invalide est ignorée');
checkEnergy(App\Models\Energy::normalizeMonth('02 2025') === '2025-02', 'Le format 02 2025 devient 2025-02');
checkEnergy(App\Models\Energy::normalizeMonth('01/2025') === '2025-01', 'Le mois de janvier est reconnu');
$historyMethod = $reflection->getMethod('history');
checkEnergy($historyMethod->getParameters()[1]->getDefaultValue() === 12, 'L’historique est limité à douze mois');

echo "\n=== Résultat : $passed réussi(s), $failed échec(s) ===\n";
exit($failed > 0 ? 1 : 0);