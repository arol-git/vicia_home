/**
 * assets/js/devices.js
 *
 * Gestion en AJAX de l'appairage des cartes ESP32 : création,
 * révocation, suppression.
 */

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('device-create-form')?.addEventListener('submit', (e) => {
        e.preventDefault();
        ViciaAjax.post('/devices', new FormData(e.target))
            .then((res) => {
                ViciaApp.toast(res.message, 'success');
                setTimeout(() => window.location.reload(), 600);
            })
            .catch((err) => ViciaApp.toast(err.message || 'Erreur lors de l’appairage.', 'error'));
    });

    document.querySelectorAll('[data-revoke-device]').forEach((btn) => {
        btn.addEventListener('click', () => {
            if (!confirm('Révoquer cette carte ? Elle ne pourra plus publier tant qu’elle n’est pas ré-appairée.')) return;
            ViciaAjax.post(`/devices/${btn.dataset.id}/revoke`)
                .then((res) => { ViciaApp.toast(res.message, 'success'); setTimeout(() => location.reload(), 600); })
                .catch((err) => ViciaApp.toast(err.message || 'Action impossible.', 'error'));
        });
    });

    document.querySelectorAll('[data-delete-device]').forEach((btn) => {
        btn.addEventListener('click', () => {
            if (!confirm(`Supprimer la carte « ${btn.dataset.label} » ?`)) return;
            ViciaAjax.del(`/devices/${btn.dataset.id}`)
                .then((res) => { ViciaApp.toast(res.message, 'success'); btn.closest('tr').remove(); })
                .catch((err) => ViciaApp.toast(err.message || 'Suppression impossible.', 'error'));
        });
    });
});
