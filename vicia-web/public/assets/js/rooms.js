/**
 * assets/js/rooms.js
 *
 * Gestion en AJAX du module "Pièces" : création, modification et
 * suppression, sans rechargement de page. Les formulaires sont
 * portés par des modales définies dans app/views/rooms/index.php.
 */

document.addEventListener('DOMContentLoaded', () => {
    const createForm = document.getElementById('room-create-form');
    const editForm = document.getElementById('room-edit-form');

    if (createForm) {
        createForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const formData = new FormData(createForm);

            ViciaAjax.post('/rooms', formData)
                .then((res) => {
                    ViciaApp.toast(res.message, 'success');
                    setTimeout(() => window.location.reload(), 600);
                })
                .catch((err) => ViciaApp.toast(err.message || 'Erreur lors de la création.', 'error'));
        });
    }

    if (editForm) {
        editForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const id = editForm.getAttribute('data-room-id');
            const formData = new FormData(editForm);

            ViciaAjax.put(`/rooms/${id}`, formData)
                .then((res) => {
                    ViciaApp.toast(res.message, 'success');
                    setTimeout(() => window.location.reload(), 600);
                })
                .catch((err) => ViciaApp.toast(err.message || 'Erreur lors de la mise à jour.', 'error'));
        });
    }

    // Ouverture de la modale d'édition pré-remplie avec les données
    // de la pièce sélectionnée (portées par des attributs data-*).
    document.querySelectorAll('[data-edit-room]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const form = document.getElementById('room-edit-form');
            form.setAttribute('data-room-id', btn.dataset.id);
            form.querySelector('[name="name"]').value = btn.dataset.name;
            form.querySelector('[name="type"]').value = btn.dataset.type;
            form.querySelector('[name="floor"]').value = btn.dataset.floor || '';
            form.querySelector('[name="description"]').value = btn.dataset.description || '';
            ViciaApp.openModal('modal-edit-room');
        });
    });

    document.querySelectorAll('[data-delete-room]').forEach((btn) => {
        btn.addEventListener('click', () => {
            if (!confirm(`Supprimer la pièce « ${btn.dataset.name} » ? Cette action est irréversible.`)) {
                return;
            }
            ViciaAjax.del(`/rooms/${btn.dataset.id}`)
                .then((res) => {
                    ViciaApp.toast(res.message, 'success');
                    btn.closest('.room-card, tr')?.remove();
                })
                .catch((err) => ViciaApp.toast(err.message || 'Suppression impossible.', 'error'));
        });
    });
});
