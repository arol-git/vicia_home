/**
 * assets/js/automation.js
 *
 * Interface du moteur de règles d'automatisation : affichage
 * dynamique des champs de condition selon le type choisi (capteur ou
 * événement système), création, activation/désactivation et
 * suppression des règles, le tout en AJAX.
 */

document.addEventListener('DOMContentLoaded', () => {
    const sourceSelect = document.getElementById('condition-source-select');
    const sensorFields = document.getElementById('condition-fields-sensor');
    const eventFields = document.getElementById('condition-fields-event');

    function toggleConditionFields() {
        if (!sourceSelect) return;
        const isSensor = sourceSelect.value === 'sensor';
        sensorFields.style.display = isSensor ? 'block' : 'none';
        eventFields.style.display = isSensor ? 'none' : 'block';
    }
    sourceSelect?.addEventListener('change', toggleConditionFields);
    toggleConditionFields();

    const createForm = document.getElementById('automation-create-form');
    if (createForm) {
        createForm.addEventListener('submit', (e) => {
            e.preventDefault();
            ViciaAjax.post('/automation', new FormData(createForm))
                .then((res) => {
                    ViciaApp.toast(res.message, 'success');
                    setTimeout(() => window.location.reload(), 600);
                })
                .catch((err) => ViciaApp.toast(err.message || 'Erreur lors de la création de la règle.', 'error'));
        });
    }

    document.querySelectorAll('[data-toggle-rule]').forEach((checkbox) => {
        checkbox.addEventListener('change', () => {
            ViciaAjax.post(`/automation/${checkbox.dataset.id}/toggle`)
                .then((res) => ViciaApp.toast(`Règle ${res.is_active ? 'activée' : 'désactivée'}.`, 'success'))
                .catch((err) => {
                    checkbox.checked = !checkbox.checked;
                    ViciaApp.toast(err.message || 'Action impossible.', 'error');
                });
        });
    });

    document.querySelectorAll('[data-delete-rule]').forEach((btn) => {
        btn.addEventListener('click', () => {
            if (!confirm(`Supprimer la règle « ${btn.dataset.name} » ?`)) return;
            ViciaAjax.del(`/automation/${btn.dataset.id}`)
                .then((res) => {
                    ViciaApp.toast(res.message, 'success');
                    btn.closest('tr')?.remove();
                })
                .catch((err) => ViciaApp.toast(err.message || 'Suppression impossible.', 'error'));
        });
    });
});
