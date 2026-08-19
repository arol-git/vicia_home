<?php
/**
 * Dashboard centré sur l'état compréhensible de la maison.
 */
$pageScripts = ['dashboard.js'];
$stats = $stats ?? [];
$recentAlerts = $recentAlerts ?? [];
$rooms = $rooms ?? [];
$allEquipments = $allEquipments ?? [];
$allSensors = $allSensors ?? [];
$currentHouse = $currentHouse ?? null;

$equipmentsByRoom = [];
foreach ($allEquipments as $equipment) {
    $equipmentsByRoom[(int) $equipment['room_id']][] = $equipment;
}

$sensorsByRoom = [];
foreach ($allSensors as $sensor) {
    $sensorsByRoom[(int) $sensor['room_id']][] = $sensor;
}

$attentionCount = (int) ($stats['alerts_unread'] ?? 0);
?>

<section class="home-status" aria-labelledby="home-status-title">
    <div class="home-status__icon <?= $attentionCount ? 'is-warning' : 'is-ok' ?>">
        <i class="fa-solid <?= $attentionCount ? 'fa-triangle-exclamation' : 'fa-circle-check' ?>"></i>
    </div>
    <div>
        <p class="home-status__eyebrow"><?= e($currentHouse['name'] ?? 'Ma maison') ?></p>
        <h1 id="home-status-title"><?= $attentionCount ? 'Une attention est nécessaire' : 'Tout va bien' ?></h1>
        <p><?= $attentionCount ? $attentionCount . ' alerte(s) à consulter.' : 'Aucun problème important détecté pour le moment.' ?></p>
    </div>
    <a class="btn btn-primary home-status__action" href="<?= url('/alerts') ?>">
        <i class="fa-solid fa-bell"></i> Voir les alertes
    </a>
</section>

<section class="home-section" aria-labelledby="rooms-title">
    <div class="home-section__header">
        <div>
            <h2 id="rooms-title">Mes pièces</h2>
            <p>Consultez rapidement ce qui se passe dans chaque espace.</p>
        </div>
        <a href="<?= url('/rooms') ?>" class="btn btn-secondary"><i class="fa-solid fa-door-open"></i> Gérer les pièces</a>
    </div>

    <?php if (empty($rooms)): ?>
        <div class="empty-state"><i class="fa-solid fa-house"></i><p>Ajoutez une pièce pour commencer.</p></div>
    <?php else: ?>
        <div class="home-room-grid">
            <?php foreach ($rooms as $room): ?>
                <article class="home-room-card">
                    <div class="home-room-card__header">
                        <div class="home-room-card__icon"><i class="fa-solid <?= e($room['icon']) ?>"></i></div>
                        <div>
                            <h3><?= e($room['name']) ?></h3>
                            <p><?= (int) $room['equipments_count'] ?> équipement(s), <?= (int) $room['sensors_count'] ?> information(s)</p>
                        </div>
                    </div>
                    <div class="home-room-card__details">
                        <?php foreach (array_slice($sensorsByRoom[(int) $room['id']] ?? [], 0, 3) as $sensor): ?>
                            <div class="home-reading">
                                <i class="fa-solid <?= e(sensor_icon($sensor['type'])) ?>" aria-hidden="true"></i>
                                <span><?= e($sensor['name']) ?></span>
                                <strong><?= e(sensor_reading_label($sensor['type'], $sensor['latest_value'])) ?></strong>
                            </div>
                        <?php endforeach; ?>
                        <?php foreach (array_slice($equipmentsByRoom[(int) $room['id']] ?? [], 0, 3) as $equipment): ?>
                            <div class="home-reading">
                                <i class="fa-solid <?= e(equipment_icon($equipment['type'])) ?>" aria-hidden="true"></i>
                                <span><?= e($equipment['name']) ?></span>
                                <strong class="home-reading__state <?= (int) $equipment['state'] ? 'is-on' : 'is-off' ?>">
                                    <?= e(equipment_state_label($equipment['type'], $equipment['state'])) ?>
                                </strong>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($sensorsByRoom[(int) $room['id']]) && empty($equipmentsByRoom[(int) $room['id']])): ?>
                            <p class="home-room-card__empty">Aucun élément à afficher.</p>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="home-section" aria-labelledby="equipment-title">
    <div class="home-section__header">
        <div>
            <h2 id="equipment-title">Commandes rapides</h2>
            <p>Allumez, éteignez ou vérifiez vos équipements importants.</p>
        </div>
        <a href="<?= url('/equipments') ?>" class="btn btn-secondary"><i class="fa-solid fa-plug"></i> Voir tous les équipements</a>
    </div>
    <div class="home-equipment-grid">
        <?php foreach (array_slice($allEquipments, 0, 8) as $equipment): ?>
            <article class="home-equipment-card">
                <div class="home-equipment-card__icon"><i class="fa-solid <?= e(equipment_icon($equipment['type'])) ?>"></i></div>
                <div class="home-equipment-card__body">
                    <h3><?= e($equipment['name']) ?></h3>
                    <p><?= e($equipment['room_name']) ?></p>
                    <span class="home-equipment-card__state <?= (int) $equipment['state'] ? 'is-on' : 'is-off' ?>" data-equipment-state="<?= (int) $equipment['id'] ?>">
                        <?= e(equipment_state_label($equipment['type'], $equipment['state'])) ?>
                    </span>
                </div>
                <button type="button" class="btn btn-primary home-equipment-card__button" data-dashboard-toggle-equipment data-id="<?= (int) $equipment['id'] ?>" aria-label="Changer l'état de <?= e($equipment['name']) ?>">
                    <i class="fa-solid fa-power-off"></i><span>Changer</span>
                </button>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="home-section home-section--alerts" aria-labelledby="alerts-title">
    <div class="home-section__header">
        <div>
            <h2 id="alerts-title">À surveiller</h2>
            <p>Les alertes qui peuvent nécessiter votre attention.</p>
        </div>
        <a href="<?= url('/alerts') ?>" class="btn btn-secondary"><i class="fa-solid fa-bell"></i> Ouvrir les alertes</a>
    </div>
    <?php if (empty($recentAlerts)): ?>
        <div class="home-empty-alert"><i class="fa-solid fa-circle-check"></i><span>Aucune alerte importante.</span></div>
    <?php else: ?>
        <div class="home-alert-list">
            <?php foreach (array_slice($recentAlerts, 0, 4) as $alert): $badge = severity_badge($alert['severity']); ?>
                <div class="home-alert-item">
                    <i class="fa-solid fa-triangle-exclamation <?= e($badge['class']) ?>"></i>
                    <div><strong><?= e($alert['message']) ?></strong><span><?= e(time_ago($alert['created_at'])) ?></span></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
