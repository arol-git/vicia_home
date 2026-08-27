<?php
/**
 * Suivi de la consommation globale de la maison.
 * Les valeurs viennent exclusivement du capteur énergétique global.
 */
$pageScripts = [];
$selectedData = $selectedData ?? [];
$selectedMonth = \App\Models\Energy::normalizeMonth((string) ($selectedMonth ?? ''));
$history = $history ?? [];
$changePercent = $changePercent ?? null;
$monthNames = ['01' => 'Janvier', '02' => 'Février', '03' => 'Mars', '04' => 'Avril', '05' => 'Mai', '06' => 'Juin', '07' => 'Juillet', '08' => 'Août', '09' => 'Septembre', '10' => 'Octobre', '11' => 'Novembre', '12' => 'Décembre'];
$monthLabel = static function (string $month): string {
    global $monthNames;
    $month = \App\Models\Energy::normalizeMonth($month);
    [$year, $number] = explode('-', $month, 2);
    return ($monthNames[$number] ?? $number) . ' ' . $year;
};
$currentTotal = $selectedData['total_kwh'] ?? null;
$formatKwh = static function (?float $value): string {
    if ($value === null) {
        return 'Aucune donnée';
    }
    $decimals = $value > 0 && $value < 0.1 ? 3 : 1;
    return number_format($value, $decimals, ',', ' ');
};
$status = $changePercent === null ? 'stable' : ($changePercent < -2 ? 'down' : ($changePercent > 2 ? 'up' : 'stable'));
?>
<link rel="stylesheet" href="<?= asset('css/consumption.css') ?>">

<div class="page-header">
    <div>
        <div class="page-header__title">Consommation énergétique</div>
        <div class="page-header__subtitle">Mesure globale de toute la maison</div>
    </div>
</div>

<div class="card consumption-overview mb-4">
    <div class="consumption-overview__heading">
        <div>
            <div class="card__title">Consommation du mois</div>
            <div class="card__subtitle"><?= e($monthLabel($selectedMonth)) ?></div>
        </div>
        <i class="fa-solid fa-house-signal" aria-hidden="true"></i>
    </div>
    <div class="consumption-overview__value">
        <?= $currentTotal !== null ? e($formatKwh((float) $currentTotal)) . ' kWh' : 'Aucune donnée disponible' ?>
    </div>
    <?php if (empty($selectedData['sensor'])): ?>
        <p class="text-muted">Aucun capteur de consommation globale actif n'est configuré pour cette maison.</p>
    <?php elseif (($selectedData['unit_mode'] ?? null) === 'power'): ?>
        <p class="text-muted">Calcul établi à partir de la puissance instantanée en watts et de l'intervalle entre les relevés.</p>
    <?php elseif (($selectedData['unit_mode'] ?? null) === 'cumulative'): ?>
        <p class="text-muted">Calcul établi à partir des variations du compteur énergétique global.</p>
    <?php endif; ?>
</div>

<div class="card mb-4">
    <div class="card__header consumption-toolbar">
        <div>
            <div class="card__title">Évolution de votre consommation</div>
            <div class="card__subtitle">Choisissez un mois pour voir son évolution quotidienne.</div>
        </div>
        <label class="consumption-month-select">
            <span class="sr-only">Mois à consulter</span>
            <select class="form-control" id="consumption-month">
                <?php foreach ($history as $item): ?>
                    <option value="<?= e($item['month']) ?>" <?= $item['month'] === $selectedMonth ? 'selected' : '' ?>><?= e($monthLabel($item['month'])) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>
    <div class="consumption-trend consumption-trend--<?= e($status) ?>">
        <span class="consumption-trend__icon" aria-hidden="true"><i class="fa-solid <?= $status === 'down' ? 'fa-arrow-down' : ($status === 'up' ? 'fa-arrow-up' : 'fa-minus') ?>"></i></span>
        <div>
            <strong><?= $status === 'down' ? 'Consommation en baisse' : ($status === 'up' ? 'Consommation en hausse' : 'Consommation stable') ?></strong>
            <?php if ($changePercent !== null): ?>
                <span><?= e(number_format(abs($changePercent), 1, ',', ' ')) ?> % par rapport au mois précédent</span>
            <?php else: ?>
                <span>La comparaison sera affichée lorsque deux mois auront des données.</span>
            <?php endif; ?>
        </div>
    </div>
    <div class="consumption-chart-area">
        <div class="consumption-chart-unit">kWh</div>
        <div class="chart-card__canvas-wrap consumption-chart-wrap"><canvas id="consumption-line"></canvas></div>
    </div>
    <div class="consumption-empty" id="consumption-empty" <?= !empty($selectedData['daily']) ? 'hidden' : '' ?>>Aucune donnée disponible pour cette période.</div>
