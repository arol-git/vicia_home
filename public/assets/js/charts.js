/**
 * assets/js/charts.js
 *
 * Fonctions utilitaires de création de graphiques Chart.js, avec une
 * configuration visuelle cohérente avec l'identité graphique de la
 * plateforme (couleurs, grille discrète, courbes lissées).
 */

const ViciaCharts = (() => {
    'use strict';

    const PRIMARY = '#2f5fa8';
    const SUCCESS = '#2e7d5b';
    const GRID_COLOR = 'rgba(140, 150, 170, 0.15)';

    function baseLineOptions() {
        return {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#16202e',
                    padding: 10,
                    cornerRadius: 8,
                    titleFont: { size: 12 },
                    bodyFont: { size: 12 },
                },
            },
            scales: {
                x: { grid: { display: false }, ticks: { color: '#6c7686', font: { size: 11 } } },
                y: { grid: { color: GRID_COLOR }, ticks: { color: '#6c7686', font: { size: 11 } } },
            },
        };
    }

    /**
     * Trace un graphique en courbe (utilisé pour l'historique de
     * mesures d'un capteur ou la tendance d'activité du tableau de
     * bord).
     */
    function lineChart(canvasId, labels, values, label = '', color = PRIMARY) {
        const ctx = document.getElementById(canvasId);
        if (!ctx) return null;

        return new Chart(ctx, {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    label,
                    data: values,
                    borderColor: color,
                    backgroundColor: hexToRgba(color, 0.12),
                    borderWidth: 2,
                    tension: 0.35,
                    fill: true,
                    pointRadius: 0,
                    pointHoverRadius: 4,
                }],
            },
            options: baseLineOptions(),
        });
    }

    /**
     * Trace un graphique en anneau (répartition de la consommation
     * par type d'équipement).
     */
    function doughnutChart(canvasId, labels, values, colors) {
        const ctx = document.getElementById(canvasId);
        if (!ctx) return null;

        return new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels,
                datasets: [{ data: values, backgroundColor: colors, borderWidth: 0 }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '68%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { color: '#6c7686', font: { size: 11 }, padding: 14, usePointStyle: true },
                    },
                },
            },
        });
    }

    /**
     * Trace un histogramme en barres (ex. nombre de mesures par
     * heure sur le tableau de bord).
     */
    function barChart(canvasId, labels, values, color = SUCCESS) {
        const ctx = document.getElementById(canvasId);
        if (!ctx) return null;

        return new Chart(ctx, {
            type: 'bar',
            data: {
                labels,
                datasets: [{ data: values, backgroundColor: hexToRgba(color, 0.75), borderRadius: 6, maxBarThickness: 26 }],
            },
            options: baseLineOptions(),
        });
    }

    function hexToRgba(hex, alpha) {
        const r = parseInt(hex.slice(1, 3), 16);
        const g = parseInt(hex.slice(3, 5), 16);
        const b = parseInt(hex.slice(5, 7), 16);
        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    }

    return { lineChart, doughnutChart, barChart };
})();
