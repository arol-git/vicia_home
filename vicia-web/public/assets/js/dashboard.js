/**
 * assets/js/dashboard.js
 *
 * Initialise les graphiques du tableau de bord à partir des données
 * injectées par la vue dans la balise <script type="application/json"
 * id="dashboard-data">, générée côté serveur par
 * app/views/dashboard/index.php.
 */

document.addEventListener('DOMContentLoaded', () => {
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