</div>

<div class="card mb-4">
    <div class="card__header"><div class="card__title">Comparaison avec le mois précédent</div></div>
    <?php if ($currentTotal !== null && $previousData && $previousData['total_kwh'] !== null): ?>
        <p><?= e($monthLabel($selectedMonth)) ?> : <strong><?= e($formatKwh((float) $currentTotal)) ?> kWh</strong></p>
        <p><?= e($monthLabel($previousData['month'])) ?> : <strong><?= e($formatKwh((float) $previousData['total_kwh'])) ?> kWh</strong></p>
        <p class="consumption-comparison <?= $status === 'down' ? 'is-down' : ($status === 'up' ? 'is-up' : '') ?>">
            <?= $changePercent < 0 ? 'Votre consommation a diminué.' : ($changePercent > 0 ? 'Votre consommation a augmenté.' : 'Votre consommation est restée stable.') ?>
        </p>
    <?php else: ?>
        <p class="text-muted">La comparaison n'est pas disponible pour le moment.</p>
    <?php endif; ?>
</div>

<div class="card mb-4">
    <div class="card__header"><div class="card__title"><i class="fa-solid fa-leaf"></i> Conseils pour économiser l'énergie</div></div>
    <ul class="consumption-tips">
        <li>Éteignez les lumières inutilisées.</li>
        <li>Évitez de laisser les équipements en marche sans raison.</li>
        <li>Comparez votre consommation avec celle des mois précédents.</li>
        <li>Une mesure globale ne permet pas de connaître la consommation d'un appareil particulier.</li>
    </ul>
</div>

<div class="card">
    <div class="card__header"><div class="card__title">Historique de consommation</div></div>
    <div class="table-wrapper">
        <table class="data-table">
            <thead><tr><th>Mois</th><th>Consommation</th><th>Évolution</th></tr></thead>
            <tbody>
            <?php foreach ($history as $index => $item): ?>
                <?php $older = $history[$index + 1] ?? null; $evolution = ($item['total_kwh'] !== null && $older && $older['total_kwh'] !== null && (float) $older['total_kwh'] > 0) ? (($item['total_kwh'] - $older['total_kwh']) / $older['total_kwh']) * 100 : null; ?>
                <tr>
                    <td data-label="Mois"><a href="<?= url('/consumption?month=' . urlencode($item['month'])) ?>"><?= e($monthLabel($item['month'])) ?></a></td>
                    <td data-label="Consommation"><?= $item['total_kwh'] !== null ? e($formatKwh((float) $item['total_kwh'])) . ' kWh' : 'Aucune donnée' ?></td>
                    <td data-label="Évolution"><?= $evolution !== null ? ($evolution < 0 ? '↓ ' : '↑ ') . e(number_format(abs($evolution), 1, ',', ' ')) . ' %' : '—' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const canvas = document.getElementById('consumption-line');
    const empty = document.getElementById('consumption-empty');
    const select = document.getElementById('consumption-month');
    const selectedData = <?= json_encode(['daily' => $selectedData['daily'] ?? []], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const monthLabel = <?= json_encode($monthLabel($selectedMonth), JSON_UNESCAPED_UNICODE) ?>;
    if (Object.keys(selectedData.daily).length > 0 && canvas) {
        const labels = Object.keys(selectedData.daily).map((day) => day.slice(-2));
        const values = Object.values(selectedData.daily);
        ViciaCharts.lineChart('consumption-line', labels, values, `Consommation globale — ${monthLabel}`);
    } else if (canvas) {
        canvas.hidden = true;
    }
    select?.addEventListener('change', () => {
        window.location.href = `${window.location.pathname}?month=${encodeURIComponent(select.value)}`;
    });
});
</script>
