/**
 * assets/js/dashboard.js
 *
 * Initialise les graphiques du tableau de bord à partir des données
 * injectées par la vue dans la balise <script type="application/json"
 * id="dashboard-data">, générée côté serveur par
 * app/views/dashboard/index.php.
 */

document.addEventListener('DOMContentLoaded', () => {
    initModeSelector();

    const dataEl = document.getElementById('dashboard-data');
    if (!dataEl) return;

    const data = JSON.parse(dataEl.textContent);

    if (data.activityTrend && data.activityTrend.labels.length) {
        ViciaCharts.barChart('activity-trend-chart', data.activityTrend.labels, data.activityTrend.values);
    }

    // Actualisation manuelle du graphique d'un capteur choisi dans le
    // sélecteur du tableau de bord (température ambiante par défaut).
    const sensorSelect = document.getElementById('dashboard-sensor-select');
    if (sensorSelect) {
        const loadSensorChart = (sensorId) => {
            ViciaAjax.get(`/sensors/${sensorId}/history?hours=24`).then((res) => {
                const canvas = document.getElementById('sensor-trend-chart');
                if (canvas && canvas._chartInstance) {
                    canvas._chartInstance.destroy();
                }
                const chart = ViciaCharts.lineChart('sensor-trend-chart', res.labels, res.values, `Valeur (${res.unit})`);
                if (canvas) canvas._chartInstance = chart;
            });
        };

        sensorSelect.addEventListener('change', (e) => loadSensorChart(e.target.value));
        if (sensorSelect.value) {
            loadSensorChart(sensorSelect.value);
        }
    }
});

function initModeSelector() {
    const selector = document.querySelector('.mode-selector');
    if (!selector) return;

    const buttons = selector.querySelectorAll('[data-dashboard-mode]');
    const storedMode = selector.dataset.currentMode || 'comfort';

    setActiveMode(buttons, storedMode);

    buttons.forEach((button) => {
        button.addEventListener('click', () => {
            const mode = button.dataset.dashboardMode;
            setButtonsLoading(buttons, true);

            ViciaAjax.post('/dashboard/mode', { mode })
                .then((res) => {
                    setActiveMode(buttons, res.mode || mode);
                    selector.dataset.currentMode = res.mode || mode;
                    updateEquipmentCounter(res);
                    showToast(res.message || `Mode ${button.textContent.trim()} activé`);
                })
                .catch((err) => {
                    showToast(err.message || 'Impossible de changer de mode.', 'error');
                })
                .finally(() => setButtonsLoading(buttons, false));
        });
    });
}

function setActiveMode(buttons, mode) {
    let hasActiveMode = false;

    buttons.forEach((button) => {
        const isActive = button.dataset.dashboardMode === mode;
        button.classList.toggle('is-active', isActive);
        button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        hasActiveMode = hasActiveMode || isActive;
    });

    if (!hasActiveMode && buttons.length) {
        buttons[0].classList.add('is-active');
        buttons[0].setAttribute('aria-pressed', 'true');
    }
}

function setButtonsLoading(buttons, loading) {
    buttons.forEach((button) => {
        button.disabled = loading;
    });
}

function showToast(message, type = 'success') {
    if (typeof ViciaApp !== 'undefined' && typeof ViciaApp.toast === 'function') {
        ViciaApp.toast(message, type);
    }
}

function updateEquipmentCounter(res) {
    const counter = document.querySelector('[data-equipment-counter]');
    if (!counter || typeof res.equipmentsActive !== 'number' || typeof res.equipmentsCount !== 'number') {
        return;
    }

    counter.textContent = `${res.equipmentsActive} / ${res.equipmentsCount}`;
}
