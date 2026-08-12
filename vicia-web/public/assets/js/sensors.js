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
        const typeInput = createForm.querySelector('[name="type"]');
        const nameInput = createForm.querySelector('[name="name"]');
        const zoneInput = createForm.querySelector('[name="zone"]');
        const deviceInput = createForm.querySelector('[name="device_id"]');
        const topicInput = createForm.querySelector('[name="mqtt_topic"]');

        // Auto-génère le topic lorsque les champs dépendants changent
        const generateTopic = () => {
            if (!typeInput?.value || !nameInput?.value || !deviceInput?.value || !topicInput) return;
            const url = `/sensors/generate-topic?device_id=${encodeURIComponent(deviceInput.value)}&zone=${encodeURIComponent(zoneInput.value || '')}&type=${encodeURIComponent(typeInput.value)}&name=${encodeURIComponent(nameInput.value)}`;
            ViciaAjax.get(url)
            .then((res) => {
                if (res && res.mqtt_topic) {
                    topicInput.value = res.mqtt_topic;
                    topicInput.style.borderColor = '';
                    topicInput.title = '';
                }
            })
            .catch(() => {});
        };

        typeInput?.addEventListener('change', generateTopic);
        nameInput?.addEventListener('input', generateTopic);
        zoneInput?.addEventListener('input', generateTopic);
        deviceInput?.addEventListener('change', generateTopic);

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
            document.getElementById('history-modal-title').textContent = `Historique — ${btn.dataset.name}`;
            ViciaApp.openModal('modal-sensor-history');

            ViciaAjax.get(`/sensors/${id}/history?hours=24`).then((res) => {
                if (historyChart) historyChart.destroy();
                if (!res.labels.length) {
                    document.getElementById('history-empty').style.display = 'block';
                    return;
                }
                document.getElementById('history-empty').style.display = 'none';
                historyChart = ViciaCharts.lineChart('sensor-history-chart', res.labels, res.values, `Valeur (${res.unit})`);
            });
        });
    });
});
