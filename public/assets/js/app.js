/**
 * assets/js/app.js
 *
 * Initialisation générale de l'interface : gestion du thème
 * clair/sombre, ouverture/fermeture des modales, système de
 * notifications "toast", et mise en surbrillance du lien de menu
 * actif. Ce fichier est chargé sur toutes les pages authentifiées.
 */

const ViciaApp = (() => {
    'use strict';

    /**
     * Bascule entre le mode clair et le mode sombre, et mémorise le
     * choix de l'utilisateur dans le stockage local du navigateur.
     */
    function initThemeToggle() {
        const root = document.documentElement;
        const stored = localStorage.getItem('vicia_theme');
        if (stored) {
            root.setAttribute('data-theme', stored);
        }

        const toggleButtons = document.querySelectorAll('[data-action="toggle-theme"]');
        toggleButtons.forEach((btn) => {
            updateThemeIcon(btn, root.getAttribute('data-theme'));
            btn.addEventListener('click', () => {
                const current = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
                root.setAttribute('data-theme', current);
                localStorage.setItem('vicia_theme', current);
                updateThemeIcon(btn, current);
            });
        });
    }

    function updateThemeIcon(btn, theme) {
        const icon = btn.querySelector('i');
        if (!icon) return;
        icon.className = theme === 'dark' ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
    }

    /**
     * Met en surbrillance, dans la barre latérale, le lien
     * correspondant à la page actuellement affichée.
     */
    function highlightActiveLink() {
        const links = document.querySelectorAll('.sidebar__link');
        const path = window.location.pathname.replace(/\/+$/, '');
        links.forEach((link) => {
            const linkPath = new URL(link.href).pathname.replace(/\/+$/, '');
            if (linkPath === path || (linkPath !== '' && path.startsWith(linkPath) && linkPath !== '/')) {
                link.classList.add('is-active');
            }
        });
    }

    /**
     * Affiche une notification "toast" temporaire en haut à droite
     * de l'écran.
     */
    function toast(message, type = 'success') {
        let container = document.querySelector('.toast-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'toast-container';
            document.body.appendChild(container);
        }

        const el = document.createElement('div');
        el.className = `toast is-${type}`;
        const icon = type === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation';
        el.innerHTML = `<i class="fa-solid ${icon}"></i><span>${escapeHtml(message)}</span>`;
        container.appendChild(el);

        setTimeout(() => {
            el.style.opacity = '0';
            el.style.transform = 'translateX(20px)';
            setTimeout(() => el.remove(), 250);
        }, 4000);
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    /**
     * Ouvre une modale par son identifiant.
     */
    function openModal(id) {
        const modal = document.getElementById(id);
        if (modal) {
            modal.classList.add('is-open');
        }
    }

    /**
     * Ferme une modale par son identifiant, ou toute modale ouverte
     * si aucun identifiant n'est fourni.
     */
    function closeModal(id) {
        if (id) {
            document.getElementById(id)?.classList.remove('is-open');
            return;
        }
        document.querySelectorAll('.modal-overlay.is-open').forEach((m) => m.classList.remove('is-open'));
    }

    function initModalTriggers() {
        document.addEventListener('click', (e) => {
            const openTrigger = e.target.closest('[data-open-modal]');
            if (openTrigger) {
                openModal(openTrigger.getAttribute('data-open-modal'));
            }

            const closeTrigger = e.target.closest('[data-close-modal]');
            if (closeTrigger) {
                closeModal(closeTrigger.closest('.modal-overlay')?.id);
            }

            // Fermeture au clic sur l'arrière-plan sombre
            if (e.target.classList.contains('modal-overlay')) {
                e.target.classList.remove('is-open');
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
    }

    /**
     * Rafraîchit périodiquement le compteur d'alertes non lues affiché
     * dans la barre supérieure et le menu latéral.
     */
    function initAlertPolling() {
        const badge = document.querySelector('[data-alert-badge]');
        if (!badge) return;

        const refresh = () => {
            ViciaAjax.get('/alerts/unread-count').then((res) => {
                if (res && typeof res.count === 'number') {
                    badge.textContent = res.count > 9 ? '9+' : res.count;
                    badge.style.display = res.count > 0 ? 'inline-flex' : 'none';
                }
            }).catch(() => {});
        };

        refresh();
        setInterval(refresh, 30000);
    }

    function initHouseSwitcher() {
        const toggle = document.querySelector('[data-toggle-house-menu]');
        const menu = document.getElementById('house-switcher-menu');
        if (!toggle || !menu) return;

        toggle.addEventListener('click', (e) => {
            e.stopPropagation();
            menu.classList.toggle('is-open');
        });

        document.addEventListener('click', (e) => {
            if (!menu.contains(e.target) && !toggle.contains(e.target)) {
                menu.classList.remove('is-open');
            }
        });

        menu.querySelectorAll('[data-switch-house]').forEach((btn) => {
            btn.addEventListener('click', () => {
                ViciaAjax.post(`/houses/switch/${btn.dataset.id}`)
                    .then((res) => { window.location.href = res.redirect || appUrl('/dashboard'); })
                    .catch((err) => toast(err.message || 'Sélection impossible.', 'error'));
            });
        });
    }

    function appUrl(path) {
        const base = document.querySelector('meta[name="app-base-url"]')?.getAttribute('content') || '/';
        return `${base.replace(/\/+$/, '')}/${String(path).replace(/^\/+/, '')}`;
    }

    function init() {
        initThemeToggle();
        highlightActiveLink();
        initModalTriggers();
        initAlertPolling();
        initHouseSwitcher();
    }

    document.addEventListener('DOMContentLoaded', init);

    return { toast, openModal, closeModal };
})();
