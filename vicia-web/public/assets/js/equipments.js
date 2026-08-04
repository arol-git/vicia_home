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
