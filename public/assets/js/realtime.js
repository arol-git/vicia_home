/**
 * Synchronisation d'état globale : SSE quand disponible, repli polling.
 * Les commandes restent désactivées lorsque l'ESP32 est déclaré hors ligne.
 */

const ViciaRealtime = (() => {
    'use strict';

    let source = null;
    let pollTimer = null;
    let reconnectTimer = null;
    let lastOnline = null;

    function init() {
        refreshState(true);
        connectStream();

        window.addEventListener('online', () => {
            setBrowserConnection(true);
            requestResync();
            reconnectStream();
        });

        window.addEventListener('offline', () => {
            setBrowserConnection(false);
            applyEsp32Status({ status: 'offline', online: false, last_seen: 0 }, true);
        });
    }

    function connectStream() {
        if (!window.EventSource) {
            startPolling();
            return;
        }

        closeStream();
        source = new EventSource(appUrl('/realtime/stream'));

        source.addEventListener('open', () => {
            setBrowserConnection(true);
            requestResync();
        });

        source.addEventListener('state', (event) => {
            applySnapshot(JSON.parse(event.data));
        });

        source.addEventListener('error', () => {
            setBrowserConnection(false);
            closeStream();
            startPolling();
            reconnectTimer = window.setTimeout(connectStream, 5000);
        });
    }

    function reconnectStream() {
        window.clearTimeout(reconnectTimer);
        stopPolling();
        connectStream();
    }

    function closeStream() {
        if (source) {
            source.close();
            source = null;
        }
    }

    function startPolling() {
        if (pollTimer) return;
        pollTimer = window.setInterval(() => refreshState(false), 5000);
    }

    function stopPolling() {
        window.clearInterval(pollTimer);
        pollTimer = null;
    }

    function refreshState(askResync) {
        ViciaAjax.get('/realtime/state')
            .then((snapshot) => {
                applySnapshot(snapshot);
                if (askResync) requestResync();
            })
            .catch(() => setBrowserConnection(false));
    }

    function requestResync() {
        ViciaAjax.post('/realtime/resync').catch(() => {});
    }

    function applySnapshot(snapshot) {
        if (!snapshot) return;
        setBrowserConnection(true);
        applyEsp32Status(snapshot.esp32 || { status: 'unknown', online: false, last_seen: 0 }, false);
        updateEquipmentCounter(snapshot);

        (snapshot.equipments || []).forEach((equipment) => {
            updateEquipmentControl(equipment);
            updateDashboardControl(equipment);
            updateEquipmentRow(equipment);
        });

        document.dispatchEvent(new CustomEvent('vicia:state-sync', { detail: snapshot }));
    }

    function updateEquipmentControl(equipment) {
        document.querySelectorAll(`[data-toggle-equipment][data-id="${equipment.id}"]`).forEach((checkbox) => {
            checkbox.checked = Number(equipment.state) === 1;
            checkbox.dataset.realState = String(equipment.state);
            checkbox.classList.remove('is-pending');
            checkbox.disabled = !canSendCommands() || Number(equipment.is_active) !== 1;
        });
    }

    function updateDashboardControl(equipment) {
        document.querySelectorAll(`[data-dashboard-toggle-equipment][data-id="${equipment.id}"]`).forEach((button) => {
            const isOn = Number(equipment.state) === 1;
            button.dataset.state = isOn ? '1' : '0';
            button.setAttribute('aria-pressed', isOn ? 'true' : 'false');
        });
        document.querySelectorAll(`[data-equipment-state="${equipment.id}"]`).forEach((state) => {
            const isOn = Number(equipment.state) === 1;
            state.textContent = isOn ? 'Allumé' : 'Éteint';
            state.classList.toggle('is-on', isOn);
            state.classList.toggle('is-off', !isOn);
        });
    }

    function updateEquipmentRow(equipment) {
        document.querySelectorAll(`[data-equipment-row="${equipment.id}"]`).forEach((row) => {
            row.classList.toggle('is-on', Number(equipment.state) === 1);
            row.classList.toggle('is-off', Number(equipment.state) !== 1);
            row.querySelectorAll('[data-equipment-state-label]').forEach((label) => {
                label.textContent = Number(equipment.state) === 1 ? 'Activé' : 'Désactivé';
                label.className = `badge ${Number(equipment.state) === 1 ? 'badge-success' : 'badge-neutral'}`;
            });
            row.querySelectorAll('[data-equipment-last-change]').forEach((label) => {
                label.textContent = equipment.last_state_change_label || equipment.last_state_change || '—';
            });
        });
    }

    function updateEquipmentCounter(snapshot) {
        const counter = document.querySelector('[data-equipment-counter]');
        if (counter && typeof snapshot.equipmentsActive === 'number' && typeof snapshot.equipmentsCount === 'number') {
            counter.textContent = `${snapshot.equipmentsActive} / ${snapshot.equipmentsCount}`;
        }

        const subtitle = document.querySelector('[data-equipment-summary]');
        if (subtitle && typeof snapshot.equipmentsActive === 'number' && typeof snapshot.equipmentsCount === 'number') {
            subtitle.textContent = `${snapshot.equipmentsCount} équipement(s) — ${snapshot.equipmentsActive} actif(s)`;
        }
    }

    function applyEsp32Status(status, browserOffline) {
        const isOnline = !browserOffline && status.status === 'online';
        const changed = lastOnline !== null && lastOnline !== isOnline;
        lastOnline = isOnline;

        document.documentElement.dataset.esp32Status = isOnline ? 'online' : status.status || 'unknown';
        document.querySelectorAll('[data-realtime-status]').forEach((el) => {
            el.textContent = statusLabel(status, browserOffline);
            el.className = `realtime-status is-${isOnline ? 'online' : (status.status === 'offline' || browserOffline ? 'offline' : 'syncing')}`;
        });

        document.querySelectorAll('[data-realtime-status-icon]').forEach((el) => {
            el.classList.toggle('is-online', isOnline);
            el.classList.toggle('is-offline', !isOnline);
        });

        document.querySelectorAll('[data-toggle-equipment]').forEach((checkbox) => {
            checkbox.disabled = !canSendCommands() || checkbox.dataset.active === '0';
        });

        if (changed && isOnline) {
            requestResync();
            ViciaApp.toast('ESP32 reconnecté. Synchronisation des états en cours.', 'success');
        } else if (changed && !isOnline) {
            ViciaApp.toast('ESP32 hors ligne. Commandes temporairement désactivées.', 'error');
        }
    }

    function setBrowserConnection(online) {
        document.documentElement.dataset.realtimeConnection = online ? 'online' : 'offline';
    }

    function statusLabel(status, browserOffline) {
        if (browserOffline) return 'Navigateur hors ligne';
        if (status.status === 'online') return 'ESP32 en ligne';
        if (status.status === 'offline') return 'ESP32 hors ligne';
        return 'Synchronisation en attente';
    }

    function canSendCommands() {
        return navigator.onLine !== false && document.documentElement.dataset.esp32Status !== 'offline';
    }

    function appUrl(path) {
        const base = document.querySelector('meta[name="app-base-url"]')?.getAttribute('content') || '/';
        return `${base.replace(/\/+$/, '')}/${String(path).replace(/^\/+/, '')}`;
    }

    document.addEventListener('DOMContentLoaded', init);

    return { refresh: () => refreshState(true), requestResync };
})();
