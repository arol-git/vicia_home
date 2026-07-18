<?php
/**
 * app/views/security/index.php
 *
 * Module de cybersécurité : appareils réseau, listes blanche/noire,
 * journal des événements réseau.
 */
use App\Core\Auth;

$pageScripts = ['security.js'];
$houseRole = Auth::roleOnHouse(Auth::currentHouseId() ?? 0);
$canManage = in_array($houseRole, ['admin', 'owner', 'technician'], true);

$statusLabels = [
    'unknown'     => ['label' => 'Inconnu', 'class' => 'badge-warning'],
    'whitelisted' => ['label' => 'Liste blanche', 'class' => 'badge-success'],
    'blacklisted' => ['label' => 'Liste noire', 'class' => 'badge-critical'],
];
?>

<div class="page-header">
    <div>
        <div class="page-header__title">Réseau &amp; cybersécurité</div>
        <div class="page-header__subtitle">Surveillance du réseau domestique et détection d'appareils inconnus</div>
    </div>
    <?php if ($canManage): ?>
        <button type="button" class="btn btn-primary" id="simulate-scan-btn">
            <i class="fa-solid fa-satellite-dish"></i> Lancer un scan réseau
        </button>
    <?php endif; ?>
</div>

<div class="grid grid-cols-4 mb-4">
    <div class="stat-card">
        <div class="stat-card__icon is-blue"><i class="fa-solid fa-network-wired"></i></div>
        <div><div class="stat-card__value"><?= (int) $stats['total_devices'] ?></div><div class="stat-card__label">Appareils détectés</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-card__icon is-orange"><i class="fa-solid fa-circle-question"></i></div>
        <div><div class="stat-card__value"><?= (int) $stats['unknown_devices'] ?></div><div class="stat-card__label">Appareils inconnus</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-card__icon is-red"><i class="fa-solid fa-ban"></i></div>
        <div><div class="stat-card__value"><?= (int) $stats['blocked_devices'] ?></div><div class="stat-card__label">Appareils bloqués</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-card__icon is-green"><i class="fa-solid fa-wifi"></i></div>
        <div><div class="stat-card__value">Sécurisé</div><div class="stat-card__label">État du Wi-Fi (WPA3)</div></div>
    </div>
</div>

<div class="card mb-4">
    <div class="card__header">
        <div class="card__title">Appareils détectés sur le réseau</div>
    </div>
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Adresse MAC</th>
                    <th>Adresse IP</th>
                    <th>Nom d'hôte</th>
                    <th>Statut</th>
                    <th>Dernière détection</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($devices)): ?>
                <tr><td colspan="6"><div class="empty-state"><i class="fa-solid fa-network-wired"></i><p>Aucun appareil détecté pour le moment.</p></div></td></tr>
            <?php endif; ?>
            <?php foreach ($devices as $device): $status = $statusLabels[$device['list_status']]; ?>
                <tr>
                    <td><code><?= e($device['mac_address']) ?></code></td>
                    <td><?= e($device['ip_address'] ?? '—') ?></td>
                    <td><?= e($device['hostname'] ?? '—') ?></td>
                    <td><span class="badge <?= $status['class'] ?>"><?= $status['label'] ?></span></td>
                    <td class="text-xs text-muted"><?= e(time_ago($device['last_seen'])) ?></td>
                    <td>
                        <?php if ($canManage && $device['list_status'] !== 'whitelisted'): ?>
                        <div class="table-actions">
                            <button type="button" class="btn btn-sm btn-secondary" data-whitelist-device data-id="<?= (int) $device['id'] ?>">
                                <i class="fa-solid fa-check"></i> Autoriser
                            </button>
                            <button type="button" class="btn btn-sm btn-danger" data-blacklist-device data-id="<?= (int) $device['id'] ?>">
                                <i class="fa-solid fa-ban"></i> Bloquer
                            </button>
                        </div>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card__header">
        <div class="card__title">Journal des événements réseau</div>
    </div>
    <?php if (empty($logs)): ?>
        <div class="empty-state"><i class="fa-solid fa-list"></i><p>Aucun événement réseau journalisé.</p></div>
    <?php else: ?>
        <div class="activity-feed">
            <?php foreach ($logs as $log): ?>
                <div class="activity-item">
                    <div class="activity-item__icon"><i class="fa-solid fa-shield-halved"></i></div>
                    <div class="activity-item__body">
                        <div class="activity-item__title"><?= e($log['description']) ?></div>
                        <div class="activity-item__meta"><?= e($log['event_type']) ?> · <?= e(time_ago($log['created_at'])) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
