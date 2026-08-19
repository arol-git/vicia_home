document.addEventListener('DOMContentLoaded', () => {
    const status = document.querySelector('[data-push-status]');
    const enableButton = document.querySelector('[data-push-enable]');
    const disableButton = document.querySelector('[data-push-disable]');
    if (!status || !enableButton || !disableButton) return;

    const setState = (enabled) => {
        status.textContent = enabled ? 'Notifications activées' : 'Notifications désactivées';
        status.className = `badge ${enabled ? 'badge-success' : 'badge-neutral'}`;
        enableButton.hidden = enabled;
        disableButton.hidden = !enabled;
    };

    const refresh = () => ViciaAjax.get('/api/v1/push/status')
        .then((response) => setState(response.enabled === true))
        .catch(() => setState(false));

    enableButton.addEventListener('click', async () => {
        enableButton.disabled = true;
        try {
            if (!window.pwaManager) throw new Error('Le module de notifications est indisponible.');
            await window.pwaManager.setupPushNotifications(true);
            await refresh();
        } catch (error) {
            ViciaApp.toast(error.message || 'Activation impossible.', 'error');
        } finally {
            enableButton.disabled = false;
        }
    });

    disableButton.addEventListener('click', async () => {
        disableButton.disabled = true;
        try {
            await window.pwaManager?.disablePushNotifications();
            setState(false);
            ViciaApp.toast('Notifications désactivées sur cet appareil.', 'success');
        } catch (error) {
            ViciaApp.toast(error.message || 'Désactivation impossible.', 'error');
        } finally {
            disableButton.disabled = false;
        }
    });

    refresh();
});
