<?php
/**
 * app/views/alerts/index.php
 *
 * Liste complète des alertes générées par le système, avec
 * possibilité de les marquer comme lues.
 */
$pageScripts = [];
?>

<div class="page-header">
    <div>
        <div class="page-header__title">Alertes &amp; notifications</div>
        <div class="page-header__subtitle"><?= count($alerts) ?> alerte(s) au total</div>
    </div>
    <div class="flex flex-gap-2">
        <button type="button" class="btn btn-primary" id="test-email-alert-btn">
            <i class="fa-solid fa-envelope-circle-check"></i> Alerte test e-mail
        </button>
        <button type="button" class="btn btn-secondary" id="mark-all-read-btn">
            <i class="fa-solid fa-check-double"></i> Tout marquer comme lu
        </button>
    </div>
</div>

<div class="card">
    <?php if (empty($alerts)): ?>
        <div class="empty-state"><i class="fa-solid fa-bell-slash"></i><p>Aucune alerte à afficher.</p></div>
    <?php else: ?>
        <?php foreach ($alerts as $alert): $badge = severity_badge($alert['severity']); ?>
            <div class="alert-item" data-alert-row data-id="<?= (int) $alert['id'] ?>" style="<?= $alert['is_read'] ? 'opacity:0.6;' : '' ?>">
                <div class="alert-item__icon <?= $badge['class'] ?>"><i class="fa-solid fa-triangle-exclamation"></i></div>
                <div style="flex:1;">
                    <div class="flex-between">
                        <div class="alert-item__title"><?= e($alert['message']) ?></div>
                        <span class="badge <?= $badge['class'] ?>"><?= $badge['label'] ?></span>
                    </div>
                    <div class="alert-item__meta">Type : <?= e($alert['type']) ?><?= $alert['source'] ? ' · Source : ' . e($alert['source']) : '' ?> · <?= e(format_date($alert['created_at'])) ?></div>
                </div>
                <?php if (!$alert['is_read']): ?>
                <button type="button" class="btn btn-sm btn-secondary" data-mark-read data-id="<?= (int) $alert['id'] ?>">Marquer comme lue</button>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-mark-read]').forEach((btn) => {
        btn.addEventListener('click', () => {
            ViciaAjax.post(`/alerts/${btn.dataset.id}/read`).then(() => {
                btn.closest('[data-alert-row]').style.opacity = '0.6';
                btn.remove();
                ViciaApp.toast('Alerte marquée comme lue.', 'success');
            });
        });
    });

    document.getElementById('mark-all-read-btn')?.addEventListener('click', () => {
        ViciaAjax.post('/alerts/read-all').then(() => {
            ViciaApp.toast('Toutes les alertes ont été marquées comme lues.', 'success');
            setTimeout(() => window.location.reload(), 500);
        });
    });

    document.getElementById('test-email-alert-btn')?.addEventListener('click', (event) => {
        const button = event.currentTarget;
        button.disabled = true;
        ViciaAjax.post('/alerts/test-email')
            .then((res) => {
                ViciaApp.toast(res.message, res.sent === false ? 'error' : 'success');
            })
            .catch((err) => ViciaApp.toast(err.message || 'Impossible d’envoyer l’e-mail de test.', 'error'))
            .finally(() => { button.disabled = false; });
    });
});
</script>
