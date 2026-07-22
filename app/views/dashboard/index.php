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

<div class="card mode-overview mb-4">
    <div class="card__header">
        <div>
            <div class="card__title">Modes de fonctionnement</div>
            <div class="card__subtitle">Comportement attendu de la maison intelligente selon la situation.</div>
        </div>
    </div>
    <div class="mode-overview__grid">
        <details class="mode-card">
            <summary>
                <span class="mode-card__summary">
                    <span class="mode-card__icon is-green"><i class="fa-solid fa-house-user"></i></span>
                    <span>
                        <span class="mode-card__title">Mode Confort</span>
                        <span class="badge badge-success">Présence</span>
                    </span>
                </span>
                <span class="mode-card__more">Plus d'infos</span>
            </summary>
            <div class="mode-card__header">
                <h3>Mode Confort</h3>
            </div>
            <p>Destiné aux occupants présents, ce mode privilégie le confort quotidien tout en gardant une sécurité adaptée.</p>
            <div class="mode-card__details">
                <div><strong>Activation</strong><span>Manuelle depuis le tableau de bord ou automatique lorsqu'une présence régulière est détectée.</span></div>
                <div><strong>Équipements concernés</strong><span>Éclairage, capteurs de température et d'humidité, ventilation, prises ou relais, accès et alertes principales.</span></div>
                <div><strong>Comportement attendu</strong><span>L'éclairage s'adapte à l'occupation, la température et la ventilation sont maintenues à un niveau agréable, les équipements utiles restent disponibles et la sécurité reste active sans gêner les occupants.</span></div>
            </div>
        </details>

        <details class="mode-card">
            <summary>
                <span class="mode-card__summary">
                    <span class="mode-card__icon is-blue"><i class="fa-solid fa-moon"></i></span>
                    <span>
                        <span class="mode-card__title">Mode Nuit</span>
                        <span class="badge badge-info">Sommeil</span>
                    </span>
                </span>
                <span class="mode-card__more">Plus d'infos</span>
            </summary>
            <div class="mode-card__header">
                <h3>Mode Nuit</h3>
            </div>
            <p>Activé pendant les heures de sommeil, ce mode réduit les consommations tout en protégeant les occupants.</p>
            <div class="mode-card__details">
                <div><strong>Activation</strong><span>Manuelle avant le coucher ou automatique selon une plage horaire définie.</span></div>
                <div><strong>Équipements concernés</strong><span>Éclairage nocturne, portes extérieures, détecteurs d'ouverture, capteurs de mouvement, alarme, ventilation et équipements non essentiels.</span></div>
                <div><strong>Comportement attendu</strong><span>Les lumières principales sont réduites ou éteintes, les accès extérieurs sont surveillés et verrouillés, les équipements inutiles passent en économie d'énergie et un éclairage discret reste disponible pour les déplacements nocturnes.</span></div>
            </div>
        </details>

        <details class="mode-card">
            <summary>
                <span class="mode-card__summary">
                    <span class="mode-card__icon is-orange"><i class="fa-solid fa-person-walking-arrow-right"></i></span>
                    <span>
                        <span class="mode-card__title">Mode Absence</span>
                        <span class="badge badge-warning">Maison vide</span>
                    </span>
                </span>
                <span class="mode-card__more">Plus d'infos</span>
            </summary>
            <div class="mode-card__header">
                <h3>Mode Absence</h3>
            </div>
            <p>Utilisé lorsque la maison est inoccupée, ce mode renforce la sécurité et limite la consommation d'énergie.</p>
            <div class="mode-card__details">
                <div><strong>Activation</strong><span>Manuelle au départ ou automatique après une période sans présence détectée.</span></div>
                <div><strong>Équipements concernés</strong><span>Alarme, caméras, détecteurs d'ouverture et de mouvement, capteurs de gaz ou fumée, réseau domestique, éclairage, prises, ventilation et serrures.</span></div>
                <div><strong>Comportement attendu</strong><span>La surveillance est renforcée, les accès sont protégés, les alertes sont envoyées en temps réel, les équipements non indispensables sont éteints et le réseau domestique est surveillé contre les appareils inconnus ou comportements suspects.</span></div>
            </div>
        </details>

        <details class="mode-card">
            <summary>
                <span class="mode-card__summary">
                    <span class="mode-card__icon is-red"><i class="fa-solid fa-triangle-exclamation"></i></span>
                    <span>
                        <span class="mode-card__title">Mode Urgence</span>
                        <span class="badge badge-critical">Automatique</span>
                    </span>
                </span>
                <span class="mode-card__more">Plus d'infos</span>
            </summary>
            <div class="mode-card__header">
                <h3>Mode Urgence</h3>
            </div>
            <p>Déclenché lors d'un événement critique, ce mode donne la priorité absolue à la sécurité des occupants et de l'habitation.</p>
            <div class="mode-card__details">
                <div><strong>Activation</strong><span>Automatique en cas d'incendie, fuite de gaz, intrusion, alerte critique ou danger détecté.</span></div>
                <div><strong>Équipements concernés</strong><span>Sirène, notifications, éclairage de sécurité, caméras, serrures, ventilation, capteurs critiques, journal d'événements et dispositifs de protection.</span></div>
                <div><strong>Comportement attendu</strong><span>Les alarmes se déclenchent, les occupants sont avertis, les actions de protection sont exécutées, les événements sont enregistrés et les équipements nécessaires passent dans l'état le plus sûr selon le type de danger.</span></div>
            </div>
        </details>
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
