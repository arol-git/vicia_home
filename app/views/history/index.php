<?php
/**
 * app/views/history/index.php
 *
 * Historique complet des activités de la plateforme (journal
 * d'audit) et des connexions récentes.
 */
?>

<div class="page-header">
    <div>
        <div class="page-header__title">Historique</div>
        <div class="page-header__subtitle">Journal complet des actions effectuées sur la plateforme</div>
    </div>
</div>

<div class="grid grid-cols-2">
    <div class="card">
        <div class="card__header"><div class="card__title">Journal d'activité</div></div>
        <?php if (empty($activities)): ?>
            <div class="empty-state"><i class="fa-solid fa-clock-rotate-left"></i><p>Aucune activité enregistrée.</p></div>
        <?php else: ?>
            <div class="activity-feed">
                <?php foreach ($activities as $activity): ?>
                    <div class="activity-item">
                        <div class="activity-item__icon"><i class="fa-solid fa-clock"></i></div>
                        <div class="activity-item__body">
                            <div class="activity-item__title"><?= e($activity['description']) ?></div>
                            <div class="activity-item__meta"><?= e($activity['user_name'] ?? 'Système') ?> · <?= e($activity['ip_address']) ?> · <?= e(format_date($activity['created_at'])) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($totalPages > 1): ?>
            <div class="flex flex-gap-2 mt-4" style="justify-content:center;">
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <a href="<?= url('/history?page=' . $p) ?>" class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-secondary' ?>"><?= $p ?></a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card__header"><div class="card__title">Connexions récentes</div></div>
        <div class="table-wrapper">
            <table class="data-table">
                <thead><tr><th>Utilisateur</th><th>Adresse IP</th><th>Statut</th><th>Date</th></tr></thead>
                <tbody>
                <?php if (empty($logins)): ?>
                    <tr><td colspan="4"><div class="empty-state"><i class="fa-solid fa-right-to-bracket"></i><p>Aucune connexion enregistrée.</p></div></td></tr>
                <?php endif; ?>
                <?php foreach ($logins as $login): ?>
                    <tr>
                        <td><?= e($login['user_name'] ?? $login['email_used']) ?></td>
                        <td class="text-xs text-muted"><?= e($login['ip_address']) ?></td>
                        <td>
                            <?php if ($login['status'] === 'success'): ?>
                                <span class="badge badge-success">Réussie</span>
                            <?php else: ?>
                                <span class="badge badge-critical">Échouée</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-xs text-muted"><?= e(format_date($login['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
