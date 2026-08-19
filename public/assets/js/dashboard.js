/**
 * Actions simples du tableau de bord.
 */
document.addEventListener('DOMContentLoaded', () => {
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
                    ViciaApp.toast(response.message || 'État de l’équipement mis à jour.');
                })
                .catch((error) => ViciaApp.toast(error.message || 'Impossible de changer cet équipement.', 'error'))
                .finally(() => { button.disabled = false; });
        });
    });
});
