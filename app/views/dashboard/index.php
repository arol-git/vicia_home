<?php
/**
 * app/views/dashboard/index.php
 *
 * Tableau de bord principal. Variables attendues : $stats,
 * $recentAlerts, $recentActivity, $rooms, $allSensors, $activityTrend.
 */
$pageScripts = ['dashboard.js'];
$stats = $stats ?? [];
$recentAlerts = $recentAlerts ?? [];
$recentActivity = $recentActivity ?? [];
$rooms = $rooms ?? [];
$allSensors = $allSensors ?? [];
$activityTrend = $activityTrend ?? [];
$currentMode = $currentMode ?? 'comfort';
?>

<div class="page-header">
    <div>
        <div class="page-header__title">Bonjour, <?= e(explode(' ', $currentUser['name'] ?? 'Utilisateur')[0]) ?> 👋</div>
        <div class="page-header__subtitle">Voici un aperçu de l'état actuel de votre maison intelligente.</div>
    </div>
    <div class="mode-selector" data-current-mode="<?= e($currentMode) ?>">
        <button type="button" class="<?= $currentMode === 'comfort' ? 'is-active' : '' ?>" data-dashboard-mode="comfort">Confort</button>
        <button type="button" class="<?= $currentMode === 'away' ? 'is-active' : '' ?>" data-dashboard-mode="away">Absence</button>
        <button type="button" class="<?= $currentMode === 'night' ? 'is-active' : '' ?>" data-dashboard-mode="night">Nuit</button>
    </div>
</div>

<div class="grid grid-cols-4 mb-4">
    <div class="stat-card">
        <div class="stat-card__icon is-blue"><i class="fa-solid fa-door-open"></i></div>
        <div>
            <div class="stat-card__value"><?= (int) ($stats['rooms_count'] ?? 0) ?></div>
            <div class="stat-card__label">Pièces</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card__icon is-green"><i class="fa-solid fa-plug-circle-bolt"></i></div>
        <div>
            <div class="stat-card__value" data-equipment-counter><?= (int) ($stats['equipments_active'] ?? 0) ?> / <?= (int) ($stats['equipments_count'] ?? 0) ?></div>
            <div class="stat-card__label">Équipements actifs</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card__icon is-orange"><i class="fa-solid fa-temperature-half"></i></div>
        <div>
            <div class="stat-card__value"><?= ($stats['temperature'] ?? null) !== null ? e($stats['temperature']) . ' °C' : '—' ?></div>
            <div class="stat-card__label">Température ambiante</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card__icon is-red"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <div>
            <div class="stat-card__value"><?= (int) ($stats['alerts_unread'] ?? 0) ?></div>
            <div class="stat-card__label">Alertes non lues</div>
        </div>
    </div>
</div>

<div class="grid grid-cols-4 mb-4">
    <div class="stat-card">
        <div class="stat-card__icon is-navy"><i class="fa-solid fa-tint"></i></div>
        <div>
            <div class="stat-card__value"><?= ($stats['humidity'] ?? null) !== null ? e($stats['humidity']) . ' %' : '—' ?></div>
            <div class="stat-card__label">Humidité ambiante</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card__icon is-blue"><i class="fa-solid fa-wifi"></i></div>
        <div>
            <div class="stat-card__value">Connecté</div>
            <div class="stat-card__label">État du Wi-Fi</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card__icon is-orange"><i class="fa-solid fa-network-wired"></i></div>
        <div>
            <div class="stat-card__value"><?= (int) ($stats['devices_unknown'] ?? 0) ?></div>
            <div class="stat-card__label">Appareils inconnus</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-card__icon is-green"><i class="fa-solid fa-shield-halved"></i></div>
        <div>
            <div class="stat-card__value">Armée</div>
            <div class="stat-card__label">État de l'alarme</div>
        </div>
    </div>
</div>

<div class="grid grid-cols-2 mb-4">
    <div class="chart-card">
        <div class="card__header">
            <div>
                <div class="card__title">Activité des capteurs (24 h)</div>
                <div class="card__subtitle">Nombre de mesures reçues par heure</div>
            </div>
        </div>
        <div class="chart-card__canvas-wrap"><canvas id="activity-trend-chart"></canvas></div>
    </div>

    <div class="chart-card">
        <div class="card__header">
            <div>
                <div class="card__title">Tendance capteur</div>
                <div class="card__subtitle">Évolution sur les dernières 24 heures</div>
            </div>
            <select id="dashboard-sensor-select" class="form-control dashboard-sensor-select">
                <?php foreach ($allSensors as $s): ?>
                    <?php if (in_array($s['type'], ['dht22_temp', 'dht22_hum', 'mq2', 'mq135', 'ldr'], true)): ?>
                        <option value="<?= (int) $s['id'] ?>"><?= e($s['name']) ?></option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="chart-card__canvas-wrap"><canvas id="sensor-trend-chart"></canvas></div>
    </div>
</div>

<div class="grid grid-cols-3 mb-4">
    <div class="card" style="grid-column: span 2;">
        <div class="card__header">
            <div class="card__title">Aperçu des pièces</div>
            <a href="<?= url('/rooms') ?>" class="btn btn-sm btn-secondary">Voir tout</a>
        </div>
        <div class="grid grid-auto">
            <?php foreach (array_slice($rooms, 0, 6) as $room): ?>
                <div class="room-mini-card">
                    <div class="room-mini-card__icon"><i class="fa-solid <?= e($room['icon']) ?>"></i></div>
                    <div>
                        <div class="room-mini-card__name"><?= e($room['name']) ?></div>
                        <div class="room-mini-card__meta"><?= (int) $room['equipments_count'] ?> équip. · <?= (int) $room['sensors_count'] ?> capteurs</div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card">
        <div class="card__header">
            <div class="card__title">Alertes récentes</div>
            <a href="<?= url('/alerts') ?>" class="btn btn-sm btn-secondary">Voir tout</a>
        </div>
        <?php if (empty($recentAlerts)): ?>
            <div class="empty-state"><i class="fa-solid fa-circle-check"></i><p>Aucune alerte pour le moment.</p></div>
        <?php else: ?>
            <?php foreach ($recentAlerts as $alert): $badge = severity_badge($alert['severity']); ?>
                <div class="alert-item">
                    <div class="alert-item__icon <?= $badge['class'] ?>"><i class="fa-solid fa-triangle-exclamation"></i></div>
                    <div>
                        <div class="alert-item__title"><?= e($alert['message']) ?></div>
                        <div class="alert-item__meta"><?= e(time_ago($alert['created_at'])) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card__header">
        <div class="card__title">Dernières activités</div>
        <a href="<?= url('/history') ?>" class="btn btn-sm btn-secondary">Historique complet</a>
    </div>
    <div class="activity-feed">
        <?php foreach ($recentActivity as $activity): ?>
            <div class="activity-item">
                <div class="activity-item__icon"><i class="fa-solid fa-clock"></i></div>
                <div class="activity-item__body">
                    <div class="activity-item__title"><?= e($activity['description']) ?></div>
                    <div class="activity-item__meta"><?= e($activity['user_name'] ?? 'Système') ?> · <?= e(time_ago($activity['created_at'])) ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script type="application/json" id="dashboard-data">
<?= json_encode([
    'activityTrend' => [
        'labels' => array_map(fn($r) => $r['hour_label'], $activityTrend),
        'values' => array_map(fn($r) => (int) $r['readings'], $activityTrend),
    ],
]) ?>
</script>
