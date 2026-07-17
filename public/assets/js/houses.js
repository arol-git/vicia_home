/**
 * assets/js/houses.js
 *
 * Gestion en AJAX des maisons : création, sélection de la maison
 * courante (mise à jour de la session serveur puis rechargement), et
 * gestion des membres.
 */

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('house-create-form')?.addEventListener('submit', (e) => {
        e.preventDefault();
        ViciaAjax.post('/houses', new FormData(e.target))
            .then((res) => {
                ViciaApp.toast(res.message, 'success');
                setTimeout(() => window.location.href = '/dashboard', 700);
            })
            .catch((err) => ViciaApp.toast(err.message || 'Erreur lors de la création.', 'error'));
    });

    document.querySelectorAll('[data-switch-house-card]').forEach((btn) => {
        btn.addEventListener('click', () => {
            ViciaAjax.post(`/houses/switch/${btn.dataset.id}`)
                .then((res) => { window.location.href = res.redirect || '/dashboard'; })
                .catch((err) => ViciaApp.toast(err.message || 'Sélection impossible.', 'error'));
        });
    });

    document.getElementById('member-add-form')?.addEventListener('submit', (e) => {
        e.preventDefault();
        const houseId = e.target.dataset.houseId;
        ViciaAjax.post(`/houses/${houseId}/members`, new FormData(e.target))
            .then((res) => { ViciaApp.toast(res.message, 'success'); setTimeout(() => location.reload(), 600); })
            .catch((err) => ViciaApp.toast(err.message || 'Erreur lors de l’ajout.', 'error'));
    });

    document.querySelectorAll('[data-remove-member]').forEach((btn) => {
        btn.addEventListener('click', () => {
            if (!confirm(`Retirer « ${btn.dataset.name} » de cette maison ?`)) return;
            ViciaAjax.del(`/houses/${btn.dataset.houseId}/members/${btn.dataset.userId}`)
                .then((res) => { ViciaApp.toast(res.message, 'success'); btn.closest('tr').remove(); })
                .catch((err) => ViciaApp.toast(err.message || 'Retrait impossible.', 'error'));
        });
    });
});
