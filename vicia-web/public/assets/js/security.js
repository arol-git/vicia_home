/**
 * assets/js/security.js
 *
 * Actions du module de cybersécurité : placement d'un appareil en
 * liste blanche ou noire, et simulation d'un scan réseau (bouton de
 * démonstration ; en conditions réelles, la détection provient de la
 * sonde réseau décrite dans le cahier des charges technique).
 */

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-whitelist-device]').forEach((btn) => {
        btn.addEventListener('click', () => {
            ViciaAjax.post(`/security/devices/${btn.dataset.id}/whitelist`)
                .then((res) => {
                    ViciaApp.toast(res.message, 'success');
                    setTimeout(() => window.location.reload(), 600);
                })
                .catch((err) => ViciaApp.toast(err.message || 'Action impossible.', 'error'));
        });
    });

    document.querySelectorAll('[data-blacklist-device]').forEach((btn) => {
        btn.addEventListener('click', () => {
            if (!confirm('Bloquer cet appareil et le placer en liste noire ?')) return;
            ViciaAjax.post(`/security/devices/${btn.dataset.id}/blacklist`)
                .then((res) => {
                    ViciaApp.toast(res.message, 'success');
                    setTimeout(() => window.location.reload(), 600);
                })
                .catch((err) => ViciaApp.toast(err.message || 'Action impossible.', 'error'));
        });
    });

    const scanBtn = document.getElementById('simulate-scan-btn');
    if (scanBtn) {
        scanBtn.addEventListener('click', () => {
            scanBtn.disabled = true;
            scanBtn.innerHTML = '<span class="spinner"></span> Analyse en cours...';
            ViciaAjax.post('/security/simulate-scan')
                .then((res) => {
                    ViciaApp.toast(res.message, 'success');
                    setTimeout(() => window.location.reload(), 700);
                })
                .catch((err) => ViciaApp.toast(err.message || 'Échec du scan.', 'error'))
                .finally(() => {
                    scanBtn.disabled = false;
                    scanBtn.innerHTML = '<i class="fa-solid fa-satellite-dish"></i> Lancer un scan réseau';
                });
        });
    }
});
