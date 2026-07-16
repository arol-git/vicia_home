/**
 * assets/js/equipments.js
 *
 * Gestion en AJAX du module "Équipements" : création, modification,
 * suppression, et surtout bascule d'état (interrupteur) qui déclenche
 * la publication d'une commande MQTT côté serveur
 * (EquipmentController::toggle).
 */

document.addEventListener('DOMContentLoaded', () => {
    // Bascule d'état d'un équipement via l'interrupteur de la liste
    document.querySelectorAll('[data-toggle-equipment]').forEach((checkbox) => {
        checkbox.addEventListener('change', () => {
            const id = checkbox.dataset.id;
            checkbox.disabled = true;

            ViciaAjax.post(`/equipments/${id}/toggle`)
                .then((res) => {
                    ViciaApp.toast(`Commande envoyée — nouvel état : ${res.state ? 'Activé' : 'Désactivé'}`, 'success');
                })
                .catch((err) => {
                    checkbox.checked = !checkbox.checked; // annule visuellement le changement
                    ViciaApp.toast(err.message || 'Impossible d’envoyer la commande.', 'error');
                })
                .finally(() => { checkbox.disabled = false; });
        });
    });

    const createForm = document.getElementById('equipment-create-form');
    if (createForm) {
        createForm.addEventListener('submit', (e) => {
            e.preventDefault();
            ViciaAjax.post('/equipments', new FormData(createForm))
                .then((res) => {
                    ViciaApp.toast(res.message, 'success');
                    setTimeout(() => window.location.reload(), 600);
                })
                .catch((err) => ViciaApp.toast(err.message || 'Erreur lors de la création.', 'error'));
        });

        // Pré-remplissage automatique du topic MQTT suggéré à partir
        // du type d'équipement et de la pièce sélectionnée, pour
        // accélérer la saisie tout en restant modifiable.
        const typeSelect = createForm.querySelector('[name="type"]');
        const roomSelect = createForm.querySelector('[name="room_id"]');
        const topicInput = createForm.querySelector('[name="mqtt_topic"]');

        const suggestTopic = () => {
            if (!typeSelect.value || !roomSelect.value || topicInput.dataset.touched === 'true') return;
            const roomSlug = roomSelect.options[roomSelect.selectedIndex]?.text
                .toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/\s+/g, '');
            topicInput.value = `home/equipment/${roomSlug}/${typeSelect.value}`;
        };
        typeSelect?.addEventListener('change', suggestTopic);
        roomSelect?.addEventListener('change', suggestTopic);
        topicInput?.addEventListener('input', () => { topicInput.dataset.touched = 'true'; });
    }

    document.querySelectorAll('[data-delete-equipment]').forEach((btn) => {
        btn.addEventListener('click', () => {
            if (!confirm(`Supprimer l’équipement « ${btn.dataset.name} » ?`)) return;
            ViciaAjax.del(`/equipments/${btn.dataset.id}`)
                .then((res) => {
                    ViciaApp.toast(res.message, 'success');
                    btn.closest('tr')?.remove();
                })
                .catch((err) => ViciaApp.toast(err.message || 'Suppression impossible.', 'error'));
        });
    });
});
