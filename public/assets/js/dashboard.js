/**
 * Actions simples du tableau de bord.
 */
document.addEventListener('DOMContentLoaded', () => {
    const modeSelect = document.querySelector('[data-dashboard-mode]');
    modeSelect?.addEventListener('change', () => {
        modeSelect.disabled = true;
        ViciaAjax.post('/dashboard/mode', { mode: modeSelect.value })
            .then((response) => ViciaApp.toast(response.message || 'Mode mis à jour.'))
            .catch((error) => ViciaApp.toast(error.message || 'Impossible de changer le mode.', 'error'))
            .finally(() => { modeSelect.disabled = false; });
    });

    document.querySelectorAll('[data-dashboard-toggle-equipment]').forEach((button) => {
        button.addEventListener('click', () => {
            const equipmentId = button.dataset.id;
            button.disabled = true;

            ViciaAjax.post(`/equipments/${equipmentId}/toggle`)
                .then((response) => {
                    const state = document.querySelector(`[data-equipment-state="${equipmentId}"]`);
                    if (state) {
                        const isOn = Number(response.state) === 1;
                        state.textContent = isOn ? 'Allumé' : 'Éteint';
                        state.classList.toggle('is-on', isOn);
                        state.classList.toggle('is-off', !isOn);
                    }
                    button.dataset.state = Number(response.state) === 1 ? '1' : '0';
                    button.setAttribute('aria-pressed', Number(response.state) === 1 ? 'true' : 'false');
                    ViciaApp.toast(response.message || 'État de l’équipement mis à jour.');
                    window.ViciaRealtime?.refresh();
                })
                .catch((error) => ViciaApp.toast(error.message || 'Impossible de changer cet équipement.', 'error'))
                .finally(() => { button.disabled = false; });
        });
    });
});
