/**
 * assets/js/sensors.js
 *
 * Gestion en AJAX du module "Capteurs" : création, suppression, et
 * affichage de l'historique d'un capteur sous forme de graphique
 * Chart.js dans une modale dédiée.
 */

document.addEventListener('DOMContentLoaded', () => {
    const createForm = document.getElementById('sensor-create-form');
    if (createForm) {
        const typeSelect = createForm.querySelector('[name="type"]');
        const roomSelect = createForm.querySelector('[name="room_id"]');
        const topicInput = createForm.querySelector('[name="mqtt_topic"]');
        const houseSlug = createForm.dataset.houseSlug || 'maison';

        const suggestTopic = () => {
            if (!typeSelect.value || !roomSelect.value || topicInput.dataset.touched === 'true') return;
            const roomSlug = roomSelect.options[roomSelect.selectedIndex]?.text
                .toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/\s+/g, '');
            const domain = {
                pir: 'security',
                dht22_temp: 'climate',
                dht22_hum: 'climate',
                mq2: 'safety',
                mq135: 'safety',
                ldr: 'lighting',
                rfid: 'access',
                humidite_sol: 'garden',
                energy_power: 'energy',
                energy_kwh: 'energy',
                energy_consumption: 'energy',
            }[typeSelect.value] || 'sensors';
            const metric = {
                dht22_temp: 'temp',
                dht22_hum: 'hum',
                humidite_sol: 'soil',
                energy_power: 'power',
                energy_kwh: 'kwh',
                energy_consumption: 'consumption',
            }[typeSelect.value] || typeSelect.value;
            topicInput.value = `home/${houseSlug}/${domain}/${roomSlug}/${metric}`;
        };

        typeSelect?.addEventListener('change', suggestTopic);
        roomSelect?.addEventListener('change', suggestTopic);
        topicInput?.addEventListener('input', () => { topicInput.dataset.touched = 'true'; });
        suggestTopic();

        createForm.addEventListener('submit', (e) => {
            e.preventDefault();
            ViciaAjax.post('/sensors', new FormData(createForm))
                .then((res) => {
                    ViciaApp.toast(res.message, 'success');
                    setTimeout(() => window.location.reload(), 600);
                })
                .catch((err) => ViciaApp.toast(err.message || 'Erreur lors de la création.', 'error'));
        });
    }

    document.querySelectorAll('[data-delete-sensor]').forEach((btn) => {
        btn.addEventListener('click', () => {
            if (!confirm(`Supprimer le capteur « ${btn.dataset.name} » ?`)) return;
            ViciaAjax.del(`/sensors/${btn.dataset.id}`)
                .then((res) => {
                    ViciaApp.toast(res.message, 'success');
                    btn.closest('tr')?.remove();
                })
                .catch((err) => ViciaApp.toast(err.message || 'Suppression impossible.', 'error'));
        });
    });

    let historyChart = null;
    document.querySelectorAll('[data-view-history]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const id = btn.dataset.id;
            const modalTitle = document.getElementById('history-modal-title');
            const emptyState = document.getElementById('history-empty');
            if (modalTitle) modalTitle.textContent = `Historique — ${btn.dataset.name}`;
            if (emptyState) emptyState.style.display = 'none';
            if (historyChart) {
                historyChart.destroy();
                historyChart = null;
            }
            ViciaApp.openModal('modal-sensor-history');

            ViciaAjax.get(`/sensors/${id}/history?hours=24`)
                .then((res) => {
                    const labels = Array.isArray(res?.labels) ? res.labels : [];
                    const values = Array.isArray(res?.values) ? res.values : [];

                    if (!labels.length || !values.length) {
                        if (emptyState) emptyState.style.display = 'block';
                        return;
                    }

                    if (emptyState) emptyState.style.display = 'none';
                    historyChart = ViciaCharts.lineChart('sensor-history-chart', labels, values, `Valeur (${res.unit || ''})`);
                })
                .catch((err) => {
                    if (emptyState) {
                        emptyState.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i><p>Impossible de charger l’historique du capteur.</p>';
                        emptyState.style.display = 'block';
                    }
                    ViciaApp.toast(err.message || 'Historique indisponible.', 'error');
                });
        });
    });
});
