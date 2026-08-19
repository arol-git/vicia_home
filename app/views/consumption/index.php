<?php
/**
 * app/views/consumption/index.php
 *
 * Suivi de la consommation électrique estimée, avec répartition par
 * type d'équipement (graphique en anneau Chart.js).
 */
$pageScripts = [];

$typeLabels = [
    'led' => 'LED', 'relais' => 'Relais', 'ventilateur' => 'Ventilateur', 'pompe' => 'Pompe',
    'servo' => 'Servo-moteur', 'porte' => 'Porte', 'fenetre' => 'Fenêtre', 'sirene' => 'Sirène',
];
$colors = ['#2f5fa8', '#2e7d5b', '#c98a1c', '#c1442f', '#2f80a8', '#8a6d3b', '#5c85c4', '#7c5cc4', '#c45c9e'];
?>

<div class="page-header">
    <div>
        <div class="page-header__title">Consommation électrique</div>
        <div class="page-header__subtitle">Estimation en temps réel basée sur l'état des équipements actifs</div>
    </div>
</div>

<div class="grid grid-cols-3 mb-4">
    <div class="stat-card">
        <div class="stat-card__icon is-orange"><i class="fa-solid fa-bolt"></i></div>
        <div><div class="stat-card__value"><?= (int) $totalActiveWatts ?> W</div><div class="stat-card__label">Puissance instantanée</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-card__icon is-blue"><i class="fa-solid fa-gauge"></i></div>
        <div><div class="stat-card__value"><?= e($estimatedDailyKwh) ?> kWh</div><div class="stat-card__label">Estimation journalière</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-card__icon is-green"><i class="fa-solid fa-leaf"></i></div>
        <div><div class="stat-card__value"><?= count(array_filter($equipments, fn($e) => $e['state'])) ?></div><div class="stat-card__label">Équipements actifs</div></div>
    </div>
</div>

<div class="grid grid-cols-2">
    <div class="chart-card">
        <div class="card__header"><div class="card__title">Répartition par type d'équipement</div></div>
        <div class="chart-card__canvas-wrap"><canvas id="consumption-doughnut"></canvas></div>
    </div>

    <div class="card">
        <div class="card__header"><div class="card__title">Équipements actifs</div></div>
        <div class="table-wrapper">
            <table class="data-table">
                <thead><tr><th>Équipement</th><th>Pièce</th><th>Type</th></tr></thead>
                <tbody>
                <?php $active = array_filter($equipments, fn($e) => $e['state']); ?>
                <?php if (empty($active)): ?>
                    <tr><td colspan="3"><div class="empty-state"><i class="fa-solid fa-plug"></i><p>Aucun équipement actif actuellement.</p></div></td></tr>
                <?php endif; ?>
                <?php foreach ($active as $eq): ?>
                    <tr>
                        <td data-label="Équipement"><?= e($eq['name']) ?></td>
                        <td data-label="Pièce"><?= e($eq['room_name']) ?></td>
                        <td data-label="Type"><span class="badge badge-neutral"><?= e($typeLabels[$eq['type']] ?? $eq['type']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
<div class="grid grid-cols-1 mt-4">
    <div class="card">
        <div class="card__header"><div class="card__title">Détail par équipement</div></div>
        <div class="table-wrapper">
            <table class="data-table">
                <thead><tr><th>Équipement</th><th>Pièce</th><th>Type</th><th>État</th><th>Puissance</th></tr></thead>
                <tbody>
                <?php if (empty($equipments)): ?>
                    <tr><td colspan="5"><div class="empty-state"><i class="fa-solid fa-plug"></i><p>Aucun équipement.</p></div></td></tr>
                <?php endif; ?>
                <?php
                    $typeLabels = ['led' => 'LED', 'relais' => 'Relais', 'ventilateur' => 'Ventilateur', 'pompe' => 'Pompe', 'servo' => 'Servo-moteur', 'porte' => 'Porte', 'fenetre' => 'Fenêtre', 'sirene' => 'Sirène'];
                    $powerWatts = ['led' => 9, 'relais' => 5, 'ventilateur' => 45, 'pompe' => 60, 'servo' => 3, 'porte' => 3, 'fenetre' => 3, 'sirene' => 4];
                ?>
                <?php foreach ($equipments as $eq): ?>
                    <tr class="<?= $eq['state'] ? 'is-active-row' : '' ?>">
                        <td data-label="Équipement"><?= e($eq['name']) ?></td>
                        <td data-label="Pièce"><?= e($eq['room_name']) ?></td>
                        <td data-label="Type"><span class="badge badge-neutral"><?= e($typeLabels[$eq['type']] ?? $eq['type']) ?></span></td>
                        <td data-label="État"><span class="badge <?= $eq['state'] ? 'badge-success' : 'badge-gray' ?>"><?= $eq['state'] ? 'Actif' : 'Inactif' ?></span></td>
                        <td data-label="Puissance"><strong><?= ($powerWatts[$eq['type']] ?? 10) ?></strong> W</td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const labels = <?= json_encode(array_map(fn($t) => $typeLabels[$t] ?? $t, array_keys($byType))) ?>;
    const values = <?= json_encode(array_values($byType)) ?>;
    const colors = <?= json_encode(array_slice($colors, 0, count($byType))) ?>;
    if (values.some(v => v > 0)) {
        ViciaCharts.doughnutChart('consumption-doughnut', labels, values, colors);
    }
});
</script>
